<?php
// varlik.php — TPAO portföy özeti + optimize grafik (Favoriler kaldırıldı, Geçmiş -> Yatırım/Çekim)
ob_start();
session_start();
if (empty($_SESSION['user_email'])) { header('Location: index.php'); exit; }

/* ---------- helpers ---------- */
function email_key(string $e): string { return strtolower(trim($e)); }
const DATA_DIR = __DIR__ . '/data';
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0775, true); }

$U = email_key($_SESSION['user_email']);
$WALLET_FILE   = DATA_DIR . "/wallet_{$U}.json";
$PANEL_FILE    = DATA_DIR . "/panel_{$U}.json";
$PORTF_FILE    = DATA_DIR . "/portfolio_{$U}.json";
$PRICEH_FILE   = DATA_DIR . "/price_history_{$U}.json";

function jload(string $file, $default){
  if(!file_exists($file)) return $default;
  $raw=@file_get_contents($file);
  $d=json_decode($raw?:'null',true);
  return ($d===null?$default:$d);
}
function tl($n){ return number_format((float)$n, 2, ',', '.'); }

/* ---------- data ---------- */
$wallet = jload($WALLET_FILE, ['balance'=>100000.00]);
$panel  = jload($PANEL_FILE,  ['buy_price'=>35.50,'sell_price'=>35.20,'fee_percent'=>0.10]);
$pf     = jload($PORTF_FILE,  ['TPAO'=>['qty'=>0,'avg_price'=>0]]);
$phist  = jload($PRICEH_FILE, []);

$qty    = (int)($pf['TPAO']['qty'] ?? 0);
$avg    = (float)($pf['TPAO']['avg_price'] ?? 0);
$buyP   = (float)$panel['buy_price'];
$sellP  = (float)$panel['sell_price'];
$bal    = (float)$wallet['balance'];

$portfolio_value = $qty * $sellP;
$unreal          = $qty>0 ? ($sellP - $avg) * $qty : 0;
$unreal_pct      = ($qty>0 && $avg>0) ? (($sellP - $avg) / $avg) * 100.0 : 0;

/* ---------- chart series from price_history (sell) ---------- */
$labels = [];
$prices = [];
foreach ($phist as $row) {
  $ts = $row['ts'] ?? null;
  $p  = isset($row['new_sell']) ? (float)$row['new_sell'] : null;
  if ($ts && $p !== null) { $labels[] = $ts; $prices[] = $p; }
}
if (empty($prices)) { $labels=[date('Y-m-d H:i:s')]; $prices=[$sellP]; }

$delta_abs = 0; $delta_pct = 0; $dir='flat';
if (count($prices) >= 2) {
  $prev = $prices[count($prices)-2];
  $last = $prices[count($prices)-1];
  $delta_abs = $last - $prev;
  $delta_pct = $prev!=0 ? ($delta_abs/$prev)*100.0 : 0;
  $dir = $delta_abs>0?'up':($delta_abs<0?'down':'flat');
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Aktif Varlıklar • TPAO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  :root{ --bg1:#0b203f; --bg2:#08192d; --card:#132437; --line:#1e3348; --txt:#cfdae6; --mut:#93a5ba; --good:#35d69c; --bad:#ff7a7a; --wrap:1100px; }
  *{box-sizing:border-box}
  body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:var(--txt);
       background:radial-gradient(1200px 900px at 70% -20%,#12345b 0%,var(--bg1) 40%,var(--bg2) 100%);min-height:100vh;}
  header{position:sticky;top:0;z-index:10;background:linear-gradient(180deg,rgba(10,20,35,.9),rgba(10,20,35,.6) 60%,transparent);backdrop-filter:blur(8px)}
  .wrap{max-width:var(--wrap);margin:0 auto;padding:18px}
  .title{font-size:28px;font-weight:800;margin:4px 0 14px}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:14px;box-shadow:0 14px 36px rgba(0,0,0,.35)}
  .k{font-size:12px;color:#9fb2c8}
  .v{font-size:22px;font-weight:800;margin-top:6px}
  .pill{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600}
  .pill.up{background:rgba(53,214,156,.12);color:#34d3a7}
  .pill.down{background:rgba(255,122,122,.12);color:#ff8a8a}
  .pill.flat{background:rgba(160,176,196,.14);color:#cdd7e5}
  .two{display:grid;grid-template-columns:2.1fr 1fr;gap:14px;margin-top:14px}
  .subgrid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .meta{color:#9ab0c8;font-size:13px;line-height:1.6}
  .bar{height:8px;background:#0e2034;border-radius:10px;overflow:hidden}
  .bar > i{display:block;height:100%;background:linear-gradient(90deg,#2edaa4,#15c3f6)}
  .foot{margin-top:10px;font-size:12px;color:#8ca2bd}
  .chartWrap{height:clamp(220px, 45vh, 460px); position:relative}
  .bottom{position:sticky;bottom:0;background:#0e1f34;box-shadow:0 -6px 20px rgba(0,0,0,.4);
          padding-bottom:max(8px, env(safe-area-inset-bottom));}
  .tabs{max-width:var(--wrap);margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);gap:6px;padding:10px 18px}
  .tab{display:grid;place-items:center;color:#b8c8db;font-size:12px;gap:6px;padding:10px 0;border-radius:12px;text-decoration:none;min-height:48px}
  .tab.active{background:#152a42}
  @media(max-width:1050px){ .grid{grid-template-columns:repeat(2,1fr)} .two{grid-template-columns:1fr} .subgrid{grid-template-columns:1fr} }
</style>
</head>
<body>

<header>
  <div class="wrap">
    <div class="title">Aktif Varlıklar · Türkiye Petrolleri A.Ş. (TPAO)</div>
    <div class="grid">
      <div class="card">
        <div class="k">Portföy Değeri (Satış)</div>
        <div class="v"><?= tl($portfolio_value) ?> ₺</div>
        <div style="margin-top:8px">
          <?php $pillClass=$dir==='up'?'up':($dir==='down'?'down':'flat'); $sign=$delta_abs>0?'+':($delta_abs<0?'-':''); ?>
          <span class="pill <?= $pillClass ?>">
            <?= $dir==='up'?'↑':($dir==='down'?'↓':'→') ?>
            <?= $sign . tl(abs($delta_abs)) ?> ₺
            (<?= ($delta_pct>=0?'+':'').number_format($delta_pct,2,',','.') ?>%)
          </span>
        </div>
      </div>
      <div class="card">
        <div class="k">Elinizdeki Lot</div>
        <div class="v"><?= (int)$qty ?></div>
        <div class="foot">Cüzdan: <?= tl($bal) ?> ₺</div>
      </div>
      <div class="card">
        <div class="k">Gerç. Olmayan K/Z</div>
        <?php $c = $unreal>=0?'color:var(--good)':'color:var(--bad)'; ?>
        <div class="v" style="<?= $c ?>"><?= ($unreal>=0?'+':'').tl($unreal) ?> ₺</div>
        <div class="foot">Oran: <?= ($unreal_pct>=0?'+':'').number_format($unreal_pct,2,',','.') ?>%</div>
      </div>
      <div class="card">
        <div class="k">Güncel Alış / Satış</div>
        <div class="v"><?= tl($buyP) ?> ₺ / <?= tl($sellP) ?> ₺</div>
        <div class="foot">Sizin ort. maliyetiniz: <b><?= tl($avg) ?> ₺</b></div>
      </div>
    </div>
  </div>
</header>

<main class="wrap">
  <div class="two">
    <!-- Grafik -->
    <div class="card">
      <div class="k" style="margin-bottom:8px">TPAO Satış Fiyatı (₺)</div>
      <div class="chartWrap"><canvas id="priceChart"></canvas></div>
      <div class="foot">Kaynak: Admin panelindeki fiyat güncellemeleri (yoksa mevcut fiyat).</div>
    </div>

    <!-- Ek bilgiler -->
    <div class="card">
      <div class="k">Portföy Özeti</div>
      <div class="subgrid" style="margin-top:8px">
        <div class="card" style="background:#0f243a;border-color:#17324a">
          <div class="k">Sizin Aldığınız Fiyat</div>
          <div class="v"><?= tl($avg) ?> ₺</div>
          <div class="meta">Panelde fiyat değişse bile burada değişmez; sadece yeni alımlar gelirse ağırlıklı ortalama güncellenir.</div>
        </div>
        <div class="card" style="background:#0f243a;border-color:#17324a">
          <div class="k">Satışta Tahmini Brüt</div>
          <div class="v"><?= tl($qty*$sellP) ?> ₺</div>
          <div class="meta">Komisyon oranı: <?= rtrim(rtrim(number_format((float)$panel['fee_percent'],2,'.',''), '0'), '.') ?>%</div>
        </div>
      </div>
      <?php $totalPower = $portfolio_value + $bal; $filled = $totalPower>0 ? ($portfolio_value/$totalPower)*100 : 0; ?>
      <div style="margin-top:12px" class="k">Portföy Doluluk</div>
      <div class="bar"><i style="width:<?= number_format($filled,2,'.','') ?>%"></i></div>
      <div class="foot"><?= number_format($filled,2,',','.') ?>% portföyde, <?= number_format(100-$filled,2,',','.') ?>% nakitte.</div>
    </div>
  </div>
</main>

<!-- bottom nav — Favoriler kaldırıldı; Geçmiş -> Yatırım/Çekim (deposit.php) -->
<nav class="bottom">
  <div class="tabs">
    <a class="tab active" href="varlik.php">📈<div>Aktif Varlıklar</div></a>
    <a class="tab" href="islemler.php">💼<div>İşlemler</div></a>
    <a class="tab" href="deposit.php">💳<div>Yatırım/Çekim</div></a>
    <a class="tab" href="menu.php">≡<div>Menü</div></a>
  </div>
</nav>

<script>
  // PHP'den gelen dizi verileri
  const labels = <?= json_encode($labels) ?>;
  const data   = <?= json_encode($prices) ?>;

  const el = document.getElementById('priceChart');
  const ctx = el.getContext('2d');

  // Dinamik min/max boşluk (±%3 aralık)
  const minV = Math.min.apply(null, data);
  const maxV = Math.max.apply(null, data);
  const pad  = (maxV - minV) * 0.03;
  const sMin = minV - pad;
  const sMax = maxV + pad;

  // Gradient dolgu
  const grad = ctx.createLinearGradient(0, 0, 0, el.parentElement.clientHeight);
  grad.addColorStop(0, 'rgba(53,214,156,.35)');
  grad.addColorStop(1, 'rgba(53,214,156,0)');

  const chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Satış (₺)',
        data,
        borderWidth: 2,
        tension: 0.25,
        pointRadius: 0,
        pointHoverRadius: 4,
        fill: true,
        backgroundColor: grad
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      parsing: false,
      animation: { duration: 400 },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => `₺ ${ctx.parsed.y.toLocaleString('tr-TR',{minimumFractionDigits:2, maximumFractionDigits:2})}`
          }
        },
        decimation: { enabled: true, algorithm: 'min-max' }
      },
      interaction: { intersect: false, mode: 'index' },
      scales: {
        x: {
          grid: { color: 'rgba(255,255,255,.06)' },
          ticks: { color: '#9fb2c8', maxRotation: 0, autoSkip: true }
        },
        y: {
          suggestedMin: sMin,
          suggestedMax: sMax,
          grid: { color: 'rgba(255,255,255,.09)' },
          ticks: {
            color: '#9fb2c8',
            callback: (v)=> '₺'+v
          }
        }
      }
    }
  });

  // Boyut değişince gradienti yeniden hesapla
  new ResizeObserver(() => {
    const h = el.parentElement.clientHeight;
    const g = ctx.createLinearGradient(0, 0, 0, h);
    g.addColorStop(0, 'rgba(53,214,156,.35)');
    g.addColorStop(1, 'rgba(53,214,156,0)');
    chart.data.datasets[0].backgroundColor = g;
    chart.update('none');
  }).observe(el.parentElement);
</script>

</body>
</html>
