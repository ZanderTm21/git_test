"""Edit a site image in place with a fixed list of operations.

    python3 tools/edit_image.py site/images/hero.jpg '[{"op": "brightness", "amount": 20}]'

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
"""
import json
import sys
from pathlib import Path

from PIL import Image, ImageEnhance, ImageFilter, ImageOps


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
    raise SystemExit(f"Unknown operation: {op}")


def _keep_alpha(original, rgb):
    if original.mode == "RGBA":
        out = rgb.convert("RGBA")
        out.putalpha(original.getchannel("A"))
        return out
    return rgb


def main():
    if len(sys.argv) != 3:
        raise SystemExit(__doc__)
    path = Path(sys.argv[1])
    if path.parent.as_posix() != "site/images" or not path.is_file():
        raise SystemExit("Only files in site/images/ can be edited.")
    ops = json.loads(sys.argv[2])
    ext = path.suffix.lower()
    im = Image.open(path)
    im = ImageOps.exif_transpose(im)
    im = im.convert("RGBA") if (ext == ".png" or im.mode in ("RGBA", "LA", "P")) and ext != ".jpg" and ext != ".jpeg" else im.convert("RGB")
    for o in ops:
        im = apply(im, o, ext)
    if ext in (".jpg", ".jpeg"):
        im.convert("RGB").save(path, quality=88)
    else:
        im.save(path)
    print(f"Saved {path} ({im.size[0]}x{im.size[1]})")


if __name__ == "__main__":
    main()
