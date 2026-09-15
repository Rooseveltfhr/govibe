<?php

/**
 * LOUVIA — Paj kolèk nòt vokal (depi telefòn).
 *
 * Pwoblèm sa a rezoud: ou sou telefòn ou, nòt vokal yo nan WhatsApp, epi
 * VPS la lòt bò. SFTP pou 100 fichye se yon soufrans, epi ranpli yon CSV
 * sou yon telefòn se pi mal toujou.
 *
 * Yon sèl fichye. Zewo depandans. Li kouri sou sèvè web ki deja la a.
 *
 * ENSTALASYON (sou VPS la) :
 *   1. Mete fichye sa a yon kote sèvè web la sèvi.
 *   2. Chanje TOKEN an anba a pou yon bagay ki lakay ou.
 *   3. Louvri : https://sit-ou.com/kolekte.php?k=TOKEN-OU-A
 *   4. Lè kolèk la fini : EFASE FICHYE A.
 *
 * SEKIRITE : yon paj ki aksepte fichye sou entènèt se yon risk. Se poutèt sa
 * gen yon token obligatwa, yon lis ekstansyon fèmen, yon limit gwosè, epi
 * dosye depo a resevwa yon .htaccess ki bloke tout egzekisyon.
 */

// ─────────────────────────────────────────────────────────────────────────────
// KONFIGIRASYON — chanje TOKEN an anvan ou mete l an liy
// ─────────────────────────────────────────────────────────────────────────────

const TOKEN = 'chanje-token-sa-a-kounye-a';
const CLIPS_DIR = __DIR__.'/clips';
const MANIFEST = __DIR__.'/manifest.csv';
const MAX_BYTES = 25 * 1024 * 1024; // 25 Mo
const ALLOWED = ['opus', 'ogg', 'oga', 'm4a', 'mp3', 'wav', 'aac', 'amr'];

// Objektif estratifikasyon (soti nan pwotokòl la).
const TARGETS = [
    'intent' => ['order' => 25, 'price' => 20, 'booking' => 20, 'info' => 20, 'complaint' => 15],
    'region' => ['ouest' => 40, 'nord' => 20, 'sud' => 20, 'artibonite' => 20],
    'noise' => ['kalm' => 30, 'mwayen' => 40, 'fo' => 30],
    'gender' => ['f' => 50, 'm' => 50],
];

const COLUMNS = [
    'file', 'intent', 'item', 'qty', 'date', 'time', 'person', 'phone', 'notes',
    'region', 'gender', 'noise', 'duration_s', 'code_switch', 'transcript_ref',
];

// ─────────────────────────────────────────────────────────────────────────────

if (! hash_equals(TOKEN, (string) ($_GET['k'] ?? ''))) {
    http_response_code(403);
    exit('403');
}

/** Anpeche nenpòt egzekisyon nan dosye depo a. */
function protectDir(string $dir): void
{
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $htaccess = $dir.'/.htaccess';
    if (! file_exists($htaccess)) {
        file_put_contents($htaccess, "php_flag engine off\nDeny from all\n");
    }
}

/** @return list<array<string, string>> */
function readManifest(): array
{
    if (! file_exists(MANIFEST)) {
        return [];
    }

    $rows = [];
    $handle = fopen(MANIFEST, 'r');
    $header = fgetcsv($handle);

    if ($header === false) {
        fclose($handle);

        return [];
    }

    while (($line = fgetcsv($handle)) !== false) {
        if ($line === [null] || ($line[0] ?? '') === '' || str_starts_with((string) $line[0], '#')) {
            continue;
        }
        $rows[] = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), ''));
    }

    fclose($handle);

    return $rows;
}

function appendManifest(array $row): void
{
    $isNew = ! file_exists(MANIFEST) || filesize(MANIFEST) === 0;
    $handle = fopen(MANIFEST, 'a');

    if ($isNew) {
        fputcsv($handle, COLUMNS);
    }

    fputcsv($handle, array_map(fn (string $c): string => (string) ($row[$c] ?? ''), COLUMNS));
    fclose($handle);
}

/** Konte konbyen clip ou genyen pa valè, pou yon dimansyon. */
function tally(array $rows, string $key): array
{
    $counts = [];
    foreach ($rows as $row) {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value !== '') {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
    }

    return $counts;
}

// ─────────────────────────────────────────────────────────────────────────────
// DEPO
// ─────────────────────────────────────────────────────────────────────────────

$flash = null;
$flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    protectDir(CLIPS_DIR);

    $upload = $_FILES['clip'] ?? null;

    if (! $upload || $upload['error'] !== UPLOAD_ERR_OK) {
        $flash = 'Fichye a pa rive. Eseye ankò (limit 25 Mo).';
        $flashType = 'err';
    } else {
        $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));

        if (! in_array($extension, ALLOWED, true)) {
            $flash = "Fòma « {$extension} » pa aksepte. Sèvi ak .opus, .ogg, .m4a oswa .mp3.";
            $flashType = 'err';
        } elseif ($upload['size'] > MAX_BYTES) {
            $flash = 'Fichye a twò gwo (limit 25 Mo).';
            $flashType = 'err';
        } elseif (trim((string) ($_POST['intent'] ?? '')) === '') {
            $flash = 'Chwazi yon entansyon anvan.';
            $flashType = 'err';
        } else {
            $next = count(readManifest()) + 1;
            $name = sprintf('n%03d.%s', $next, $extension);

            if (! move_uploaded_file($upload['tmp_name'], CLIPS_DIR.'/'.$name)) {
                $flash = 'Pa ka sove fichye a — verifye dwa ekriti sou dosye clips/.';
                $flashType = 'err';
            } else {
                appendManifest([
                    'file' => $name,
                    'intent' => trim((string) $_POST['intent']),
                    'item' => trim((string) ($_POST['item'] ?? '')),
                    'qty' => trim((string) ($_POST['qty'] ?? '')),
                    'date' => trim((string) ($_POST['date'] ?? '')),
                    'time' => trim((string) ($_POST['time'] ?? '')),
                    'region' => trim((string) ($_POST['region'] ?? '')),
                    'gender' => trim((string) ($_POST['gender'] ?? '')),
                    'noise' => trim((string) ($_POST['noise'] ?? '')),
                    'code_switch' => trim((string) ($_POST['code_switch'] ?? 'non')),
                    'transcript_ref' => trim((string) ($_POST['transcript_ref'] ?? '')),
                ]);

                $flash = "✓ {$name} anrejistre.";
            }
        }
    }
}

$rows = readManifest();
$total = count($rows);
$tallies = [];
foreach (TARGETS as $key => $targets) {
    $tallies[$key] = tally($rows, $key);
}

// Kenbe dènye chwa yo pou pwochen clip la — machann nan souvan menm rejyon.
$lastRegion = $rows ? ($rows[$total - 1]['region'] ?? '') : '';
$lastNoise = $rows ? ($rows[$total - 1]['noise'] ?? '') : '';

?><!doctype html>
<html lang="ht">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Kolèk nòt vokal — LOUVIA</title>
<style>
  :root {
    --bg:#F5F7FA; --card:#fff; --soft:#EBF0F6; --rule:#D9E1EA;
    --ink:#0C1017; --dim:#57647A; --blue:#0B58CC; --green:#0B7A44;
    --amber:#8F6208; --red:#AC2E26;
  }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#080C11; --card:#101720; --soft:#18202B; --rule:#26303D;
      --ink:#E5ECF4; --dim:#8998AB; --blue:#6AA5FF; --green:#4ECA8A;
      --amber:#E0A63E; --red:#EF8279; }
  }
  * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
  body { margin:0; background:var(--bg); color:var(--ink);
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    font-size:16px; line-height:1.55; padding-bottom:env(safe-area-inset-bottom); }
  .wrap { max-width:560px; margin:0 auto; padding:1rem .9rem 3rem; }
  h1 { font-size:1.35rem; margin:0 0 .15rem; letter-spacing:-.02em; }
  .sub { color:var(--dim); font-size:.85rem; margin:0 0 1rem; }

  .card { background:var(--card); border:1px solid var(--rule); border-radius:12px;
    padding:1rem; margin-bottom:.85rem; }

  .flash { border-radius:10px; padding:.7rem .9rem; margin-bottom:.85rem;
    font-size:.92rem; font-weight:600; }
  .flash.ok { background:color-mix(in srgb,var(--green) 16%,transparent); color:var(--green); }
  .flash.err { background:color-mix(in srgb,var(--red) 15%,transparent); color:var(--red); }

  .count { display:flex; align-items:baseline; gap:.5rem; margin-bottom:.7rem; }
  .count b { font-size:2.1rem; font-weight:700; letter-spacing:-.03em;
    font-variant-numeric:tabular-nums; }
  .count span { color:var(--dim); font-size:.85rem; }

  .prog { height:8px; background:var(--soft); border-radius:99px; overflow:hidden; margin-bottom:1rem; }
  .prog i { display:block; height:100%; background:var(--green); border-radius:99px; }

  .grp { margin-bottom:.9rem; }
  .grp > label, .lbl { display:block; font-size:.78rem; font-weight:650;
    color:var(--dim); text-transform:uppercase; letter-spacing:.06em; margin-bottom:.4rem; }

  .chips { display:flex; flex-wrap:wrap; gap:.4rem; }
  .chips input { position:absolute; opacity:0; pointer-events:none; }
  .chips label { border:1.5px solid var(--rule); border-radius:99px;
    padding:.55rem .85rem; font-size:.9rem; cursor:pointer; user-select:none;
    display:flex; align-items:center; gap:.35rem; background:var(--card); }
  .chips input:checked + label { border-color:var(--blue); background:var(--blue); color:#fff; font-weight:600; }
  .chips label .need { font-size:.68rem; opacity:.6; font-variant-numeric:tabular-nums; }
  .chips input:checked + label .need { opacity:.85; }
  .chips label.full { opacity:.5; }

  input[type=file] { width:100%; padding:.9rem; border:2px dashed var(--rule);
    border-radius:10px; background:var(--soft); font-size:.9rem; }
  input[type=text], input[type=number], textarea {
    width:100%; padding:.7rem .8rem; border:1.5px solid var(--rule); border-radius:9px;
    font-size:1rem; font-family:inherit; background:var(--card); color:var(--ink); }
  textarea { min-height:70px; resize:vertical; }
  input:focus, textarea:focus { outline:none; border-color:var(--blue); }

  .two { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }

  button { width:100%; padding:1rem; border:0; border-radius:11px;
    background:var(--blue); color:#fff; font-size:1.05rem; font-weight:650;
    font-family:inherit; cursor:pointer; }
  button:active { transform:scale(.99); }

  details { margin-top:.3rem; }
  details summary { font-size:.85rem; color:var(--blue); cursor:pointer; padding:.4rem 0; }

  .tally { display:flex; flex-direction:column; gap:.55rem; }
  .trow { display:grid; grid-template-columns:5.5rem 1fr 3rem; gap:.5rem; align-items:center; font-size:.82rem; }
  .trow .tk { color:var(--dim); }
  .trow .tt { height:6px; background:var(--soft); border-radius:99px; overflow:hidden; }
  .trow .tt i { display:block; height:100%; border-radius:99px; background:var(--blue); }
  .trow .tt i.done { background:var(--green); }
  .trow .tn { text-align:right; font-variant-numeric:tabular-nums; color:var(--dim); font-size:.78rem; }

  .hint { font-size:.82rem; color:var(--dim); margin-top:.5rem; }
  .warn { border-left:3px solid var(--amber); padding-left:.7rem; font-size:.85rem; color:var(--dim); }
</style>
</head>
<body>
<div class="wrap">

  <h1>Kolèk nòt vokal</h1>
  <p class="sub">Yon clip alafwa. ~15 segond pa clip.</p>

  <?php if ($flash !== null) { ?>
    <div class="flash <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
  <?php } ?>

  <div class="card">
    <div class="count"><b><?= $total ?></b><span>/ 100 clip</span></div>
    <div class="prog"><i style="width:<?= min(100, $total) ?>%"></i></div>

    <div class="tally">
      <?php foreach (TARGETS as $key => $targets) { ?>
        <?php foreach ($targets as $value => $target) {
            $have = $tallies[$key][$value] ?? 0;
            $pct = min(100, (int) round($have / $target * 100));
            ?>
          <div class="trow">
            <span class="tk"><?= htmlspecialchars($value) ?></span>
            <span class="tt"><i class="<?= $have >= $target ? 'done' : '' ?>" style="width:<?= $pct ?>%"></i></span>
            <span class="tn"><?= $have ?>/<?= $target ?></span>
          </div>
        <?php } ?>
        <div style="height:.35rem"></div>
      <?php } ?>
    </div>
    <p class="hint">Bar vèt = objektif la ranpli. Konsantre sou sa ki poko plen.</p>
  </div>

  <form method="post" enctype="multipart/form-data" class="card">

    <div class="grp">
      <span class="lbl">1 · Nòt vokal la</span>
      <input type="file" name="clip" accept="audio/*,.opus,.ogg,.m4a,.amr" required>
      <p class="hint">Nan WhatsApp : peze lontan sou nòt la → Transfere → Sove nan Fichye.</p>
    </div>

    <div class="grp">
      <span class="lbl">2 · Kisa moun nan mande</span>
      <div class="chips">
        <?php foreach (TARGETS['intent'] as $value => $target) {
            $have = $tallies['intent'][$value] ?? 0; ?>
          <input type="radio" name="intent" id="i-<?= $value ?>" value="<?= $value ?>" required>
          <label for="i-<?= $value ?>" class="<?= $have >= $target ? 'full' : '' ?>">
            <?= ['order' => 'Kòmand', 'price' => 'Pri', 'booking' => 'Randevou',
                'info' => 'Enfòmasyon', 'complaint' => 'Plent'][$value] ?>
            <span class="need"><?= $have ?>/<?= $target ?></span>
          </label>
        <?php } ?>
      </div>
    </div>

    <div class="grp">
      <span class="lbl">3 · Kote moun nan soti</span>
      <div class="chips">
        <?php foreach (TARGETS['region'] as $value => $target) {
            $have = $tallies['region'][$value] ?? 0; ?>
          <input type="radio" name="region" id="r-<?= $value ?>" value="<?= $value ?>"
                 <?= $value === $lastRegion ? 'checked' : '' ?> required>
          <label for="r-<?= $value ?>" class="<?= $have >= $target ? 'full' : '' ?>">
            <?= ['ouest' => 'Lwès', 'nord' => 'Nò', 'sud' => 'Sid',
                'artibonite' => 'Atibonit'][$value] ?>
            <span class="need"><?= $have ?>/<?= $target ?></span>
          </label>
        <?php } ?>
      </div>
    </div>

    <div class="two">
      <div class="grp">
        <span class="lbl">4 · Vwa</span>
        <div class="chips">
          <input type="radio" name="gender" id="g-f" value="f" required>
          <label for="g-f">Fanm</label>
          <input type="radio" name="gender" id="g-m" value="m">
          <label for="g-m">Gason</label>
        </div>
      </div>
      <div class="grp">
        <span class="lbl">5 · Bri</span>
        <div class="chips">
          <?php foreach (['kalm' => 'Kalm', 'mwayen' => 'Mwayen', 'fo' => 'Fò'] as $value => $label) { ?>
            <input type="radio" name="noise" id="n-<?= $value ?>" value="<?= $value ?>"
                   <?= $value === $lastNoise ? 'checked' : '' ?> required>
            <label for="n-<?= $value ?>"><?= $label ?></label>
          <?php } ?>
        </div>
      </div>
    </div>

    <div class="grp">
      <span class="lbl">6 · Èske li melanje ak fransè/anglè?</span>
      <div class="chips">
        <input type="radio" name="code_switch" id="cs-non" value="non" checked>
        <label for="cs-non">Non, kreyòl sèl</label>
        <input type="radio" name="code_switch" id="cs-wi" value="wi">
        <label for="cs-wi">Wi, melanje</label>
      </div>
    </div>

    <details>
      <summary>Detay anplis (opsyonèl men itil)</summary>
      <div style="padding-top:.6rem;display:flex;flex-direction:column;gap:.75rem">
        <div class="two">
          <div><span class="lbl">Kisa li mande</span><input type="text" name="item" placeholder="poul, diri…"></div>
          <div><span class="lbl">Konbyen</span><input type="number" name="qty" min="1" placeholder="2"></div>
        </div>
        <div class="two">
          <div><span class="lbl">Ki dat</span><input type="text" name="date" placeholder="25"></div>
          <div><span class="lbl">Ki lè</span><input type="text" name="time" placeholder="10"></div>
        </div>
        <div>
          <span class="lbl">Sa moun nan di vre</span>
          <textarea name="transcript_ref" placeholder="mwen ta renmen de poul ak yon diri kole"></textarea>
          <p class="hint">Ranpli sa a sou omwen 30 clip — se li ki pèmèt konpare founisè yo.</p>
        </div>
      </div>
    </details>

    <button type="submit">Anrejistre clip la</button>
  </form>

  <div class="card">
    <p class="warn">
      <b>Pa bay moun yon tèks pou yo li.</b> Yon moun k ap li pale dousman epi klè —
      sistèm nan bay yon bon nòt ki fo. Di senpleman : « voye yon nòt tankou ou ta fè l tout bon ».
    </p>
    <p class="warn" style="margin-top:.6rem">
      <b>Efase fichye sa a lè kolèk la fini.</b> Yon paj ki aksepte fichye sou entènèt
      pa dwe rete louvri.
    </p>
  </div>

</div>
</body>
</html>
