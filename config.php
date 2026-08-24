<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('DB_PATH', __DIR__ . '/data/kanban.sqlite');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'domain' => '',
    'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Strict',
]);
session_name('kanban_sid');
session_start();

const COLUMN_COLORS = ['sky', 'rose', 'slate', 'amber', 'emerald', 'neutral', 'violet', 'cyan', 'lime', 'fuchsia', 'orange', 'teal', 'indigo', 'pink'];
const PRIORITIES = ['niedrig', 'mittel', 'hoch'];

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }
    return $pdo;
}

function table_has_column(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['name'] === $column) return true;
    }
    return false;
}

function ensure_schema(): void {
    $pdo = db();
    // WICHTIG: SQLite-String-Literale MUESSEN einfache Anfuehrungszeichen nutzen ('now'),
    // doppelte Anfuehrungszeichen werden als Bezeichner interpretiert und sind in
    // DEFAULT-Klauseln nicht als "konstant" zugelassen (-> PDOException).
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL, failed_attempts INTEGER NOT NULL DEFAULT 0,
        locked_until INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT (datetime('now')))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS board_columns (
        id INTEGER PRIMARY KEY AUTOINCREMENT, col_key TEXT NOT NULL UNIQUE, label TEXT NOT NULL,
        color TEXT NOT NULL DEFAULT 'slate', position INTEGER NOT NULL DEFAULT 0, wip_limit INTEGER NOT NULL DEFAULT 0)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cards (
        id INTEGER PRIMARY KEY AUTOINCREMENT, column_key TEXT NOT NULL, title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '', due_date TEXT, priority TEXT, tag TEXT,
        archived INTEGER NOT NULL DEFAULT 0, position INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')), updated_at TEXT NOT NULL DEFAULT (datetime('now')))");

    if (!table_has_column($pdo, 'board_columns', 'wip_limit')) $pdo->exec('ALTER TABLE board_columns ADD COLUMN wip_limit INTEGER NOT NULL DEFAULT 0');
    if (!table_has_column($pdo, 'cards', 'due_date')) $pdo->exec('ALTER TABLE cards ADD COLUMN due_date TEXT');
    if (!table_has_column($pdo, 'cards', 'priority')) $pdo->exec('ALTER TABLE cards ADD COLUMN priority TEXT');
    if (!table_has_column($pdo, 'cards', 'archived')) $pdo->exec('ALTER TABLE cards ADD COLUMN archived INTEGER NOT NULL DEFAULT 0');
    if (!table_has_column($pdo, 'cards', 'tag')) $pdo->exec('ALTER TABLE cards ADD COLUMN tag TEXT');

    $colCount = (int)$pdo->query('SELECT COUNT(*) FROM board_columns')->fetchColumn();
    if ($colCount === 0) {
        $defaults = [
            ['neue_ideen', 'Neue Ideen', 'sky'], ['bugs', 'Bugs', 'rose'], ['todo', 'To Do', 'slate'],
            ['in_arbeit', 'In Arbeit', 'amber'], ['abgeschlossen', 'Abgeschlossen', 'emerald'], ['abgelehnt', 'Abgelehnte Ideen', 'neutral'],
        ];
        $stmt = $pdo->prepare('INSERT INTO board_columns (col_key, label, color, position) VALUES (:k, :l, :c, :p)');
        foreach ($defaults as $i => $d) $stmt->execute([':k' => $d[0], ':l' => $d[1], ':c' => $d[2], ':p' => $i]);
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_check(?string $token): bool {
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function require_login(): void {
    if (!is_logged_in()) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Nicht angemeldet.']);
            exit;
        }
        header('Location: login.php');
        exit;
    }
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function slugify(string $text): string {
    $text = mb_strtolower(trim($text));
    $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    $text = trim($text, '_');
    return $text !== '' ? $text : 'spalte';
}
