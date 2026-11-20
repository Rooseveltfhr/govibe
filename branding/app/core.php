<?php
/**
 * Noyau : utilitaires, base SQLite, schéma + données initiales, sécurité (sessions, CSRF,
 * limitation de débit), contenu (requêtes), uploads, e-mail.
 * Compatible PHP 7.4+. Aucune dépendance Composer.
 */
declare(strict_types=1);

if (!defined('ROOT')) define('ROOT', dirname(__DIR__));
mb_internal_encoding('UTF-8');

/* ------------------------------------------------------------ polyfills */
if (!function_exists('str_contains')) { function str_contains(string $h, string $n): bool { return $n === '' || strpos($h, $n) !== false; } }
if (!function_exists('str_starts_with')) { function str_starts_with(string $h, string $n): bool { return strncmp($h, $n, strlen($n)) === 0; } }

/* ------------------------------------------------------------ utilitaires */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return gmdate('Y-m-d H:i:s'); }
function is_https(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); }
function base_path(): string {
    static $b = null;
    if ($b === null) { $d = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/app.php')); $b = ($d === '/' || $d === '.') ? '' : rtrim($d, '/'); }
    return $b;
}
function url(string $p = ''): string { return base_path() . '/' . ltrim($p, '/'); }
function site_url(string $p = ''): string { return rtrim((string)setting('site_url', 'https://rooseveltforestal.com'), '/') . '/' . ltrim($p, '/'); }
function abs_asset(?string $p): string { if (!$p) return site_url('assets/img/og-cover.jpg'); return preg_match('#^https?://#', $p) ? $p : site_url($p); }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
function ip_hash(): string { return substr(hash('sha256', client_ip() . setting('salt', 'x')), 0, 16); }
function redirect(string $to, int $code = 302): void { header('Location: ' . $to, true, $code); exit; }
function json_out($data, int $code = 200): void { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function slugify(string $s): string {
    $s = norm($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim(substr($s, 0, 80), '-') ?: 'item';
}
/** minuscules + sans accents : sert à la recherche et aux slugs */
function norm(string $s): string {
    $s = mb_strtolower($s);
    return strtr($s, ['à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a','ç'=>'c','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ñ'=>'n','ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y','œ'=>'oe','æ'=>'ae','’'=>"'"]);
}
function trunc(string $s, int $n): string { $s = trim(preg_replace('/\s+/', ' ', strip_tags($s))); return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s; }
function fmt_date(?string $utc, string $lang = 'fr', bool $time = false): string {
    if (!$utc) return '';
    try {
        $d = new DateTime($utc, new DateTimeZone('UTC')); $d->setTimezone(new DateTimeZone((string)setting('timezone', 'America/Port-au-Prince')));
    } catch (Exception $ex) { return $utc; }
    $m = [
        'fr' => ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'],
        'ht' => ['janvye','fevriye','mas','avril','me','jen','jiyè','out','septanm','oktòb','novanm','desanm'],
        'en' => ['January','February','March','April','May','June','July','August','September','October','November','December'],
    ][$lang] ?? [];
    $s = $lang === 'en' ? $m[(int)$d->format('n') - 1] . ' ' . $d->format('j, Y') : $d->format('j') . ' ' . $m[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
    return $time ? $s . ' ' . $d->format('H:i') : $s;
}
function local_to_utc(string $local): ?string {
    $local = trim($local); if ($local === '') return null;
    try { $d = new DateTime($local, new DateTimeZone((string)setting('timezone', 'America/Port-au-Prince'))); $d->setTimezone(new DateTimeZone('UTC')); return $d->format('Y-m-d H:i:s'); }
    catch (Exception $ex) { return null; }
}
function utc_to_local_input(?string $utc): string {
    if (!$utc) return '';
    try { $d = new DateTime($utc, new DateTimeZone('UTC')); $d->setTimezone(new DateTimeZone((string)setting('timezone', 'America/Port-au-Prince'))); return $d->format('Y-m-d\TH:i'); }
    catch (Exception $ex) { return ''; }
}

/* ------------------------------------------------------------ base de données */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = ROOT . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $ptr = $dir . '/dbname.php';
    if (!is_file($ptr)) file_put_contents($ptr, "<?php return 'site_" . bin2hex(random_bytes(8)) . ".sqlite';\n");
    $name = require $ptr;
    $new = !is_file($dir . '/' . $name);
    $pdo = new PDO('sqlite:' . $dir . '/' . $name, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    migrate($pdo, $new);
    return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $st = db()->prepare($sql); $st->execute($p); return $st; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetch(PDO::FETCH_NUM); return $r ? $r[0] : null; }

function migrate(PDO $pdo, bool $fresh): void {
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= 1) return;
    $pdo->exec("
    CREATE TABLE categories(id INTEGER PRIMARY KEY, kind TEXT NOT NULL, slug TEXT NOT NULL, name TEXT NOT NULL, name_ht TEXT, name_en TEXT, sort INTEGER DEFAULT 0, UNIQUE(kind,slug));
    CREATE TABLE content(
      id INTEGER PRIMARY KEY, kind TEXT NOT NULL, type TEXT, slug TEXT NOT NULL, title TEXT NOT NULL, summary TEXT, body TEXT, extra TEXT,
      cover TEXT, category_id INTEGER, tags TEXT, status TEXT NOT NULL DEFAULT 'draft', publish_at TEXT, featured INTEGER DEFAULT 0,
      views INTEGER DEFAULT 0, seo_title TEXT, seo_desc TEXT, lang TEXT DEFAULT 'fr', video_url TEXT, youtube_id TEXT, transcript TEXT,
      search_text TEXT, title_norm TEXT, created_at TEXT, updated_at TEXT, UNIQUE(kind,slug));
    CREATE INDEX idx_content_list ON content(kind,status,publish_at);
    CREATE TABLE subscribers(id INTEGER PRIMARY KEY, email TEXT UNIQUE NOT NULL, status TEXT NOT NULL DEFAULT 'pending', token TEXT NOT NULL, lang TEXT, source TEXT, consent_at TEXT, confirmed_at TEXT, unsub_at TEXT, created_at TEXT);
    CREATE TABLE submissions(id INTEGER PRIMARY KEY, type TEXT NOT NULL, name TEXT, location TEXT, contact TEXT, category TEXT, message TEXT NOT NULL, consent INTEGER DEFAULT 0, status TEXT NOT NULL DEFAULT 'new', lang TEXT, created_at TEXT);
    CREATE TABLE stats(day TEXT NOT NULL, event TEXT NOT NULL, ref TEXT NOT NULL DEFAULT '', n INTEGER NOT NULL DEFAULT 0, PRIMARY KEY(day,event,ref));
    CREATE TABLE searches(term TEXT PRIMARY KEY, n INTEGER NOT NULL DEFAULT 0, last_at TEXT);
    CREATE TABLE settings(k TEXT PRIMARY KEY, v TEXT);
    CREATE TABLE users(id INTEGER PRIMARY KEY, email TEXT UNIQUE NOT NULL, pass TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'admin', created_at TEXT);
    CREATE TABLE throttle(k TEXT NOT NULL, t INTEGER NOT NULL);
    CREATE INDEX idx_throttle ON throttle(k,t);
    ");
    $ins = $pdo->prepare('INSERT INTO settings(k,v) VALUES(?,?)');
    foreach (['salt' => bin2hex(random_bytes(16)), 'site_url' => 'https://rooseveltforestal.com', 'timezone' => 'America/Port-au-Prince', 'admin_email' => 'mr@rooseveltforestal.com', 'mail_from' => 'mr@rooseveltforestal.com',
        'consultation_title' => '', 'consultation_question' => '', 'program_pdf' => '',
        'bio_short' => "Roosevelt Forestal est ingénieur informatique, entrepreneur et fondateur de GOVIBE Innovation Hub. Il construit en Haïti un écosystème d'innovation qui relie technologie, éducation et entrepreneuriat, avec l'ambition de le déployer dans la Caraïbe puis en Afrique.",
        'bio_long' => "Roosevelt Forestal est un ingénieur informatique haïtien, entrepreneur et leader d'innovation. Fondateur et CEO de GOVIBE Innovation Hub, il pilote un ensemble de projets couvrant l'intelligence artificielle, le développement logiciel, les plateformes SaaS, la formation et l'incubation de startups.\n\nParmi ses réalisations : Tchekela.com (plateforme de retour des documents perdus), Klasyo.org (éducation numérique), HOSPIFY (santé) et TAGTOA (plateforme NFC pour les entreprises). Il a formé des professionnels et des étudiants au sein d'ONG, d'organisations locales et d'universités, et a donné une keynote à DevExpo, grand événement technologique haïtien.\n\nSa conviction : le talent est universel, l'opportunité ne l'est pas. Son travail consiste à réduire cet écart.",
        'press_email' => 'mr@rooseveltforestal.com'] as $k => $v) $ins->execute([$k, $v]);

    $ci = $pdo->prepare('INSERT INTO categories(kind,slug,name,name_ht,name_en,sort) VALUES(?,?,?,?,?,?)');
    $themes = [
        ['education','Éducation','Edikasyon','Education'], ['jeunesse','Jeunesse','Jenès','Youth'], ['emploi-travail','Emploi et travail','Travay ak anplwa','Jobs and labour'],
        ['agriculture','Agriculture','Agrikilti','Agriculture'], ['entrepreneuriat','Entrepreneuriat','Antreprenarya','Entrepreneurship'], ['economie','Économie','Ekonomi','Economy'],
        ['technologie-ia','Technologie et IA','Teknoloji ak IA','Technology and AI'], ['sante','Santé','Sante','Health'], ['securite','Sécurité','Sekirite','Security'],
        ['justice','Justice','Jistis','Justice'], ['diaspora','Diaspora','Dyaspora','Diaspora'], ['infrastructures','Infrastructures','Enfrastrikti','Infrastructure'],
        ['developpement-territorial','Développement territorial','Devlopman teritoryal','Territorial development'], ['gouvernance','Gouvernance','Gouvènans','Governance'],
        ['modernisation-etat',"Modernisation de l'État",'Modènizasyon Leta','State modernisation'], ['droits-citoyens','Droits des citoyens','Dwa sitwayen yo',"Citizens' rights"],
    ];
    foreach ($themes as $i => $c) $ci->execute(['theme', $c[0], $c[1], $c[2], $c[3], $i]);
    $vids = [['discours','Discours','Diskou','Speeches'], ['interviews','Interviews','Entèvyou','Interviews'], ['roosevelt-explique','Roosevelt explique','Roosevelt eksplike','Roosevelt explains'],
        ['politique','Politique','Politik','Politics'], ['reformes','Réformes','Refòm','Reforms'], ['activites','Activités','Aktivite','Activities'],
        ['entrepreneuriat','Entrepreneuriat','Antreprenarya','Entrepreneurship'], ['technologie','Technologie','Teknoloji','Technology'], ['shorts','Shorts','Shorts','Shorts']];
    foreach ($vids as $i => $c) $ci->execute(['video', $c[0], $c[1], $c[2], $c[3], $i]);
    $pdo->exec('PRAGMA user_version=1');

    // Exemples en BROUILLON uniquement (jamais publics) : à remplacer par le contenu réel avant publication.
    $tid = (int)$pdo->query("SELECT id FROM categories WHERE kind='theme' AND slug='technologie-ia'")->fetchColumn();
    $extra = json_encode(['problem' => "[À COMPLÉTER] Décrire le problème identifié.", 'analysis' => "[À COMPLÉTER] Analyse du contexte et des causes.", 'solution' => "[À COMPLÉTER] Décrire la solution proposée.", 'objectives' => "- [À COMPLÉTER] Objectif 1\n- [À COMPLÉTER] Objectif 2", 'beneficiaries' => "[À COMPLÉTER]", 'impact' => "[À COMPLÉTER]", 'implementation' => "[À COMPLÉTER] Étapes, calendrier, acteurs, budget."], JSON_UNESCAPED_UNICODE);
    $now = now();
    $pdo->prepare("INSERT INTO content(kind,type,slug,title,summary,body,extra,category_id,tags,status,featured,lang,search_text,title_norm,created_at,updated_at) VALUES('proposal','proposition','exemple-proposition','EXEMPLE — Titre de la proposition','Résumé en deux phrases de la proposition. À remplacer.','',?,?,?,'draft',0,'fr','exemple proposition','exemple titre de la proposition',?,?)")
        ->execute([$extra, $tid, 'exemple', $now, $now]);
    $pdo->prepare("INSERT INTO content(kind,type,slug,title,summary,body,category_id,tags,status,featured,lang,search_text,title_norm,created_at,updated_at) VALUES('article','actualite','exemple-article','EXEMPLE — Titre de l''article','Résumé de l''article. À remplacer.',?,?,?,'draft',0,'fr','exemple article','exemple titre de l''article',?,?)")
        ->execute(["## Sous-titre\n\nTexte de l'article. Vous pouvez utiliser **gras**, *italique*, des listes et des liens.\n\n- Premier point\n- Second point", $tid, 'exemple', $now, $now]);
}

/* ------------------------------------------------------------ réglages */
function setting(string $k, $default = '') {
    static $cache = null;
    if ($cache === null) { try { $cache = []; foreach (rows('SELECT k,v FROM settings') as $r) $cache[$r['k']] = $r['v']; } catch (Throwable $e) { return $default; } }
    return $cache[$k] ?? $default;
}
function set_setting(string $k, string $v): void { q('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v', [$k, $v]); }

/* ------------------------------------------------------------ limitation de débit */
function throttle_ok(string $key, int $limit, int $window): bool {
    $t = time();
    q('DELETE FROM throttle WHERE t < ?', [$t - 86400]);
    $n = (int)val('SELECT COUNT(*) FROM throttle WHERE k=? AND t>?', [$key, $t - $window]);
    if ($n >= $limit) return false;
    q('INSERT INTO throttle(k,t) VALUES(?,?)', [$key, $t]);
    return true;
}

/* ------------------------------------------------------------ anti-spam formulaires publics */
function form_token(): string { $t = (string)time(); return $t . '.' . substr(hash_hmac('sha256', $t, (string)setting('salt')), 0, 20); }
function form_token_ok(string $tok, int $minAge = 3, int $maxAge = 7200): bool {
    $p = explode('.', $tok, 2); if (count($p) !== 2 || !ctype_digit($p[0])) return false;
    if (!hash_equals(substr(hash_hmac('sha256', $p[0], (string)setting('salt')), 0, 20), $p[1])) return false;
    $age = time() - (int)$p[0]; return $age >= $minAge && $age <= $maxAge;
}
function same_origin(): bool {
    $o = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? ''); if ($o === '') return true;
    $h = parse_url($o, PHP_URL_HOST); return !$h || strcasecmp($h, $_SERVER['HTTP_HOST'] ?? '') === 0 || preg_replace('/^www\./', '', (string)$h) === preg_replace('/^www\./', '', (string)parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
}

/* ------------------------------------------------------------ authentification admin */
function sess(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('rfadm');
    session_set_cookie_params(['lifetime' => 0, 'path' => base_path() . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
function admin_user(): ?array { sess(); return isset($_SESSION['uid']) ? row('SELECT id,email,role FROM users WHERE id=?', [$_SESSION['uid']]) : null; }
function require_admin(): array { $u = admin_user(); if (!$u) redirect(url('admin/login')); header('Cache-Control: no-store'); return $u; }
function csrf(): string { sess(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">'; }
function csrf_check(): void { sess(); if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) { http_response_code(419); exit('Session expirée. Rechargez la page.'); } }

/* ------------------------------------------------------------ contenu */
const ARTICLE_TYPES = ['actualite','communique','analyse','opinion','tribune','proposition','activite','interview','rapport','discours'];
const PROPOSAL_TYPES = ['proposition','reforme','projet-de-loi','programme','initiative'];
const PROPOSAL_FIELDS = ['problem','analysis','solution','objectives','beneficiaries','impact','implementation'];

function visible_sql(string $a = ''): string { $p = $a ? $a . '.' : ''; return "{$p}status='published' AND ({$p}publish_at IS NULL OR {$p}publish_at <= '" . now() . "')"; }
function content_search_text(array $r, array $extra, string $catName): string {
    return norm(implode(' ', [$r['title'], $r['summary'] ?? '', strip_tags((string)($r['body'] ?? '')), $r['tags'] ?? '', $catName, implode(' ', array_map('strval', $extra)), $r['transcript'] ?? '']));
}
function cat_name(array $c, string $lang): string { return ($lang === 'ht' && $c['name_ht']) ? $c['name_ht'] : (($lang === 'en' && $c['name_en']) ? $c['name_en'] : $c['name']); }
function categories(string $kind): array { return rows('SELECT * FROM categories WHERE kind=? ORDER BY sort,name', [$kind]); }
function category(int $id): ?array { return row('SELECT * FROM categories WHERE id=?', [$id]); }
function get_content(string $kind, string $slug, bool $any = false): ?array {
    $r = row('SELECT c.*, cat.name AS cat_name, cat.name_ht AS cat_ht, cat.name_en AS cat_en, cat.slug AS cat_slug FROM content c LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.kind=? AND c.slug=?' . ($any ? '' : ' AND ' . visible_sql('c')), [$kind, $slug]);
    return $r;
}
/** liste filtrée (kind, type, catégorie, recherche, tri) */
function list_content(string $kind, array $f = [], int $limit = 12, int $offset = 0, string $order = 'recent', bool &$more = false, int &$total = 0): array {
    $w = ['c.kind=?', visible_sql('c')]; $p = [$kind];
    if (!empty($f['type'])) { $w[] = 'c.type=?'; $p[] = $f['type']; }
    if (!empty($f['cat'])) { $w[] = 'cat.slug=?'; $p[] = $f['cat']; }
    if (!empty($f['featured'])) $w[] = 'c.featured=1';
    if (!empty($f['exclude'])) { $w[] = 'c.id<>?'; $p[] = (int)$f['exclude']; }
    $rank = '';
    if (!empty($f['q'])) {
        $terms = array_filter(preg_split('/\s+/', norm($f['q'])));
        foreach ($terms as $t) { $w[] = 'c.search_text LIKE ?'; $p[] = '%' . str_replace(['%', '_'], '', $t) . '%'; }
        $rank = 'CASE WHEN c.title_norm LIKE ? THEN 0 ELSE 1 END, ';
        $rankP = ['%' . norm($f['q']) . '%'];
    }
    $ord = $order === 'views' ? 'c.views DESC, c.publish_at DESC' : 'c.featured DESC, COALESCE(c.publish_at,c.created_at) DESC';
    $sqlW = implode(' AND ', $w);
    $total = (int)val("SELECT COUNT(*) FROM content c LEFT JOIN categories cat ON cat.id=c.category_id WHERE $sqlW", $p);
    $params = $p; if ($rank) $params = array_merge($params, $rankP);
    $rowsx = rows("SELECT c.*, cat.name AS cat_name, cat.name_ht AS cat_ht, cat.name_en AS cat_en, cat.slug AS cat_slug FROM content c LEFT JOIN categories cat ON cat.id=c.category_id WHERE $sqlW ORDER BY {$rank}{$ord} LIMIT " . ($limit + 1) . " OFFSET $offset", $params);
    $more = count($rowsx) > $limit;
    return array_slice($rowsx, 0, $limit);
}
function bump_views(int $id): void {
    $seen = isset($_COOKIE['rfv']) ? array_filter(explode(',', (string)$_COOKIE['rfv'])) : [];
    if (in_array((string)$id, $seen, true)) return;
    if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|preview/i', $_SERVER['HTTP_USER_AGENT'] ?? '')) return;
    q('UPDATE content SET views=views+1 WHERE id=?', [$id]);
    $seen[] = (string)$id; $seen = array_slice($seen, -30);
    setcookie('rfv', implode(',', $seen), ['expires' => time() + 43200, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => is_https()]);
}
function content_path(array $r): string { return ['proposal' => 'proposition', 'article' => 'article', 'video' => 'video'][$r['kind']] . '/' . $r['slug']; }
function extra_of(array $r): array { $x = json_decode((string)($r['extra'] ?? ''), true); return is_array($x) ? $x : []; }
function tag_list(?string $t): array { return array_values(array_filter(array_map('trim', explode(',', (string)$t)))); }
function youtube_id(string $u): string {
    $u = trim($u);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $u)) return $u;
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~', $u, $m)) return $m[1];
    return '';
}
function stat_add(string $event, string $ref = '', int $n = 1): void {
    q('INSERT INTO stats(day,event,ref,n) VALUES(?,?,?,?) ON CONFLICT(day,event,ref) DO UPDATE SET n=n+excluded.n', [gmdate('Y-m-d'), $event, $ref, $n]);
}

/* ------------------------------------------------------------ uploads (images validées, ré-encodées) */
function upload_dir(): string { $d = ROOT . '/uploads'; if (!is_dir($d)) @mkdir($d, 0755, true); return $d; }
function save_image_upload(array $f): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [null, ($f['error'] ?? 0) === UPLOAD_ERR_NO_FILE ? null : 'Échec du téléversement.'];
    if ($f['size'] > 6 * 1024 * 1024) return [null, 'Image trop lourde (6 Mo maximum).'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return [null, 'Format non accepté (JPG, PNG, WebP).'];
    $name = 'img_' . bin2hex(random_bytes(8)) . '.jpg';
    $dest = upload_dir() . '/' . $name;
    if (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring((string)file_get_contents($f['tmp_name'])))) {
        $w = imagesx($im); $h = imagesy($im); $k = min(1, 1600 / max($w, $h));
        if ($k < 1) { $im2 = imagescale($im, (int)round($w * $k), (int)round($h * $k)); if ($im2) { imagedestroy($im); $im = $im2; } }
        $bg = imagecreatetruecolor(imagesx($im), imagesy($im)); imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255)); imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
        imagejpeg($bg, $dest, 84); imagedestroy($im); imagedestroy($bg);
    } else { $name = 'img_' . bin2hex(random_bytes(8)) . ($mime === 'image/png' ? '.png' : ($mime === 'image/webp' ? '.webp' : '.jpg')); $dest = upload_dir() . '/' . $name; move_uploaded_file($f['tmp_name'], $dest); }
    return ['uploads/' . $name, null];
}

/* ------------------------------------------------------------ e-mail (tolérant : ne casse jamais la page) */
function send_mail(string $to, string $subject, string $text): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $from = (string)setting('mail_from', 'mr@rooseveltforestal.com');
    $h = "From: Roosevelt Forestal <{$from}>\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\nX-Mailer: rooseveltforestal.com";
    try { return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $h); } catch (Throwable $e) { return false; }
}
