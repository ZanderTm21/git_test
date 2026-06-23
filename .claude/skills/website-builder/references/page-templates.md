# Page Templates

Section-level skeletons for the common pages of a business/marketing site. Use these as the structural starting point, then fill with real copy (apply the **copywriting** skill) and the shared design system (`styles.css`). Every page repeats the same `<header>` and `<footer>`.

## Shared header

```html
<header class="site-header">
  <nav class="nav" aria-label="Primary">
    <a class="nav__logo" href="index.html">[Business Name]</a>
    <button class="nav__toggle" aria-label="Menu" aria-expanded="false">☰</button>
    <ul class="nav__links">
      <li><a href="index.html" class="is-active">Home</a></li>
      <li><a href="services.html">Services</a></li>
      <li><a href="sectors.html">Sectors</a></li>
      <li><a href="about.html">About</a></li>
      <li><a class="btn btn--primary" href="contact.html">[Primary CTA]</a></li>
    </ul>
  </nav>
</header>
```

## Shared footer

```html
<footer class="site-footer">
  <div class="container grid grid--3">
    <div>
      <h3>[Business Name]</h3>
      <p>[One-line description]. Serving [areas covered].</p>
    </div>
    <div>
      <h3>Contact</h3>
      <p><a href="tel:[PHONE]">[PHONE]</a><br>
         <a href="mailto:[EMAIL]">[EMAIL]</a></p>
    </div>
    <div>
      <h3>Links</h3>
      <p><a href="services.html">Services</a> · <a href="about.html">About</a> · <a href="contact.html">Contact</a></p>
    </div>
  </div>
  <div class="container"><p class="small">© [Year] [Business Name]. [Company no. / accreditations].</p></div>
</footer>
```

---

## Home (index.html)

1. **Hero** — headline (core value prop), subhead, primary CTA + secondary CTA, supporting image.
2. **Trust strip** — client logos / accreditations / key stat ("[X] projects delivered").
3. **Services overview** — 3–6 cards linking to Services.
4. **Why us** — 3–4 differentiators.
5. **How it works** — 3–4 steps.
6. **Sectors** — who you serve (B2B).
7. **Testimonial / case snippet.**
8. **Final CTA band** — recap + primary action.

## Services (services.html)

- Intro: the breadth of what you do.
- One block per service: name, what it includes, the outcome, a CTA.
- Optional process section.
- Final CTA.

## Sectors (sectors.html) — strong for B2B

- Intro paragraph.
- One card/section per sector served. For a property-sector business, e.g.:
  property management companies, housing associations, facilities management,
  estate / sales agents, main contractors — each with the specific problem you
  solve for them and a tailored CTA.

## About (about.html)

- Story: why the business exists, experience, values.
- Team / credentials (optional).
- Accreditations & insurance.
- CTA (don't end a page without one).

## Contact (contact.html)

- Heading + reassurance ("We reply within [timeframe]").
- `tel:` and `mailto:` links, address, areas covered, hours.
- Short form: Name, Email, Phone, Message (+ a relevant qualifier field).
  Without a backend, use a static form service (Formspree / Netlify Forms) or a `mailto:` action — state which.
- Optional embedded map.

---

## Per-page `<head>` checklist

```html
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>[Unique page title] | [Business Name]</title>
  <meta name="description" content="[Unique 150–160 char description]">
  <meta property="og:title" content="[Page title]">
  <meta property="og:description" content="[Description]">
  <meta property="og:type" content="website">
  <link rel="icon" href="assets/favicon.png">
  <link rel="stylesheet" href="styles.css">
</head>
```
