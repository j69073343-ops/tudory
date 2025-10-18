<?php
// admin.php — Giriş (SQLite DB doğrulamalı)
session_start();

// Zaten giriş yaptıysa dashboard'a gönder
if (!empty($_SESSION['auth']) && $_SESSION['auth'] === true) {
    header('Location: dashboard.php');
    exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// *** ÖNEMLİ: DB YOLU (debug_users.php'nin gösterdiğiyle birebir) ***
$DB_PATH = __DIR__ . '/data/users.db';

// İstersen demo fallback açık kalsın (admin / Griffon123!)
$ALLOW_FALLBACK_DEMO = true;
$DEMO_USER = 'admin';
$DEMO_PASS = 'Griffon123!';

$error = '';

function verify_user_from_db(string $username, string $password, string $dbPath): bool {
    if (!file_exists($dbPath)) return false;
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && password_verify($password, $row['password_hash']);
    } catch (Throwable $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Güvenlik doğrulaması başarısız (CSRF).';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Lütfen kullanıcı adı ve şifre girin.';
        } else {
            $ok = verify_user_from_db($username, $password, $DB_PATH);

            // Opsiyonel demo fallback
            if (!$ok && $ALLOW_FALLBACK_DEMO && $username === $DEMO_USER && $password === $DEMO_PASS) {
                $ok = true;
            }

            if ($ok) {
                $_SESSION['auth'] = true;
                $_SESSION['user'] = $username;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // token yenile
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Kullanıcı adı veya şifre hatalı.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Giriş Yap | Griffon Kullanıcı Paneli</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style> html { scroll-behavior: smooth; } </style>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <header class="bg-gray-400 rounded-t-xl px-6 py-4 shadow-md">
      <div class="flex items-center space-x-3">
        <img src="assets/logo.png" alt="Griffon Logo" class="h-16 w-auto" onerror="this.style.display='none'">
        <span class="text-lg font-semibold text-gray-900">Griffon Kullanıcı Paneli</span>
      </div>
    </header>

    <div class="bg-white shadow-md rounded-b-xl px-6 py-8">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">Giriş Yap</h1>
      <p class="text-gray-600 mb-6">Lütfen kullanıcı adınız ve şifrenizle giriş yapın.</p>

      <?php if (!empty($error)): ?>
      <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700 border border-red-200">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
      </div>
      <?php endif; ?>

      <form method="post" class="space-y-4" autocomplete="off" action="admin.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>" />

        <div>
          <label for="username" class="block text-sm font-medium text-gray-700">Kullanıcı Adı</label>
          <input id="username" name="username" type="text" required
                 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2"
                 placeholder="kullanıcı adınız" />
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-gray-700">Şifre</label>
          <input id="password" name="password" type="password" required
                 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2"
                 placeholder="••••••••" />
        </div>

        <button type="submit"
                class="w-full py-2.5 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white font-semibold transition">
          Giriş Yap
        </button>
      </form>

      <div class="mt-6 text-xs text-gray-500">
        <p>: <code><?= htmlspecialchars($DB_PATH, ENT_QUOTES, 'UTF-8'); ?></code></p>
        <p>.</p>
      </div>
    </div>
  </div>
</body>
</html>
