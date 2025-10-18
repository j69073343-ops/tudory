<?php
// Flexible router for PHP built-in server (Render)

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// 1) Statik dosyaları aynen servis et
if ($uri !== '/' && file_exists($file) && is_file($file)) {
  return false;
}

// Kök veya /griffonpanel altındaki bir dosyayı çöz
function resolve_app($name) {
  $root = __DIR__ . '/' . $name;
  $sub  = __DIR__ . '/griffonpanel/' . $name;
  if (file_exists($root)) return $root;
  if (file_exists($sub))  return $sub;
  return null;
}

/* ---- ROOT (/) ÖNCELİK: index.html, sonra index.php ---- */
if ($uri === '/') {
  if (file_exists(__DIR__ . '/index.html')) {
    readfile(__DIR__ . '/index.html');
    exit;
  }
  if ($p = resolve_app('index.php')) {
    require $p;
    exit;
  }
  http_response_code(404);
  echo 'No index found.';
  exit;
}

// Pretty ve .php yollarını aynı hedefe düşürelim
$map = [
  '/admin'            => 'admin.php',
  '/admin.php'        => 'admin.php',
  '/login'            => 'admin.php',
  '/dashboard'        => 'dashboard.php',
  '/dashboard.php'    => 'dashboard.php',
  '/manage'           => 'manage_users.php',
  '/manage_users.php' => 'manage_users.php',
  '/logout'           => 'logout.php',
  '/logout.php'       => 'logout.php',
  '/panel.php'        => 'dashboard.php', // eski yönlendirmeler için
];

// Eşleşen route varsa çalıştır
if (isset($map[$uri])) {
  $target = resolve_app($map[$uri]);
  if ($target) { require $target; exit; }
  http_response_code(404);
  echo basename($map[$uri]) . ' not found';
  exit;
}

// 404
http_response_code(404);
echo 'Not Found';

