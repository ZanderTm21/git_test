#!/usr/bin/env python3
"""
Lyon Scale — Cold Email AI Agent
================================
Reads a CSV of property-sector companies, (optionally) researches each one's
website for a personalisation hook, then writes a personalised first email plus
a follow-up sequence for each — ready to import into a sender (Smartlead /
Instantly / lemlist).

It is the AI *brain* of the pipeline. It does NOT send email itself — that is
done by a warmed-up sending tool so your domain stays out of spam.

Usage:
    export ANTHROPIC_API_KEY=sk-ant-...
    pip install -r requirements.txt
    python agent.py --input companies.sample.csv --output out

Outputs:
    out/emails.csv   (one row per contact, all steps as columns — import this)
    out/emails.json  (full structured data)
"""
import argparse, csv, json, os, re, sys, time, pathlib
import yaml
import requests
from anthropic import Anthropic

HERE = pathlib.Path(__file__).parent


def load_config(path):
    with open(path, "r", encoding="utf-8") as f:
        return yaml.safe_load(f)


def fetch_site_text(url, timeout=10):
    """Grab a company's homepage text to find a personalisation angle. Best-effort."""
    if not url:
        return ""
    if not url.startswith("http"):
        url = "https://" + url
    try:
        r = requests.get(url, timeout=timeout, headers={"User-Agent": "Mozilla/5.0 (cold-email-agent)"})
        html = r.text
        html = re.sub(r"(?is)<(script|style|noscript).*?</\1>", " ", html)
        text = re.sub(r"(?s)<[^>]+>", " ", html)
        text = re.sub(r"\s+", " ", text).strip()
        return text[:1800]
    except Exception as e:
        print(f"   · research skipped ({e})", file=sys.stderr)
        return ""


def pick_title(sector, supplied_title, cfg):
    if supplied_title:
        return supplied_title
    titles = cfg.get("sectors", {}).get((sector or "").strip().lower())
    return titles[0] if titles else "the decision maker"


def build_prompt(row, site_text, title, cfg):
    s, o, c = cfg["sender"], cfg["offer"], cfg["compliance"]
    seq = cfg["sequence"]
    return f"""You are an expert B2B cold-email copywriter writing to the UK property sector.
Write a cold email + {len(seq)}-step follow-up for ONE prospect. Follow these rules strictly:

RULES (grounded in 2026 cold-email conversion data)
- Plain, natural UK English. Peer-to-peer, never salesy or hypey. No buzzwords, no corporate filler.
- PROBLEM-FIRST: lead with their pain/situation, not with who you are. Mention the offer only as the resolution.
- First email STRICTLY under 80 words (short emails reply best). Follow-ups under 60 words each.
- Subject lines: 2-5 words, under 50 characters, lowercase feel, relevance over cleverness. Personalised subjects open ~21% vs ~15% generic — make it specific to them, never clickbait.
- Open with a SPECIFIC, personalised first line from the research/company/sector below. Never "I came across your website" or generic flattery.
- Exactly ONE call to action, and make it a low-friction binary ask, e.g. "Worth a quick look?", "Does this resonate?", "Open to a 10-min call next week?". Never two asks.
- Do NOT invent facts, stats, names, or fake compliments. If research is thin, personalise on their sub-sector and role instead. Honesty protects deliverability and trust.
- Follow-ups: 42% of replies come from follow-ups — each must add a NEW angle, proof, or useful idea. Never "just bumping this".
- Deliverability: avoid spam-trigger words (free, guarantee, act now, click here, !!!, ALL CAPS). Keep links to at most one, and only from step 2 onward.
- No merge-tag artefacts, no placeholders like [Company] left unfilled — write the real words.

PROSPECT
- Company: {row.get('company_name','')}
- Sector: {row.get('sector','')}
- Recipient role: {title}
- Recipient name: {row.get('contact_name','') or '(unknown — use a role-appropriate greeting)'}
- Website research (may be empty): {site_text or '(none)'}

OFFER (what we sell them)
- What we do: {o.get('one_liner','')}
- Main outcome: {o.get('outcome','')}
- Proof: {o.get('proof','')}

SENDER
- {s.get('name','')}, {s.get('role','')} at {s.get('company','')}
- Booking link (use only in a later step if natural): {s.get('calendar_link','')}

Return ONLY valid JSON, no markdown, in exactly this shape:
{{
  "personalization_note": "one line: the angle you used",
  "steps": [
    {{"step": 1, "subject": "...", "body": "..."}},
    {{"step": 2, "subject": "...", "body": "..."}},
    {{"step": 3, "subject": "...", "body": "..."}},
    {{"step": 4, "subject": "...", "body": "..."}}
  ]
}}
Write {len(seq)} steps. Do not include a signature or unsubscribe line — those are appended automatically."""


def parse_json(text):
    text = text.strip()
    text = re.sub(r"^```(json)?", "", text).strip()
    text = re.sub(r"```$", "", text).strip()
    start, end = text.find("{"), text.rfind("}")
    return json.loads(text[start:end + 1])


def split_name(full):
    full = (full or "").strip()
    if not full:
        return "", ""
    parts = full.split()
    return parts[0], (" ".join(parts[1:]) if len(parts) > 1 else "")


def write_apollo_csv(results, out_dir, n_steps):
    """Apollo-ready import. Create matching custom fields in Apollo, then your
    sequence step templates are simply {{es1_subject}} / {{es1_body}} etc.,
    so every contact gets its own AI-written, personalised email."""
    cols = ["first_name", "last_name", "email", "company", "title"]
    for s in range(1, n_steps + 1):
        cols += [f"es{s}_subject", f"es{s}_body"]
    with open(out_dir / "apollo_import.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=cols)
        w.writeheader()
        for r in results:
            fn, ln = split_name(r.get("contact_name", ""))
            flat = {"first_name": fn, "last_name": ln, "email": r.get("contact_email", ""),
                    "company": r.get("company", ""), "title": r.get("title_used", "")}
            for st in r.get("steps", []):
                s = st["step"]
                flat[f"es{s}_subject"] = st.get("subject", "")
                flat[f"es{s}_body"] = st.get("body", "")
            w.writerow(flat)


def signature(cfg):
    s, c = cfg["sender"], cfg["compliance"]
    return (f"\n\n{s.get('name','')}\n{s.get('role','')}, {s.get('company','')}\n"
            f"{s.get('phone','')} · {s.get('email','')}\n\n"
            f"{c.get('unsubscribe_line','')}\n{c.get('physical_address','')}")


def generate_for_row(client, row, cfg):
    title = pick_title(row.get("sector"), row.get("contact_title"), cfg)
    site_text = fetch_site_text(row.get("website", "")) if cfg.get("research_websites") else ""
    prompt = build_prompt(row, site_text, title, cfg)
    msg = client.messages.create(
        model=cfg.get("model", "claude-sonnet-4-6"),
        max_tokens=1600,
        messages=[{"role": "user", "content": prompt}],
    )
    data = parse_json(msg.content[0].text)
    sig = signature(cfg)
    for st in data.get("steps", []):
        st["body"] = (st.get("body", "").strip() + sig).strip()
        st["send_day"] = next((q["day"] for q in cfg["sequence"] if q["step"] == st["step"]), "")
    data["title_used"] = title
    return data


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--input", required=True, help="CSV of companies")
    ap.add_argument("--output", default="out", help="output folder")
    ap.add_argument("--config", default=str(HERE / "config.yaml"))
    ap.add_argument("--limit", type=int, default=0, help="only process first N rows (0 = all)")
    ap.add_argument("--no-research", action="store_true")
    args = ap.parse_args()

    if not os.environ.get("ANTHROPIC_API_KEY"):
        sys.exit("ERROR: set ANTHROPIC_API_KEY first  (export ANTHROPIC_API_KEY=sk-ant-...)")

    cfg = load_config(args.config)
    if args.no_research:
        cfg["research_websites"] = False
    client = Anthropic()

    rows = list(csv.DictReader(open(args.input, encoding="utf-8")))
    if args.limit:
        rows = rows[: args.limit]

    out_dir = pathlib.Path(args.output)
    out_dir.mkdir(parents=True, exist_ok=True)
    results = []
    n_steps = len(cfg["sequence"])

    for i, row in enumerate(rows, 1):
        company = row.get("company_name", "?")
        print(f"[{i}/{len(rows)}] {company} …")
        try:
            data = generate_for_row(client, row, cfg)
            results.append({"company": company,
                            "contact_name": row.get("contact_name", ""),
                            "contact_email": row.get("contact_email", ""),
                            "sector": row.get("sector", ""),
                            **data})
        except Exception as e:
            print(f"   ! failed: {e}", file=sys.stderr)
        time.sleep(0.5)  # gentle pacing

    # JSON
    with open(out_dir / "emails.json", "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2, ensure_ascii=False)

    # CSV (flat, sender-import friendly)
    cols = ["company", "contact_name", "contact_email", "sector", "personalization_note"]
    for s in range(1, n_steps + 1):
        cols += [f"subject_{s}", f"body_{s}", f"send_day_{s}"]
    with open(out_dir / "emails.csv", "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=cols)
        w.writeheader()
        for r in results:
            flat = {k: r.get(k, "") for k in ["company", "contact_name", "contact_email", "sector", "personalization_note"]}
            for st in r.get("steps", []):
                s = st["step"]
                flat[f"subject_{s}"] = st.get("subject", "")
                flat[f"body_{s}"] = st.get("body", "")
                flat[f"send_day_{s}"] = st.get("send_day", "")
            w.writerow(flat)

    # Apollo-ready import (custom-field method)
    write_apollo_csv(results, out_dir, n_steps)

    print(f"\nDone. {len(results)}/{len(rows)} written to:")
    print(f"  {out_dir}/emails.csv        (human review)")
    print(f"  {out_dir}/emails.json       (full data)")
    print(f"  {out_dir}/apollo_import.csv (import into Apollo — see README)")


if __name__ == "__main__":
    main()
