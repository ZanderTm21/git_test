#!/usr/bin/env python3
"""
Apollo push (ADVANCED / optional)
=================================
Takes the agent's output (out/emails.json), creates each person as a Contact in
Apollo, and adds them to an existing Apollo sequence via the API.

Most people should use the simpler CSV route instead (out/apollo_import.csv +
custom fields — see README). Use this only if you want full automation.

Requirements (Apollo paid plan):
  - A MASTER API key  (Settings -> Integrations -> API). Non-master keys get 403.
  - An existing, ACTIVE sequence id  (the long id in the sequence URL).
  - The email account id you send from (Settings -> Mailboxes, or via API).

Usage:
  export APOLLO_API_KEY=...
  python apollo_push.py --sequence-id <id> --email-account-id <id> --input out/emails.json
"""
import argparse, json, os, sys, time
import requests

BASE = "https://api.apollo.io/api/v1"


def headers():
    key = os.environ.get("APOLLO_API_KEY")
    if not key:
        sys.exit("ERROR: set APOLLO_API_KEY (must be a MASTER key)")
    return {"Cache-Control": "no-cache", "Content-Type": "application/json", "X-Api-Key": key}


def create_contact(person):
    """Create a contact in your Apollo database. Returns contact id or None."""
    fn = (person.get("contact_name") or "").split(" ")[0]
    ln = " ".join((person.get("contact_name") or "").split(" ")[1:])
    payload = {
        "first_name": fn,
        "last_name": ln,
        "email": person.get("contact_email"),
        "organization_name": person.get("company"),
        "title": person.get("title_used", ""),
    }
    r = requests.post(f"{BASE}/contacts", headers=headers(), json=payload, timeout=30)
    if r.status_code == 429:
        time.sleep(5); return create_contact(person)
    if not r.ok:
        print(f"   ! create_contact failed [{r.status_code}]: {r.text[:160]}", file=sys.stderr)
        return None
    return r.json().get("contact", {}).get("id")


def add_to_sequence(contact_ids, sequence_id, email_account_id):
    url = f"{BASE}/emailer_campaigns/{sequence_id}/add_contact_ids"
    payload = {
        "contact_ids": contact_ids,
        "send_email_from_email_account_id": email_account_id,
        "sequence_active_in_other_campaigns": False,
    }
    r = requests.post(url, headers=headers(), json=payload, timeout=60)
    if r.status_code == 403:
        sys.exit("403 — Apollo requires a MASTER API key for this endpoint.")
    if r.status_code == 429:
        time.sleep(10); return add_to_sequence(contact_ids, sequence_id, email_account_id)
    if not r.ok:
        sys.exit(f"add_to_sequence failed [{r.status_code}]: {r.text[:300]}")
    return r.json()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--input", default="out/emails.json")
    ap.add_argument("--sequence-id", required=True)
    ap.add_argument("--email-account-id", required=True)
    args = ap.parse_args()

    people = json.load(open(args.input, encoding="utf-8"))
    print(f"Creating {len(people)} contacts in Apollo …")
    ids = []
    for i, p in enumerate(people, 1):
        cid = create_contact(p)
        if cid:
            ids.append(cid)
            print(f"  [{i}/{len(people)}] {p.get('company','?')} -> {cid}")
        time.sleep(0.4)

    if not ids:
        sys.exit("No contacts created — nothing to add.")
    print(f"Adding {len(ids)} contacts to sequence {args.sequence_id} …")
    add_to_sequence(ids, args.sequence_id, args.email_account_id)
    print("Done. Contacts enrolled — they enter step 1 per the sequence schedule.")
    print("NOTE: per-contact bodies are set via custom fields; see README's CSV method "
          "if you need each email fully unique inside Apollo templates.")


if __name__ == "__main__":
    main()
