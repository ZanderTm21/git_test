# Rosinetti Stone – Bathrooms landing page

Single-page, mobile-first landing page (plain HTML/CSS/JS, no build step). Open `index.html` in a browser to view it.

- `index.html` – page structure and copy
- `css/style.css` – theme (colours and fonts at the top in `:root`)
- `js/main.js` – sticky header/bar, before/after sliders, quote pop-up
- `css/theme-terracotta.css` – light cream & terracotta theme (matches the monogram logo)
- `tools/build_previews.py` – builds `preview-dark.html` and `preview-terracotta.html`, single files you can send on WhatsApp
- `images/` – drop photos and logo here (see `images/README.md` for file names)

Visiting `index.html#quote` opens the quote form directly.

## Still to fill in
- Phone number: search for `07000000000` / `07000 000000`
- Town names, reviews, FAQ answers, areas covered
- Quote form: connect to an email/CRM handler (see the TODO in `index.html` and `js/main.js`)

## AI editor (editor/)
A private, password-protected page at `/editor/` on the live site: people you add type changes in a chat,
Claude edits the site files, they see a live preview of the draft, and publish when happy (with undo and
a version history). Admins manage who has access. Build the Hostinger upload with
`python3 tools/build_hostinger.py OUT_DIR` (installs the Anthropic PHP SDK and prints a one-time setup code).
