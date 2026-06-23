# Lyon Scale — Cold Email AI Agent

An AI agent that researches UK property-sector companies, writes a **personalised
cold email + 4-step follow-up sequence** for each, and loads them into **Apollo**
(or any sender) to go out from warmed inboxes. It runs the brain of a full
cold-outreach pipeline. **The moment a lead shows interest, you take over and close.**

Everything it does is grounded in 2026 cold-email conversion data — see
[`references/playbook.md`](references/playbook.md) for the research + sources.

---

## How it works — top to bottom (the implicit, made explicit)

```
   YOUR LEAD LIST (CSV)                         ← step 1: sourcing (Apollo/Clay) — you supply this
        │
        ▼
 ┌───────────────────────────────────────────┐
 │  agent.py  (THE AI BRAIN — what we built)  │
 │                                            │
 │  for each company:                         │
 │   1. fetch its website  → research hook    │ ← personalisation = +32% reply
 │   2. pick decision-maker title for sector  │
 │   3. Claude writes, per the playbook rules:│
 │        • subject (2-5 words, <50 chars)    │
 │        • email 1 (<80 words, problem-first,│
 │          ONE binary CTA)                   │
 │        • follow-ups 2,3,4 (new angle each) │ ← 42% of replies come from follow-ups
 │   4. append signature + opt-out (legal)    │
 └───────────────────────────────────────────┘
        │
        ▼
   OUTPUT (out/)
     • emails.csv         → you read & approve
     • emails.json        → full data
     • apollo_import.csv  → load into Apollo
        │
        ▼
 ┌───────────────────────────────────────────┐
 │  APOLLO  (sender + sequencer)              │ ← step 4-5: sending & follow-up
 │   • import apollo_import.csv               │
 │   • custom fields hold each unique email   │
 │   • sequence runs steps on schedule        │
 │   • sends from WARMED inboxes (not spam)   │
 └───────────────────────────────────────────┘
        │
        ▼
   POSITIVE REPLY  →  HANDED OFF TO YOU  →  you close (your 100% close rate)
```

**What the agent decides for you (the "AI" part):**
- *Who to address* — picks the right decision-maker title per sub-sector if you didn't supply one.
- *What hook to open with* — reads the company's website and finds one true, specific angle.
- *What to say* — applies the conversion rules (short, problem-first, one CTA) so you don't have to be a copywriter.
- *What the 4 follow-ups say* — each a fresh angle, because that's where ~42% of replies live.

**What the agent deliberately does NOT do (and why):**
- *It doesn't send.* Sending from code wrecks deliverability — Apollo + warmed inboxes do this safely.
- *It doesn't invent facts.* Made-up stats/compliments kill trust and inbox placement.
- *It doesn't handle replies.* That's your cue to step in and close.

---

## Quick start

```bash
cd cold-email-agent
pip install -r requirements.txt
export ANTHROPIC_API_KEY=sk-ant-your-key      # from console.anthropic.com

# 1) Edit config.yaml — your offer, sender details, booking link, address
# 2) Put your leads in a CSV like companies.sample.csv
# 3) Generate (start small and READ the output):
python agent.py --input companies.sample.csv --output out --limit 3
```

Outputs in `out/`: `emails.csv` (review), `emails.json` (data), `apollo_import.csv` (load into Apollo).

---

## Step 1 — Sourcing the list (before the agent)

Build a verified list with the right contact. Tools: **Apollo / Cognism / Clay /
LinkedIn Sales Navigator**. Verify emails (**NeverBounce / MillionVerifier**) to keep
bounce rate <3%. Decision-maker titles per sub-sector are in `config.yaml` and the
[playbook](references/playbook.md).

Input CSV columns (required: `company_name`, `sector`, `contact_email`):

| column | required | notes |
|---|---|---|
| company_name | ✅ | |
| sector | ✅ | property management · housing association · facilities management · estate agent · sales agent · main contractor |
| contact_email | ✅ | verified |
| website | optional | enables personalised openers (recommended) |
| contact_name | optional | better greeting |
| contact_title | optional | else we pick a sensible role |

---

## Step 2 — Loading into Apollo (two routes)

### Route A — CSV + custom fields (recommended, no API key)
1. In Apollo, create **custom fields** named exactly:
   `es1_subject, es1_body, es2_subject, es2_body, es3_subject, es3_body, es4_subject, es4_body`.
2. **Sequences → Create Sequence.** Add 4 email steps with delays (day 0, 3, 7, 12).
3. Set each step's template to just the variables, e.g. subject `{{es1_subject}}` and body `{{es1_body}}` (step 2 → `es2_*`, etc.). Now every contact gets its own AI-written email.
4. **Import `out/apollo_import.csv`** as contacts (maps first_name, last_name, email, company, title + the custom fields).
5. Add them to the sequence, pick your sending mailbox, **Activate**.

### Route B — API automation (advanced)
```bash
export APOLLO_API_KEY=your-MASTER-key
python apollo_push.py --sequence-id <id> --email-account-id <id> --input out/emails.json
```
Creates contacts and enrols them in an existing active sequence. Needs a **master** key (else 403). See `config.yaml > apollo`.

---

## Step 3 — Warming so you don't go to spam (do this BEFORE real sends)

This is the part most people skip and then wonder why nothing lands. From the
[playbook](references/playbook.md):

- **Don't use your main domain.** Buy **2–3 look-alike domains** for cold sending.
- **Set SPF, DKIM, DMARC** on each (Apollo/your provider guides this). Mandatory.
- **Turn on warm-up** for ~**2–3 weeks** before real sends (full reputation takes 8–12 weeks).
- A few **inboxes per domain**, cap **~30–50 sends/inbox/day**; scale by adding inboxes, not raising the cap.
- Keep **bounce <3%** (verify lists) and content mostly text, ≤1 link early.

Guardrail values live in `config.yaml > warming` for reference.

---

## Step 4 — When a lead is interested → you

The agent + Apollo run everything up to the reply. A positive reply is your
signal: jump in, book the call (your Calendly is in the signature from step 2+),
and close. That's the hand-off you asked for.

---

## What converts (baked into the agent)

Short, **problem-first**, **under 80 words**, **one binary CTA**, **personalised
opener**, **4 follow-ups**. Subjects 2–5 words, <50 chars. Avoid spam words. Full
data + sources in [`references/playbook.md`](references/playbook.md).

---

## Config cheat-sheet (`config.yaml`)
- **sender / offer / compliance** — who it's from, what you sell, your address + opt-out (legally required).
- **sequence** — follow-up timing.
- **sectors** — decision-maker titles per sub-sector.
- **apollo** — sequence id, mailbox id, custom-field names.
- **warming** — domain/inbox/limit guardrails.
- **model** — `claude-sonnet-4-6` (default) · `claude-haiku-4-5-20251001` (cheapest at scale) · `claude-opus-4-8` (top quality).

---

## Legal (UK B2B cold email)
Allowed to corporate contacts under PECR + GDPR legitimate interest **if** every
email identifies you (auto-appended signature + address) and offers an easy
opt-out (auto-appended), and you honour opt-outs / keep a suppression list. Not
legal advice — confirm your specifics.

---

## Files
- `agent.py` — the AI brain (research + write + sequence + exports)
- `apollo_push.py` — optional API push to Apollo
- `config.yaml` — everything you edit
- `companies.sample.csv` — example input
- `references/playbook.md` — researched best practices + sources
- `requirements.txt` — `anthropic`, `pyyaml`, `requests`

Want me to add **step 1 (auto-sourcing via Apollo/Clay)** or **step 6 (auto reply-detection + handoff)** as code too? Say the word.
