<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
ensure_schema();

$userCount = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$alreadyInstalled = $userCount > 0;

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($alreadyInstalled) {
        $error = 'Es existiert bereits ein Account. Aus Sicherheitsgründen wird die Einrichtung hier abgebrochen.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($username === '' || strlen($username) < 3) {
            $error = 'Benutzername muss mindestens 3 Zeichen haben.';
        } elseif (strlen($password) < 8) {
            $error = 'Passwort muss mindestens 8 Zeichen haben.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwörter stimmen nicht überein.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (:u, :p)');
            $stmt->execute([':u' => $username, ':p' => $hash]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SimpleKan – Einrichtung</title>
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
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg p-8 w-full max-w-md">
    <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 mb-1">SimpleKan Setup</h1>
    <p class="text-slate-500 dark:text-slate-400 text-sm mb-6">Einmalige Einrichtung deines Admin-Accounts.</p>

    <?php if ($success): ?>
        <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 rounded-lg p-4 mb-4 text-sm">
            Account erfolgreich erstellt! Bitte lösche jetzt <code class="bg-green-100 dark:bg-green-900 px-1 rounded">install.php</code> vom Server und melde dich an.
        </div>
        <a href="login.php" class="block text-center bg-slate-800 dark:bg-slate-600 text-white rounded-lg py-2.5 font-medium hover:bg-slate-700 dark:hover:bg-slate-500 transition">Zum Login</a>
    <?php elseif ($alreadyInstalled): ?>
        <div class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 rounded-lg p-4 mb-4 text-sm">
            Es existiert bereits eine Installation. Lösche <code class="bg-amber-100 dark:bg-amber-900 px-1 rounded">install.php</code> vom Server.
        </div>
        <a href="login.php" class="block text-center bg-slate-800 dark:bg-slate-600 text-white rounded-lg py-2.5 font-medium hover:bg-slate-700 dark:hover:bg-slate-500 transition">Zum Login</a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-lg p-3 mb-4 text-sm"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Benutzername</label>
                <input type="text" name="username" required minlength="3"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Passwort (min. 8 Zeichen)</label>
                <input type="password" name="password" required minlength="8"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Passwort bestätigen</label>
                <input type="password" name="password_confirm" required minlength="8"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400">
            </div>
            <button type="submit" class="w-full bg-slate-800 dark:bg-slate-600 text-white rounded-lg py-2.5 font-medium hover:bg-slate-700 dark:hover:bg-slate-500 transition">
                Account erstellen
            </button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
