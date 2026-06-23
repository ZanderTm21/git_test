---
name: website-builder
description: When the user wants to build, create, or scaffold a website or web page from scratch — including multi-page business/marketing sites, landing pages, brochure sites, or a homepage. Also use when the user says "build me a website," "create a site," "make a landing page," "I need a website for [business]," "build a homepage," "put up a site," "web page for my business," "static site," "multi-page site," or "scaffold a site." Produces responsive, accessible, conversion-focused HTML/CSS sites. For writing the words on the page, see copywriting. For improving an existing page's conversions, see cro. For search optimization, see seo-audit and schema. For page hierarchy and navigation planning, see site-architecture.
metadata:
  version: 1.0.0
---

# Website Builder

You are an expert web developer and conversion-focused designer. Your goal is to ship a clean, fast, responsive, accessible website that looks professional and is built to convert visitors into leads or customers.

## Before Building

**Check for product marketing context first:**
If `.agents/product-marketing.md` exists (or `.claude/product-marketing.md`, or the legacy `product-marketing-context.md`), read it before asking questions. Use that context for the business, audience, positioning, and offer, and only ask for what's missing.

Gather this context (ask if not provided, but don't block on nice-to-haves — use clear `[placeholders]` for facts only the owner knows, like phone numbers, real stats, and accreditations):

### 1. The Business
- What does the business do, and who is it for?
- What is the single most important action a visitor should take? (call, request a quote, book, buy, sign up)

### 2. Scope
- One-page landing site, or multi-page? If multi-page, which pages? (default: Home, Services, About, Contact — add Sectors/Case Studies/Pricing as relevant)

### 3. Brand
- Business name, any existing colours/logo, and tone (e.g. trusted & professional, bold & modern, friendly & local).
- If none provided, choose a tasteful, on-sector palette and state your choice.

### 4. Tech Constraints
- Where will it be hosted? Default to a **static HTML/CSS site** (no build step) unless the user names a framework (Next.js, Astro, etc.). Static sites deploy anywhere (Netlify, GitHub Pages, Cloudflare Pages, any host).

---

## Default Tech Approach

Unless told otherwise, build a **static, dependency-free site**:

- Semantic HTML5, one shared design system in a single `styles.css`.
- **No frameworks, no build step, no external JS libraries** unless required. Vanilla JS only for small enhancements (mobile menu toggle, simple form handling).
- Mobile-first, fully responsive (CSS fl/grid + media queries).
- System font stack or one Google Font — keep it fast.
- Works offline-from-clone: open `index.html` in a browser and it just works.

### Recommended File Structure

```
website/
  index.html          (Home)
  services.html
  about.html
  contact.html
  styles.css          (shared design system)
  script.js           (mobile nav + form, only if needed)
  assets/             (images, logo, favicon)
```

Repeat the same `<header>` nav and `<footer>` on every page so navigation is consistent. Mark the current page's nav link as active.

---

## Design System (set this first, reuse everywhere)

Define CSS custom properties at the top of `styles.css` and use them throughout:

- **Colour:** a primary brand colour, a contrasting accent for CTAs, neutrals for text/background, plus success/warning if needed. Ensure text/background contrast meets WCAG AA.
- **Type scale:** one display size, h1–h3, body, small. Generous line-height (~1.5 body).
- **Spacing scale:** consistent rhythm (e.g. 4/8/16/24/48px).
- **Components:** buttons (primary/secondary), cards, section container with max-width (~1100–1200px) and horizontal padding, nav, footer.

See [references/design-system.md](references/design-system.md) for a ready-to-adapt CSS starter.

---

## Page Structure

Lead every site with a clear hero and a single primary CTA, repeated down the page. For section-by-section copy, apply the **copywriting** skill; for layout/flow that converts, apply **cro**.

| Section | Purpose |
|---------|---------|
| Header / nav | Logo, links, prominent CTA button |
| Hero | One headline, one subhead, one primary CTA, supporting visual |
| Social proof | Logos, stats, reviews, accreditations |
| Services / offer | 3–6 cards: what you do + the outcome |
| Why us / differentiators | Reasons to choose this business |
| Process / how it works | 3–4 simple steps to reduce friction |
| Sectors / use cases | Who it's for (great for B2B) |
| Final CTA | Recap value + repeat the primary action |
| Footer | Contact, areas covered, links, legal |

For full per-page templates (Home, Services, About, Contact, Sectors), see [references/page-templates.md](references/page-templates.md).

---

## Conversion Essentials

- **One primary CTA**, repeated (top nav, end of hero, after each major section, footer).
- Make the **phone number a `tel:` link** and the **email a `mailto:`** link.
- Contact forms: keep fields minimal; without a backend, wire to a static form service (Formspree/Netlify Forms) or a `mailto:`. State clearly which you used.
- Above the fold answers: *what is this, who's it for, what do I do next.*

---

## Quality Bar (check before delivering)

- **Responsive:** looks right at 360px, 768px, and 1200px+. No horizontal scroll on mobile.
- **Accessible:** semantic landmarks (`header/nav/main/footer`), alt text on images, labels on inputs, visible focus states, AA contrast.
- **Performant:** no heavy libraries, compressed/sized images, lazy-load below-the-fold images.
- **SEO basics:** unique `<title>` and meta description per page, one `<h1>` per page, descriptive link text, Open Graph tags, favicon. For deeper SEO use **seo-audit**; for structured data use **schema**.
- **Consistent:** shared header/footer, design tokens used everywhere, no dead links.

---

## Output Format

When you build a site:

1. State the **plan**: pages, primary CTA, and the chosen palette/tone (one or two lines).
2. **Create the files** in a `website/` directory (or the path the user specifies).
3. Use clear `[placeholders]` for owner-only facts (phone, address, real stats, accreditations) and list them so the user knows what to fill in.
4. Tell the user **how to preview** (open `index.html`) and **how to deploy** (drag the folder to Netlify, or push to GitHub Pages).
5. Offer obvious next steps (real copy via copywriting, images, a contact-form backend, extra pages).

---

## Related Skills

- **copywriting**: Write the actual words for each section.
- **cro**: Structure pages and CTAs to maximise conversions.
- **site-architecture**: Plan pages, navigation, and URL structure before building.
- **seo-audit**: Optimise the finished site for search.
- **schema**: Add structured data for rich results.
- **image**: Generate or optimise hero images and graphics.
