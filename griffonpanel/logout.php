<?php
// logout.php — Güvenli çıkış işlemi

session_start();

// Tüm session değişkenlerini temizle
$_SESSION = [];

// Session cookie'sini geçersiz kıl
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Oturumu tamamen yok et
session_destroy();

// Giriş sayfasına yönlendir
header('Location: admin.php');
exit;
?>
