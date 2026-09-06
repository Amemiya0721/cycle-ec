document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-confirm]');
    if (trigger && !window.confirm(trigger.dataset.confirm)) {
        event.preventDefault();
    }
});