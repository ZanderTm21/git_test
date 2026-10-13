"""Build the Hostinger upload: the live site (as separate files the editor can change) plus the AI editor.

    python3 tools/build_hostinger.py OUT_DIR

Creates OUT_DIR/public_html/ and OUT_DIR/rosinetti-hostinger.zip, and prints the one-time setup code
for the editor. Needs PHP's composer on PATH (installs the Anthropic PHP SDK into editor/vendor).
"""
import secrets
import shutil
import subprocess
import sys
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
out = Path(sys.argv[1]).resolve()
site = out / "public_html"
if site.exists():
    shutil.rmtree(site)
site.mkdir(parents=True)

# Site: black & gold theme with the monogram logo, CSS/JS kept as separate files.
html = (ROOT / "index.html").read_text()
html = html.replace("family=Jost:wght@200;300;400;500", "family=Jost:wght@200;300;400;500&family=Cormorant+Garamond:wght@300;400;500", 1)
html = html.replace('<link rel="stylesheet" href="css/style.css">', '<link rel="stylesheet" href="css/style.css">\n  <link rel="stylesheet" href="css/theme-gold.css">', 1)
html = html.replace('src="images/logo.png" alt="Rosinetti Stone" class="logo-img"', 'src="images/logo-rosinetti-gold.png" alt="Rosinetti" class="logo-img"', 1)
html = html.replace('src="images/logo.png" alt="Rosinetti Stone" class="footer-logo"', 'src="images/logo-rosinetti-full-gold.png" alt="Rosinetti" class="footer-logo"', 1)
html = html.replace("<title>Bathroom Renovations | Rosinetti Stone</title>", "<title>Rosinetti | Tiling, Bathrooms &amp; Interiors</title>", 1)
assert "theme-gold.css" in html and "logo-rosinetti-gold.png" in html and "logo-rosinetti-full-gold.png" in html
(site / "index.html").write_text(html)
for rel in ["css/style.css", "css/theme-gold.css", "js/main.js",
            "images/logo-rosinetti-gold.png", "images/logo-rosinetti-full-gold.png", "images/README.md"]:
    (site / rel).parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(ROOT / rel, site / rel)

# Editor
ed = site / "editor"
ed.mkdir()
for f in ["index.php", "api.php", "ai.php", "image.php", "github.php", "github_templates.php", "lib.php", "preview.php", "asset.php", "composer.json", "composer.lock"]:
    shutil.copy2(ROOT / "editor" / f, ed / f)
shutil.copytree(ROOT / "editor" / "github-template", ed / "github-template")
subprocess.run(["composer", "install", "--no-dev", "--prefer-dist", "--optimize-autoloader", "--no-interaction", "--quiet"], cwd=ed, check=True)
# Drop what the libraries don't need at runtime (git history, tests, docs) to keep the upload small.
for pkg in (ed / "vendor").glob("*/*"):
    for junk in [".git", ".github", "tests", "docs", "examples", "fixtures", "scripts", "vendor-bin"]:
        if (pkg / junk).is_dir():
            shutil.rmtree(pkg / junk)
for f in ["composer.json", "composer.lock"]:
    (ed / f).unlink()
data = ed / "data"
data.mkdir()
(data / ".htaccess").write_text("<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n")
(data / "index.html").write_text("")
code = secrets.token_hex(6)
(data / "setup.php").write_text(f"<?php\nreturn ['key' => '{code}'];\n")
# Keep vendor/ and the editor's PHP internals from being browsed directly.
(ed / "vendor" / ".htaccess").write_text((data / ".htaccess").read_text())

zpath = out / "rosinetti-hostinger.zip"
if zpath.exists():
    zpath.unlink()
with zipfile.ZipFile(zpath, "w", zipfile.ZIP_DEFLATED) as z:
    for p in sorted(site.rglob("*")):
        if p.is_file():
            z.write(p, p.relative_to(site))
print(f"built {zpath} ({zpath.stat().st_size // 1024} KB)")
print(f"SETUP CODE: {code}")
