<?php
session_start();
if (empty($_SESSION['auth']) || $_SESSION['auth'] !== true) { header('Location: admin.php'); exit; }

if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token']=bin2hex(random_bytes(32)); }
$csrf=$_SESSION['csrf_token'];

$dbDir=__DIR__.'/data'; $dbFile=$dbDir.'/users.db'; if(!is_dir($dbDir)) mkdir($dbDir,0755,true);
$pdo=new PDO('sqlite:'.$dbFile); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

/* tablo + kolonlar */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )
");
$cols=[]; foreach($pdo->query("PRAGMA table_info(users)") as $c){ $cols[]=$c['name']; }
foreach (['install_locked INTEGER DEFAULT 0','lock_reason TEXT','active_step INTEGER DEFAULT 1','created TEXT'] as $ddl) {
  $name=explode(' ',$ddl)[0]; if(!in_array($name,$cols)) $pdo->exec("ALTER TABLE users ADD COLUMN $ddl");
}
$pdo->exec("UPDATE users SET created = created_at WHERE created IS NULL");

function flash($m){ $_SESSION['flash']=$m; } function get_flash(){ $m=$_SESSION['flash']??null; unset($_SESSION['flash']); return $m; }

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(empty($_POST['csrf_token']) || !hash_equals($csrf,$_POST['csrf_token'])){ flash('Güvenlik doğrulaması başarısız.'); header('Location: manage_users.php'); exit; }
  $action=$_POST['action']??'';
  try{
    if($action==='add'){
      $u=trim($_POST['username']??''); $p=$_POST['password']??''; $r=in_array($_POST['role']??'user',['user','admin'])?$_POST['role']:'user';
      if($u===''||$p==='') throw new Exception('Kullanıcı adı ve şifre gerekli.');
      $hash=password_hash($p,PASSWORD_DEFAULT);
      $pdo->prepare("INSERT INTO users (username,password_hash,role) VALUES (:u,:p,:r)")->execute([':u'=>$u,':p'=>$hash,':r'=>$r]);
      flash("Kullanıcı \"$u\" oluşturuldu."); header('Location: manage_users.php'); exit;
    }
    if($action==='update'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new Exception('Geçersiz id.');
      $p=$_POST['password']??''; $r=in_array($_POST['role']??'user',['user','admin'])?$_POST['role']:'user';
      if($p!==''){
        $hash=password_hash($p,PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash=:p, role=:r WHERE id=:id")->execute([':p'=>$hash,':r'=>$r,':id'=>$id]);
      }else{
        $pdo->prepare("UPDATE users SET role=:r WHERE id=:id")->execute([':r'=>$r,':id'=>$id]);
      }
      flash('Kullanıcı güncellendi.'); header('Location: manage_users.php'); exit;
    }
    if($action==='delete'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new Exception('Geçersiz id.');
      $cur=$_SESSION['user']??''; $st=$pdo->prepare("SELECT username FROM users WHERE id=:id"); $st->execute([':id'=>$id]); $row=$st->fetch(PDO::FETCH_ASSOC);
      if($row && $row['username']===$cur) throw new Exception('Kendinizi silemezsiniz.');
      $pdo->prepare("DELETE FROM users WHERE id=:id")->execute([':id'=>$id]);
      flash('Kullanıcı silindi.'); header('Location: manage_users.php'); exit;
    }
    if($action==='unblock_by_id'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new Exception('Geçersiz id.');
      $pdo->prepare("UPDATE users SET install_locked=0, lock_reason=NULL, active_step=1 WHERE id=:id")->execute([':id'=>$id]);
      flash("Kullanıcı Adım 1'e alındı ve engel kaldırıldı."); header('Location: manage_users.php'); exit;
    }
    if($action==='unlock_5001'){
      $id=(int)($_POST['id']??0); if($id<=0) throw new Exception('Geçersiz id.');
      $pdo->prepare("
        UPDATE users
        SET install_locked=0, lock_reason=NULL, active_step=1
        WHERE id=:id AND lock_reason='5001'
      ")->execute([':id'=>$id]);
      flash("5001 kilidi kaldırıldı ve akış Adım 1'e alındı."); header('Location: manage_users.php'); exit;
    }
    if($action==='unblock_current'){
      $cur=$_SESSION['user']??''; if($cur==='') throw new Exception('Oturum bulunamadı.');
      $pdo->prepare("UPDATE users SET install_locked=0, lock_reason=NULL, active_step=1 WHERE username=:u")->execute([':u'=>$cur]);
      flash("Oturumdaki kullanıcı ($cur) Adım 1'e alındı."); header('Location: manage_users.php'); exit;
    }
  }catch(Exception $e){ flash('Hata: '.$e->getMessage()); header('Location: manage_users.php'); exit; }
}

$rows=$pdo->query("
  SELECT id,username,role,COALESCE(created,created_at) AS created_display,install_locked,lock_reason,active_step
  FROM users ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);
$flash=get_flash();
$sessionUser=$_SESSION['user']??'';
$sessionRow=null;
if($sessionUser!==''){
  $st=$pdo->prepare("SELECT id,active_step,install_locked,lock_reason FROM users WHERE username=:u");
  $st->execute([':u'=>$sessionUser]);
  $sessionRow=$st->fetch(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kullanıcı Yönetimi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<header class="bg-gray-300 shadow">
  <div class="container mx-auto px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <img src="assets/logo.png" class="h-10" alt="Logo" onerror="this.style.display='none'">
      <div><div class="font-semibold text-gray-800">Yönetici Paneli</div><div class="text-xs text-gray-600">Kullanıcı Yönetimi</div></div>
    </div>
    <div class="flex items-center gap-3">
      <div class="text-sm text-gray-700">Merhaba, <strong><?= htmlspecialchars($sessionUser) ?></strong></div>
      <a href="dashboard.php" class="px-3 py-2 bg-indigo-600 text-white rounded">Panele Dön</a>
      <a href="logout.php" class="px-3 py-2 bg-gray-800 text-white rounded">Çıkış</a>
    </div>
  </div>
</header>

<main class="container mx-auto px-4 py-6">
  <?php if($flash): ?><div class="mb-4 p-3 rounded bg-green-50 text-green-700 border border-green-200"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

  <?php if($sessionRow): ?>
    <div class="mb-6 p-4 bg-white border rounded-lg flex items-center justify-between">
      <div class="text-sm text-gray-700">
        <div><strong>Şu anki oturum:</strong> <?= htmlspecialchars($sessionUser) ?> (ID: <?= (int)$sessionRow['id'] ?>)</div>
        <div>Adım: <?= (int)$sessionRow['active_step'] ?> — Kilit: <?= (int)$sessionRow['install_locked'] ?> <?= $sessionRow['lock_reason'] ? '('.htmlspecialchars($sessionRow['lock_reason']).')' : '' ?></div>
      </div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="unblock_current">
        <button class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Oturumdaki Kullanıcıyı Adım 1'e Sıfırla</button>
      </form>
    </div>
  <?php endif; ?>

  <div class="grid md:grid-cols-3 gap-6">
    <div class="bg-white rounded-lg shadow p-6">
      <h2 class="font-semibold mb-3">Yeni Kullanıcı Oluştur</h2>
      <form method="post" class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="add">
        <div><label class="block text-sm text-gray-600">Kullanıcı Adı</label><input name="username" required class="mt-1 block w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm text-gray-600">Şifre</label><input name="password" type="password" required class="mt-1 block w-full border rounded px-3 py-2"></div>
        <div><label class="block text-sm text-gray-600">Rol</label>
          <select name="role" class="mt-1 block w-full border rounded px-3 py-2">
            <option value="user">Kullanıcı</option><option value="admin">Yönetici</option>
          </select>
        </div>
        <button class="w-full py-2 bg-indigo-600 text-white rounded">Oluştur</button>
      </form>
    </div>

    <div class="md:col-span-2 space-y-4">
      <div class="bg-white rounded-lg shadow p-4 overflow-x-auto">
        <h2 class="font-semibold mb-3">Kullanıcılar</h2>
        <table class="w-full text-sm">
          <thead class="text-left text-gray-600">
            <tr>
              <th class="py-2">ID</th><th>Kullanıcı Adı</th><th>Rol</th><th>Üyelik Tarihi</th><th>Kilit Durumu</th><th>Aktif Adım</th><th class="text-right">İşlemler</th>
            </tr>
          </thead>
          <tbody>
            <?php if($rows): foreach($rows as $u): ?>
            <tr class="border-t">
              <td class="py-2"><?= htmlspecialchars($u['id']) ?></td>
              <td><?= htmlspecialchars($u['username']) ?><?= ($u['username']===$sessionUser)?' <span class="text-xs text-indigo-600">(oturum)</span>':'' ?></td>
              <td><?= htmlspecialchars($u['role']) ?></td>
              <td class="text-xs text-gray-500"><?= $u['created_display'] ? date('d.m.Y H:i', strtotime($u['created_display'])) : 'Bilinmiyor' ?></td>
              <td>
                <?php
                  $badge='<span class="px-2 py-1 rounded text-xs bg-gray-200 text-gray-700">Kilitsiz</span>';
                  if(!empty($u['install_locked'])){
                    if($u['lock_reason']==='5001')   $badge='<span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700 border border-red-300">5001</span>';
                    elseif($u['lock_reason']==='double') $badge='<span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700 border border-red-300">Çift Koruma</span>';
                    else $badge='<span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700 border border-red-300">Kilitli</span>';
                  }
                  echo $badge;
                ?>
              </td>
              <td><?= (int)($u['active_step'] ?? 1) ?></td>
              <td class="text-right">
                <!-- 5001'e özel buton -->
                <?php if(!empty($u['install_locked']) && $u['lock_reason']==='5001'): ?>
                  <form method="post" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="unlock_5001">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                    <button class="px-3 py-1.5 rounded bg-amber-600 hover:bg-amber-700 text-white text-xs">5001 Kilidini Aç (Adım 1)</button>
                  </form>
                <?php endif; ?>

                <!-- Genel: Adım 1'e sıfırla -->
                <form method="post" class="inline ml-2">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="action" value="unblock_by_id">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                  <button class="px-3 py-1.5 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs">Adım 1'e Sıfırla</button>
                </form>

                <!-- Düzenle -->
                <details class="inline-block mx-2 align-middle">
                  <summary class="cursor-pointer px-2 py-1 bg-gray-100 rounded">Düzenle</summary>
                  <form method="post" class="mt-2 p-3 bg-gray-50 border rounded space-y-2">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                    <div><label class="text-xs text-gray-600">Yeni Şifre (boş = değiştirme)</label><input name="password" type="password" class="mt-1 block w-full border rounded px-2 py-1"></div>
                    <div><label class="text-xs text-gray-600">Rol</label>
                      <select name="role" class="mt-1 block w-full border rounded px-2 py-1">
                        <option value="user" <?= $u['role']==='user'?'selected':'' ?>>Kullanıcı</option>
                        <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Yönetici</option>
                      </select>
                    </div>
                    <button class="px-3 py-1 bg-green-600 text-white rounded text-sm">Kaydet</button>
                  </form>
                </details>

                <!-- Sil -->
                <form method="post" class="inline" onsubmit="return confirm('Kullanıcı silinecek. Emin misiniz?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                  <button class="ml-2 px-2 py-1 bg-red-600 text-white rounded text-sm">Sil</button>
                </form>
              </td>
            </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="7" class="py-4 text-gray-500">Kayıtlı kullanıcı yok.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
</body>
</html>
