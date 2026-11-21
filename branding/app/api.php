<?php
/** API publique : mesure d'audience anonyme + « à la une » pour pages statiques. */
declare(strict_types=1);

function api_track(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false], 405);
    if (!same_origin()) json_out(['ok' => false], 403);
    if (!throttle_ok('trk:' . ip_hash(), 120, 60)) json_out(['ok' => false], 429);
    $d = json_decode(substr((string)file_get_contents('php://input'), 0, 600), true);
    $e = is_array($d) ? (string)($d['e'] ?? '') : '';
    $allowed = ['campaign_view', 'flyer_generated', 'flyer_download', 'share_click', 'program_click', 'program_pdf', 'ribbon_click'];
    if (!in_array($e, $allowed, true)) json_out(['ok' => false], 400);
    $r = strtolower(substr(preg_replace('~[^a-zA-Z0-9:|_./-]~', '', (string)($d['r'] ?? '')), 0, 80));
    stat_add($e, $r);
    http_response_code(204); exit;
}

function api_featured(): void {
    $kind = (string)($_GET['kind'] ?? 'articles'); $html = '';
    $map = ['articles' => ['article', 'actualites', 'news_kicker'], 'videos' => ['video', 'videos', 'videos_title'], 'proposals' => ['proposal', 'propositions', 'props_title']];
    if (!isset($map[$kind])) json_out(['html' => '']);
    [$k, $path, $title] = $map[$kind];
    $items = list_content($k, ['featured' => 1], 3);
    if (count($items) < 3) { $ids = array_column($items, 'id'); foreach (list_content($k, [], 3) as $r) if (count($items) < 3 && !in_array($r['id'], $ids)) $items[] = $r; }
    if ($items) {
        $html = '<div class="grid c3">'; foreach ($items as $r) $html .= card($r); $html .= '</div><p class="mt-2 center"><a class="btn btn-ghost" href="' . e(url($path)) . '">' . e(t('see_all')) . ' →</a></p>';
    }
    header('Cache-Control: public, max-age=60');
    json_out(['html' => $html]);
}
