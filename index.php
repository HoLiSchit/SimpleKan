<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require_login();
ensure_schema();

$token = csrf_token();
$username = h($_SESSION['username'] ?? '');
$jsVersion = @filemtime(__DIR__ . '/assets/js/board.js') ?: time();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SimpleKan</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = { darkMode: 'class' };
</script>
<script>
    if (localStorage.getItem('theme') === 'dark' ||
        (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }
</script>
<style>
    .dragging { opacity: 0.4; }
    .drag-over { outline: 2px dashed #64748b; outline-offset: -4px; }
    ::-webkit-scrollbar { height: 8px; width: 8px; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .dark ::-webkit-scrollbar-thumb { background: #475569; }
</style>
</head>
<body class="bg-slate-100 dark:bg-slate-900 min-h-screen flex flex-col transition-colors">

<header class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-6 py-3 flex flex-wrap items-center justify-between gap-3 shrink-0">
    <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">SimpleKan</h1>
    <div class="flex items-center gap-2 flex-1 min-w-[180px] max-w-md">
        <input id="search-input" type="search" placeholder="Karten durchsuchen…"
            class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-slate-400">
        <select id="tag-filter"
            class="text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-slate-400 shrink-0">
            <option value="">Alle Orte</option>
        </select>
    </div>
    <div class="flex items-center gap-3">
        <button id="archive-btn" type="button"
            class="text-sm bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg transition">
            Archiv
        </button>
        <button id="theme-toggle" type="button"
            class="text-sm bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg transition">
            <span id="theme-icon">Modus</span>
        </button>
        <span class="text-sm text-slate-500 dark:text-slate-400">Angemeldet als <strong class="text-slate-700 dark:text-slate-200"><?= $username ?></strong></span>
        <a href="logout.php" class="text-sm bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg transition">Abmelden</a>
    </div>
</header>

<main class="flex-1 overflow-x-auto p-6">
    <div id="board" class="flex gap-4 items-start h-full min-w-max"></div>
</main>

<!-- Card Modal -->
<div id="card-modal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-md p-6">
        <h3 id="modal-title" class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4">Neue Karte</h3>
        <form id="card-form" class="space-y-4">
            <input type="hidden" id="card-id" value="">
            <input type="hidden" id="card-column" value="">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Titel</label>
                <input id="card-title" type="text" required maxlength="200"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Beschreibung</label>
                <textarea id="card-description" rows="3" maxlength="2000"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Ort / Projekt</label>
                <input id="card-tag" type="text" maxlength="40" list="tag-suggestions" placeholder="z. B. Swipe-Stack, Homepage…"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
                <datalist id="tag-suggestions"></datalist>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fälligkeitsdatum</label>
                    <input id="card-due-date" type="date"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Priorität</label>
                    <select id="card-priority"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
                        <option value="">Keine</option>
                        <option value="niedrig">Niedrig</option>
                        <option value="mittel">Mittel</option>
                        <option value="hoch">Hoch</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-between items-center pt-2">
                <div class="flex gap-3">
                    <button type="button" id="archive-card-btn" class="hidden text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">Archivieren</button>
                    <button type="button" id="delete-card-btn" class="hidden text-sm text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300">Löschen</button>
                </div>
                <div class="flex gap-2 ml-auto">
                    <button type="button" id="cancel-modal-btn" class="text-sm px-3 py-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Abbrechen</button>
                    <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-slate-800 dark:bg-slate-600 text-white hover:bg-slate-700 dark:hover:bg-slate-500">Speichern</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Column Modal -->
<div id="column-modal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 id="column-modal-title" class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4">Neue Spalte</h3>
        <form id="column-form" class="space-y-4">
            <input type="hidden" id="column-key" value="">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name</label>
                <input id="column-label" type="text" required maxlength="60"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Farbe</label>
                    <select id="column-color"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">WIP-Limit</label>
                    <input id="column-wip-limit" type="number" min="0" step="1" placeholder="0 = unbegrenzt"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
                </div>
            </div>
            <div id="column-move-wrapper" class="hidden">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                    Diese Spalte enthält Karten. Wohin verschieben?
                </label>
                <select id="column-move-to"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
                </select>
            </div>
            <div class="flex justify-between items-center pt-2">
                <button type="button" id="delete-column-btn" class="hidden text-sm text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300">Spalte löschen</button>
                <div class="flex gap-2 ml-auto">
                    <button type="button" id="cancel-column-modal-btn" class="text-sm px-3 py-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Abbrechen</button>
                    <button type="submit" class="text-sm px-4 py-2 rounded-lg bg-slate-800 dark:bg-slate-600 text-white hover:bg-slate-700 dark:hover:bg-slate-500">Speichern</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Archive Modal -->
<div id="archive-modal" class="fixed inset-0 bg-black/40 hidden items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-lg p-6 max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Archiv</h3>
            <button type="button" id="close-archive-btn" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100">Schließen</button>
        </div>
        <div id="archive-list" class="space-y-2 overflow-y-auto flex-1">
            <p class="text-sm text-slate-400">Lädt…</p>
        </div>
    </div>
</div>

<script>
    window.CSRF_TOKEN = <?= json_encode($token) ?>;
</script>
<script src="assets/js/board.js?v=<?= (int)$jsVersion ?>"></script>
</body>
</html>
