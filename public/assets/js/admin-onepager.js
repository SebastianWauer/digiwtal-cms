'use strict';
(() => {
  const list = document.querySelector('[data-onepager-list]');
  const order = document.querySelector('[data-onepager-order]');
  if (!list || !order) return;
  const sync = () => {
    const rows = [...list.children];
    order.value = JSON.stringify(rows.map(row => Number(row.dataset.pageId)));
    rows.forEach((row, index) => {
      row.querySelector('[data-position]').textContent = String(index + 1);
      if (row.dataset.home === '1' || row.getAttribute('draggable') !== 'true') return;
      row.querySelector('[data-move="up"]').disabled = !row.previousElementSibling || row.previousElementSibling.dataset.home === '1';
      row.querySelector('[data-move="down"]').disabled = !row.nextElementSibling;
    });
  };
  list.addEventListener('click', event => {
    const button = event.target.closest('[data-move]');
    if (!button || button.disabled) return;
    const row = button.closest('[data-page-id]');
    if (button.dataset.move === 'up') row.previousElementSibling?.before(row);
    else row.nextElementSibling?.after(row);
    sync();
  });
  let dragged = null;
  list.addEventListener('dragstart', event => {
    const row = event.target.closest('[data-page-id]');
    if (!row || row.dataset.home === '1' || event.target.closest('input,a,button')) { event.preventDefault(); return; }
    dragged = row;
    row.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', row.dataset.pageId);
  });
  list.addEventListener('dragover', event => {
    const target = event.target.closest('[data-page-id]');
    if (!dragged || !target || target === dragged || target.dataset.home === '1') return;
    event.preventDefault();
    const below = event.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2;
    if (below) target.after(dragged); else target.before(dragged);
    sync();
  });
  list.addEventListener('drop', event => event.preventDefault());
  list.addEventListener('dragend', () => { dragged?.classList.remove('is-dragging'); dragged = null; sync(); });
  sync();
})();
