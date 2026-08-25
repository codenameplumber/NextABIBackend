/**
 * Password feedback that DESCRIBES the password rather than scoring it.
 *
 * A meter that says "weak" without saying why helps nobody — the user is left
 * guessing which of four rules they have not met. Ported verbatim in behaviour
 * from the prototype's register page.
 */
export function bindStrength(inputId = 'password', hintId = 'strength') {
  const input = document.getElementById(inputId);
  const hint = document.getElementById(hintId);

  if (!input || !hint) return;

  const DEFAULT = 'Use 8 characters or more.';
  hint.textContent = DEFAULT;

  input.addEventListener('input', (e) => {
    const v = e.target.value;

    if (!v) {
      hint.textContent = DEFAULT;
      hint.style.color = '';
      return;
    }

    const has = {
      len: v.length >= 8,
      num: /\d/.test(v),
      up: /[A-Z]/.test(v),
      sym: /[^\w\s]/.test(v),
    };
    const score = Object.values(has).filter(Boolean).length;

    const missing = [
      !has.len && 'more characters',
      !has.num && 'a number',
      !has.up && 'a capital',
      !has.sym && 'a symbol',
    ].filter(Boolean);

    hint.textContent = score >= 3
      ? 'Strong enough.'
      : `Could use ${missing.slice(0, 2).join(' and ')}.`;
    hint.style.color = score >= 3 ? 'var(--pos)' : '';
  });
}

bindStrength();
