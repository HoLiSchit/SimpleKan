<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();
ensure_schema();

function json_body(): array { $raw = file_get_contents('php://input'); $data = json_decode($raw, true); return is_array($data) ? $data : []; }
function respond_json($data, int $code = 200): void { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$pdo = db();

if ($method === 'GET' && $action === 'export') {
    $columns = $pdo->query('SELECT col_key, label, color, position, wip_limit FROM board_columns ORDER BY position ASC')->fetchAll(PDO::FETCH_ASSOC);
    $cards = $pdo->query('SELECT column_key, title, description, due_date, priority, tag, archived, position FROM cards ORDER BY column_key, position ASC')->fetchAll(PDO::FETCH_ASSOC);
    $payload = [
        'app' => 'SimpleKan', 'format_version' => 1, 'exported_at' => date('c'),
        'columns' => array_map(fn($c) => ['key' => $c['col_key'], 'label' => $c['label'], 'color' => $c['color'], 'position' => (int)$c['position'], 'wip_limit' => (int)$c['wip_limit']], $columns),
        'cards' => array_map(fn($c) => ['column' => $c['column_key'], 'title' => $c['title'], 'description' => $c['description'], 'due_date' => $c['due_date'], 'priority' => $c['priority'], 'tag' => $c['tag'], 'archived' => (int)$c['archived'], 'position' => (int)$c['position']], $cards),
    ];
    $filename = 'simplekan-backup-' . date('Y-m-d_His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && $action === 'import') {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_check($csrfHeader)) respond_json(['error' => 'Ungültiges CSRF-Token.'], 403);

    $body = json_body();
    $columns = $body['columns'] ?? null;
    $cards = $body['cards'] ?? null;
    if (!is_array($columns) || !is_array($cards) || count($columns) === 0) {
        respond_json(['error' => 'Ungültige Backup-Datei: Es muss mindestens eine Spalte enthalten sein.'], 422);
    }

    $cleanColumns = []; $seenKeys = [];
    foreach ($columns as $c) {
        if (!is_array($c)) continue;
        $key = slugify((string)($c['key'] ?? $c['label'] ?? ''));
        if ($key === '' || isset($seenKeys[$key])) continue;
        $seenKeys[$key] = true;
        $label = trim((string)($c['label'] ?? $key));
        if ($label === '') $label = $key;
        $label = mb_substr($label, 0, 60);
        $color = (string)($c['color'] ?? 'slate');
        if (!in_array($color, COLUMN_COLORS, true)) $color = 'slate';
        $cleanColumns[] = ['key' => $key, 'label' => $label, 'color' => $color, 'position' => (int)($c['position'] ?? count($cleanColumns)), 'wip_limit' => max(0, (int)($c['wip_limit'] ?? 0))];
    }
    if (count($cleanColumns) === 0) respond_json(['error' => 'Keine gültigen Spalten im Backup gefunden.'], 422);
    $validKeys = array_column($cleanColumns, 'key');
    $fallbackKey = $validKeys[0];

    $cleanCards = [];
    foreach ($cards as $c) {
        if (!is_array($c)) continue;
        $title = trim((string)($c['title'] ?? ''));
        if ($title === '') continue;
        $title = mb_substr($title, 0, 200);
        $column = (string)($c['column'] ?? $fallbackKey);
        if (!in_array($column, $validKeys, true)) $column = $fallbackKey;
        $description = mb_substr((string)($c['description'] ?? ''), 0, 2000);
        $dueDate = $c['due_date'] ?? null;
        if ($dueDate !== null) {
            $d = DateTime::createFromFormat('Y-m-d', (string)$dueDate);
            if (!$d || $d->format('Y-m-d') !== $dueDate) $dueDate = null;
        }
        $priority = $c['priority'] ?? null;
        if (!in_array($priority, PRIORITIES, true)) $priority = null;
        $tag = trim((string)($c['tag'] ?? ''));
        $tag = $tag !== '' ? mb_substr($tag, 0, 40) : null;
        $cleanCards[] = ['column' => $column, 'title' => $title, 'description' => $description, 'due_date' => $dueDate, 'priority' => $priority, 'tag' => $tag, 'archived' => !empty($c['archived']) ? 1 : 0, 'position' => (int)($c['position'] ?? 0)];
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM cards');
        $pdo->exec('DELETE FROM board_columns');
        $colStmt = $pdo->prepare('INSERT INTO board_columns (col_key, label, color, position, wip_limit) VALUES (:k, :l, :c, :p, :w)');
        foreach ($cleanColumns as $c) $colStmt->execute([':k' => $c['key'], ':l' => $c['label'], ':c' => $c['color'], ':p' => $c['position'], ':w' => $c['wip_limit']]);
        $cardStmt = $pdo->prepare("INSERT INTO cards (column_key, title, description, due_date, priority, tag, archived, position, updated_at) VALUES (:col, :t, :d, :due, :prio, :tag, :arch, :pos, datetime('now'))");
        foreach ($cleanCards as $c) $cardStmt->execute([':col' => $c['column'], ':t' => $c['title'], ':d' => $c['description'], ':due' => $c['due_date'], ':prio' => $c['priority'], ':tag' => $c['tag'], ':arch' => $c['archived'], ':pos' => $c['position']]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); respond_json(['error' => 'Wiederherstellung fehlgeschlagen: ' . $e->getMessage()], 500); }

    respond_json(['ok' => true, 'columns' => count($cleanColumns), 'cards' => count($cleanCards)]);
}

respond_json(['error' => 'Unbekannte Aktion.'], 404);
