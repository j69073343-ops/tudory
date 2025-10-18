<?php
// Flexible router for PHP built-in server (Render).
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// 1) Serve static files directly (css/js/img, etc.)
if ($uri !== '/' && file_exists($file) && is_file($file)) {
    return false;
}

// Helpers: resolve a file either in root or in /griffonpanel
function resolve_app($name) {
    $root = __DIR__ . '/' . $name;
    $sub  = __DIR__ . '/griffonpanel/' . $name;
    if (file_exists($root)) return $root;
    if (file_exists($sub))  return $sub;
    return null;
}

// 2) Pretty routes
switch ($uri) {
  case '/':
    // Try root index first, then /griffonpanel/index.php, then index.html
    if ($p = resolve_app('index.php')) {
      require $p; break;
    }
    if (file_exists(__DIR__.'/index.html')) { readfile(__DIR__.'/index.html'); break; }
    if (file_exists(__DIR__.'/griffonpanel/index.php')) { require __DIR__.'/griffonpanel/index.php'; break; }
    http_response_code(404); echo 'No index found.'; break;

  case '/admin':
  case '/login':
    if ($p = resolve_app('admin.php')) { require $p; break; }
    http_response_code(404); echo 'admin.php not found'; break;

  case '/dashboard':
    if ($p = resolve_app('dashboard.php')) { require $p; break; }
    http_response_code(404); echo 'dashboard.php not found'; break;

  case '/manage':
    if ($p = resolve_app('manage_users.php')) { require $p; break; }
    http_response_code(404); echo 'manage_users.php not found'; break;

  case '/logout':
    if ($p = resolve_app('logout.php')) { require $p; break; }
    http_response_code(404); echo 'logout.php not found'; break;

  default:
    http_response_code(404);
    echo 'Not Found';
}
