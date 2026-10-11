<?php
// Site editor: first-time setup, sign-in, and the chat + live preview workspace.
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

ensure_data_dir();
start_session();
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$error = '';
$noUsers = count(users()) === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';
    if (!csrf_ok($_POST['csrf'] ?? null)) {
        $error = 'The page expired. Try again.';
    } elseif ($form === 'setup' && $noUsers) {
        $setup = load_php('setup.php');
        $name = strtolower(trim((string) ($_POST['name'] ?? '')));
        $pass = (string) ($_POST['password'] ?? '');
        $key = trim((string) ($_POST['api_key'] ?? ''));
        if (empty($setup['key']) || !hash_equals($setup['key'], trim((string) ($_POST['setup_key'] ?? '')))) {
            $error = 'The setup code is wrong.';
        } elseif (!valid_username($name)) {
            $error = 'Usernames are 3–32 characters: lowercase letters, numbers, dot, dash or underscore.';
        } elseif (strlen($pass) < 10) {
            $error = 'Use a password of at least 10 characters.';
        } elseif ($key !== '' && !str_starts_with($key, 'sk-ant-')) {
            $error = 'That doesn\'t look like a Claude API key. It should start with "sk-ant-".';
        } else {
            save_php('users.php', [$name => make_user($pass, 'admin', true)]);
            save_php('config.php', ['api_key' => $key, 'model' => DEFAULT_MODEL]);
            @unlink(DATA_DIR . '/setup.php');
            log_in($name);
            header('Location: ./');
            exit;
        }
    } elseif ($form === 'login') {
        $name = strtolower(trim((string) ($_POST['name'] ?? '')));
        $pass = (string) ($_POST['password'] ?? '');
        $key = ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $name;
        $u = users()[$name] ?? null;
        if (!login_attempt_allowed($key)) {
            $error = 'Too many wrong attempts. Wait 15 minutes and try again.';
        } elseif (!$u || !password_verify($pass, $u['hash'])) {
            login_failed($key);
            $error = 'Wrong username or password.';
        } else {
            log_in($name);
            header('Location: ./');
            exit;
        }
    } elseif ($form === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: ./');
        exit;
    }
}

$user = current_user();
$view = $noUsers ? 'setup' : ($user ? 'app' : 'login');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Rosinetti Editor</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
/* Layout: chat column on the left, live preview of the draft on the right; stacked with tabs on phones. */
:root {
  --bg: #0d0d0f; --panel: #151518; --raise: #1d1d21; --line: #2b2a2e;
  --text: #f1eee8; --muted: #a39f97; --gold: #c9a24a; --gold-2: #e6c97c; --danger: #e07a6a; --ok: #8fc49a;
  --display: "Cormorant Garamond", Georgia, serif; --body: "Jost", "Helvetica Neue", Arial, sans-serif;
  color-scheme: dark;
}
* { box-sizing: border-box; }
[hidden] { display: none !important; }
html, body { height: 100%; }
body { margin: 0; background: var(--bg); color: var(--text); font: 300 15px/1.55 var(--body); }
button, input, select, textarea { font: inherit; color: inherit; }
a { color: var(--gold-2); }
h1, h2 { font-family: var(--display); font-weight: 500; letter-spacing: .03em; margin: 0; }
.btn { display: inline-flex; align-items: center; gap: .5em; min-height: 38px; padding: 0 14px; border-radius: 4px; border: 1px solid var(--line); background: var(--raise); cursor: pointer; letter-spacing: .04em; white-space: nowrap; }
.btn:hover:not(:disabled) { border-color: var(--gold); }
.btn:disabled { opacity: .45; cursor: not-allowed; }
.btn:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; }
.btn-gold { background: linear-gradient(100deg, var(--gold-2), var(--gold) 60%, #b08433); color: #16120a; border-color: transparent; font-weight: 500; }
.btn-danger { color: var(--danger); }
.field { display: grid; gap: 6px; }
.field span { font-size: .78rem; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }
input[type=text], input[type=password], select, textarea { width: 100%; min-height: 42px; padding: 8px 12px; background: var(--bg); border: 1px solid var(--line); border-radius: 4px; }
.err { color: var(--danger); }
.muted { color: var(--muted); }

/* Auth pages */
.auth { min-height: 100%; display: grid; place-items: center; padding: 24px 16px; }
.auth form { width: min(420px, 100%); display: grid; gap: 16px; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 28px; }
.auth h1 { font-size: 2rem; color: var(--gold-2); }

/* App */
.app { height: 100%; display: grid; grid-template-rows: auto 1fr; }
.top { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 10px; padding: 10px 16px; border-bottom: 1px solid var(--line); background: var(--panel); }
.brand { font-family: var(--display); font-size: 1.35rem; color: var(--gold-2); margin-right: auto; }
.pill { font-size: .78rem; padding: 3px 10px; border-radius: 999px; border: 1px solid var(--line); }
.pill.dirty { color: var(--gold-2); border-color: rgba(201,162,74,.5); }
.pill.clean { color: var(--ok); }
.work { display: grid; grid-template-columns: minmax(320px, 420px) 1fr; min-height: 0; }
.side { display: grid; grid-template-rows: 1fr auto auto; min-height: 0; border-right: 1px solid var(--line); }
.chat { overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.msg { max-width: 92%; padding: 10px 13px; border-radius: 10px; background: var(--raise); white-space: pre-wrap; overflow-wrap: anywhere; }
.msg.user { align-self: flex-end; background: #2a2416; border: 1px solid rgba(201,162,74,.35); }
.msg.error { border: 1px solid rgba(224,122,106,.5); }
.msg .meta { display: block; font-size: .75rem; color: var(--muted); margin-bottom: 3px; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.chip { font-size: .72rem; padding: 2px 8px; border-radius: 999px; background: rgba(201,162,74,.15); color: var(--gold-2); }
.empty { color: var(--muted); margin: auto 0; text-align: center; }
.compose { display: grid; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--line); }
.compose textarea { min-height: 84px; resize: vertical; }
.compose .row { display: flex; gap: 8px; align-items: center; justify-content: space-between; }
.photos { padding: 12px 16px 16px; border-top: 1px solid var(--line); }
.photos summary { cursor: pointer; color: var(--gold-2); }
.photos form { display: grid; gap: 10px; margin-top: 10px; }
.stage { display: grid; grid-template-rows: auto 1fr; min-height: 0; background: #08080a; }
.stage-bar { display: flex; gap: 8px; align-items: center; padding: 8px 12px; border-bottom: 1px solid var(--line); }
.frame-wrap { display: flex; justify-content: center; min-height: 0; overflow: auto; padding: 12px; }
.frame-wrap iframe { width: 100%; height: 100%; min-height: 480px; border: 1px solid var(--line); border-radius: 6px; background: #000; transition: width .3s; }
.frame-wrap.phone iframe { width: 390px; max-width: 100%; }
.tabs { display: none; }
dialog { width: min(560px, calc(100% - 32px)); max-height: calc(100% - 48px); background: var(--panel); color: var(--text); border: 1px solid var(--line); border-radius: 10px; padding: 24px; }
dialog::backdrop { background: rgba(0,0,0,.6); }
dialog h2 { font-size: 1.6rem; margin-bottom: 14px; }
dialog form { display: grid; gap: 14px; }
.list { display: grid; gap: 8px; margin: 0 0 18px; padding: 0; list-style: none; }
.list li { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; padding: 10px 12px; background: var(--raise); border-radius: 6px; }
.toast { position: fixed; left: 50%; bottom: 20px; transform: translateX(-50%); background: var(--raise); border: 1px solid var(--gold); padding: 10px 16px; border-radius: 6px; z-index: 10; max-width: calc(100% - 32px); }
@media (max-width: 860px) {
  .work { grid-template-columns: 1fr; }
  .side { border-right: 0; }
  .tabs { display: flex; gap: 8px; padding: 8px 16px; border-bottom: 1px solid var(--line); }
  .app { grid-template-rows: auto auto 1fr; }
  .work[data-tab="chat"] .stage, .work[data-tab="preview"] .side { display: none; }
}
@media (prefers-reduced-motion: reduce) { .frame-wrap iframe { transition: none; } }
</style>
</head>
<body>
<?php if ($view === 'setup'): ?>
  <div class="auth">
    <form method="post" autocomplete="off">
      <h1>Set up the editor</h1>
      <p class="muted">Create the first admin account. You'll add everyone else from inside the editor.</p>
      <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
      <input type="hidden" name="form" value="setup"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label class="field"><span>Setup code</span><input id="setup_key" type="text" name="setup_key" required></label>
      <label class="field"><span>Your username</span><input id="setup_name" type="text" name="name" required autocomplete="username"></label>
      <label class="field"><span>Password (10+ characters)</span><input id="setup_pass" type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <label class="field"><span>Claude API key (you can add it later)</span><input id="setup_api" type="password" name="api_key" placeholder="sk-ant-…"></label>
      <button class="btn btn-gold" type="submit">Create admin account</button>
    </form>
  </div>
<?php elseif ($view === 'login'): ?>
  <div class="auth">
    <form method="post">
      <h1>Rosinetti Editor</h1>
      <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
      <input type="hidden" name="form" value="login"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label class="field"><span>Username</span><input id="login_name" type="text" name="name" required autocomplete="username"></label>
      <label class="field"><span>Password</span><input id="login_pass" type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn btn-gold" type="submit">Sign in</button>
    </form>
  </div>
<?php else: ?>
  <div class="app">
    <header class="top">
      <span class="brand">Rosinetti Editor</span>
      <span id="status" class="pill"></span>
      <button class="btn" id="undo" type="button">Undo</button>
      <button class="btn" id="discard" type="button">Discard changes</button>
      <button class="btn btn-gold" id="publish" type="button">Publish</button>
      <a class="btn" href="../" target="_blank" rel="noopener">View live site ↗</a>
      <button class="btn admin-only" id="open-users" type="button" hidden>Users</button>
      <button class="btn admin-only" id="open-versions" type="button" hidden>Versions</button>
      <button class="btn admin-only" id="open-settings" type="button" hidden>Settings</button>
      <button class="btn" id="open-account" type="button"></button>
      <form method="post"><input type="hidden" name="form" value="logout"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><button class="btn" type="submit">Sign out</button></form>
    </header>
    <nav class="tabs"><button class="btn" data-tab="chat" type="button">Chat</button><button class="btn" data-tab="preview" type="button">Preview</button></nav>
    <main class="work" data-tab="chat">
      <section class="side" aria-label="Chat">
        <div class="chat" id="chat" aria-live="polite"></div>
        <form class="compose" id="compose">
          <label class="field"><span>What should change?</span>
            <textarea id="message" placeholder="e.g. Change the main title to “Bathrooms built to last” and make the buttons a bit larger"></textarea></label>
          <div class="row"><span class="muted" id="hint">Ctrl + Enter to send</span><button class="btn btn-gold" id="send" type="submit">Send</button></div>
        </form>
        <details class="photos">
          <summary>Photos</summary>
          <form id="photo-form">
            <label class="field"><span>Where does it go?</span><select id="slot"></select></label>
            <label class="field"><span>Photo (JPG, PNG or WebP)</span><input id="photo" type="file" accept="image/jpeg,image/png,image/webp" required></label>
            <button class="btn" type="submit">Upload to draft</button>
          </form>
        </details>
      </section>
      <section class="stage" aria-label="Preview">
        <div class="stage-bar">
          <span class="muted">Draft preview</span>
          <button class="btn" id="view-desktop" type="button">Desktop</button>
          <button class="btn" id="view-phone" type="button">Phone</button>
        </div>
        <div class="frame-wrap" id="frame-wrap"><iframe id="preview" title="Draft preview" src="preview.php"></iframe></div>
      </section>
    </main>
  </div>

  <dialog id="dlg-users"><h2>Users</h2>
    <p class="muted">Only people listed here can sign in. Admins can manage users and settings; others can edit, and publish only if allowed.</p>
    <ul class="list" id="user-list"></ul>
    <form id="user-form">
      <label class="field"><span>Username</span><input id="u_name" type="text" required></label>
      <label class="field"><span>Password (10+ characters, leave empty to keep it)</span><input id="u_pass" type="password" autocomplete="new-password"></label>
      <label class="field"><span>Role</span><select id="u_role"><option value="editor">Editor</option><option value="admin">Admin</option></select></label>
      <label><input id="u_pub" type="checkbox"> Can publish</label>
      <div class="row"><button class="btn btn-gold" type="submit">Save user</button> <button class="btn" type="button" data-close>Close</button></div>
    </form>
  </dialog>
  <dialog id="dlg-versions"><h2>Published versions</h2>
    <p class="muted">Each time someone publishes, the previous live site is kept here. Loading one puts it in the draft; publish to make it live again.</p>
    <ul class="list" id="version-list"></ul>
    <button class="btn" type="button" data-close>Close</button>
  </dialog>
  <dialog id="dlg-settings"><h2>Settings</h2>
    <form id="settings-form">
      <p id="key-status" class="muted"></p>
      <label class="field"><span>Claude API key (leave empty to keep the current one)</span><input id="s_key" type="password" placeholder="sk-ant-…" autocomplete="off"></label>
      <label class="field"><span>Model</span><input id="s_model" type="text"></label>
      <div><button class="btn btn-gold" type="submit">Save</button> <button class="btn" type="button" data-close>Close</button></div>
    </form>
  </dialog>
  <dialog id="dlg-account"><h2>Change password</h2>
    <form id="account-form">
      <label class="field"><span>Current password</span><input id="a_old" type="password" required autocomplete="current-password"></label>
      <label class="field"><span>New password (10+ characters)</span><input id="a_new" type="password" required minlength="10" autocomplete="new-password"></label>
      <div><button class="btn btn-gold" type="submit">Change password</button> <button class="btn" type="button" data-close>Close</button></div>
    </form>
  </dialog>

<script>
let csrf = <?= json_encode(csrf_token()) ?>;
let S = null;
const $ = (id) => document.getElementById(id);

async function api(action, body, query = '') {
  const isForm = body instanceof FormData;
  const res = await fetch('api.php?action=' + action + query, {
    method: 'POST',
    headers: Object.assign({ 'X-CSRF-Token': csrf }, isForm ? {} : { 'Content-Type': 'application/json' }),
    body: isForm ? body : JSON.stringify(body || {}),
  });
  let data = {};
  try { data = await res.json(); } catch (e) { data = { error: 'The server sent an unexpected answer (' + res.status + ').' }; }
  if (!res.ok || data.error) throw new Error(data.error || 'Something went wrong.');
  return data;
}

function toast(text) {
  const t = document.createElement('div');
  t.className = 'toast'; t.textContent = text; t.setAttribute('role', 'status');
  document.body.appendChild(t); setTimeout(() => t.remove(), 4000);
}

function time(ts) { return new Date(ts * 1000).toLocaleString([], { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }); }

function render(s) {
  S = s;
  const chat = $('chat'); chat.textContent = '';
  if (!s.chat.length) {
    const p = document.createElement('p'); p.className = 'empty';
    p.textContent = 'Describe a change in your own words. The preview on the right updates when it\'s done; nothing goes live until someone presses Publish.';
    chat.appendChild(p);
  }
  for (const m of s.chat) {
    const d = document.createElement('div');
    d.className = 'msg ' + (m.role === 'user' ? 'user' : 'ai') + (m.error ? ' error' : '');
    const meta = document.createElement('span'); meta.className = 'meta'; meta.textContent = m.who + ' · ' + time(m.at);
    d.appendChild(meta); d.appendChild(document.createTextNode(m.text));
    if (m.changed && m.changed.length) {
      const c = document.createElement('div'); c.className = 'chips';
      m.changed.forEach((f) => { const x = document.createElement('span'); x.className = 'chip'; x.textContent = f; c.appendChild(x); });
      d.appendChild(c);
    }
    chat.appendChild(d);
  }
  chat.scrollTop = chat.scrollHeight;
  $('status').textContent = s.dirty ? 'Unpublished changes' : 'Draft matches live site';
  $('status').className = 'pill ' + (s.dirty ? 'dirty' : 'clean');
  $('undo').disabled = !s.canUndo;
  $('discard').disabled = !s.dirty;
  $('publish').disabled = !s.dirty || !s.user.can_publish;
  $('publish').title = s.user.can_publish ? '' : 'Ask an admin for permission to publish';
  $('open-account').textContent = s.user.name;
  document.querySelectorAll('.admin-only').forEach((b) => { b.hidden = s.user.role !== 'admin'; });
  $('hint').textContent = s.hasKey ? 'Ctrl + Enter to send' : 'No Claude API key yet: an admin adds it under Settings.';
  const slot = $('slot'), keep = slot.value; slot.textContent = '';
  for (const [file, label] of Object.entries(s.slots)) {
    const o = document.createElement('option'); o.value = file;
    o.textContent = label + (s.draftImages.includes(file) ? '  ✓ uploaded' : ''); slot.appendChild(o);
  }
  if (keep) slot.value = keep;
  if (s.users) renderAdmin(s);
}

function renderAdmin(s) {
  const ul = $('user-list'); ul.textContent = '';
  for (const u of s.users) {
    const li = document.createElement('li');
    const label = document.createElement('span');
    label.textContent = u.name + ' · ' + (u.role === 'admin' ? 'Admin' : 'Editor') + (u.role !== 'admin' ? (u.can_publish ? ' · can publish' : ' · can\'t publish') : '');
    const actions = document.createElement('span');
    const edit = document.createElement('button'); edit.className = 'btn'; edit.type = 'button'; edit.textContent = 'Edit';
    edit.onclick = () => { $('u_name').value = u.name; $('u_role').value = u.role; $('u_pub').checked = u.can_publish; $('u_pass').value = ''; $('u_pass').focus(); };
    actions.appendChild(edit);
    if (u.name !== s.user.name) {
      const del = document.createElement('button'); del.className = 'btn btn-danger'; del.type = 'button'; del.textContent = 'Remove';
      del.onclick = async () => { if (!confirm('Remove ' + u.name + '? They will be signed out.')) return; try { render(await api('user_delete', { name: u.name })); toast('Removed ' + u.name); } catch (e) { toast(e.message); } };
      actions.append(' ', del);
    }
    li.append(label, actions); ul.appendChild(li);
  }
  const vl = $('version-list'); vl.textContent = '';
  if (!s.versions.length) { const li = document.createElement('li'); li.textContent = 'Nothing published from the editor yet.'; vl.appendChild(li); }
  for (const v of s.versions) {
    const li = document.createElement('li');
    const label = document.createElement('span'); label.textContent = 'Live site before ' + v.by + '\'s publish · ' + time(v.at);
    const b = document.createElement('button'); b.className = 'btn'; b.type = 'button'; b.textContent = 'Load into draft';
    b.onclick = async () => { try { render(await api('restore', { id: v.id })); reloadPreview(); $('dlg-versions').close(); toast('Loaded into the draft. Publish to make it live.'); } catch (e) { toast(e.message); } };
    li.append(label, b); vl.appendChild(li);
  }
  $('key-status').textContent = s.hasKey ? 'A Claude API key is saved.' : 'No Claude API key saved yet.';
  $('s_model').value = s.model;
}

function reloadPreview() {
  const f = $('preview'); const y = f.contentWindow ? f.contentWindow.scrollY : 0;
  f.onload = () => { try { f.contentWindow.scrollTo(0, y); } catch (e) {} };
  f.src = 'preview.php?t=' + Date.now();
}

$('compose').addEventListener('submit', async (e) => {
  e.preventDefault();
  const text = $('message').value.trim(); if (!text) return;
  $('send').disabled = true; $('message').disabled = true;
  const started = Date.now();
  const timer = setInterval(() => { $('hint').textContent = 'Working on it… ' + Math.round((Date.now() - started) / 1000) + 's (usually under a minute)'; }, 1000);
  try {
    render(await api('ask', { message: text }));
    $('message').value = ''; reloadPreview();
    if (window.matchMedia('(max-width: 860px)').matches) setTab('preview');
  } catch (err) {
    toast(err.message);
    try { render(await api('state')); } catch (e2) {}
  } finally {
    clearInterval(timer); $('send').disabled = false; $('message').disabled = false;
    if (S) $('hint').textContent = S.hasKey ? 'Ctrl + Enter to send' : 'No Claude API key yet: an admin adds it under Settings.';
  }
});
$('message').addEventListener('keydown', (e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) $('compose').requestSubmit(); });

$('undo').onclick = async () => { try { render(await api('undo')); reloadPreview(); } catch (e) { toast(e.message); } };
$('discard').onclick = async () => {
  if (!confirm('Throw away all unpublished changes? The draft will match the live site again.')) return;
  try { render(await api('discard')); reloadPreview(); } catch (e) { toast(e.message); }
};
$('publish').onclick = async () => {
  if (!confirm('Publish the draft? Visitors will see these changes right away.')) return;
  try { render(await api('publish')); toast('Published. The live site is updated.'); } catch (e) { toast(e.message); }
};

$('photo-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const file = $('photo').files[0]; if (!file) return;
  const fd = new FormData(); fd.append('photo', file);
  try { render(await api('upload', fd, '&slot=' + encodeURIComponent($('slot').value))); $('photo').value = ''; reloadPreview(); toast('Photo added to the draft.'); }
  catch (err) { toast(err.message); }
});

$('view-desktop').onclick = () => $('frame-wrap').classList.remove('phone');
$('view-phone').onclick = () => $('frame-wrap').classList.add('phone');
function setTab(t) { document.querySelector('.work').dataset.tab = t; }
document.querySelectorAll('.tabs [data-tab]').forEach((b) => { b.onclick = () => setTab(b.dataset.tab); });

const dialogs = { 'open-users': 'dlg-users', 'open-versions': 'dlg-versions', 'open-settings': 'dlg-settings', 'open-account': 'dlg-account' };
for (const [btn, dlg] of Object.entries(dialogs)) $(btn).onclick = () => $(dlg).showModal();
document.querySelectorAll('[data-close]').forEach((b) => { b.onclick = () => b.closest('dialog').close(); });

$('user-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  try {
    render(await api('user_save', { name: $('u_name').value, password: $('u_pass').value, role: $('u_role').value, can_publish: $('u_pub').checked }));
    toast('Saved ' + $('u_name').value.trim().toLowerCase()); e.target.reset();
  } catch (err) { toast(err.message); }
});
$('settings-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  try { render(await api('settings', { api_key: $('s_key').value, model: $('s_model').value })); $('s_key').value = ''; toast('Settings saved.'); }
  catch (err) { toast(err.message); }
});
$('account-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  try { const r = await api('password', { old: $('a_old').value, new: $('a_new').value }); csrf = r.csrf; e.target.reset(); $('dlg-account').close(); toast('Password changed.'); }
  catch (err) { toast(err.message); }
});

api('state').then(render).catch((e) => toast(e.message));
</script>
<?php endif; ?>
</body>
</html>
