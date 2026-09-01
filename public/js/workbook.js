document.addEventListener('click', (event) => {
  const printButton = event.target.closest('[data-workbook-print]');
  if (!printButton) return;

  window.print();
});
