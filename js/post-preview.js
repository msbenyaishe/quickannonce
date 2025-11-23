// Live preview for post forms
(function () {
  function formatPrice(value) {
    const number = Number(value);
    if (Number.isNaN(number)) return value;
    return new Intl.NumberFormat(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(number);
  }

  function resetPreviewImage(container) {
    if (!container) return;
    container.textContent = 'Image preview';
  }

  function updatePreviewImage(container, file) {
    if (!container) return;
    if (!file) {
      resetPreviewImage(container);
      return;
    }

    const reader = new FileReader();
    reader.onload = function (event) {
      container.innerHTML = '';
      const img = document.createElement('img');
      img.src = event.target?.result || '';
      img.alt = 'Ad preview image';
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'cover';
      img.style.borderRadius = '10px';
      container.appendChild(img);
    };
    reader.readAsDataURL(file);
  }

  function initPreview() {
    const form = document.getElementById('post-form');
    if (!form) return;

    const previewRoot = form.closest('.post-grid') || document;
    const previewCard = previewRoot.querySelector('.preview-card');
    if (!previewCard) return;

    const previewImage = previewCard.querySelector('.preview-image');
    const previewTitle = previewCard.querySelector('.card-title');
    const previewMeta = previewCard.querySelector('.card-meta');
    const cardBody = previewCard.querySelector('.card-body');

    if (!previewTitle || !previewMeta || !cardBody) return;

    let previewDescription = cardBody.querySelector('.preview-description');
    if (!previewDescription) {
      previewDescription = document.createElement('p');
      previewDescription.className = 'preview-description';
      previewDescription.style.marginTop = '8px';
      previewDescription.style.color = 'var(--muted)';
      previewDescription.textContent = 'Description preview will appear here.';
      cardBody.appendChild(previewDescription);
    }

    const titleInput = form.querySelector('#title');
    const categorySelect = form.querySelector('#category');
    const priceInput = form.querySelector('#price');
    const citySelect = form.querySelector('#city');
    const descriptionInput = form.querySelector('#description');
    const photoInput = form.querySelector('#photo') || form.querySelector('#photos');

    function buildMetaText() {
      const parts = [];
      if (citySelect && citySelect.value) parts.push(citySelect.value);
      if (priceInput && priceInput.value) parts.push(`€${formatPrice(priceInput.value)}`);
      if (categorySelect && categorySelect.value) parts.push(categorySelect.value);
      return parts.length ? parts.join(' • ') : 'City • Price';
    }

    function updateTextPreview() {
      if (previewTitle && titleInput) {
        const value = titleInput.value.trim();
        previewTitle.textContent = value || 'Your title will appear here';
      }

      previewMeta.textContent = buildMetaText();

      if (previewDescription && descriptionInput) {
        const desc = descriptionInput.value.trim();
        previewDescription.textContent = desc || 'Description preview will appear here.';
      }
    }

    updateTextPreview();

    const inputsToWatch = [titleInput, categorySelect, priceInput, citySelect, descriptionInput];
    inputsToWatch.forEach((input) => {
      if (!input) return;
      const eventName = input.tagName === 'SELECT' ? 'change' : 'input';
      input.addEventListener(eventName, updateTextPreview);
    });

    if (photoInput) {
      photoInput.addEventListener('change', () => {
        const files = photoInput.files;
        const file = files && files.length ? files[0] : null;
        updatePreviewImage(previewImage, file);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPreview);
  } else {
    initPreview();
  }
})();

