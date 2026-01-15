(() => {
  const data = window.ovlFavoritesData;
  if (!data) return;

  const buttons = Array.from(document.querySelectorAll('[data-ovl-fav]'));
  if (!buttons.length) return;

  const favoriteSet = new Set(
    Array.isArray(data.favoriteIds) ? data.favoriteIds.map((id) => parseInt(id, 10)).filter(Boolean) : []
  );

  const setButtonState = (button, isActive) => {
    const label = button.querySelector('.ovl-favorite-button__label');
    if (isActive) {
      button.classList.add('is-active');
      button.setAttribute('aria-pressed', 'true');
      if (label) label.textContent = 'お気に入り済み';
    } else {
      button.classList.remove('is-active');
      button.setAttribute('aria-pressed', 'false');
      if (label) label.textContent = 'お気に入りに追加';
    }
  };

  const toggleFavorite = async (button, propertyId) => {
    if (!data.isLoggedIn) return;

    button.disabled = true;
    button.classList.add('is-loading');

    try {
      const response = await fetch(data.toggleUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': data.nonce,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ property_id: propertyId }),
      });

      if (!response.ok) {
        throw new Error('お気に入りの更新に失敗しました。時間をおいて再度お試しください。');
      }

      const result = await response.json();
      const favorited = !!result.favorited;
      const ids = Array.isArray(result.favorites) ? result.favorites.map((id) => parseInt(id, 10)).filter(Boolean) : [];

      favoriteSet.clear();
      ids.forEach((id) => favoriteSet.add(id));
      setButtonState(button, favorited);
    } catch (err) {
      // eslint-disable-next-line no-alert
      alert(err.message || 'お気に入りの更新に失敗しました。');
    } finally {
      button.classList.remove('is-loading');
      button.disabled = false;
    }
  };

  buttons.forEach((button) => {
    const propertyId = parseInt(button.dataset.propertyId || '0', 10);
    if (!propertyId) return;

    setButtonState(button, favoriteSet.has(propertyId));

    if (button.disabled || button.classList.contains('is-disabled') || !data.isLoggedIn) {
      return;
    }

    button.addEventListener('click', (event) => {
      event.preventDefault();
      toggleFavorite(button, propertyId);
    });
  });
})();
