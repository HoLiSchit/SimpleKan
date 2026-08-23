<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();
ensure_schema();

header('Content-Type: application/json; charset=utf-8');

function json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function valid_column(PDO $pdo, string $key): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM board_columns WHERE col_key = :k');
    $stmt->execute([':k' => $key]);
    return (int)$stmt->fetchColumn() > 0;
}

function valid_due_date(?string $date): bool
{
    if ($date === null || $date === '') return true;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function normalize_priority(?string $p): ?string
{
    if ($p === null || $p === '') return null;
    return in_array($p, PRIORITIES, true) ? $p : null;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method !== 'GET') {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!csrf_check($csrfHeader)) {
        respond(['error' => 'Ungültiges CSRF-Token.'], 403);
    }
}

$pdo = db();

switch ("$method:$action") {

    case 'GET:list':
        $archived = isset($_GET['archived']) && $_GET['archived'] === '1' ? 1 : 0;
        $stmt = $pdo->prepare('SELECT id, column_key, title, description, due_date, priority, position FROM cards WHERE archived = :a ORDER BY column_key, position ASC');
        $stmt->execute([':a' => $archived]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];
        $flat = [];
        foreach ($rows as $row) {
            $card = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'due_date' => $row['due_date'],
                'priority' => $row['priority'],
                'position' => (int)$row['position'],
                'column' => $row['column_key'],
            ];
            $grouped[$row['column_key']][] = $card;
            $flat[] = $card;
        }
        respond($archived ? ['cards' => $flat] : ['columns' => $grouped]);
        break;

    case 'POST:create':
        $body = json_body();
        $column = (string)($body['column'] ?? '');
        $title = trim((string)($body['title'] ?? ''));
        $description = trim((string)($body['description'] ?? ''));
        $dueDate = $body['due_date'] ?? null;
        $priority = normalize_priority($body['priority'] ?? null);

        if (!valid_column($pdo, $column)) respond(['error' => 'Ungültige Spalte.'], 422);
        if ($title === '' || mb_strlen($title) > 200) respond(['error' => 'Titel ist erforderlich (max. 200 Zeichen).'], 422);
        if (mb_strlen($description) > 2000) respond(['error' => 'Beschreibung zu lang.'], 422);
        if (!valid_due_date($dueDate)) respond(['error' => 'Ungültiges Datum.'], 422);

        $maxPos = (int)$pdo->query("SELECT COALESCE(MAX(position), -1) FROM cards WHERE column_key = " . $pdo->quote($column))->fetchColumn();

        $stmt = $pdo->prepare('INSERT INTO cards (column_key, title, description, due_date, priority, position, updated_at) VALUES (:c, :t, :d, :due, :p, :pos, datetime("now"))');
        $stmt->execute([':c' => $column, ':t' => $title, ':d' => $description, ':due' => $dueDate ?: null, ':p' => $priority, ':pos' => $maxPos + 1]);

        respond(['id' => (int)$pdo->lastInsertId()], 201);
        break;

    case 'POST:update':
        $body = json_body();
        $id = (int)($body['id'] ?? 0);
        $title = trim((string)($body['title'] ?? ''));
        $description = trim((string)($body['description'] ?? ''));
        $dueDate = $body['due_date'] ?? null;
        $priority = normalize_priority($body['priority'] ?? null);

        if ($id <= 0) respond(['error' => 'Ungültige ID.'], 422);
        if ($title === '' || mb_strlen($title) > 200) respond(['error' => 'Titel ist erforderlich (max. 200 Zeichen).'], 422);
        if (mb_strlen($description) > 2000) respond(['error' => 'Beschreibung zu lang.'], 422);
        if (!valid_due_date($dueDate)) respond(['error' => 'Ungültiges Datum.'], 422);

        $stmt = $pdo->prepare('UPDATE cards SET title = :t, description = :d, due_date = :due, priority = :p, updated_at = datetime("now") WHERE id = :id');
        $stmt->execute([':t' => $title, ':d' => $description, ':due' => $dueDate ?: null, ':p' => $priority, ':id' => $id]);

        respond(['ok' => true]);
        break;

    case 'POST:archive':
        $body = json_body();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) respond(['error' => 'Ungültige ID.'], 422);
        $pdo->prepare('UPDATE cards SET archived = 1, updated_at = datetime("now") WHERE id = :id')->execute([':id' => $id]);
        respond(['ok' => true]);
        break;

    case 'POST:restore':
        $body = json_body();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) respond(['error' => 'Ungültige ID.'], 422);

        $stmt = $pdo->prepare('SELECT column_key FROM cards WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $columnKey = $stmt->fetchColumn();
        if ($columnKey === false) respond(['error' => 'Karte nicht gefunden.'], 404);
        if (!valid_column($pdo, (string)$columnKey)) {
            $columnKey = (string)$pdo->query('SELECT col_key FROM board_columns ORDER BY position ASC LIMIT 1')->fetchColumn();
        }

        $maxPos = (int)$pdo->query("SELECT COALESCE(MAX(position), -1) FROM cards WHERE column_key = " . $pdo->quote((string)$columnKey) . ' AND archived = 0')->fetchColumn();
        $pdo->prepare('UPDATE cards SET archived = 0, column_key = :c, position = :p, updated_at = datetime("now") WHERE id = :id')
            ->execute([':c' => $columnKey, ':p' => $maxPos + 1, ':id' => $id]);

        respond(['ok' => true]);
        break;

    case 'POST:delete':
        $body = json_body();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) respond(['error' => 'Ungültige ID.'], 422);
        $pdo->prepare('DELETE FROM cards WHERE id = :id')->execute([':id' => $id]);
        respond(['ok' => true]);
        break;

    case 'POST:reorder':
        $body = json_body();
        $cols = $body['columns'] ?? [];
        if (!is_array($cols)) respond(['error' => 'Ungültiges Format.'], 422);

        $pdo->beginTransaction();
        try {
            $updateStmt = $pdo->prepare('UPDATE cards SET column_key = :c, position = :p, updated_at = datetime("now") WHERE id = :id');
            foreach ($cols as $columnKey => $cardIds) {
                if (!valid_column($pdo, (string)$columnKey) || !is_array($cardIds)) continue;
                foreach (array_values($cardIds) as $position => $cardId) {
                    $updateStmt->execute([':c' => $columnKey, ':p' => $position, ':id' => (int)$cardId]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            respond(['error' => 'Fehler beim Speichern der Reihenfolge.'], 500);
        }

        respond(['ok' => true]);
        break;

    default:
        respond(['error' => 'Unbekannte Aktion.'], 404);
}
