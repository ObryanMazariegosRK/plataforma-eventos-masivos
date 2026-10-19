/* Soirée: búsqueda y filtros visuales de la página actual de eventos.
   No modifica solicitudes API, reservas, navegación ni permisos. */
(() => {
  'use strict';
  const panel = document.getElementById('eventos-tools');
  const tbody = document.getElementById('tbody');
  if (!panel || !tbody || document.getElementById('so-event-filters')) return;

  const controls = document.createElement('div');
  controls.id = 'so-event-filters';
  controls.className = 'so-event-filters';
  controls.hidden = true;

  const search = document.createElement('input');
  search.type = 'search';
  search.placeholder = 'Buscar evento en esta página…';
  search.setAttribute('aria-label', 'Buscar eventos de esta página');

  const status = document.createElement('select');
  status.setAttribute('aria-label', 'Filtrar eventos por estado');
  [['', 'Todos los estados'], ['publicado', 'Publicados'], ['borrador', 'Borradores'], ['agotado', 'Agotados'], ['finalizado', 'Finalizados'], ['cancelado', 'Cancelados']]
    .forEach(([value, label]) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = label;
      status.append(option);
    });

  const count = document.createElement('span');
  count.className = 'so-filter-count';
  count.setAttribute('aria-live', 'polite');
  controls.append(search, status, count);
  panel.insertAdjacentElement('afterend', controls);

  function normalize(s) {
    return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
  }

  function apply() {
    const isEvents = !panel.hidden;
    controls.hidden = !isEvents;
    if (!isEvents) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    let visible = 0;
    const needle = normalize(search.value.trim());
    const wanted = normalize(status.value);
    for (const row of rows) {
      const cells = row.querySelectorAll('td');
      if (!cells.length || row.querySelector('.empty')) continue;
      const name = normalize(cells[1]?.textContent || row.textContent);
      const stateText = normalize(row.querySelector('.status')?.textContent || '');
      const matches = (!needle || name.includes(needle)) && (!wanted || stateText.includes(wanted));
      row.hidden = !matches;
      // El CSS original puede cambiar display de filas: asegurar ocultación.
      row.style.display = matches ? '' : 'none';
      if (matches) visible++;
    }
    count.textContent = `${visible} evento${visible === 1 ? '' : 's'} visible${visible === 1 ? '' : 's'} (página actual)`;
  }

  search.addEventListener('input', apply);
  status.addEventListener('change', apply);
  const observer = new MutationObserver(apply);
  observer.observe(tbody, { childList: true });
  // La sección cambia cuando el usuario hace clic en la navegación.
  const nav = document.getElementById('nav');
  if (nav) nav.addEventListener('click', () => queueMicrotask(apply));
  new MutationObserver(apply).observe(panel, { attributes: true, attributeFilter: ['hidden'] });
  apply();
})();
