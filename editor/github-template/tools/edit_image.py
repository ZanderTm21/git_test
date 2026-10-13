"""Edit a site image in place with a fixed list of operations.

    python3 tools/edit_image.py site/images/hero.jpg '[{"op": "brightness", "amount": 20}]'
    python3 tools/edit_image.py site/images/photo.jpg '[...]' site/images/photo-bathroom.jpg   # save as a new file

Operations (applied in order):
  crop {x, y, width, height}  fractions 0-1 of the current size, from the top-left corner
  resize {amount}             longest side in px (only shrinks)
  rotate {degrees}            clockwise
  flip_horizontal, flip_vertical, grayscale
  brightness / contrast / saturation {amount}  -100..100
  warmth {amount}             -100 (cooler) .. 100 (warmer)
  tint {color, amount}        blend towards a hex color, amount 0..100
  recolor {color}             one color family keeping shading and transparency (logos)
  remove_background {color?, amount?}  plain background -> transparent (PNG only), tolerance default 45
  sharpen {amount} / blur {amount}     1..100
  text {text, position?, size?, color?, background?, opacity?, font?}
        position: top | center | bottom | top-left | top-right | bottom-left | bottom-right (default bottom)
        size: text height as % of the image height (default 6); color: hex (default #ffffff)
        background: hex color of a band behind the text, or null for none; opacity: of that band 0..100 (default 55)
        font: sans (default) | serif ; long text wraps automatically
  add_image {image, position?, size?, opacity?}
        paste another file from site/images (e.g. the logo) onto this one; size = its width as % of the photo
        width (default 20); opacity 0..100 (default 100); position as for text (default bottom-right)
  overlay {color?, amount?}       tint the whole photo with a flat color layer, amount 0..100 (default 35, black)
  border {color?, amount?}        frame in px (default 12, white)
  round_corners {amount?}         corner radius in px (default 40); PNG keeps the transparent corners
"""
import json
import sys
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw, ImageEnhance, ImageFilter, ImageFont, ImageOps


def hex_rgb(value, default=(201, 162, 74)):
    try:
        v = str(value).lstrip("#")
        return tuple(int(v[i:i + 2], 16) for i in (0, 2, 4)) if len(v) == 6 else default
    except (TypeError, ValueError):
        return default


def clamp(v, lo, hi, default):
    try:
        return max(lo, min(hi, float(v)))
    except (TypeError, ValueError):
        return default


def apply(im, o, ext):
    op = o.get("op")
    amount = o.get("amount")
    if op == "crop":
        w, h = im.size
        x = int(clamp(o.get("x"), 0, 1, 0) * w)
        y = int(clamp(o.get("y"), 0, 1, 0) * h)
        cw = int(clamp(o.get("width"), 0.02, 1, 1) * w)
        ch = int(clamp(o.get("height"), 0.02, 1, 1) * h)
        return im.crop((x, y, min(w, x + cw), min(h, y + ch)))
    if op == "resize":
        im.thumbnail((int(clamp(amount, 50, 4000, 1600)),) * 2, Image.LANCZOS)
        return im
    if op == "rotate":
        fill = (0, 0, 0, 0) if im.mode == "RGBA" else (255, 255, 255)
        return im.rotate(-clamp(o.get("degrees"), -360, 360, 0), expand=True, fillcolor=fill)
    if op == "flip_horizontal":
        return ImageOps.mirror(im)
    if op == "flip_vertical":
        return ImageOps.flip(im)
    if op == "grayscale":
        return ImageOps.grayscale(im.convert("RGB")).convert(im.mode) if im.mode != "RGBA" else _keep_alpha(im, ImageOps.grayscale(im.convert("RGB")).convert("RGB"))
    if op in ("brightness", "contrast", "saturation"):
        factor = 1 + clamp(amount, -100, 100, 0) / 100
        enh = {"brightness": ImageEnhance.Brightness, "contrast": ImageEnhance.Contrast, "saturation": ImageEnhance.Color}[op]
        return _keep_alpha(im, enh(im.convert("RGB")).enhance(factor))
    if op == "warmth":
        t = clamp(amount, -100, 100, 0) / 100
        r, g, b = im.convert("RGB").split()
        r = r.point(lambda v: min(255, max(0, v + 30 * t)))
        g = g.point(lambda v: min(255, max(0, v + 8 * t)))
        b = b.point(lambda v: min(255, max(0, v - 30 * t)))
        return _keep_alpha(im, Image.merge("RGB", (r, g, b)))
    if op == "tint":
        layer = Image.new("RGB", im.size, hex_rgb(o.get("color")))
        return _keep_alpha(im, Image.blend(im.convert("RGB"), layer, clamp(amount, 0, 100, 25) / 100))
    if op == "recolor":
        c = hex_rgb(o.get("color"))
        dark = tuple(int(v * 0.55) for v in c)
        light = tuple(int(v + (255 - v) * 0.6) for v in c)
        gray = ImageOps.grayscale(im.convert("RGB"))
        return _keep_alpha(im, ImageOps.colorize(gray, black=dark, mid=c, white=light))
    if op == "remove_background":
        if ext != ".png":
            raise SystemExit("remove_background needs a PNG image; this one is " + ext)
        rgba = im.convert("RGBA")
        w, h = rgba.size
        bg = hex_rgb(o.get("color"), None) if o.get("color") else None
        if bg is None:
            corners = [rgba.getpixel(p)[:3] for p in ((0, 0), (w - 1, 0), (0, h - 1), (w - 1, h - 1))]
            bg = tuple(sum(c[i] for c in corners) / 4 for i in range(3))
        tol = clamp(amount, 5, 200, 45)
        px = rgba.load()
        for yy in range(h):
            for xx in range(w):
                r, g, b, a = px[xx, yy]
                d = ((r - bg[0]) ** 2 + (g - bg[1]) ** 2 + (b - bg[2]) ** 2) ** 0.5
                if d < tol * 1.6:
                    keep = max(0.0, (d - tol) / (tol * 0.6))
                    px[xx, yy] = (r, g, b, min(a, int(keep * 255)))
        return rgba
    if op == "sharpen":
        return im.filter(ImageFilter.UnsharpMask(radius=2, percent=int(clamp(amount, 1, 100, 30) * 3), threshold=2))
    if op == "blur":
        return im.filter(ImageFilter.GaussianBlur(clamp(amount, 1, 100, 20) / 10))
    if op == "text":
        return draw_text(im, o)
    if op == "add_image":
        return paste_image(im, o)
    if op == "overlay":
        layer = Image.new("RGB", im.size, hex_rgb(o.get("color"), (0, 0, 0)))
        return _keep_alpha(im, Image.blend(im.convert("RGB"), layer, clamp(amount, 0, 100, 35) / 100))
    if op == "border":
        w = int(clamp(amount, 1, 400, 12))
        return ImageOps.expand(im, border=w, fill=hex_rgb(o.get("color"), (255, 255, 255)) + ((255,) if im.mode == "RGBA" else ()))
    if op == "round_corners":
        r = int(clamp(amount, 1, 2000, 40))
        mask = Image.new("L", im.size, 0)
        ImageDraw.Draw(mask).rounded_rectangle((0, 0, im.size[0] - 1, im.size[1] - 1), radius=r, fill=255)
        if ext == ".png" or ext == ".webp":
            out = im.convert("RGBA")
            out.putalpha(ImageChops.multiply(out.getchannel("A"), mask))
            return out
        bg = Image.new("RGB", im.size, (255, 255, 255))
        bg.paste(im.convert("RGB"), mask=mask)
        return bg
    raise SystemExit(f"Unknown operation: {op}")


FONTS = {
    "sans": ["/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf"],
    "serif": ["/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf", "/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf"],
}


def load_font(kind, px):
    for path in FONTS.get(kind, FONTS["sans"]) + FONTS["sans"]:
        if Path(path).is_file():
            return ImageFont.truetype(path, px)
    return ImageFont.load_default(size=px)


def anchor_xy(pos, box_w, box_h, w, h, margin):
    pos = (pos or "bottom").lower()
    x = (w - box_w) // 2
    y = (h - box_h) // 2
    if "left" in pos:
        x = margin
    if "right" in pos:
        x = w - box_w - margin
    if pos.startswith("top"):
        y = margin
    if pos.startswith("bottom"):
        y = h - box_h - margin
    return max(0, x), max(0, y)


def draw_text(im, o):
    text = str(o.get("text") or "").strip()
    if not text:
        raise SystemExit("text needs a 'text' value")
    w, h = im.size
    px = max(10, int(h * clamp(o.get("size"), 1, 40, 6) / 100))
    font = load_font(str(o.get("font") or "sans"), px)
    draw = ImageDraw.Draw(im)
    # Wrap to ~88% of the width
    lines, cur = [], ""
    for word in text.split():
        trial = (cur + " " + word).strip()
        if draw.textlength(trial, font=font) <= w * 0.88 or not cur:
            cur = trial
        else:
            lines.append(cur)
            cur = word
    lines.append(cur)
    line_h = int(px * 1.25)
    text_w = int(max(draw.textlength(l, font=font) for l in lines))
    text_h = line_h * len(lines)
    pad = int(px * 0.45)
    box_w, box_h = text_w + 2 * pad, text_h + 2 * pad
    x, y = anchor_xy(o.get("position"), box_w, box_h, w, h, int(min(w, h) * 0.04))
    base = im.convert("RGBA")
    layer = Image.new("RGBA", base.size, (0, 0, 0, 0))
    ld = ImageDraw.Draw(layer)
    bg = o.get("background")
    if bg:
        alpha = int(clamp(o.get("opacity"), 0, 100, 55) * 2.55)
        ld.rounded_rectangle((x, y, x + box_w, y + box_h), radius=int(pad * 0.8), fill=hex_rgb(bg, (0, 0, 0)) + (alpha,))
    color = hex_rgb(o.get("color"), (255, 255, 255)) + (255,)
    shadow = not bg
    for i, line in enumerate(lines):
        lw = draw.textlength(line, font=font)
        lx = x + pad + (text_w - lw) / 2
        ly = y + pad + i * line_h
        if shadow:  # soft shadow keeps text readable on busy photos
            ld.text((lx + max(1, px // 18), ly + max(1, px // 18)), line, font=font, fill=(0, 0, 0, 150))
        ld.text((lx, ly), line, font=font, fill=color)
    out = Image.alpha_composite(base, layer)
    return out if im.mode == "RGBA" else out.convert("RGB")


def paste_image(im, o):
    src = Path("site/images") / Path(str(o.get("image") or "")).name
    if not src.is_file():
        raise SystemExit(f"add_image: {src} not found")
    over = ImageOps.exif_transpose(Image.open(src)).convert("RGBA")
    w, h = im.size
    target_w = max(8, int(w * clamp(o.get("size"), 2, 100, 20) / 100))
    over = over.resize((target_w, max(1, int(over.size[1] * target_w / over.size[0]))), Image.LANCZOS)
    alpha = clamp(o.get("opacity"), 0, 100, 100) / 100
    if alpha < 1:
        over.putalpha(over.getchannel("A").point(lambda a: int(a * alpha)))
    x, y = anchor_xy(o.get("position") or "bottom-right", over.size[0], over.size[1], w, h, int(min(w, h) * 0.04))
    base = im.convert("RGBA")
    base.alpha_composite(over, (x, y))
    return base if im.mode == "RGBA" else base.convert("RGB")


def _keep_alpha(original, rgb):
    if original.mode == "RGBA":
        out = rgb.convert("RGBA")
        out.putalpha(original.getchannel("A"))
        return out
    return rgb


def main():
    if len(sys.argv) not in (3, 4):
        raise SystemExit(__doc__)
    path = Path(sys.argv[1])
    if path.parent.as_posix() != "site/images" or not path.is_file():
        raise SystemExit("Only files in site/images/ can be edited.")
    out = Path(sys.argv[3]) if len(sys.argv) == 4 else path
    if out.parent.as_posix() != "site/images" or out.suffix.lower() not in (".jpg", ".jpeg", ".png", ".webp"):
        raise SystemExit("The output must be a .jpg, .png or .webp file in site/images/.")
    ops = json.loads(sys.argv[2])
    ext = out.suffix.lower()
    im = Image.open(path)
    im = ImageOps.exif_transpose(im)
    im = im.convert("RGBA") if (ext == ".png" or im.mode in ("RGBA", "LA", "P")) and ext != ".jpg" and ext != ".jpeg" else im.convert("RGB")
    for o in ops:
        im = apply(im, o, ext)
    if ext in (".jpg", ".jpeg"):
        im.convert("RGB").save(out, quality=88)
    else:
        im.save(out)
    print(f"Saved {out} ({im.size[0]}x{im.size[1]})")


if __name__ == "__main__":
    main()
