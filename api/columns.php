<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();
ensure_schema();
header('Content-Type: application/json; charset=utf-8');

function json_body(): array { $raw = file_get_contents('php://input'); $data = json_decode($raw, true); return is_array($data) ? $data : []; }
function respond($data, int $code = 200): void { http_response_code($code); echo json_encode($data); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
if ($method !== 'GET') {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_check($csrfHeader)) respond(['error' => 'Ungültiges CSRF-Token.'], 403);
}
$pdo = db();

function all_columns(PDO $pdo): array {
    $stmt = $pdo->query('SELECT bc.col_key, bc.label, bc.color, bc.position, bc.wip_limit,
        COUNT(CASE WHEN c.archived = 0 THEN 1 END) AS card_count
        FROM board_columns bc LEFT JOIN cards c ON c.column_key = bc.col_key
        GROUP BY bc.col_key ORDER BY bc.position ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($r) => ['key' => $r['col_key'], 'label' => $r['label'], 'color' => $r['color'],
        'position' => (int)$r['position'], 'wipLimit' => (int)$r['wip_limit'], 'cardCount' => (int)$r['card_count']], $rows);
}

switch ("$method:$action") {
    case 'GET:list':
        respond(['columns' => all_columns($pdo)]);
        break;

    case 'POST:create':
        $body = json_body();
        $label = trim((string)($body['label'] ?? ''));
        $color = (string)($body['color'] ?? 'slate');
        $wipLimit = max(0, (int)($body['wip_limit'] ?? 0));
        if ($label === '' || mb_strlen($label) > 60) respond(['error' => 'Name ist erforderlich (max. 60 Zeichen).'], 422);
        if (!in_array($color, COLUMN_COLORS, true)) $color = 'slate';
        $baseKey = slugify($label); $key = $baseKey;
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM board_columns WHERE col_key = :k');
        $i = 2;
        while (true) {
            $checkStmt->execute([':k' => $key]);
            if ((int)$checkStmt->fetchColumn() === 0) break;
            $key = $baseKey . '_' . $i; $i++;
        }
        $maxPos = (int)$pdo->query('SELECT COALESCE(MAX(position), -1) FROM board_columns')->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO board_columns (col_key, label, color, position, wip_limit) VALUES (:k, :l, :c, :p, :w)');
        $stmt->execute([':k' => $key, ':l' => $label, ':c' => $color, ':p' => $maxPos + 1, ':w' => $wipLimit]);
        respond(['key' => $key], 201);
        break;

    case 'POST:rename':
        $body = json_body(); $key = (string)($body['key'] ?? ''); $label = trim((string)($body['label'] ?? ''));
        if ($key === '' || $label === '' || mb_strlen($label) > 60) respond(['error' => 'Ungültige Eingabe.'], 422);
        $pdo->prepare('UPDATE board_columns SET label = :l WHERE col_key = :k')->execute([':l' => $label, ':k' => $key]);
        respond(['ok' => true]);
        break;

    case 'POST:recolor':
        $body = json_body(); $key = (string)($body['key'] ?? ''); $color = (string)($body['color'] ?? '');
        if ($key === '' || !in_array($color, COLUMN_COLORS, true)) respond(['error' => 'Ungültige Eingabe.'], 422);
        $pdo->prepare('UPDATE board_columns SET color = :c WHERE col_key = :k')->execute([':c' => $color, ':k' => $key]);
        respond(['ok' => true]);
        break;

    case 'POST:set_limit':
        $body = json_body(); $key = (string)($body['key'] ?? ''); $limit = max(0, (int)($body['wip_limit'] ?? 0));
        if ($key === '') respond(['error' => 'Ungültige Eingabe.'], 422);
        $pdo->prepare('UPDATE board_columns SET wip_limit = :w WHERE col_key = :k')->execute([':w' => $limit, ':k' => $key]);
        respond(['ok' => true]);
        break;

    case 'POST:delete':
        $body = json_body(); $key = (string)($body['key'] ?? ''); $moveTo = $body['moveTo'] ?? null;
        if ($key === '') respond(['error' => 'Ungültige Spalte.'], 422);
        $totalCols = (int)$pdo->query('SELECT COUNT(*) FROM board_columns')->fetchColumn();
        if ($totalCols <= 1) respond(['error' => 'Die letzte verbleibende Spalte kann nicht gelöscht werden.'], 422);
        $cardCountStmt = $pdo->prepare('SELECT COUNT(*) FROM cards WHERE column_key = :k AND archived = 0');
        $cardCountStmt->execute([':k' => $key]);
        $cardCount = (int)$cardCountStmt->fetchColumn();
        if ($cardCount > 0 && !$moveTo) respond(['error' => 'needs_move_to', 'cardCount' => $cardCount], 409);
        $pdo->beginTransaction();
        try {
            if ($cardCount > 0 && $moveTo) {
                $existsStmt = $pdo->prepare('SELECT COUNT(*) FROM board_columns WHERE col_key = :k');
                $existsStmt->execute([':k' => $moveTo]);
                if ((int)$existsStmt->fetchColumn() === 0) throw new RuntimeException('Zielspalte existiert nicht.');
                $maxPos = (int)$pdo->query('SELECT COALESCE(MAX(position), -1) FROM cards WHERE column_key = ' . $pdo->quote((string)$moveTo))->fetchColumn();
                $cards = $pdo->prepare('SELECT id FROM cards WHERE column_key = :k AND archived = 0 ORDER BY position ASC');
                $cards->execute([':k' => $key]);
                $moveStmt = $pdo->prepare('UPDATE cards SET column_key = :c, position = :p WHERE id = :id');
                foreach ($cards->fetchAll(PDO::FETCH_ASSOC) as $row) { $maxPos++; $moveStmt->execute([':c' => $moveTo, ':p' => $maxPos, ':id' => $row['id']]); }
            }
            $fallback = $moveTo ?: (string)$pdo->query('SELECT col_key FROM board_columns WHERE col_key != ' . $pdo->quote($key) . ' ORDER BY position ASC LIMIT 1')->fetchColumn();
            $pdo->prepare('UPDATE cards SET column_key = :c WHERE column_key = :k AND archived = 1')->execute([':c' => $fallback, ':k' => $key]);
            $pdo->prepare('DELETE FROM board_columns WHERE col_key = :k')->execute([':k' => $key]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); respond(['error' => 'Löschen fehlgeschlagen: ' . $e->getMessage()], 500); }
        respond(['ok' => true]);
        break;

    case 'POST:reorder':
        $body = json_body(); $order = $body['order'] ?? [];
        if (!is_array($order)) respond(['error' => 'Ungültiges Format.'], 422);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE board_columns SET position = :p WHERE col_key = :k');
            foreach (array_values($order) as $pos => $key) $stmt->execute([':p' => $pos, ':k' => (string)$key]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); respond(['error' => 'Sortierung fehlgeschlagen.'], 500); }
        respond(['ok' => true]);
        break;

    default:
        respond(['error' => 'Unbekannte Aktion.'], 404);
}
