// Header background once the page scrolls past the hero top
const header = document.querySelector('.site-header');
const stickyBar = document.querySelector('.sticky-bar');
const heroButtons = document.querySelector('.hero .btn-row');
const onScroll = () => {
  header.classList.toggle('scrolled', window.scrollY > 40);
  // Bottom bar appears once the hero's own buttons have scrolled away
  stickyBar.classList.toggle('visible', heroButtons.getBoundingClientRect().bottom < 0);
};
window.addEventListener('scroll', onScroll, { passive: true });
onScroll();

// Before / after drag sliders
document.querySelectorAll('.ba-compare').forEach((box) => {
  const range = box.querySelector('.ba-range');
  const set = (v) => { range.value = v; box.style.setProperty('--pos', v + '%'); };
  const fromPointer = (e) => {
    const r = box.getBoundingClientRect();
    set(Math.round(Math.min(100, Math.max(0, ((e.clientX - r.left) / r.width) * 100))));
  };
  range.addEventListener('input', () => set(range.value));
  box.addEventListener('pointerdown', (e) => { box.setPointerCapture(e.pointerId); fromPointer(e); });
  box.addEventListener('pointermove', (e) => { if (box.hasPointerCapture(e.pointerId)) fromPointer(e); });
  // Fallback for older in-app browsers without pointer events
  if (!window.PointerEvent) {
    box.addEventListener('touchstart', (e) => fromPointer(e.touches[0]), { passive: true });
    box.addEventListener('touchmove', (e) => fromPointer(e.touches[0]), { passive: true });
  }
});

// Nudge each slider once when it comes into view, so people see it can move
if ('IntersectionObserver' in window) {
  const io = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      io.unobserve(entry.target);
      const box = entry.target;
      const start = performance.now();
      const step = (t) => {
        const k = Math.min(1, (t - start) / 1400);
        box.style.setProperty('--pos', (50 + Math.sin(k * Math.PI * 2) * 22).toFixed(1) + '%');
        if (k < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  }, { threshold: 0.6 });
  document.querySelectorAll('.ba-compare').forEach((box) => io.observe(box));
}

// Quote modal
const modal = document.getElementById('quote-modal');
const formView = modal.querySelector('.quote-form-view');
const thanks = modal.querySelector('.quote-thanks');

function openQuote() {
  modal.hidden = false;
  document.body.classList.add('modal-open');
  modal.querySelector('input').focus();
}
function closeQuote() {
  modal.hidden = true;
  document.body.classList.remove('modal-open');
  if (location.hash === '#quote') history.replaceState(null, '', location.pathname);
}

document.querySelectorAll('[data-open-quote]').forEach((el) => el.addEventListener('click', openQuote));
document.querySelectorAll('[data-close-quote]').forEach((el) => el.addEventListener('click', closeQuote));
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) closeQuote(); });

// Links ending in #quote (like the original site's /bathrooms/#quote) open the form directly
if (location.hash === '#quote') openQuote();

// Form submit: replace with a real handler (CRM webhook, Formspree, etc.)
modal.querySelector('.quote-form').addEventListener('submit', (e) => {
  e.preventDefault();
  formView.hidden = true;
  thanks.hidden = false;
});

document.getElementById('year').textContent = new Date().getFullYear();

// Hide the filename label on image slots once the real image loads
document.querySelectorAll('.img-slot[data-file]').forEach((slot) => {
  const img = new Image();
  img.onload = () => slot.classList.add('has-img');
  img.src = slot.dataset.file;
});

// ===== Motion =====
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Scroll reveal: sections and their cards slide up as they come into view.
// Anything already on screen at load stays visible.
if (!reduceMotion && 'IntersectionObserver' in window) {
  const groups = ['.section .container > *:not(.card-grid):not(.ba-list):not(.gallery):not(.steps):not(.review-track):not(.faq)',
    '.card', '.ba-card', '.gallery-item', '.steps li', '.faq details', '.band-inner > *', '.stat'];
  const revealIO = new IntersectionObserver((entries) => {
    entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.remove('pre'); revealIO.unobserve(e.target); } });
  }, { threshold: 0.15 });
  document.querySelectorAll(groups.join(',')).forEach((el) => {
    el.classList.add('reveal');
    const i = [...el.parentElement.children].indexOf(el);
    el.style.setProperty('--d', Math.min(i, 5) * 0.08 + 's');
    if (el.getBoundingClientRect().top > window.innerHeight) { el.classList.add('pre'); revealIO.observe(el); }
  });
}

// Stats count up from 0 the first time they are seen (the final number is in the HTML)
if (!reduceMotion && 'IntersectionObserver' in window) {
  const countIO = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      countIO.unobserve(e.target);
      const el = e.target;
      const target = parseInt(el.textContent, 10);
      const suffix = el.textContent.replace(/^\d+/, '');
      const start = performance.now();
      const tick = (t) => {
        const k = Math.min(1, (t - start) / 1600);
        el.textContent = Math.round(target * (1 - Math.pow(1 - k, 3))) + suffix;
        if (k < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });
  }, { threshold: 0.6 });
  document.querySelectorAll('.stat-num').forEach((el) => countIO.observe(el));
}
