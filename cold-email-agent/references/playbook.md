# Cold Outreach Playbook (researched, 2026)

The rules below are baked into the agent's prompt. This file is the "why" — so
the implicit is explicit and you can tune with confidence.

## 1. What actually converts (2026 benchmarks)

- **Reply rates:** average cold email ~**3.4%**. "Good" B2B = **5–10%**. Top campaigns **8–15%**. Hyper-targeted + personalised can hit **40–50%**. → The win is *relevance*, not volume.
- **Follow-ups are half your results:** ~**58%** of replies come from email 1, **42%** from follow-ups. A single email leaves nearly half the replies on the table. → Always run a 4-step sequence.
- **Personalisation pays:** personalised subject lines open ~**21%** vs ~**15%** generic; tailored emails get ~**32%** higher reply. → Every email opens on a specific, true detail.
- **Short wins:** elite senders keep emails **under ~80 words**, **problem-first**, with **one** CTA.
- **Subject lines:** **2–5 words, under ~50 characters**, relevance over cleverness, never clickbait.
- **CTA:** exactly one, **low-friction & binary** — "Worth a quick look?", "Does this resonate?", "Open to a 10-min call?" Each extra ask lowers reply rate.

## 2. The sequence (default cadence)

| Step | Day | Job |
|------|-----|-----|
| 1 | 0 | Problem-first hook + one specific personalisation + single soft CTA |
| 2 | +3 | New angle or a proof point; short |
| 3 | +7 | A useful idea/resource or a different pain; light |
| 4 | +12 | Polite breakup ("should I close your file?") — these convert surprisingly well |

## 3. Email warming & deliverability (so you don't land in spam)

- **Warm-up takes 8–12 weeks** to reach full deliverability. Start low, ramp gradually — sudden spikes trigger filters.
- **Authentication is mandatory:** SPF, DKIM, DMARC on every sending domain. Without it, Gmail/Outlook distrust you.
- **Protect your main domain:** buy **2–3 separate look-alike domains** for cold sending; never blast from your primary.
- **Inboxes & limits:** a few inboxes per domain; cap **~30–50 sends/inbox/day**. Scale by adding inboxes, not by raising the cap.
- **List hygiene:** verify every address (NeverBounce / MillionVerifier). Keep **bounce rate <3%**.
- **Reputation = engagement:** opens, replies, low complaints. One CTA, real personalisation and tight lists all help.
- **Content:** mostly text, ≤1 link early on, avoid spam words (free, guarantee, act now, click here, !!!, ALL CAPS).

## 4. How Apollo sequences work

- A **sequence** = multi-step outreach (emails/tasks) Apollo runs on a schedule.
- **Build:** Sequences → Create Sequence → edit step templates → Settings (schedule, ruleset) → toggle steps on → **Activate**.
- **Add contacts:** upload a CSV (needs email, first name, company) or via API. When the sequence is active, contacts enter **step 1** per the schedule.
- **Per-contact unique emails:** Apollo templates use variables. Create **custom fields** matching our export columns (`es1_subject`, `es1_body`, …) and set each step template to just `{{es1_subject}}` / `{{es1_body}}` — now every contact gets its own AI-written email.
- **API:** `POST /api/v1/emailer_campaigns/{sequence_id}/add_contact_ids` — needs a **master API key** (else 403). Flow: enrich → create contact → add to sequence.

## Sources
- [Cold email benchmarks 2026 — Instantly](https://instantly.ai/cold-email-benchmark-report-2026)
- [Cold email response rate data — Cleanlist](https://www.cleanlist.ai/blog/2026-02-18-cold-email-response-rate-statistics)
- [Cold email statistics — Snov.io](https://snov.io/blog/cold-email-statistics/)
- [Cold email conversion benchmarks — Reachoutly](https://reachoutly.com/cold-email/conversion-rate/)
- [Email warm-up guide — Growthlist](https://growthlist.co/email-warm-up-guide/)
- [Avoid the spam folder — Mailgun](https://www.mailgun.com/blog/deliverability/avoid-emails-going-to-spam/)
- [Create a Sequence — Apollo](https://knowledge.apollo.io/hc/en-us/articles/4409231193101-Create-a-Sequence)
- [Add Contacts to a Sequence — Apollo API](https://docs.apollo.io/reference/add-contacts-to-sequence)
