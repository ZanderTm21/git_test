# Design System Starter (CSS)

A ready-to-adapt, dependency-free CSS starter. Set the tokens once, reuse everywhere. Swap the colour values to match the brand.

```css
/* ---- Tokens ---- */
:root {
  /* Colour — replace with brand palette */
  --color-bg: #ffffff;
  --color-surface: #f6f8fa;
  --color-text: #1f2933;
  --color-muted: #52606d;
  --color-primary: #0f2942;     /* brand */
  --color-accent: #f5a623;      /* CTA / highlight */
  --color-accent-ink: #1f2933;  /* text on accent */
  --color-border: #e2e8f0;

  /* Type */
  --font-sans: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
  --fs-display: clamp(2.2rem, 5vw, 3.4rem);
  --fs-h2: clamp(1.5rem, 3vw, 2.1rem);
  --fs-h3: 1.2rem;
  --fs-body: 1.05rem;
  --fs-small: 0.9rem;
  --lh: 1.55;

  /* Spacing */
  --space-1: 4px;  --space-2: 8px;  --space-3: 16px;
  --space-4: 24px; --space-5: 48px; --space-6: 80px;

  --radius: 12px;
  --shadow: 0 6px 24px rgba(15, 41, 66, 0.08);
  --maxw: 1160px;
}

/* ---- Base ---- */
* { box-sizing: border-box; }
body {
  margin: 0; font-family: var(--font-sans); color: var(--color-text);
  background: var(--color-bg); font-size: var(--fs-body); line-height: var(--lh);
}
h1, h2, h3 { color: var(--color-primary); line-height: 1.15; margin: 0 0 var(--space-3); }
h1 { font-size: var(--fs-display); }
h2 { font-size: var(--fs-h2); }
h3 { font-size: var(--fs-h3); }
p  { margin: 0 0 var(--space-3); color: var(--color-muted); }
img { max-width: 100%; height: auto; display: block; }
a { color: var(--color-primary); }

/* ---- Layout ---- */
.container { max-width: var(--maxw); margin: 0 auto; padding: 0 var(--space-4); }
.section { padding: var(--space-6) 0; }
.section--alt { background: var(--color-surface); }
.grid { display: grid; gap: var(--space-4); }
@media (min-width: 720px) {
  .grid--2 { grid-template-columns: repeat(2, 1fr); }
  .grid--3 { grid-template-columns: repeat(3, 1fr); }
}

/* ---- Buttons ---- */
.btn {
  display: inline-block; padding: 14px 24px; border-radius: var(--radius);
  font-weight: 700; text-decoration: none; cursor: pointer; border: 2px solid transparent;
}
.btn--primary { background: var(--color-accent); color: var(--color-accent-ink); }
.btn--primary:hover { filter: brightness(0.95); }
.btn--secondary { background: transparent; color: var(--color-primary); border-color: var(--color-primary); }

/* ---- Cards ---- */
.card {
  background: var(--color-bg); border: 1px solid var(--color-border);
  border-radius: var(--radius); padding: var(--space-4); box-shadow: var(--shadow);
}

/* ---- Header / Nav ---- */
.site-header { position: sticky; top: 0; background: var(--color-bg);
  border-bottom: 1px solid var(--color-border); z-index: 50; }
.nav { display: flex; align-items: center; justify-content: space-between;
  padding: var(--space-3) var(--space-4); max-width: var(--maxw); margin: 0 auto; }
.nav__links { display: flex; gap: var(--space-4); list-style: none; margin: 0; padding: 0; }
.nav__links a { text-decoration: none; color: var(--color-text); font-weight: 600; }
.nav__links a.is-active { color: var(--color-accent); }
.nav__toggle { display: none; background: none; border: 0; font-size: 1.6rem; cursor: pointer; }

/* ---- Hero ---- */
.hero { padding: var(--space-6) 0; }
.hero h1 { margin-bottom: var(--space-3); }
.hero .lead { font-size: 1.2rem; color: var(--color-muted); max-width: 42ch; }

/* ---- Footer ---- */
.site-footer { background: var(--color-primary); color: #cdd8e3; padding: var(--space-5) 0; }
.site-footer a { color: #fff; }

/* ---- Accessibility ---- */
:focus-visible { outline: 3px solid var(--color-accent); outline-offset: 2px; }

/* ---- Mobile nav ---- */
@media (max-width: 720px) {
  .nav__toggle { display: block; }
  .nav__links { display: none; position: absolute; top: 100%; left: 0; right: 0;
    flex-direction: column; background: var(--color-bg); padding: var(--space-3) var(--space-4);
    border-bottom: 1px solid var(--color-border); }
  .nav__links.is-open { display: flex; }
}
```

Minimal `script.js` for the mobile menu:

```js
const toggle = document.querySelector('.nav__toggle');
const links = document.querySelector('.nav__links');
if (toggle && links) {
  toggle.addEventListener('click', () => links.classList.toggle('is-open'));
}
```

## Picking a palette by sector (fallbacks)
- **Construction / property / trades:** deep navy + amber accent (trust + energy).
- **Professional services / B2B:** navy or slate + a single bright accent.
- **Health / care:** teal/green + warm neutral.
- **Tech / SaaS:** near-black + one vivid accent (indigo, electric blue).

Always verify contrast: body text on background should pass WCAG AA (4.5:1).
