document.addEventListener('click', (event) => {
  const menu = document.querySelector('.catalog-menu');
  if (menu && !menu.contains(event.target)) menu.open = false;
  if (event.target.closest('[data-focus-search]')) {
    event.preventDefault();
    document.querySelector('#product-search')?.focus();
  }
});
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    const menu = document.querySelector('.catalog-menu');
    if (menu?.open) {
      menu.open = false;
      menu.querySelector('summary').focus();
    }
  }
});
