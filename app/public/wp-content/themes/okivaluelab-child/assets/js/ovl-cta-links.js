document.addEventListener('DOMContentLoaded', () => {
  const cards = document.querySelectorAll('.cta-mini .ovl-cta-links__list li');
  if (!cards.length) {
    return;
  }

  cards.forEach((card) => {
    const link = card.querySelector('a');
    if (!link || !link.href) {
      return;
    }

    card.setAttribute('role', 'link');
    card.setAttribute('tabindex', '0');

    card.addEventListener('click', (event) => {
      if (event.target.closest('a')) {
        return;
      }
      window.location.href = link.href;
    });

    card.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        window.location.href = link.href;
      }
    });
  });
});
