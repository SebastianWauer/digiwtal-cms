(function () {
  const list = document.getElementById('navList');
  const form = document.getElementById('navForm');
  const navJson = document.getElementById('navJson');

  const pages = Array.isArray(window.__CMS_PAGES__) ? window.__CMS_PAGES__ : [];

  if (!list || !form || !navJson) return;

  let dragEl = null;

  function safeParseJson(str, fallback) {
    try {
      const v = JSON.parse(str);
      return Array.isArray(v) ? v : fallback;
    } catch {
      return fallback;
    }
  }

  function renumberOrders() {
    const rows = Array.from(list.querySelectorAll('.row'));
    rows.forEach((row, idx) => {
      const order = (idx + 1) * 10;
      row.dataset.order = String(order);
      const o = row.querySelector('.js-order');
      if (o) o.textContent = String(order);
    });
  }

  function renderVisibilityBoxes(row) {
    const visibleBox = row.querySelector('.js-visible-box');
    const hiddenBox = row.querySelector('.js-hidden-box');
    if (!visibleBox || !hiddenBox) return;

    const visible = safeParseJson(row.dataset.visible || '[]', []);
    const hidden  = safeParseJson(row.dataset.hidden  || '[]', []);

    visibleBox.innerHTML = '';
    hiddenBox.innerHTML = '';

    // helper für Checkboxen
    function addCheckbox(container, kind, page, checked) {
      const id = `${kind}_${btoa(page.slug).replace(/=/g, '')}_${Math.random().toString(16).slice(2)}`;

      const wrap = document.createElement('label');
      wrap.className = 'vischk';
      wrap.htmlFor = id;

      const input = document.createElement('input');
      input.type = 'checkbox';
      input.id = id;
      input.dataset.kind = kind;
      input.dataset.slug = page.slug;
      input.checked = checked;

      const text = document.createElement('span');
      text.className = 'vischk__text';
      text.textContent = `${page.title} (${page.slug})`;

      wrap.appendChild(input);
      wrap.appendChild(text);
      container.appendChild(wrap);
    }

    pages.forEach((p) => {
      if (!p || typeof p.slug !== 'string') return;
      const inVisible = visible.includes(p.slug);
      const inHidden  = hidden.includes(p.slug);

      addCheckbox(visibleBox, 'visible', p, inVisible);
      addCheckbox(hiddenBox,  'hidden',  p, inHidden);
    });
  }

  function collect() {
    const rows = Array.from(list.querySelectorAll('.row'));

    return rows.map((row) => {
      const enabled = row.querySelector('.js-enabled')?.checked ?? true;
      const header = row.querySelector('.js-header')?.checked ?? false;
      const footer = row.querySelector('.js-footer')?.checked ?? false;

      const visible = safeParseJson(row.dataset.visible || '[]', []);
      const hidden  = safeParseJson(row.dataset.hidden  || '[]', []);

      return {
        label: row.dataset.label || '',
        url: row.dataset.url || '/',
        enabled: !!enabled,
        show_in_header: !!header,
        show_in_footer: !!footer,
        order: parseInt(row.dataset.order || '0', 10) || 0,
        visible_on: visible,
        hidden_on: hidden,
      };
    });
  }

  function updateHiddenField() {
    navJson.value = JSON.stringify(collect());
  }

  // Checkboxen (Aktiv/Header/Footer) sofort in JSON
  list.addEventListener('change', (e) => {
    const target = e.target;
    if (!(target instanceof HTMLElement)) return;

    // Sichtbarkeit-Checkboxen
    if (target.matches('input[type="checkbox"][data-kind]')) {
      const row = target.closest('.row');
      if (!row) return;

      const kind = target.dataset.kind;
      const slug = target.dataset.slug;
      if (!kind || !slug) return;

      const current = safeParseJson(kind === 'visible' ? row.dataset.visible : row.dataset.hidden, []);

      const next = target.checked
        ? Array.from(new Set([...current, slug]))
        : current.filter((s) => s !== slug);

      if (kind === 'visible') row.dataset.visible = JSON.stringify(next);
      if (kind === 'hidden')  row.dataset.hidden  = JSON.stringify(next);

      updateHiddenField();
      return;
    }

    updateHiddenField();
  });

  // Toggle Sichtbarkeit-Panel
  list.addEventListener('click', (e) => {
    const btn = e.target.closest('.js-visibility');
    if (!btn) return;

    const row = btn.closest('.row');
    if (!row) return;

    const panel = row.querySelector('.js-visibility-panel');
    if (!panel) return;

    const isHidden = panel.hasAttribute('hidden');
    if (isHidden) {
      renderVisibilityBoxes(row);
      panel.removeAttribute('hidden');
    } else {
      panel.setAttribute('hidden', '');
    }
  });

  // Clear buttons
  list.addEventListener('click', (e) => {
    const row = e.target.closest('.row');
    if (!row) return;

    if (e.target.closest('.js-clear-visible')) {
      row.dataset.visible = '[]';
      renderVisibilityBoxes(row);
      updateHiddenField();
    }

    if (e.target.closest('.js-clear-hidden')) {
      row.dataset.hidden = '[]';
      renderVisibilityBoxes(row);
      updateHiddenField();
    }
  });

  // Drag & Drop
  list.addEventListener('dragstart', (e) => {
    const target = e.target.closest('.row');
    if (!target) return;
    dragEl = target;
    target.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });

  list.addEventListener('dragend', () => {
    if (dragEl) dragEl.classList.remove('dragging');
    Array.from(list.querySelectorAll('.row')).forEach((r) => r.classList.remove('over'));
    dragEl = null;
    renumberOrders();
    updateHiddenField();
  });

  list.addEventListener('dragover', (e) => {
    e.preventDefault();
    const over = e.target.closest('.row');
    if (!over || !dragEl || over === dragEl) return;

    Array.from(list.querySelectorAll('.row')).forEach((r) => r.classList.remove('over'));
    over.classList.add('over');

    const rect = over.getBoundingClientRect();
    const before = (e.clientY - rect.top) < rect.height / 2;

    if (before) {
      list.insertBefore(dragEl, over);
    } else {
      list.insertBefore(dragEl, over.nextSibling);
    }
  });

  // Beim Submit final JSON setzen
  form.addEventListener('submit', () => {
    renumberOrders();
    updateHiddenField();
  });

  // Initial
  renumberOrders();
  updateHiddenField();
})();
