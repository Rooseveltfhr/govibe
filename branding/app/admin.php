<?php
/** Administration sécurisée : contenus, catégories, citoyens, abonnés, programme PDF, réglages, statistiques. */
declare(strict_types=1);

function a_layout(string $title, string $body, string $active = ''): void {
    http_response_code(200); header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Frame-Options: DENY'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
    $nav = ['' => ['', 'Tableau de bord'], 'content|article' => ['content?kind=article', 'Articles'], 'content|proposal' => ['content?kind=proposal', 'Propositions'], 'content|video' => ['content?kind=video', 'Vidéos'], 'categories' => ['categories', 'Catégories'], 'submissions' => ['submissions', 'Espace citoyen'], 'subscribers' => ['subscribers', 'Newsletter'], 'program' => ['program', 'Programme PDF'], 'settings' => ['settings', 'Réglages']];
    $n = ''; foreach ($nav as $key => [$p, $l]) $n .= '<a href="' . e(url('admin/' . $p)) . '" class="' . ($active === $key ? 'on' : '') . '">' . e($l) . '</a>';
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . e($title) . ' — Administration</title><link rel="icon" href="' . e(url('assets/img/favicon.svg')) . '"><link rel="stylesheet" href="' . e(url('assets/css/admin.css')) . '?v=' . e(version()) . '"></head><body class="adm">';
    if ($active !== 'login') echo '<aside class="side"><div class="logo">RF <small>Admin</small></div><nav>' . $n . '</nav><div class="side-foot"><a href="' . e(url('index.html')) . '" target="_blank">Voir le site ↗</a><form method="post" action="' . e(url('admin/logout')) . '">' . csrf_field() . '<button>Déconnexion</button></form></div></aside>';
    echo '<main class="adm-main' . ($active === 'login' ? ' center' : '') . '"><h1>' . e($title) . '</h1>' . $body . '</main></body></html>'; exit;
}
function a_flash(string $m = '', string $kind = 'ok'): string { sess(); if ($m !== '') { $_SESSION['flash'] = [$m, $kind]; return ''; } if (empty($_SESSION['flash'])) return ''; [$t, $k] = $_SESSION['flash']; unset($_SESSION['flash']); return '<div class="alert ' . $k . '">' . e($t) . '</div>'; }
function a_post(): void { if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; } csrf_check(); }
function a_status_label(array $r): string {
    if ($r['status'] === 'published') return $r['publish_at'] && $r['publish_at'] > now() ? '<span class="b sch">Programmé</span>' : '<span class="b pub">Publié</span>';
    return $r['status'] === 'archived' ? '<span class="b arc">Archivé</span>' : '<span class="b dra">Brouillon</span>';
}

function admin_dispatch(array $seg): void {
    $a = $seg[0] ?? ''; $b = $seg[1] ?? '';
    if ($a === 'login') { a_login(); return; }
    if ($a === 'setup') { a_setup(); return; }
    if ($a === 'logout') { a_post(); sess(); $_SESSION = []; session_destroy(); redirect(url('admin/login')); }
    if ((int)val('SELECT COUNT(*) FROM users') === 0) redirect(url('admin/setup'));
    $u = require_admin();
    switch ($a) {
        case '': a_dashboard(); return;
        case 'content': if ($b === 'edit') a_content_edit(); elseif ($b === 'save') a_content_save(); elseif ($b === 'delete') a_content_delete(); elseif ($b === 'toggle') a_content_toggle(); else a_content_list(); return;
        case 'categories': a_categories(); return;
        case 'submissions': a_submissions($b); return;
        case 'subscribers': a_subscribers($b); return;
        case 'program': a_program(); return;
        case 'settings': a_settings($u); return;
    }
    a_layout('Introuvable', '<p>Page inconnue.</p>');
}

/* ---------------------------------------------------------------- installation + connexion */
function a_setup(): void {
    if ((int)val('SELECT COUNT(*) FROM users') > 0) redirect(url('admin/login'));
    $f = ROOT . '/data/SETUP-CODE.txt'; if (!is_file($f)) file_put_contents($f, bin2hex(random_bytes(6)));
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = trim((string)file_get_contents($f)); $email = strtolower(trim((string)($_POST['email'] ?? ''))); $pw = (string)($_POST['password'] ?? '');
        if (!throttle_ok('setup:' . ip_hash(), 8, 900)) $err = 'Trop de tentatives.';
        elseif (!hash_equals($code, trim((string)($_POST['code'] ?? '')))) $err = 'Code d\'installation incorrect.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'E-mail invalide.';
        elseif (strlen($pw) < 10) $err = 'Mot de passe : 10 caractères minimum.';
        else {
            q('INSERT INTO users(email,pass,role,created_at) VALUES(?,?,?,?)', [$email, password_hash($pw, PASSWORD_DEFAULT), 'admin', now()]);
            @unlink($f); sess(); session_regenerate_id(true); $_SESSION['uid'] = (int)db()->lastInsertId(); redirect(url('admin'));
        }
    }
    a_layout('Installation', ($err ? '<div class="alert err">' . e($err) . '</div>' : '') . '<div class="card"><p>Créez le compte administrateur. Le <b>code d\'installation</b> se trouve dans le fichier <code>data/SETUP-CODE.txt</code> de votre hébergement (File Manager). Il est supprimé automatiquement après l\'installation.</p><form method="post"><label>Code d\'installation<input name="code" required autocomplete="off"></label><label>E-mail administrateur<input type="email" name="email" required></label><label>Mot de passe (10 caractères minimum)<input type="password" name="password" required minlength="10" autocomplete="new-password"></label><button class="btn">Créer le compte</button></form></div>', 'login');
}
function a_login(): void {
    if ((int)val('SELECT COUNT(*) FROM users') === 0) redirect(url('admin/setup'));
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = strtolower(trim((string)($_POST['email'] ?? ''))); $pw = (string)($_POST['password'] ?? '');
        if (!throttle_ok('login:' . ip_hash(), 10, 900) || !throttle_ok('login:' . sha1($email), 8, 900)) $err = 'Trop de tentatives. Réessayez dans 15 minutes.';
        else {
            $u = row('SELECT * FROM users WHERE email=?', [$email]);
            if ($u && password_verify($pw, $u['pass'])) { sess(); session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; redirect(url('admin')); }
            usleep(400000); $err = 'Identifiants incorrects.';
        }
    }
    a_layout('Connexion', ($err ? '<div class="alert err">' . e($err) . '</div>' : '') . '<div class="card"><form method="post"><label>E-mail<input type="email" name="email" required autocomplete="username"></label><label>Mot de passe<input type="password" name="password" required autocomplete="current-password"></label><button class="btn">Se connecter</button></form></div>', 'login');
}

/* ---------------------------------------------------------------- tableau de bord + statistiques */
function a_dashboard(): void {
    $sum = function (string $ev, int $days = 0): int { return (int)val('SELECT COALESCE(SUM(n),0) FROM stats WHERE event=?' . ($days ? ' AND day>=?' : ''), $days ? [$ev, gmdate('Y-m-d', strtotime("-$days days"))] : [$ev]); };
    $card = fn($label, $v, $sub = '') => '<div class="stat"><b>' . e((string)$v) . '</b><span>' . e($label) . '</span>' . ($sub ? '<small>' . e($sub) . '</small>' : '') . '</div>';
    $net = []; foreach (rows("SELECT ref, SUM(n) n FROM stats WHERE event='share_click' GROUP BY ref") as $r) { $ref = (string)$r['ref']; $name = strpos($ref, 'campaign:') === 0 ? substr($ref, 9) : explode('|', $ref)[0]; $net[$name] = ($net[$name] ?? 0) + (int)$r['n']; }
    arsort($net);
    $body = a_flash() . '<div class="stats">'
        . $card('Visites page campagne', $sum('campaign_view'), '30 j : ' . $sum('campaign_view', 30)) . $card('dont via QR code', (int)val("SELECT COALESCE(SUM(n),0) FROM stats WHERE event='campaign_view' AND ref='qr'"))
        . $card('Flyers générés', $sum('flyer_generated')) . $card('Téléchargements de flyers', $sum('flyer_download'))
        . $card('Clics de partage (total)', array_sum($net)) . $card('Clics vers le programme', $sum('program_click')) . $card('Programme PDF téléchargé', $sum('program_pdf_dl') + $sum('program_pdf'))
        . $card('Inscrits newsletter', (int)val("SELECT COUNT(*) FROM subscribers WHERE status='confirmed'"), 'en attente : ' . (int)val("SELECT COUNT(*) FROM subscribers WHERE status='pending'"))
        . $card('Messages citoyens à lire', (int)val("SELECT COUNT(*) FROM submissions WHERE status='new'"), 'total : ' . (int)val('SELECT COUNT(*) FROM submissions'))
        . $card('Recherches', (int)val('SELECT COALESCE(SUM(n),0) FROM searches')) . '</div>';
    $body .= '<div class="cols"><div class="card"><h2>Partages par réseau</h2>' . ($net ? '<table>' : '<p class="muted">Aucune donnée.</p>');
    foreach ($net as $k => $v) $body .= '<tr><td>' . e($k) . '</td><td class="r">' . $v . '</td></tr>'; if ($net) $body .= '</table>';
    $body .= '</div><div class="card"><h2>Recherches les plus fréquentes</h2>';
    $s = rows('SELECT term,n FROM searches ORDER BY n DESC, last_at DESC LIMIT 10'); if (!$s) $body .= '<p class="muted">Aucune recherche.</p>'; else { $body .= '<table>'; foreach ($s as $r) $body .= '<tr><td>' . e($r['term']) . '</td><td class="r">' . (int)$r['n'] . '</td></tr>'; $body .= '</table>'; }
    $body .= '</div></div><div class="cols3">';
    foreach (['article' => 'Articles les plus consultés', 'proposal' => 'Propositions les plus consultées', 'video' => 'Vidéos les plus consultées'] as $k => $lbl) {
        $body .= '<div class="card"><h2>' . e($lbl) . '</h2>'; $t = rows('SELECT id,title,views FROM content WHERE kind=? AND views>0 ORDER BY views DESC LIMIT 5', [$k]);
        if (!$t) $body .= '<p class="muted">Aucune vue.</p>'; else { $body .= '<table>'; foreach ($t as $r) $body .= '<tr><td><a href="' . e(url('admin/content/edit?id=' . $r['id'])) . '">' . e(trunc($r['title'], 50)) . '</a></td><td class="r">' . (int)$r['views'] . '</td></tr>'; $body .= '</table>'; }
        $body .= '</div>';
    }
    $body .= '</div><div class="card"><h2>14 derniers jours (campagne)</h2><table><tr><th>Jour</th><th class="r">Visites</th><th class="r">Flyers</th><th class="r">Téléchargements</th></tr>';
    for ($i = 0; $i < 14; $i++) { $d = gmdate('Y-m-d', strtotime("-$i days")); $g = fn($ev) => (int)val('SELECT COALESCE(SUM(n),0) FROM stats WHERE day=? AND event=?', [$d, $ev]); $body .= '<tr><td>' . $d . '</td><td class="r">' . $g('campaign_view') . '</td><td class="r">' . $g('flyer_generated') . '</td><td class="r">' . $g('flyer_download') . '</td></tr>'; }
    $body .= '</table><p class="muted">Mesures anonymes : aucun nom, aucune photo ni adresse IP n\'est enregistré. Les photos des flyers ne quittent jamais l\'appareil du visiteur.</p></div>';
    a_layout('Tableau de bord', $body, '');
}

/* ---------------------------------------------------------------- contenus */
function a_content_list(): void {
    $kind = in_array($_GET['kind'] ?? '', ['article', 'proposal', 'video'], true) ? $_GET['kind'] : 'article';
    $st = (string)($_GET['status'] ?? ''); $qv = trim((string)($_GET['q'] ?? ''));
    $w = ['c.kind=?']; $p = [$kind];
    if (in_array($st, ['draft', 'published', 'archived'], true)) { $w[] = 'c.status=?'; $p[] = $st; }
    if ($qv !== '') { $w[] = 'c.search_text LIKE ?'; $p[] = '%' . norm($qv) . '%'; }
    $items = rows('SELECT c.*, cat.name AS cat_name FROM content c LEFT JOIN categories cat ON cat.id=c.category_id WHERE ' . implode(' AND ', $w) . ' ORDER BY c.updated_at DESC LIMIT 300', $p);
    $names = ['article' => 'Articles', 'proposal' => 'Propositions', 'video' => 'Vidéos'];
    $body = a_flash() . '<div class="bar"><a class="btn" href="' . e(url('admin/content/edit?kind=' . $kind)) . '">+ Nouveau</a><form class="inl" method="get"><input type="hidden" name="kind" value="' . $kind . '"><select name="status" onchange="this.form.submit()"><option value="">Tous les statuts</option>';
    foreach (['draft' => 'Brouillons', 'published' => 'Publiés', 'archived' => 'Archivés'] as $k => $l) $body .= '<option value="' . $k . '"' . ($st === $k ? ' selected' : '') . '>' . $l . '</option>';
    $body .= '</select><input name="q" value="' . e($qv) . '" placeholder="Rechercher…"><button class="btn ghost">OK</button></form></div><div class="card"><table><tr><th>Titre</th><th>Type</th><th>Catégorie</th><th>Statut</th><th>Date</th><th class="r">Vues</th><th></th></tr>';
    if (!$items) $body .= '<tr><td colspan="7" class="muted">Aucun contenu.</td></tr>';
    foreach ($items as $r) {
        $pub = $r['status'] === 'published';
        $body .= '<tr><td><a href="' . e(url('admin/content/edit?id=' . $r['id'])) . '"><b>' . e(trunc($r['title'], 70)) . '</b></a>' . ($r['featured'] ? ' <span title="À la une">★</span>' : '') . '</td><td>' . e($r['kind'] === 'video' ? '—' : type_label((string)$r['type'])) . '</td><td>' . e((string)$r['cat_name']) . '</td><td>' . a_status_label($r) . '</td><td>' . e(fmt_date($r['publish_at'] ?: $r['created_at'], 'fr')) . '</td><td class="r">' . (int)$r['views'] . '</td><td class="act"><a href="' . e(url(content_path($r))) . '" target="_blank">Voir</a>';
        $body .= '<form method="post" action="' . e(url('admin/content/toggle')) . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $r['id'] . '"><input type="hidden" name="kind" value="' . $kind . '"><button name="do" value="feature">' . ($r['featured'] ? '☆' : '★') . '</button><button name="do" value="' . ($pub ? 'draft' : 'publish') . '">' . ($pub ? 'Dépublier' : 'Publier') . '</button><button name="do" value="archive">Archiver</button></form></td></tr>';
    }
    a_layout($names[$kind], $body . '</table></div>', 'content|' . $kind);
}
function a_content_edit(): void {
    $id = (int)($_GET['id'] ?? 0); $r = $id ? row('SELECT * FROM content WHERE id=?', [$id]) : null;
    $kind = $r ? $r['kind'] : (in_array($_GET['kind'] ?? '', ['article', 'proposal', 'video'], true) ? $_GET['kind'] : 'article');
    $r = $r ?: ['id' => 0, 'kind' => $kind, 'type' => $kind === 'proposal' ? 'proposition' : 'actualite', 'slug' => '', 'title' => '', 'summary' => '', 'body' => '', 'extra' => '', 'cover' => '', 'category_id' => 0, 'tags' => '', 'status' => 'draft', 'publish_at' => null, 'featured' => 0, 'seo_title' => '', 'seo_desc' => '', 'lang' => 'fr', 'video_url' => '', 'youtube_id' => '', 'transcript' => ''];
    $x = extra_of($r); $err = $_SESSION['form_err'] ?? ''; unset($_SESSION['form_err']);
    $sel = function (string $name, array $opts, $cur) { $h = '<select name="' . $name . '">'; foreach ($opts as $v => $l) $h .= '<option value="' . e((string)$v) . '"' . ((string)$cur === (string)$v ? ' selected' : '') . '>' . e($l) . '</option>'; return $h . '</select>'; };
    $catOpts = [0 => '— Aucune —']; foreach (categories($kind === 'video' ? 'video' : 'theme') as $c) $catOpts[$c['id']] = $c['name'];
    $f = a_flash() . ($err ? '<div class="alert err">' . e($err) . '</div>' : '') . '<form method="post" enctype="multipart/form-data" action="' . e(url('admin/content/save')) . '" class="card edit">' . csrf_field() . '<input type="hidden" name="id" value="' . $r['id'] . '"><input type="hidden" name="kind" value="' . $kind . '">';
    $f .= '<label>Titre *<input name="title" required maxlength="200" value="' . e($r['title']) . '"></label>';
    $f .= '<div class="g2"><label>Adresse (URL permanente)<input name="slug" maxlength="80" value="' . e($r['slug']) . '" placeholder="auto à partir du titre"></label>';
    if ($kind !== 'video') $f .= '<label>Type' . $sel('type', array_combine($kind === 'proposal' ? PROPOSAL_TYPES : ARTICLE_TYPES, array_map(fn($t) => type_label($t), $kind === 'proposal' ? PROPOSAL_TYPES : ARTICLE_TYPES)), $r['type']) . '</label>'; else $f .= '<span></span>';
    $f .= '</div><div class="g2"><label>Catégorie' . $sel('category_id', $catOpts, $r['category_id']) . '</label><label>Mots-clés (séparés par des virgules)<input name="tags" value="' . e((string)$r['tags']) . '"></label></div>';
    if ($kind === 'video') $f .= '<label>Lien YouTube * <small>(watch, youtu.be, shorts…)</small><input name="video_url" value="' . e((string)$r['video_url']) . '" placeholder="https://www.youtube.com/watch?v=…"></label>';
    else $f .= '<label>Vidéo YouTube (facultatif)<input name="video_url" value="' . e((string)$r['video_url']) . '"></label>';
    $f .= '<label>Résumé <small>(affiché dans les listes et le partage)</small><textarea name="summary" rows="3" maxlength="400">' . e((string)$r['summary']) . '</textarea></label>';
    if ($kind === 'proposal') {
        $lab = ['problem' => 'Problème identifié', 'analysis' => 'Analyse', 'solution' => 'Solution proposée', 'objectives' => 'Objectifs', 'beneficiaries' => 'Bénéficiaires', 'impact' => 'Impact attendu', 'implementation' => 'Mise en œuvre'];
        foreach (PROPOSAL_FIELDS as $k) $f .= '<label>' . $lab[$k] . '<textarea name="x_' . $k . '" rows="' . (in_array($k, ['problem', 'analysis', 'solution'], true) ? 6 : 4) . '">' . e((string)($x[$k] ?? '')) . '</textarea></label>';
        $f .= '<label>Contenu additionnel (facultatif)<textarea name="body" rows="5">' . e((string)$r['body']) . '</textarea></label>';
    } else {
        $f .= '<label>' . ($kind === 'video' ? 'Description' : 'Contenu') . ' <small>(Markdown : ## titre, **gras**, *italique*, - liste, [lien](https://…))</small><textarea name="body" rows="' . ($kind === 'video' ? 6 : 16) . '">' . e((string)$r['body']) . '</textarea></label>';
        if ($kind === 'video') $f .= '<label>Résumé / transcription (facultatif)<textarea name="transcript" rows="6">' . e((string)$r['transcript']) . '</textarea></label>';
    }
    $f .= '<div class="g2"><label>Image de couverture (JPG/PNG/WebP, 6 Mo max)<input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label><div>' . ($r['cover'] ? '<img class="thumb" src="' . e(url($r['cover'])) . '" alt=""><label class="chk"><input type="checkbox" name="remove_cover" value="1"> Retirer l\'image</label>' : '<span class="muted">Aucune image</span>') . '</div></div>';
    $f .= '<div class="g3"><label>Statut' . $sel('status', ['draft' => 'Brouillon', 'published' => 'Publié (ou programmé)', 'archived' => 'Archivé'], $r['status']) . '</label><label>Publier le (heure d\'Haïti) <small>vide = maintenant</small><input type="datetime-local" name="publish_at" value="' . e(utc_to_local_input($r['publish_at'])) . '"></label><label>Langue' . $sel('lang', ['fr' => 'Français', 'ht' => 'Kreyòl', 'en' => 'English'], $r['lang']) . '</label></div>';
    $f .= '<label class="chk"><input type="checkbox" name="featured" value="1"' . ($r['featured'] ? ' checked' : '') . '> À la une (page d\'accueil et en-têtes de listes)</label>';
    $f .= '<details><summary>Référencement (SEO)</summary><label>Titre SEO<input name="seo_title" maxlength="70" value="' . e((string)$r['seo_title']) . '"></label><label>Description SEO<textarea name="seo_desc" rows="2" maxlength="170">' . e((string)$r['seo_desc']) . '</textarea></label></details>';
    $f .= '<div class="bar"><button class="btn" name="go" value="stay">Enregistrer</button><button class="btn ghost" name="go" value="list">Enregistrer et revenir à la liste</button>' . ($r['id'] ? '<a class="btn ghost" target="_blank" href="' . e(url(content_path($r))) . '">Aperçu ↗</a>' : '') . '</div></form>';
    if ($r['id']) $f .= '<form method="post" action="' . e(url('admin/content/delete')) . '" onsubmit="return confirm(\'Supprimer définitivement ?\')" class="danger">' . csrf_field() . '<input type="hidden" name="id" value="' . $r['id'] . '"><button class="btn red">Supprimer définitivement</button></form>';
    a_layout(($r['id'] ? 'Modifier' : 'Nouveau') . ' — ' . ['article' => 'article', 'proposal' => 'proposition', 'video' => 'vidéo'][$kind], $f, 'content|' . $kind);
}
function a_content_save(): void {
    a_post(); $id = (int)($_POST['id'] ?? 0); $kind = in_array($_POST['kind'] ?? '', ['article', 'proposal', 'video'], true) ? $_POST['kind'] : 'article';
    $old = $id ? row('SELECT * FROM content WHERE id=?', [$id]) : null; if ($id && !$old) redirect(url('admin'));
    $back = function (string $m) use ($id, $kind) { sess(); $_SESSION['form_err'] = $m; redirect(url('admin/content/edit?' . ($id ? 'id=' . $id : 'kind=' . $kind))); };
    $title = trim((string)($_POST['title'] ?? '')); if ($title === '' || mb_strlen($title) > 200) $back('Le titre est obligatoire (200 caractères maximum).');
    $slug = slugify(trim((string)($_POST['slug'] ?? '')) ?: $title); $base = $slug; $n = 2;
    while (val('SELECT COUNT(*) FROM content WHERE kind=? AND slug=? AND id<>?', [$kind, $slug, $id])) $slug = $base . '-' . $n++;
    $types = $kind === 'proposal' ? PROPOSAL_TYPES : ARTICLE_TYPES; $type = $kind === 'video' ? 'video' : (in_array($_POST['type'] ?? '', $types, true) ? $_POST['type'] : $types[0]);
    $cid = (int)($_POST['category_id'] ?? 0); $cat = $cid ? category($cid) : null; if ($cat && $cat['kind'] !== ($kind === 'video' ? 'video' : 'theme')) { $cat = null; $cid = 0; }
    $vurl = trim((string)($_POST['video_url'] ?? '')); $yid = $vurl !== '' ? youtube_id($vurl) : '';
    if ($vurl !== '' && $yid === '') $back('Lien YouTube non reconnu.'); if ($kind === 'video' && $yid === '') $back('Un lien YouTube valide est obligatoire pour une vidéo.');
    $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'archived'], true) ? $_POST['status'] : 'draft';
    $pub = local_to_utc((string)($_POST['publish_at'] ?? '')); if ($status === 'published' && !$pub) $pub = $old && $old['publish_at'] && $old['status'] === 'published' ? $old['publish_at'] : now();
    $extra = []; if ($kind === 'proposal') foreach (PROPOSAL_FIELDS as $k) $extra[$k] = trim((string)($_POST['x_' . $k] ?? ''));
    $cover = $old['cover'] ?? ''; [$up, $upErr] = save_image_upload($_FILES['cover'] ?? ['error' => UPLOAD_ERR_NO_FILE]); if ($upErr) $back($upErr); if ($up) $cover = $up; elseif (!empty($_POST['remove_cover'])) $cover = '';
    $rec = ['title' => $title, 'summary' => trim((string)($_POST['summary'] ?? '')), 'body' => trim((string)($_POST['body'] ?? '')), 'tags' => trim((string)($_POST['tags'] ?? '')), 'transcript' => trim((string)($_POST['transcript'] ?? ''))];
    $st = content_search_text($rec, $extra, $cat['name'] ?? '');
    $vals = [$type, $slug, $title, $rec['summary'], $rec['body'], json_encode($extra, JSON_UNESCAPED_UNICODE), $cover, $cid ?: null, $rec['tags'], $status, $pub, empty($_POST['featured']) ? 0 : 1, mb_substr(trim((string)($_POST['seo_title'] ?? '')), 0, 70), mb_substr(trim((string)($_POST['seo_desc'] ?? '')), 0, 170), in_array($_POST['lang'] ?? '', ['fr', 'ht', 'en'], true) ? $_POST['lang'] : 'fr', $vurl, $yid, $rec['transcript'], $st, norm($title), now()];
    if ($id) { q('UPDATE content SET type=?,slug=?,title=?,summary=?,body=?,extra=?,cover=?,category_id=?,tags=?,status=?,publish_at=?,featured=?,seo_title=?,seo_desc=?,lang=?,video_url=?,youtube_id=?,transcript=?,search_text=?,title_norm=?,updated_at=? WHERE id=?', array_merge($vals, [$id])); }
    else { q('INSERT INTO content(type,slug,title,summary,body,extra,cover,category_id,tags,status,publish_at,featured,seo_title,seo_desc,lang,video_url,youtube_id,transcript,search_text,title_norm,updated_at,kind,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [$kind, now()])); $id = (int)db()->lastInsertId(); }
    a_flash('Enregistré.');
    redirect(($_POST['go'] ?? '') === 'list' ? url('admin/content?kind=' . $kind) : url('admin/content/edit?id=' . $id));
}
function a_content_toggle(): void {
    a_post(); $id = (int)($_POST['id'] ?? 0); $r = row('SELECT * FROM content WHERE id=?', [$id]); if (!$r) redirect(url('admin'));
    switch ($_POST['do'] ?? '') {
        case 'feature': q('UPDATE content SET featured=1-featured, updated_at=? WHERE id=?', [now(), $id]); break;
        case 'publish': q("UPDATE content SET status='published', publish_at=COALESCE(publish_at, ?), updated_at=? WHERE id=?", [now(), now(), $id]); break;
        case 'draft': q("UPDATE content SET status='draft', updated_at=? WHERE id=?", [now(), $id]); break;
        case 'archive': q("UPDATE content SET status='archived', updated_at=? WHERE id=?", [now(), $id]); break;
    }
    redirect(url('admin/content?kind=' . $r['kind']));
}
function a_content_delete(): void {
    a_post(); $id = (int)($_POST['id'] ?? 0); $r = row('SELECT * FROM content WHERE id=?', [$id]);
    if ($r) { q('DELETE FROM content WHERE id=?', [$id]); if ($r['cover'] && strpos($r['cover'], 'uploads/') === 0 && !val('SELECT COUNT(*) FROM content WHERE cover=?', [$r['cover']])) @unlink(ROOT . '/' . $r['cover']); a_flash('Supprimé.'); }
    redirect(url('admin/content?kind=' . ($r['kind'] ?? 'article')));
}

/* ---------------------------------------------------------------- catégories */
function a_categories(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        a_post(); $do = $_POST['do'] ?? ''; $kind = ($_POST['kind'] ?? '') === 'video' ? 'video' : 'theme';
        if ($do === 'add' && trim((string)$_POST['name']) !== '') { $slug = slugify((string)$_POST['name']); try { q('INSERT INTO categories(kind,slug,name,name_ht,name_en,sort) VALUES(?,?,?,?,?,?)', [$kind, $slug, trim((string)$_POST['name']), trim((string)($_POST['name_ht'] ?? '')), trim((string)($_POST['name_en'] ?? '')), (int)($_POST['sort'] ?? 99)]); a_flash('Catégorie ajoutée.'); } catch (Throwable $e) { a_flash('Cette catégorie existe déjà.', 'err'); } }
        elseif ($do === 'save') { q('UPDATE categories SET name=?,name_ht=?,name_en=?,sort=? WHERE id=?', [trim((string)$_POST['name']), trim((string)$_POST['name_ht']), trim((string)$_POST['name_en']), (int)$_POST['sort'], (int)$_POST['id']]); a_flash('Enregistré.'); }
        elseif ($do === 'delete') { $id = (int)$_POST['id']; if (val('SELECT COUNT(*) FROM content WHERE category_id=?', [$id])) a_flash('Impossible : des contenus utilisent cette catégorie.', 'err'); else { q('DELETE FROM categories WHERE id=?', [$id]); a_flash('Supprimée.'); } }
        redirect(url('admin/categories'));
    }
    $b = a_flash();
    foreach (['theme' => 'Thématiques (articles et propositions)', 'video' => 'Catégories de vidéos'] as $kind => $lbl) {
        $b .= '<div class="card"><h2>' . e($lbl) . '</h2><table><tr><th>Français</th><th>Kreyòl</th><th>English</th><th>Ordre</th><th></th></tr>';
        foreach (categories($kind) as $c) $b .= '<tr><form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . $c['id'] . '"><td><input name="name" value="' . e($c['name']) . '"></td><td><input name="name_ht" value="' . e((string)$c['name_ht']) . '"></td><td><input name="name_en" value="' . e((string)$c['name_en']) . '"></td><td><input name="sort" type="number" value="' . (int)$c['sort'] . '" style="width:70px"></td><td class="act"><button name="do" value="save">Enregistrer</button><button name="do" value="delete" onclick="return confirm(\'Supprimer ?\')">Supprimer</button></td></form></tr>';
        $b .= '<tr><form method="post">' . csrf_field() . '<input type="hidden" name="kind" value="' . $kind . '"><td><input name="name" placeholder="Nouvelle catégorie" required></td><td><input name="name_ht"></td><td><input name="name_en"></td><td><input name="sort" type="number" value="99" style="width:70px"></td><td class="act"><button name="do" value="add">Ajouter</button></td></form></tr></table></div>';
    }
    a_layout('Catégories', $b, 'categories');
}

/* ---------------------------------------------------------------- citoyens */
function csv_cell($v): string { $s = (string)$v; if ($s !== '' && strpos('=+-@', $s[0]) !== false) $s = "'" . $s; return '"' . str_replace('"', '""', $s) . '"'; }
function csv_out(string $name, array $head, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $name . '"'); echo "\xEF\xBB\xBF" . implode(';', array_map('csv_cell', $head)) . "\r\n";
    foreach ($rows as $r) echo implode(';', array_map('csv_cell', $r)) . "\r\n"; exit;
}
function a_submissions(string $sub): void {
    if ($sub === 'export') { $r = rows('SELECT created_at,type,name,location,contact,category,message,status FROM submissions ORDER BY id DESC'); csv_out('messages-citoyens.csv', ['Date (UTC)', 'Type', 'Nom', 'Lieu', 'Contact', 'Catégorie', 'Message', 'Statut'], array_map('array_values', $r)); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        a_post(); $id = (int)($_POST['id'] ?? 0);
        if (($_POST['do'] ?? '') === 'delete') q('DELETE FROM submissions WHERE id=?', [$id]); elseif (in_array($_POST['do'] ?? '', ['new', 'read', 'archived'], true)) q('UPDATE submissions SET status=? WHERE id=?', [$_POST['do'], $id]);
        redirect(url('admin/submissions?' . http_build_query(array_filter(['status' => $_POST['filter'] ?? '']))));
    }
    $st = (string)($_GET['status'] ?? ''); $w = in_array($st, ['new', 'read', 'archived'], true) ? ' WHERE status=\'' . $st . '\'' : '';
    $items = rows('SELECT * FROM submissions' . $w . ' ORDER BY id DESC LIMIT 300');
    $b = '<div class="bar"><a class="btn ghost" href="' . e(url('admin/submissions/export')) . '">Exporter CSV</a>';
    foreach (['' => 'Tous', 'new' => 'Nouveaux', 'read' => 'Lus', 'archived' => 'Archivés'] as $k => $l) $b .= '<a class="chipa' . ($st === $k ? ' on' : '') . '" href="' . e(url('admin/submissions') . ($k ? '?status=' . $k : '')) . '">' . $l . '</a>';
    $b .= '</div>'; if (!$items) $b .= '<div class="card"><p class="muted">Aucun message.</p></div>';
    foreach ($items as $r) {
        $b .= '<div class="card sub ' . e($r['status']) . '"><div class="bar between"><b>' . e(t('t_' . ['idea' => 'idea', 'reform' => 'reform', 'problem' => 'problem', 'commune' => 'commune', 'topic' => 'topic', 'message' => 'message', 'consultation' => 'consult'][$r['type']])) . '</b><span class="muted">' . e(fmt_date($r['created_at'], 'fr', true)) . ' (Haïti) · <span class="b ' . ($r['status'] === 'new' ? 'sch' : 'arc') . '">' . e($r['status']) . '</span></span></div><p>' . nl2br(e($r['message'])) . '</p><p class="muted">' . e(implode(' · ', array_filter([$r['name'], $r['location'], $r['contact'], $r['category']]))) . '</p><form method="post" class="act">' . csrf_field() . '<input type="hidden" name="id" value="' . $r['id'] . '"><input type="hidden" name="filter" value="' . e($st) . '"><button name="do" value="read">Marquer lu</button><button name="do" value="archived">Archiver</button><button name="do" value="new">Marquer nouveau</button><button name="do" value="delete" onclick="return confirm(\'Supprimer ?\')">Supprimer</button></form></div>';
    }
    a_layout('Espace citoyen — messages', $b, 'submissions');
}

/* ---------------------------------------------------------------- newsletter */
function a_subscribers(string $sub): void {
    if ($sub === 'export') { $all = !empty($_GET['all']); $r = rows('SELECT email,status,lang,source,consent_at,confirmed_at,created_at FROM subscribers ' . ($all ? '' : "WHERE status='confirmed' ") . 'ORDER BY id DESC'); csv_out('abonnes.csv', ['E-mail', 'Statut', 'Langue', 'Source', 'Consentement (UTC)', 'Confirmé (UTC)', 'Créé (UTC)'], array_map('array_values', $r)); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        a_post(); $id = (int)($_POST['id'] ?? 0);
        if (($_POST['do'] ?? '') === 'delete') q('DELETE FROM subscribers WHERE id=?', [$id]); elseif (($_POST['do'] ?? '') === 'confirm') q("UPDATE subscribers SET status='confirmed', confirmed_at=? WHERE id=?", [now(), $id]);
        redirect(url('admin/subscribers'));
    }
    $items = rows('SELECT * FROM subscribers ORDER BY id DESC LIMIT 500'); $conf = array_column(array_filter($items, fn($s) => $s['status'] === 'confirmed'), 'email');
    $b = '<div class="bar"><a class="btn ghost" href="' . e(url('admin/subscribers/export')) . '">Exporter CSV (confirmés)</a><a class="btn ghost" href="' . e(url('admin/subscribers/export?all=1')) . '">Tout exporter</a></div><div class="card"><table><tr><th>E-mail</th><th>Statut</th><th>Langue</th><th>Inscrit le</th><th></th></tr>';
    if (!$items) $b .= '<tr><td colspan="5" class="muted">Aucun abonné.</td></tr>';
    foreach ($items as $s) $b .= '<tr><td>' . e($s['email']) . '</td><td><span class="b ' . ($s['status'] === 'confirmed' ? 'pub' : 'arc') . '">' . e($s['status']) . '</span></td><td>' . e((string)$s['lang']) . '</td><td>' . e(fmt_date($s['created_at'], 'fr')) . '</td><td class="act"><form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . $s['id'] . '">' . ($s['status'] !== 'confirmed' ? '<button name="do" value="confirm">Confirmer</button>' : '') . '<button name="do" value="delete" onclick="return confirm(\'Supprimer ?\')">Supprimer</button></form></td></tr>';
    $b .= '</table></div>';
    if ($conf) $b .= '<div class="card"><h2>Copier les adresses confirmées (champ Cci)</h2><textarea rows="3" readonly onclick="this.select()">' . e(implode(', ', $conf)) . '</textarea><p class="muted">Pour envoyer une campagne, utilisez votre messagerie en Cci (ou un service dédié). Chaque message doit contenir un lien de désinscription.</p></div>';
    a_layout('Newsletter — abonnés', $b, 'subscribers');
}

/* ---------------------------------------------------------------- programme PDF officiel */
function a_program(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        a_post();
        if (($_POST['do'] ?? '') === 'remove') { $f = (string)setting('program_pdf'); if ($f && strpos($f, 'uploads/') === 0) @unlink(ROOT . '/' . $f); set_setting('program_pdf', ''); a_flash('PDF officiel retiré : le PDF généré automatiquement sera utilisé.'); }
        elseif (!empty($_FILES['pdf']['tmp_name'])) {
            $f = $_FILES['pdf']; $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 5);
            if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 25 * 1024 * 1024 || $head !== '%PDF-') a_flash('Fichier PDF invalide (25 Mo maximum).', 'err');
            else { $name = 'uploads/programme_' . bin2hex(random_bytes(6)) . '.pdf'; move_uploaded_file($f['tmp_name'], ROOT . '/' . $name); $old = (string)setting('program_pdf'); if ($old && strpos($old, 'uploads/') === 0) @unlink(ROOT . '/' . $old); set_setting('program_pdf', $name); a_flash('PDF officiel enregistré.'); }
        }
        redirect(url('admin/program'));
    }
    $cur = (string)setting('program_pdf');
    $n = (int)val("SELECT COUNT(*) FROM content c WHERE c.kind='proposal' AND " . visible_sql('c'));
    $b = a_flash() . '<div class="card"><h2>Fichier téléchargé par les visiteurs</h2><p>Bouton « Télécharger le programme (PDF) » : <a href="' . e(url('programme.pdf')) . '" target="_blank">' . e(site_url('programme.pdf')) . '</a></p>' . ($cur ? '<p>✅ PDF officiel en ligne : <code>' . e($cur) . '</code></p><form method="post">' . csrf_field() . '<button class="btn red" name="do" value="remove">Retirer et revenir au PDF automatique</button></form>' : '<p>Par défaut, le PDF est <b>généré automatiquement</b> à partir des propositions publiées (' . $n . ' actuellement) : couverture, sommaire, numéros de page.</p>') . '</div>';
    $b .= '<div class="card"><h2>Remplacer par votre PDF mis en page</h2><form method="post" enctype="multipart/form-data">' . csrf_field() . '<input type="file" name="pdf" accept="application/pdf" required><button class="btn">Téléverser</button></form><p class="muted">Utile si vous préparez le programme dans Canva/Word. Les PDF individuels par proposition restent générés automatiquement.</p></div>';
    a_layout('Programme PDF', $b, 'program');
}

/* ---------------------------------------------------------------- réglages */
function a_settings(array $u): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        a_post();
        if (($_POST['do'] ?? '') === 'password') {
            $me = row('SELECT * FROM users WHERE id=?', [$u['id']]);
            if (!password_verify((string)($_POST['current'] ?? ''), $me['pass'])) a_flash('Mot de passe actuel incorrect.', 'err');
            elseif (strlen((string)$_POST['new']) < 10) a_flash('Nouveau mot de passe : 10 caractères minimum.', 'err');
            else { q('UPDATE users SET pass=? WHERE id=?', [password_hash((string)$_POST['new'], PASSWORD_DEFAULT), $u['id']]); a_flash('Mot de passe modifié.'); }
        } else {
            foreach (['site_url', 'timezone', 'admin_email', 'mail_from', 'press_email', 'bio_short', 'bio_long', 'consultation_title', 'consultation_question'] as $k) {
                $v = trim((string)($_POST[$k] ?? ''));
                if ($k === 'site_url' && !preg_match('~^https?://[a-z0-9.-]+(:\d+)?$~i', rtrim($v, '/'))) continue;
                if ($k === 'timezone' && !in_array($v, timezone_identifiers_list(), true)) continue;
                set_setting($k, $k === 'site_url' ? rtrim($v, '/') : $v);
            }
            a_flash('Réglages enregistrés.');
        }
        redirect(url('admin/settings'));
    }
    $f = fn($k, $l, $type = 'text') => '<label>' . $l . '<input type="' . $type . '" name="' . $k . '" value="' . e((string)setting($k)) . '"></label>';
    $ta = fn($k, $l, $r = 4) => '<label>' . $l . '<textarea name="' . $k . '" rows="' . $r . '">' . e((string)setting($k)) . '</textarea></label>';
    $b = a_flash() . '<form method="post" class="card edit">' . csrf_field() . '<h2>Site</h2><div class="g2">' . $f('site_url', 'Adresse du site (sans / final)') . $f('timezone', 'Fuseau horaire') . '</div><div class="g3">' . $f('admin_email', 'E-mail qui reçoit les notifications', 'email') . $f('mail_from', 'Expéditeur des e-mails', 'email') . $f('press_email', 'Contact presse', 'email') . '</div>'
        . '<h2>Biographies (page Presse & Media + dossier de presse)</h2>' . $ta('bio_short', 'Biographie courte', 4) . $ta('bio_long', 'Biographie complète', 10)
        . '<h2>Consultation citoyenne</h2><p class="muted">Remplissez pour ouvrir une consultation (onglet « Consultation » de l\'espace citoyen). Laissez vide pour la fermer.</p>' . $f('consultation_title', 'Titre de la consultation') . $ta('consultation_question', 'Question posée aux citoyens', 3) . '<button class="btn">Enregistrer</button></form>';
    $b .= '<form method="post" class="card edit">' . csrf_field() . '<h2>Changer le mot de passe</h2><input type="hidden" name="do" value="password"><div class="g2"><label>Mot de passe actuel<input type="password" name="current" required autocomplete="current-password"></label><label>Nouveau (10 caractères min.)<input type="password" name="new" required minlength="10" autocomplete="new-password"></label></div><button class="btn">Modifier</button></form>';
    a_layout('Réglages', $b, 'settings');
}
