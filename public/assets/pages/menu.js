document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.menu-tile').forEach(tile => {
    tile.addEventListener('click', () => {
      tile.style.transform = 'scale(.98)';
      setTimeout(() => tile.style.transform = '', 120);
    });
  });
});