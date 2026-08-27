/**
 * Show/hide on every password field.
 *
 * Written as a scanner rather than as a Blade component on purpose. The seven
 * password inputs across the four auth pages carry three different markup
 * styles between them, and a component would have meant editing all of them
 * and hoping the next one added remembers to use it. This finds any
 * `input[type="password"]` on the page, so a field added later is covered
 * without anyone remembering anything.
 *
 * Progressive enhancement: with this file blocked the field is an ordinary
 * password input, which is exactly what it was before.
 *
 * Idempotent, and it re-scans after a Livewire navigation.
 */

const EYE = 'M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z';
const PUPIL = 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z';
const SLASH = 'M3 3l18 18';

function svg(paths) {
  return `<svg viewBox="0 0 24 24" width="17" height="17" fill="none"
    stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
    stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;
}

const ICON_SHOW = svg(`<path d="${EYE}"/><path d="${PUPIL}"/>`);
const ICON_HIDE = svg(`<path d="${EYE}"/><path d="${PUPIL}"/><path d="${SLASH}"/>`);

function enhance(input) {
  if (input.dataset.pwEye) return;
  input.dataset.pwEye = '1';

  /* The input is wrapped rather than the button being appended as a sibling,
     because the button is positioned against the field and the field's parent
     is a .field that also holds the label, the hint and the error. */
  const wrap = document.createElement('div');
  wrap.className = 'pw';
  input.parentNode.insertBefore(wrap, input);
  wrap.appendChild(input);

  const btn = document.createElement('button');
  btn.type = 'button';                 // never submits the form it sits in
  btn.className = 'pw__eye';
  btn.innerHTML = ICON_SHOW;
  btn.setAttribute('aria-label', 'Show password');
  btn.setAttribute('aria-pressed', 'false');
  btn.tabIndex = 0;
  wrap.appendChild(btn);

  btn.addEventListener('click', () => {
    const shown = input.type === 'text';

    input.type = shown ? 'password' : 'text';
    btn.innerHTML = shown ? ICON_SHOW : ICON_HIDE;
    btn.setAttribute('aria-label', shown ? 'Show password' : 'Hide password');
    btn.setAttribute('aria-pressed', shown ? 'false' : 'true');

    /* Focus goes back to the field with the caret where it was. Without this
       the cursor jumps to the start, which on a half-typed password means
       the next keystroke lands in the wrong place. */
    const pos = input.value.length;
    input.focus();
    try { input.setSelectionRange(pos, pos); } catch { /* not all types allow it */ }
  });
}

function scan() {
  document.querySelectorAll('input[type="password"]').forEach(enhance);
}

scan();
document.addEventListener('livewire:navigated', scan);
