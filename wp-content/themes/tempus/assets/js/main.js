/* Tempus — front-end behavior. Vanilla JS, no dependencies. */
(function () {
  'use strict';

  // --- Scroll reveal: fade/slide-up sections as they enter the viewport ---
  var revealEls = document.querySelectorAll('.tz-reveal');
  if ('IntersectionObserver' in window && revealEls.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    // No IO support: just show everything.
    revealEls.forEach(function (el) { el.classList.add('is-visible'); });
  }

  // --- Mobile nav toggle ---
  var toggle = document.querySelector('.tz-nav-toggle');
  var nav = document.querySelector('.tz-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.style.display === 'flex';
      nav.style.display = open ? '' : 'flex';
      nav.style.flexDirection = 'column';
      nav.style.position = 'absolute';
      nav.style.top = '76px';
      nav.style.left = '0';
      nav.style.right = '0';
      nav.style.height = 'auto';
      nav.style.background = 'var(--espresso)';
      nav.style.padding = '12px 0';
      nav.style.borderBottom = '1px solid var(--outline-variant)';
    });
  }

  // --- Smooth-scroll for same-page anchors (#rituals, #about, etc.) ---
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var id = a.getAttribute('href');
      if (id.length > 1) {
        var target = document.querySelector(id);
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }
    });
  });
})();
