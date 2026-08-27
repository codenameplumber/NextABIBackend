/**
 * NexABI — the hero carousel.
 *
 * CSS scroll-snap does the paging, the layout and the touch handling. This
 * file only does the three things CSS cannot: move scrollLeft when an arrow or
 * a dot is pressed, work out which slide is currently centred, and advance on
 * a timer.
 *
 * Written that way on purpose. A JS carousel that owns its own layout breaks
 * the moment a slide's height changes or a phone rotates; scroll-snap is the
 * browser doing the same job natively, and it keeps working with this file
 * disabled — the strip is still swipeable, which on a phone is the whole
 * interaction anyway.
 *
 * Idempotent: every carousel is marked once initialised, so re-running after a
 * Livewire navigation neither double-binds nor leaves a dead instance behind.
 */

const INIT = 'carouselReady';

function setup(root) {
  if (root.dataset[INIT]) return;
  root.dataset[INIT] = '1';

  const track = root.querySelector('[data-carousel-track]');
  const slides = [...root.querySelectorAll('[data-carousel-slide]')];
  const dots = [...root.querySelectorAll('[data-carousel-dot]')];
  const prev = root.querySelector('[data-carousel-prev]');
  const next = root.querySelector('[data-carousel-next]');

  if (!track || slides.length === 0) return;

  let index = 0;
  let timer = null;

  /* Geometry is measured with getBoundingClientRect rather than offsetLeft.

     offsetLeft is relative to the nearest POSITIONED ancestor, which here is
     .carousel (it is position:relative for the arrows) and not the track — and
     the track carries a negative margin to let the card shadow escape its
     overflow box. So offsetLeft arithmetic lands a few pixels off, which
     scroll-snap then quietly corrects, leaving the dots one behind. Rects are
     measured against the viewport, so the difference between two of them is
     exact whatever the offset parent happens to be. */
  function centreOffset(slide) {
    const s = slide.getBoundingClientRect();
    const t = track.getBoundingClientRect();

    return (s.left - t.left) - (t.width - s.width) / 2;
  }

  /* Which slide is nearest the centre. Derived from live position rather than
     tracked in a variable, because the user can swipe, and a counter that does
     not know about that goes out of step with what is on screen. */
  function currentIndex() {
    let best = 0;
    let bestDist = Infinity;

    slides.forEach((slide, i) => {
      const dist = Math.abs(centreOffset(slide));
      if (dist < bestDist) {
        bestDist = dist;
        best = i;
      }
    });

    return best;
  }

  function paint(i) {
    index = i;
    dots.forEach((dot, d) => {
      const on = d === i;
      dot.classList.toggle('is-on', on);
      dot.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    slides.forEach((slide, s) => {
      // Off-screen slides are hidden from the accessibility tree but stay
      // focusable-in-order once scrolled to; inert would break that.
      slide.setAttribute('aria-hidden', s === i ? 'false' : 'true');
    });
  }

  function goTo(i, smooth = true) {
    const wrapped = (i + slides.length) % slides.length;

    track.scrollTo({
      left: track.scrollLeft + centreOffset(slides[wrapped]),
      behavior: smooth && !prefersReducedMotion() ? 'smooth' : 'auto',
    });

    paint(wrapped);
  }

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  /* ------------------------------------------------------------ autoplay */

  const interval = Number(root.dataset.interval || 0);

  function start() {
    // Respecting reduced-motion here matters more than anywhere else on the
    // page: an auto-advancing carousel is the canonical vestibular trigger.
    if (!interval || prefersReducedMotion() || slides.length < 2) return;
    stop();
    timer = setInterval(() => goTo(index + 1), interval);
  }

  function stop() {
    if (timer) clearInterval(timer);
    timer = null;
  }

  /* -------------------------------------------------------------- events */

  prev?.addEventListener('click', () => { stop(); goTo(index - 1); start(); });
  next?.addEventListener('click', () => { stop(); goTo(index + 1); start(); });

  dots.forEach((dot) => {
    dot.addEventListener('click', () => {
      stop();
      goTo(Number(dot.dataset.carouselDot));
      start();
    });
  });

  // Pause while someone is actually looking at or interacting with it. An
  // advance mid-read is the single most irritating thing a carousel does.
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', (e) => {
    if (!root.contains(e.relatedTarget)) start();
  });

  root.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') { e.preventDefault(); stop(); goTo(index - 1); start(); }
    if (e.key === 'ArrowRight') { e.preventDefault(); stop(); goTo(index + 1); start(); }
  });

  // Swipes and momentum scrolling change the index without going through
  // goTo(), so the dots are repainted from the scroll position itself.
  let raf = null;
  track.addEventListener('scroll', () => {
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => paint(currentIndex()));
  }, { passive: true });

  // A tab in the background still fires setInterval, so a minute away would
  // otherwise come back to a carousel that had raced through twelve slides.
  document.addEventListener('visibilitychange', () => {
    document.hidden ? stop() : start();
  });

  paint(0);
  start();
}

function scan() {
  document.querySelectorAll('[data-carousel]').forEach(setup);
}

scan();
document.addEventListener('livewire:navigated', scan);
