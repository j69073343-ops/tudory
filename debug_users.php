<?php
// debug_users.php — sadece yerelde kullan (yayına açma)
header('Content-Type: text/plain; charset=utf-8');

$candidates = [
    __DIR__ . '/data/users.db',
    __DIR__ . '/data/users',
];

$dbPath = null;
foreach ($candidates as $c) {
    if (file_exists($c)) { $dbPath = $c; break; }
}

echo "== debug_users.php ==\n";
if (!$dbPath) {
    echo "Hiç DB bulunamadı. Şu yollar denendi:\n";
    foreach ($candidates as $c) echo " - $c\n";
    exit;
}
echo "DB path: $dbPath\n\n";

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY id DESC');
    foreach ($stmt as $row) {
        printf("id=%d | user=%s | role=%s | created=%s\n",
            $row['id'], $row['username'], $row['role'], $row['created_at']);
    }
} catch (Throwable $e) {
    echo "PDO hata: " . $e->getMessage() . "\n";
}
