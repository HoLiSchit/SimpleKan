<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
ensure_schema();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;
const MAX_ATTEMPTS = 5;
const LOCK_SECONDS = 300;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = time();
        if ($user && (int)$user['locked_until'] > $now) {
            $error = 'Zu viele Fehlversuche. Bitte in ein paar Minuten erneut versuchen.';
        } elseif ($user && password_verify($password, $user['password_hash'])) {
            $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = 0 WHERE id = :id')
                ->execute([':id' => $user['id']]);

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            header('Location: index.php');
            exit;
        } else {
            if ($user) {
                $attempts = (int)$user['failed_attempts'] + 1;
                $lockUntil = $attempts >= MAX_ATTEMPTS ? $now + LOCK_SECONDS : 0;
                $pdo->prepare('UPDATE users SET failed_attempts = :a, locked_until = :l WHERE id = :id')
                    ->execute([':a' => $attempts, ':l' => $lockUntil, ':id' => $user['id']]);
            }
            $error = 'Benutzername oder Passwort falsch.';
            usleep(300000);
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – SimpleKan</title>
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
</head>
<body class="bg-slate-100 dark:bg-slate-900 min-h-screen flex items-center justify-center p-4 transition-colors">
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 w-full max-w-sm">
    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 mb-1">Anmelden</h1>
    <p class="text-slate-500 dark:text-slate-400 text-sm mb-6">SimpleKan</p>

    <?php if ($error): ?>
        <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg p-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Benutzername</label>
            <input type="text" name="username" required autofocus
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Passwort</label>
            <input type="password" name="password" required
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
        </div>
        <button type="submit" class="w-full bg-slate-800 dark:bg-slate-600 text-white rounded-lg py-2.5 font-medium hover:bg-slate-700 dark:hover:bg-slate-500 transition">
            Anmelden
        </button>
    </form>
</div>
</body>
</html>
