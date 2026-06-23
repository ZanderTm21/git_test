# Domain Warming & Authentication — step by step

Your domains exist but aren't warmed/authenticated. Do this **before** real sends.
Start now — the clock runs ~2–3 weeks (full reputation 8–12 weeks).

> Honest note on Apollo: Apollo *sends* sequences from your connected mailboxes,
> but it is not a great mailbox **warm-up** tool. Warm the inboxes with a
> dedicated service (below), then connect them to Apollo to send.

## 1. Protect your main domain
Never cold-email from your primary domain. Buy **2–3 look-alike domains**, e.g.
`getlyonscale.com`, `lyonscale.io`, `trylyonscale.com`. Burned cold domains are
replaceable; your main one isn't.

## 2. Create inboxes
- Set up **Google Workspace** or **Microsoft 365** on each cold domain.
- **2–3 inboxes per domain** (real human names: alex@, alexlyon@, etc.).
- 3 domains × 3 inboxes × ~40/day ≈ **~360 sends/day** headroom once warmed.

## 3. Authenticate DNS (mandatory — Gmail/Outlook reject without it)
On each domain's DNS, add:
- **SPF** — TXT record authorising your sender (Google/MS give you the exact value).
- **DKIM** — TXT key from your mailbox provider (turn on DKIM in Workspace/365 admin).
- **DMARC** — TXT at `_dmarc` like: `v=DMARC1; p=none; rua=mailto:dmarc@yourdomain`.
- **Custom tracking domain** (Apollo/your sender provides) so open/click tracking isn't on a shared domain.
- Point the domain to a simple redirect/landing page (a bare parked domain looks suspicious).

Verify all three pass with a checker (e.g. MXToolbox, or your sender's built-in test).

## 4. Warm up (2–3 weeks minimum)
- Use a warm-up service: **Smartlead, Instantly, Mailreach or Warmbox** (turn on for every inbox).
- It auto-sends and replies to seed inboxes, building reputation.
- Start ~5–10/day/inbox, ramp gradually to ~40. **Don't spike volume.**
- Keep warm-up running at a low level even after you start real sends.

## 5. Go-live ramp
- Week 1 real sends: ~10–15/inbox/day.
- Add ~5/day each week up to ~40/inbox/day.
- Watch **bounce <3%**, spam complaints near zero, replies healthy. If deliverability dips, slow down.

## 6. List hygiene (protects all the above)
- Verify every address (**NeverBounce / MillionVerifier**) before importing.
- Remove role addresses where possible (info@, admin@) — they bounce/ignore more.
- Maintain a **suppression list** of opt-outs and bounces; never re-email them.

## Quick checklist
- [ ] 2–3 cold domains bought
- [ ] 2–3 inboxes per domain
- [ ] SPF + DKIM + DMARC pass on each
- [ ] Custom tracking domain set
- [ ] Warm-up running on every inbox (2–3 wks)
- [ ] List verified, bounce <3%
- [ ] Ramp plan set, suppression list ready
