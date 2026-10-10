"""Build self-contained, shareable HTML previews (CSS, JS and logo inlined).

    python3 tools/build_previews.py

Writes preview-gold.html (black & gold, monogram logo), preview-dark.html (black & gold,
first logo) and preview-terracotta.html (cream & terracotta)
in the repo root. Photos stay as links to images/ so the files remain small.
"""
import base64
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


def data_uri(path):
    return "data:image/png;base64," + base64.b64encode((ROOT / path).read_bytes()).decode()


def build(theme_css=None, header_logo="images/logo.png", footer_logo="images/logo.png", fonts=None):
    html = (ROOT / "index.html").read_text()
    css = (ROOT / "css/style.css").read_text()
    if theme_css:
        css += "\n" + (ROOT / theme_css).read_text()
    if fonts:
        html = html.replace("family=Jost:wght@200;300;400;500", "family=Jost:wght@200;300;400;500&family=" + fonts, 1)
    html = html.replace('<link rel="stylesheet" href="css/style.css">', "<style>\n" + css + "</style>", 1)
    html = html.replace('<script src="js/main.js"></script>', "<script>\n" + (ROOT / "js/main.js").read_text() + "</script>", 1)
    html = html.replace('src="images/logo.png" alt="Rosinetti Stone" class="logo-img"', f'src="{data_uri(header_logo)}" alt="Rosinetti" class="logo-img"', 1)
    html = html.replace('src="images/logo.png" alt="Rosinetti Stone" class="footer-logo"', f'src="{data_uri(footer_logo)}" alt="Rosinetti" class="footer-logo"', 1)
    assert "css/style.css" not in html and "js/main.js" not in html and 'src="images/logo' not in html
    return html


(ROOT / "preview-dark.html").write_text(build())
(ROOT / "preview-terracotta.html").write_text(build(
    theme_css="css/theme-terracotta.css",
    header_logo="images/logo-rosinetti.png",
    footer_logo="images/logo-rosinetti-full.png",
    fonts="Cormorant+Garamond:wght@400;500",
))
(ROOT / "preview-gold.html").write_text(build(
    theme_css="css/theme-gold.css",
    header_logo="images/logo-rosinetti-gold.png",
    footer_logo="images/logo-rosinetti-full-gold.png",
    fonts="Cormorant+Garamond:wght@300;400;500",
))
print("built preview-gold.html, preview-dark.html, preview-terracotta.html")
