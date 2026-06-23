// Mobile nav toggle
const toggle = document.querySelector('.nav__toggle');
const links = document.querySelector('.nav__links');
if (toggle && links) {
  toggle.addEventListener('click', () => {
    const open = links.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
}

// Basic front-end form acknowledgement (no backend wired yet)
const form = document.querySelector('form[data-demo]');
if (form) {
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const note = form.querySelector('.form-note');
    if (note) {
      note.hidden = false;
      note.textContent = 'Thanks — this is a demo form. Connect Formspree or Netlify Forms to receive enquiries.';
    }
    form.reset();
  });
}
