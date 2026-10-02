(() => {
    const list = document.querySelector('#category-sort-list');
    const status = document.querySelector('#category-sort-status');
    const retryButton = document.querySelector('#category-sort-retry');
    if (!list || !status || !retryButton) {
        return;
    }

    let draggedRow = null;
    let dragStartOrder = '';
    let saving = false;
    let dirty = false;

    const rows = () => Array.from(list.querySelectorAll(':scope > tr[data-category-id]'));
    const currentOrder = () => rows().map((row) => row.dataset.categoryId).join(',');

    function updateControls() {
        const categoryRows = rows();
        categoryRows.forEach((row, index) => {
            const position = row.querySelector('[data-sort-position]');
            const moveUp = row.querySelector('[data-move-up]');
            const moveDown = row.querySelector('[data-move-down]');
            const dragHandle = row.querySelector('[data-drag-handle]');

            if (position) {
                position.textContent = String(index + 1);
            }
            if (moveUp) {
                moveUp.disabled = saving || index === 0;
            }
            if (moveDown) {
                moveDown.disabled = saving || index === categoryRows.length - 1;
            }
            if (dragHandle) {
                dragHandle.disabled = saving;
            }
        });
    }

    async function saveOrder() {
        if (saving || !dirty) {
            return;
        }

        saving = true;
        retryButton.hidden = true;
        status.className = 'mb-0 text-muted';
        status.textContent = '表示順を保存しています…';
        updateControls();

        try {
            const response = await fetch(list.dataset.reorderUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': list.dataset.csrfToken,
                },
                body: JSON.stringify({
                    category_ids: rows().map((row) => Number(row.dataset.categoryId)),
                }),
            });
            const result = await response.json();
            if (!response.ok || result.success !== true) {
                throw new Error(typeof result.message === 'string' ? result.message : '保存に失敗しました。');
            }

            dirty = false;
            status.className = 'mb-0 text-success';
            status.textContent = result.message;
        } catch (error) {
            status.className = 'mb-0 text-danger';
            status.textContent = `${error.message || '通信に失敗しました。'} 表示順は未保存です。`;
            retryButton.hidden = false;
        } finally {
            saving = false;
            updateControls();
        }
    }

    function markChanged() {
        dirty = true;
        updateControls();
        saveOrder();
    }

    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-move-up], [data-move-down]');
        if (!button || saving) {
            return;
        }

        const row = button.closest('tr[data-category-id]');
        if (!row) {
            return;
        }

        if (button.hasAttribute('data-move-up') && row.previousElementSibling?.matches('tr[data-category-id]')) {
            list.insertBefore(row, row.previousElementSibling);
            markChanged();
        } else if (button.hasAttribute('data-move-down') && row.nextElementSibling?.matches('tr[data-category-id]')) {
            list.insertBefore(row.nextElementSibling, row);
            markChanged();
        }
    });

    list.addEventListener('dragstart', (event) => {
        const handle = event.target.closest('[data-drag-handle]');
        if (!handle || saving) {
            event.preventDefault();
            return;
        }

        draggedRow = handle.closest('tr[data-category-id]');
        if (!draggedRow) {
            event.preventDefault();
            return;
        }

        dragStartOrder = currentOrder();
        draggedRow.classList.add('table-active');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggedRow.dataset.categoryId);
    });

    list.addEventListener('dragover', (event) => {
        if (!draggedRow || saving) {
            return;
        }

        const targetRow = event.target.closest('tr[data-category-id]');
        if (!targetRow || targetRow === draggedRow) {
            return;
        }

        event.preventDefault();
        const bounds = targetRow.getBoundingClientRect();
        const insertAfter = event.clientY >= bounds.top + bounds.height / 2;
        list.insertBefore(draggedRow, insertAfter ? targetRow.nextSibling : targetRow);
    });

    list.addEventListener('drop', (event) => {
        if (draggedRow) {
            event.preventDefault();
        }
    });

    list.addEventListener('dragend', () => {
        if (!draggedRow) {
            return;
        }

        draggedRow.classList.remove('table-active');
        draggedRow = null;
        if (currentOrder() !== dragStartOrder) {
            markChanged();
        }
    });

    retryButton.addEventListener('click', saveOrder);
    updateControls();
})();