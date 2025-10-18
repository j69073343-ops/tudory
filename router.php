<?php
// Built-in server için router
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// Statik dosyaları doğrudan servis et
if ($uri !== '/' && file_exists($file) && is_file($file)) {
    return false;
}

// Güzel URL eşlemeleri
switch ($uri) {
  case '/':
    // landing (index.php ya da index.html hangisi sende varsa)
    if (file_exists(__DIR__.'/index.php')) { require __DIR__.'/index.php'; }
    elseif (file_exists(__DIR__.'/index.html')) { readfile(__DIR__.'/index.html'); }
    else { http_response_code(404); echo 'No index found.'; }
    break;

  case '/admin':
  case '/login':
    require __DIR__.'/admin.php';
    break;

  case '/dashboard':
    require __DIR__.'/dashboard.php';
    break;

  case '/manage':
    require __DIR__.'/manage_users.php';
    break;

  case '/logout':
    require __DIR__.'/logout.php';
    break;

  default:
    // 404
    http_response_code(404);
    echo 'Not Found';
}
