<?php
/** Contrôleur frontal : toutes les URL dynamiques passent ici (voir .htaccess). */
declare(strict_types=1);
if (PHP_VERSION_ID < 70400) { http_response_code(500); exit('PHP 7.4 minimum requis.'); }
define('ROOT', __DIR__);
require ROOT . '/app/core.php';
require ROOT . '/app/render.php';
require ROOT . '/app/public.php';
require ROOT . '/app/api.php';

function dispatch(): void {
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $b = base_path(); if ($b !== '' && strpos($path, $b) === 0) $path = substr($path, strlen($b));
    $path = trim(rawurldecode($path), '/');
    $seg = $path === '' ? [] : explode('/', $path);
    $m = $_SERVER['REQUEST_METHOD'];
    if ($path === '' ) redirect(url('index.html'));
    if ($seg[0] === 'admin') { require ROOT . '/app/admin.php'; admin_dispatch(array_slice($seg, 1)); return; }
    switch ($path) {
        case 'programme': r_program(); return;
        case 'programme.pdf': r_program_pdf(); return;
        case 'propositions': r_props(); return;
        case 'actualites': r_news(); return;
        case 'videos': r_videos(); return;
        case 'recherche': r_search(); return;
        case 'espace-citoyen': r_citizen(); return;
        case 'newsletter': r_newsletter(); return;
        case 'dossier-presse.pdf': r_press_pdf(); return;
        case 'media.html': case 'presse': r_press(); return;
        case 'sitemap.xml': r_sitemap(); return;
        case 'rss.xml': r_rss(); return;
        case 'api/track': api_track(); return;
        case 'api/featured': api_featured(); return;
    }
    if (count($seg) === 2) {
        $s = $seg[1];
        if ($seg[0] === 'proposition') { if (substr($s, -4) === '.pdf') r_proposition_pdf(substr($s, 0, -4)); else r_proposition($s); return; }
        if ($seg[0] === 'article') { r_article($s); return; }
        if ($seg[0] === 'video') { r_video($s); return; }
    }
    if (count($seg) === 3 && $seg[0] === 'newsletter' && in_array($seg[1], ['confirmer', 'desinscription'], true)) { r_newsletter_action($seg[1], $seg[2]); return; }
    not_found();
}

try {
    dispatch();
} catch (Throwable $e) {
    @file_put_contents(ROOT . '/data/error.log', gmdate('c') . ' ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine() . "\n", FILE_APPEND);
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
    echo '<!doctype html><meta charset="utf-8"><title>Erreur</title><body style="font-family:sans-serif;padding:40px"><h1>Une erreur est survenue</h1><p>Veuillez réessayer dans quelques instants.</p><p><a href="' . e(url('index.html')) . '">Retour à l\'accueil</a></p>';
}
