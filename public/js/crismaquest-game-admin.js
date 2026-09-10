(() => {
  const search = document.getElementById('cq-mission-search');
  const counter = document.getElementById('cq-mission-count');
  if (!search || !counter) return;
  const rows = [...document.querySelectorAll('#cq-mission-list tbody tr')];
  const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const entries = rows.map(row => ({row, text:normalize(row.textContent)}));
  search.addEventListener('input', () => {
    const query = normalize(search.value.trim());
    let visible = 0;
    for (const entry of entries) {
      entry.row.hidden = !entry.text.includes(query);
      if (!entry.row.hidden) visible++;
    }
    counter.textContent = `${visible} de ${rows.length} missões`;
  });
})();
