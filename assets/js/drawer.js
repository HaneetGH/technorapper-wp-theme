(function () {
  var toggle = document.querySelector('.menu-toggle');
  var drawer = document.getElementById('site-drawer');
  var backdrop = document.querySelector('.drawer-backdrop');
  if (!toggle || !drawer || !backdrop) {
    return;
  }

  document.documentElement.classList.add('js');

  var lastFocus = null;

  function isOpen() {
    return drawer.classList.contains('is-open');
  }

  function focusables() {
    return drawer.querySelectorAll('a[href], button:not([disabled])');
  }

  function openDrawer() {
    lastFocus = document.activeElement;
    document.body.classList.add('drawer-open');
    drawer.inert = false;
    drawer.classList.add('is-open');
    backdrop.classList.add('is-open');
    toggle.setAttribute('aria-expanded', 'true');
    drawer.setAttribute('aria-hidden', 'false');
    var closeBtn = drawer.querySelector('.drawer-close');
    if (closeBtn) {
      closeBtn.focus();
    }
  }

  function closeDrawer() {
    if (!isOpen()) {
      return;
    }
    document.body.classList.remove('drawer-open');
    drawer.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    drawer.inert = true;
    toggle.setAttribute('aria-expanded', 'false');
    drawer.setAttribute('aria-hidden', 'true');
    if (lastFocus && typeof lastFocus.focus === 'function') {
      lastFocus.focus();
    }
  }

  toggle.addEventListener('click', function () {
    if (isOpen()) {
      closeDrawer();
    } else {
      openDrawer();
    }
  });

  document.querySelectorAll('[data-drawer-close]').forEach(function (el) {
    el.addEventListener('click', closeDrawer);
  });

  document.addEventListener('keydown', function (e) {
    if (!isOpen()) {
      return;
    }
    if (e.key === 'Escape') {
      closeDrawer();
      return;
    }
    if (e.key !== 'Tab') {
      return;
    }
    var nodes = Array.prototype.slice.call(focusables());
    if (!nodes.length) {
      return;
    }
    var first = nodes[0];
    var last = nodes[nodes.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });

  drawer.querySelectorAll('.drawer-more').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.closest('li');
      var expanded = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      btn.textContent = expanded ? '+' : '\u2212';
      if (item) {
        item.classList.toggle('is-expanded', !expanded);
      }
    });
  });
})();
