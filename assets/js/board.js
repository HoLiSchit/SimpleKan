(function () {
    'use strict';

    const CSRF = window.CSRF_TOKEN;
    const CARDS_API = 'api/cards.php';
    const COLUMNS_API = 'api/columns.php';

    const boardEl = document.getElementById('board');
    const searchInput = document.getElementById('search-input');
    const tagFilterSelect = document.getElementById('tag-filter');
    const tagSuggestions = document.getElementById('tag-suggestions');

    const cardModal = document.getElementById('card-modal');
    const cardForm = document.getElementById('card-form');
    const cardModalTitle = document.getElementById('modal-title');
    const inputId = document.getElementById('card-id');
    const inputColumn = document.getElementById('card-column');
    const inputTitle = document.getElementById('card-title');
    const inputDescription = document.getElementById('card-description');
    const inputTag = document.getElementById('card-tag');
    const inputDueDate = document.getElementById('card-due-date');
    const inputPriority = document.getElementById('card-priority');
    const deleteCardBtn = document.getElementById('delete-card-btn');
    const archiveCardBtn = document.getElementById('archive-card-btn');
    const cancelCardBtn = document.getElementById('cancel-modal-btn');

    const columnModal = document.getElementById('column-modal');
    const columnForm = document.getElementById('column-form');
    const columnModalTitle = document.getElementById('column-modal-title');
    const columnKeyInput = document.getElementById('column-key');
    const columnLabelInput = document.getElementById('column-label');
    const columnColorSelect = document.getElementById('column-color');
    const columnWipInput = document.getElementById('column-wip-limit');
    const columnMoveWrapper = document.getElementById('column-move-wrapper');
    const columnMoveToSelect = document.getElementById('column-move-to');
    const deleteColumnBtn = document.getElementById('delete-column-btn');
    const cancelColumnBtn = document.getElementById('cancel-column-modal-btn');

    const archiveBtn = document.getElementById('archive-btn');
    const archiveModal = document.getElementById('archive-modal');
    const archiveList = document.getElementById('archive-list');
    const closeArchiveBtn = document.getElementById('close-archive-btn');

    const themeToggle = document.getElementById('theme-toggle');
    const themeIcon = document.getElementById('theme-icon');

    const COLORS = ['sky', 'rose', 'slate', 'amber', 'emerald', 'neutral', 'violet', 'cyan', 'lime', 'fuchsia', 'orange', 'teal', 'indigo', 'pink'];
    const COLOR_LABELS = { sky: 'Blau', rose: 'Rot', slate: 'Grau', amber: 'Gelb', emerald: 'Grün', neutral: 'Neutral', violet: 'Violett', cyan: 'Türkis', lime: 'Limette', fuchsia: 'Pink', orange: 'Orange', teal: 'Petrol', indigo: 'Indigo', pink: 'Rosa' };
    const DOT_CLASS = { sky: 'bg-sky-500', rose: 'bg-rose-500', slate: 'bg-slate-500', amber: 'bg-amber-500', emerald: 'bg-emerald-500', neutral: 'bg-neutral-400', violet: 'bg-violet-500', cyan: 'bg-cyan-500', lime: 'bg-lime-500', fuchsia: 'bg-fuchsia-500', orange: 'bg-orange-500', teal: 'bg-teal-500', indigo: 'bg-indigo-500', pink: 'bg-pink-500' };
    const BORDER_CLASS = { sky: 'border-l-sky-500', rose: 'border-l-rose-500', slate: 'border-l-slate-500', amber: 'border-l-amber-500', emerald: 'border-l-emerald-500', neutral: 'border-l-neutral-400', violet: 'border-l-violet-500', cyan: 'border-l-cyan-500', lime: 'border-l-lime-500', fuchsia: 'border-l-fuchsia-500', orange: 'border-l-orange-500', teal: 'border-l-teal-500', indigo: 'border-l-indigo-500', pink: 'border-l-pink-500' };
    const PRIORITY_LABELS = { niedrig: 'Niedrig', mittel: 'Mittel', hoch: 'Hoch' };
    const PRIORITY_CLASS = { niedrig: 'bg-slate-100 text-slate-600 dark:bg-slate-600 dark:text-slate-200', mittel: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300', hoch: 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300' };
    const TAG_CHIP_CLASSES = [
        'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300',
        'bg-violet-100 text-violet-700 dark:bg-violet-900/50 dark:text-violet-300',
        'bg-teal-100 text-teal-700 dark:bg-teal-900/50 dark:text-teal-300',
        'bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-300',
        'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/50 dark:text-fuchsia-300',
        'bg-lime-100 text-lime-700 dark:bg-lime-900/50 dark:text-lime-300',
        'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300',
        'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300',
    ];

    let columnsState = [];
    let allTags = [];
    // 'card' | 'column' | null — verhindert, dass sich Karten- und Spalten-Drag gegenseitig stören
    let draggedType = null;
    // Snapshots zur Erkennung ungespeicherter Änderungen (Klick-außerhalb-Schutz)
    let cardModalSnapshot = null;
    let columnModalSnapshot = null;

    // --- Dark Mode Toggle ---
    function updateThemeLabel() {
        if (!themeIcon) return;
        themeIcon.textContent = document.documentElement.classList.contains('dark') ? 'Hell' : 'Dunkel';
    }
    if (themeToggle) {
        updateThemeLabel();
        themeToggle.addEventListener('click', function (e) {
            e.preventDefault();
            const isDark = document.documentElement.classList.toggle('dark');
            try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch (err) {}
            updateThemeLabel();
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : str;
        return div.innerHTML;
    }

    function hashString(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = (hash << 5) - hash + str.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash);
    }

    function tagChipClass(tag) {
        return TAG_CHIP_CLASSES[hashString(tag.toLowerCase()) % TAG_CHIP_CLASSES.length];
    }

    function setCardBorderColor(cardEl, columnKey) {
        const col = columnsState.find((c) => c.key === columnKey);
        const color = col ? col.color : 'slate';
        Object.values(BORDER_CLASS).forEach((cls) => cardEl.classList.remove(cls));
        cardEl.classList.add(BORDER_CLASS[color] || BORDER_CLASS.slate);
    }

    async function apiCall(base, action, method, body, extraQuery) {
        method = method || 'GET';
        const opts = { method: method, headers: { 'Content-Type': 'application/json' } };
        if (method !== 'GET') opts.headers['X-CSRF-Token'] = CSRF;
        if (body) opts.body = JSON.stringify(body);
        const qs = extraQuery ? `&${extraQuery}` : '';
        const res = await fetch(`${base}?action=${action}${qs}`, opts);
        if (res.status === 401) { window.location.href = 'login.php'; return null; }
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const err = new Error(data.error || 'Fehler bei der Anfrage.');
            err.payload = data;
            err.status = res.status;
            throw err;
        }
        return data;
    }

    function populateColorSelect(select, selected) {
        select.innerHTML = COLORS.map((c) => `<option value="${c}" ${c === selected ? 'selected' : ''}>${COLOR_LABELS[c] || c}</option>`).join('');
    }

    async function refreshTags() {
        try {
            const data = await apiCall(CARDS_API, 'tags');
            allTags = (data && data.tags) || [];
            tagSuggestions.innerHTML = allTags.map((t) => `<option value="${escapeHtml(t)}">`).join('');
            const currentFilter = tagFilterSelect.value;
            tagFilterSelect.innerHTML = '<option value="">Alle Orte</option>' +
                allTags.map((t) => `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`).join('');
            if (allTags.includes(currentFilter)) tagFilterSelect.value = currentFilter;
        } catch (err) { /* still fine without tags */ }
    }
    if (tagFilterSelect) tagFilterSelect.addEventListener('change', applySearchFilter);

    function formatDate(iso) {
        const [y, m, d] = iso.split('-');
        return `${d}.${m}.${y}`;
    }

    function dueDateBadge(dueDate) {
        if (!dueDate) return '';
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const due = new Date(dueDate + 'T00:00:00');
        const diffDays = Math.round((due - today) / 86400000);
        let cls = 'bg-slate-100 text-slate-600 dark:bg-slate-600 dark:text-slate-200';
        if (diffDays < 0) cls = 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300';
        else if (diffDays <= 2) cls = 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300';
        return `<span class="inline-block text-xs px-1.5 py-0.5 rounded ${cls}">${formatDate(dueDate)}</span>`;
    }

    function priorityBadge(priority) {
        if (!priority || !PRIORITY_LABELS[priority]) return '';
        return `<span class="inline-block text-xs px-1.5 py-0.5 rounded ${PRIORITY_CLASS[priority]}">${PRIORITY_LABELS[priority]}</span>`;
    }

    function tagBadge(tag) {
        if (!tag) return '';
        return `<span class="inline-block text-xs px-1.5 py-0.5 rounded font-medium ${tagChipClass(tag)}">${escapeHtml(tag)}</span>`;
    }

    // --- Card rendering ---
    function createCardEl(card, columnKey, color) {
        const el = document.createElement('div');
        el.className = `card bg-white dark:bg-slate-700 rounded-lg shadow-sm border-l-4 ${BORDER_CLASS[color] || BORDER_CLASS.slate} p-3 cursor-grab hover:shadow-md dark:hover:shadow-slate-900/50 transition text-sm`;
        el.draggable = true;
        el.dataset.id = card.id;
        el.dataset.title = card.title.toLowerCase();
        el.dataset.description = (card.description || '').toLowerCase();
        el.dataset.tag = (card.tag || '').toLowerCase();
        const badges = tagBadge(card.tag) + dueDateBadge(card.due_date) + priorityBadge(card.priority);
        el.innerHTML = `
            <div class="font-medium text-slate-800 dark:text-slate-100 break-words">${escapeHtml(card.title)}</div>
            ${card.description ? `<div class="text-slate-500 dark:text-slate-400 text-xs mt-1 break-words line-clamp-3">${escapeHtml(card.description)}</div>` : ''}
            ${badges ? `<div class="flex gap-1 flex-wrap mt-2">${badges}</div>` : ''}
        `;
        el.addEventListener('click', () => openEditCardModal(card, columnKey));
        el.addEventListener('dragstart', (e) => {
            draggedType = 'card';
            el.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        el.addEventListener('dragend', () => { el.classList.remove('dragging'); draggedType = null; });
        return el;
    }

    function attachCardListDnD(list) {
        list.addEventListener('dragover', (e) => {
            if (draggedType !== 'card') return;
            e.preventDefault();
            const dragging = document.querySelector('.card.dragging');
            if (!dragging) return;
            list.classList.add('drag-over');
            const afterEl = getDragAfterElement(list, e.clientY, '.card:not(.dragging)');
            if (afterEl == null) list.appendChild(dragging);
            else list.insertBefore(dragging, afterEl);
        });
        list.addEventListener('dragleave', () => list.classList.remove('drag-over'));
        list.addEventListener('drop', async (e) => {
            if (draggedType !== 'card') return;
            e.preventDefault();
            list.classList.remove('drag-over');
            // Bugfix: Randfarbe der verschobenen Karte an die neue Spalte anpassen,
            // sonst behält die Karte optisch die Farbe der Ursprungsspalte.
            const dragging = document.querySelector('.card.dragging');
            if (dragging) setCardBorderColor(dragging, list.dataset.column);
            updateCounts();
            await persistCardOrder();
        });
    }

    function getDragAfterElement(container, y, selector) {
        const els = [...container.querySelectorAll(selector)];
        return els.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) return { offset, element: child };
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    }

    function updateCounts() {
        document.querySelectorAll('section[data-column]').forEach((section) => {
            const key = section.dataset.column;
            const col = columnsState.find((c) => c.key === key);
            const visibleCount = section.querySelectorAll('.card-list .card').length;
            const badge = section.querySelector('.card-count');
            if (!badge) return;
            const limit = col ? col.wipLimit : 0;
            badge.textContent = limit > 0 ? `${visibleCount}/${limit}` : String(visibleCount);
            badge.classList.toggle('bg-rose-100', limit > 0 && visibleCount > limit);
            badge.classList.toggle('text-rose-700', limit > 0 && visibleCount > limit);
            badge.classList.toggle('dark:bg-rose-900/50', limit > 0 && visibleCount > limit);
            badge.classList.toggle('dark:text-rose-300', limit > 0 && visibleCount > limit);
        });
    }

    // --- Column rendering ---
    function renderColumns(columns) {
        columnsState = columns;
        boardEl.innerHTML = '';

        columns.forEach((col) => {
            const section = document.createElement('section');
            section.className = 'bg-slate-200/60 dark:bg-slate-800/60 rounded-xl w-72 shrink-0 flex flex-col max-h-full';
            section.dataset.column = col.key;

            section.innerHTML = `
                <div class="column-header flex items-center justify-between px-3 py-2.5 sticky top-0 cursor-grab" draggable="true">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0 ${DOT_CLASS[col.color] || DOT_CLASS.slate}"></span>
                        <h2 class="font-semibold text-slate-700 dark:text-slate-200 text-sm truncate">${escapeHtml(col.label)}</h2>
                        <span class="card-count text-xs text-slate-400 bg-white/70 dark:bg-slate-900/50 rounded-full px-1.5 py-0.5 shrink-0">0</span>
                    </div>
                    <button type="button" class="column-settings-btn text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 px-1 shrink-0" title="Spalte bearbeiten">&#8942;</button>
                </div>
                <div class="card-list flex-1 overflow-y-auto px-2 pb-2 space-y-2 min-h-[60px]" data-column="${col.key}"></div>
                <button class="add-card-btn m-2 text-left text-sm text-slate-500 dark:text-slate-400 hover:bg-slate-300/50 dark:hover:bg-slate-700/50 rounded-lg px-2 py-1.5 transition" data-column="${col.key}">
                    + Karte hinzufügen
                </button>
            `;

            section.querySelector('.add-card-btn').addEventListener('click', () => openCreateCardModal(col.key));
            section.querySelector('.column-settings-btn').addEventListener('click', () => openEditColumnModal(col));
            attachCardListDnD(section.querySelector('.card-list'));
            attachColumnDnD(section, section.querySelector('.column-header'));

            boardEl.appendChild(section);
        });

        const addColBtn = document.createElement('button');
        addColBtn.type = 'button';
        addColBtn.className = 'shrink-0 h-12 w-40 mt-1 border-2 border-dashed border-slate-300 dark:border-slate-600 text-slate-400 dark:text-slate-500 hover:border-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded-xl text-sm transition';
        addColBtn.textContent = '+ Spalte hinzufügen';
        addColBtn.addEventListener('click', openCreateColumnModal);
        boardEl.appendChild(addColBtn);
    }

    function attachColumnDnD(section, handle) {
        handle.addEventListener('dragstart', (e) => {
            draggedType = 'column';
            section.classList.add('opacity-50');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', section.dataset.column);
        });
        handle.addEventListener('dragend', () => { section.classList.remove('opacity-50'); draggedType = null; });

        section.addEventListener('dragover', (e) => {
            if (draggedType !== 'column') return;
            e.preventDefault();
            const dragging = boardEl.querySelector('section.opacity-50');
            if (!dragging || dragging === section) return;
            const rect = section.getBoundingClientRect();
            const before = e.clientX < rect.left + rect.width / 2;
            boardEl.insertBefore(dragging, before ? section : section.nextSibling);
        });
        section.addEventListener('drop', async (e) => {
            if (draggedType !== 'column') return;
            e.preventDefault();
            await persistColumnOrder();
        });
    }

    async function persistColumnOrder() {
        const order = [...boardEl.querySelectorAll('section[data-column]')].map((s) => s.dataset.column);
        try {
            await apiCall(COLUMNS_API, 'reorder', 'POST', { order });
        } catch (err) {
            alert('Reihenfolge der Spalten konnte nicht gespeichert werden: ' + err.message);
            await loadBoard();
        }
    }

    function renderCards(columnsData) {
        document.querySelectorAll('.card-list').forEach((list) => {
            const key = list.dataset.column;
            const col = columnsState.find((c) => c.key === key);
            list.innerHTML = '';
            (columnsData[key] || []).forEach((card) => {
                list.appendChild(createCardEl(card, key, col ? col.color : 'slate'));
            });
        });
        updateCounts();
        applySearchFilter();
    }

    function applySearchFilter() {
        const term = (searchInput.value || '').trim().toLowerCase();
        const tagFilter = (tagFilterSelect.value || '').toLowerCase();
        document.querySelectorAll('.card').forEach((card) => {
            const matchesText = !term || card.dataset.title.includes(term) || card.dataset.description.includes(term);
            const matchesTag = !tagFilter || card.dataset.tag === tagFilter;
            card.style.display = (matchesText && matchesTag) ? '' : 'none';
        });
        updateCounts();
    }
    if (searchInput) searchInput.addEventListener('input', applySearchFilter);

    async function loadBoard() {
        const [colsData, cardsData] = await Promise.all([
            apiCall(COLUMNS_API, 'list'),
            apiCall(CARDS_API, 'list'),
            refreshTags(),
        ]);
        if (colsData) renderColumns(colsData.columns);
        if (cardsData) renderCards(cardsData.columns);
    }

    // --- Card modal ---
    function snapshotCardForm() {
        return {
            title: inputTitle.value,
            description: inputDescription.value,
            tag: inputTag.value,
            dueDate: inputDueDate.value,
            priority: inputPriority.value,
        };
    }

    function cardFormIsDirty() {
        if (!cardModalSnapshot) return false;
        const current = snapshotCardForm();
        return Object.keys(current).some((k) => current[k] !== cardModalSnapshot[k]);
    }

    function openCreateCardModal(columnKey) {
        cardModalTitle.textContent = 'Neue Karte';
        inputId.value = '';
        inputColumn.value = columnKey;
        inputTitle.value = '';
        inputDescription.value = '';
        inputTag.value = '';
        inputDueDate.value = '';
        inputPriority.value = '';
        deleteCardBtn.classList.add('hidden');
        archiveCardBtn.classList.add('hidden');
        showModal(cardModal);
        cardModalSnapshot = snapshotCardForm();
        inputTitle.focus();
    }

    function openEditCardModal(card, columnKey) {
        cardModalTitle.textContent = 'Karte bearbeiten';
        inputId.value = card.id;
        inputColumn.value = columnKey;
        inputTitle.value = card.title;
        inputDescription.value = card.description || '';
        inputTag.value = card.tag || '';
        inputDueDate.value = card.due_date || '';
        inputPriority.value = card.priority || '';
        deleteCardBtn.classList.remove('hidden');
        archiveCardBtn.classList.remove('hidden');
        showModal(cardModal);
        cardModalSnapshot = snapshotCardForm();
        inputTitle.focus();
    }

    function closeCardModalSafely() {
        if (cardFormIsDirty() && !confirm('Ungespeicherte Änderungen verwerfen?')) return;
        hideModal(cardModal);
    }

    cancelCardBtn.addEventListener('click', () => hideModal(cardModal));
    // Bugfix: Klick außerhalb des Popups fragt jetzt nach, statt Eingaben kommentarlos zu verwerfen.
    cardModal.addEventListener('click', (e) => { if (e.target === cardModal) closeCardModalSafely(); });

    cardForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = inputId.value;
        const payload = {
            title: inputTitle.value.trim(),
            description: inputDescription.value.trim(),
            column: inputColumn.value,
            tag: inputTag.value.trim() || null,
            due_date: inputDueDate.value || null,
            priority: inputPriority.value || null,
        };
        try {
            if (id) await apiCall(CARDS_API, 'update', 'POST', { id: Number(id), ...payload });
            else await apiCall(CARDS_API, 'create', 'POST', payload);
            hideModal(cardModal);
            await loadBoard();
        } catch (err) {
            alert(err.message);
        }
    });

    deleteCardBtn.addEventListener('click', async () => {
        if (!inputId.value) return;
        if (!confirm('Diese Karte wirklich endgültig löschen?')) return;
        try {
            await apiCall(CARDS_API, 'delete', 'POST', { id: Number(inputId.value) });
            hideModal(cardModal);
            await loadBoard();
        } catch (err) { alert(err.message); }
    });

    archiveCardBtn.addEventListener('click', async () => {
        if (!inputId.value) return;
        try {
            await apiCall(CARDS_API, 'archive', 'POST', { id: Number(inputId.value) });
            hideModal(cardModal);
            await loadBoard();
        } catch (err) { alert(err.message); }
    });

    async function persistCardOrder() {
        const columns = {};
        document.querySelectorAll('.card-list').forEach((list) => {
            columns[list.dataset.column] = [...list.querySelectorAll('.card')].map((c) => Number(c.dataset.id));
        });
        try {
            await apiCall(CARDS_API, 'reorder', 'POST', { columns });
        } catch (err) {
            alert('Reihenfolge konnte nicht gespeichert werden: ' + err.message);
            await loadBoard();
        }
    }

    // --- Column modal ---
    function snapshotColumnForm() {
        return {
            label: columnLabelInput.value,
            color: columnColorSelect.value,
            wip: columnWipInput.value,
        };
    }

    function columnFormIsDirty() {
        if (!columnModalSnapshot) return false;
        const current = snapshotColumnForm();
        return Object.keys(current).some((k) => current[k] !== columnModalSnapshot[k]);
    }

    function openCreateColumnModal() {
        columnModalTitle.textContent = 'Neue Spalte';
        columnKeyInput.value = '';
        columnLabelInput.value = '';
        columnWipInput.value = '';
        populateColorSelect(columnColorSelect, 'slate');
        columnMoveWrapper.classList.add('hidden');
        deleteColumnBtn.classList.add('hidden');
        showModal(columnModal);
        columnModalSnapshot = snapshotColumnForm();
        columnLabelInput.focus();
    }

    function openEditColumnModal(col) {
        columnModalTitle.textContent = 'Spalte bearbeiten';
        columnKeyInput.value = col.key;
        columnLabelInput.value = col.label;
        columnWipInput.value = col.wipLimit > 0 ? col.wipLimit : '';
        populateColorSelect(columnColorSelect, col.color);
        columnMoveWrapper.classList.add('hidden');
        deleteColumnBtn.classList.toggle('hidden', columnsState.length <= 1);
        showModal(columnModal);
        columnModalSnapshot = snapshotColumnForm();
        columnLabelInput.focus();
    }

    function closeColumnModalSafely() {
        if (columnFormIsDirty() && !confirm('Ungespeicherte Änderungen verwerfen?')) return;
        hideModal(columnModal);
    }

    cancelColumnBtn.addEventListener('click', () => hideModal(columnModal));
    // Bugfix: Klick außerhalb des Popups fragt jetzt nach, statt Eingaben kommentarlos zu verwerfen.
    columnModal.addEventListener('click', (e) => { if (e.target === columnModal) closeColumnModalSafely(); });

    columnForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const key = columnKeyInput.value;
        const label = columnLabelInput.value.trim();
        const color = columnColorSelect.value;
        const wipLimit = columnWipInput.value ? Math.max(0, parseInt(columnWipInput.value, 10)) : 0;
        try {
            if (key) {
                await apiCall(COLUMNS_API, 'rename', 'POST', { key, label });
                await apiCall(COLUMNS_API, 'recolor', 'POST', { key, color });
                await apiCall(COLUMNS_API, 'set_limit', 'POST', { key, wip_limit: wipLimit });
            } else {
                await apiCall(COLUMNS_API, 'create', 'POST', { label, color, wip_limit: wipLimit });
            }
            hideModal(columnModal);
            await loadBoard();
        } catch (err) { alert(err.message); }
    });

    deleteColumnBtn.addEventListener('click', async () => {
        const key = columnKeyInput.value;
        if (!key) return;
        if (!confirm('Diese Spalte wirklich löschen?')) return;
        try {
            await apiCall(COLUMNS_API, 'delete', 'POST', { key });
            hideModal(columnModal);
            await loadBoard();
        } catch (err) {
            if (err.status === 409 && err.payload && err.payload.error === 'needs_move_to') {
                const others = columnsState.filter((c) => c.key !== key);
                if (others.length === 0) { alert('Es gibt keine andere Spalte, in die verschoben werden könnte.'); return; }
                columnMoveWrapper.classList.remove('hidden');
                columnMoveToSelect.innerHTML = others.map((c) => `<option value="${c.key}">${escapeHtml(c.label)}</option>`).join('');
                alert(`Diese Spalte enthält noch ${err.payload.cardCount} Karte(n). Wähle eine Zielspalte und klicke erneut auf "Spalte löschen".`);
                const onceHandler = async () => {
                    try {
                        await apiCall(COLUMNS_API, 'delete', 'POST', { key, moveTo: columnMoveToSelect.value });
                        hideModal(columnModal);
                        columnMoveWrapper.classList.add('hidden');
                        await loadBoard();
                    } catch (err2) { alert(err2.message); }
                };
                deleteColumnBtn.addEventListener('click', onceHandler, { once: true });
            } else {
                alert(err.message);
            }
        }
    });

    // --- Archive modal ---
    async function openArchiveModal() {
        showModal(archiveModal);
        archiveList.innerHTML = '<p class="text-sm text-slate-400">Lädt…</p>';
        try {
            const data = await apiCall(CARDS_API, 'list', 'GET', null, 'archived=1');
            const cards = (data && data.cards) || [];
            if (cards.length === 0) {
                archiveList.innerHTML = '<p class="text-sm text-slate-400">Das Archiv ist leer.</p>';
                return;
            }
            archiveList.innerHTML = cards.map((c) => `
                <div class="flex items-center justify-between gap-3 bg-slate-100 dark:bg-slate-700 rounded-lg px-3 py-2" data-archive-id="${c.id}">
                    <span class="text-sm text-slate-700 dark:text-slate-200 truncate">${escapeHtml(c.title)}${c.tag ? ` <span class="text-xs text-slate-400">(${escapeHtml(c.tag)})</span>` : ''}</span>
                    <div class="flex gap-2 shrink-0">
                        <button type="button" class="restore-btn text-xs text-emerald-600 dark:text-emerald-400 hover:underline">Wiederherstellen</button>
                        <button type="button" class="perma-delete-btn text-xs text-rose-600 dark:text-rose-400 hover:underline">Löschen</button>
                    </div>
                </div>
            `).join('');
            archiveList.querySelectorAll('.restore-btn').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    const id = Number(e.target.closest('[data-archive-id]').dataset.archiveId);
                    await apiCall(CARDS_API, 'restore', 'POST', { id });
                    await openArchiveModal();
                    await loadBoard();
                });
            });
            archiveList.querySelectorAll('.perma-delete-btn').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    if (!confirm('Diese Karte endgültig löschen?')) return;
                    const id = Number(e.target.closest('[data-archive-id]').dataset.archiveId);
                    await apiCall(CARDS_API, 'delete', 'POST', { id });
                    await openArchiveModal();
                });
            });
        } catch (err) {
            archiveList.innerHTML = `<p class="text-sm text-rose-500">${escapeHtml(err.message)}</p>`;
        }
    }
    if (archiveBtn) archiveBtn.addEventListener('click', openArchiveModal);
    if (closeArchiveBtn) closeArchiveBtn.addEventListener('click', () => hideModal(archiveModal));
    archiveModal.addEventListener('click', (e) => { if (e.target === archiveModal) hideModal(archiveModal); });

    function showModal(modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    function hideModal(modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }

    loadBoard();
})();
