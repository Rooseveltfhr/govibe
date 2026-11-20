<?php
/** Pages publiques : programme, propositions, actualités, vidéos, recherche, espace citoyen, newsletter, flux. */
declare(strict_types=1);

function cat_chips(array $cats, string $current, string $path, array $keep = []): string {
    $h = '<div class="chips">';
    $mk = function (string $slug, string $label, bool $on) use ($path, $keep) { $q = $keep; if ($slug !== '') $q['cat'] = $slug; $qs = http_build_query(array_filter($q)); return '<a class="chip' . ($on ? ' on' : '') . '" href="' . e(url($path) . ($qs ? '?' . $qs : '')) . '">' . e($label) . '</a>'; };
    $h .= $mk('', t('all'), $current === '');
    foreach ($cats as $c) $h .= $mk($c['slug'], cat_name($c, lang()), $current === $c['slug']);
    return $h . '</div>';
}
function type_chips(array $types, string $current, string $path, array $keep = []): string {
    $h = '<div class="chips">';
    $mk = function (string $ty, string $label, bool $on) use ($path, $keep) { $q = $keep; if ($ty !== '') $q['type'] = $ty; $qs = http_build_query(array_filter($q)); return '<a class="chip' . ($on ? ' on' : '') . '" href="' . e(url($path) . ($qs ? '?' . $qs : '')) . '">' . e($label) . '</a>'; };
    $h .= $mk('', t('all'), $current === '');
    foreach ($types as $ty) $h .= $mk($ty, type_label($ty), $current === $ty);
    return $h . '</div>';
}
function log_search(string $q): void {
    $n = norm(trim($q)); if (mb_strlen($n) < 2 || mb_strlen($n) > 60) return;
    q('INSERT INTO searches(term,n,last_at) VALUES(?,1,?) ON CONFLICT(term) DO UPDATE SET n=n+1,last_at=excluded.last_at', [$n, now()]);
}
function breadcrumbs(array $trail): string {
    $h = '<p class="crumbs"><a href="' . e(url()) . '">' . e(t('home')) . '</a>';
    foreach ($trail as $l => $u) $h .= ' / ' . ($u ? '<a href="' . e(url($u)) . '">' . e($l) . '</a>' : e($l));
    return $h . '</p>';
}
function meta_line(array $r): string {
    $lang = lang(); $bits = [];
    if ($r['kind'] !== 'video') $bits[] = '<b>' . e(type_label((string)$r['type'])) . '</b>';
    if (!empty($r['cat_name'])) $bits[] = '<span>' . e(cat_name(['name' => $r['cat_name'], 'name_ht' => $r['cat_ht'] ?? '', 'name_en' => $r['cat_en'] ?? ''], $lang)) . '</span>';
    $d = fmt_date($r['publish_at'] ?: $r['created_at'], $lang); if ($d) $bits[] = '<span>' . e($d) . '</span>';
    return '<div class="post-meta">' . implode('', $bits) . '</div>';
}
function tag_chips(?string $tags, string $kindPath): string {
    $l = tag_list($tags); if (!$l) return '';
    $h = '<div class="chips mt-2">'; foreach ($l as $tg) $h .= '<a class="chip" href="' . e(url($kindPath) . '?q=' . rawurlencode($tg)) . '">#' . e($tg) . '</a>';
    return $h . '</div>';
}

/* ------------------------------------------------------------ PROGRAMME */
function r_program(): void {
    $lang = lang();
    $all = rows("SELECT c.id,c.title,c.summary,c.slug,c.type,c.category_id,cat.name,cat.name_ht,cat.name_en,cat.slug AS cslug,cat.sort FROM content c JOIN categories cat ON cat.id=c.category_id WHERE c.kind='proposal' AND " . visible_sql('c') . " ORDER BY cat.sort, c.featured DESC, COALESCE(c.publish_at,c.created_at)");
    $chapters = []; foreach ($all as $r) $chapters[$r['category_id']][] = $r;
    $pdfOk = true;
    $cta = '<div class="pdf-cta"><div><h3>' . e(t('download_pdf')) . '</h3><p>' . e(t('pdf_note')) . '</p></div><a class="btn btn-gold" href="' . e(url('programme.pdf')) . '" data-track="program_pdf">' . e(t('download_pdf')) . '</a></div>';
    $body = hero(t('prog_kicker'), t('prog_title'), t('prog_lead'));
    $body .= '<section style="padding-top:20px"><div class="wrap">';
    if (!$chapters) {
        $body .= '<div class="empty"><h3>' . e(t('prog_empty_t')) . '</h3><p>' . e(t('prog_empty')) . '</p><p class="mt-2"><a class="btn btn-gold" href="' . e(url('espace-citoyen')) . '">' . e(t('citizen_title')) . '</a></p></div>';
    } else {
        $body .= $cta . '<div class="toolbar">' . search_box('propositions') . '<a class="btn btn-ghost" href="' . e(url('propositions')) . '">' . e(t('props_title')) . ' →</a></div>';
        $toc = '<aside class="toc" aria-label="' . e(t('chapters')) . '"><h4>' . e(t('chapters')) . '</h4>';
        $main = ''; $i = 0;
        foreach ($chapters as $items) {
            $i++; $c = $items[0]; $nm = cat_name(['name' => $c['name'], 'name_ht' => $c['name_ht'], 'name_en' => $c['name_en']], $lang);
            $toc .= '<a href="#ch-' . e($c['cslug']) . '"><span>' . e($nm) . '</span><small>' . count($items) . '</small></a>';
            $main .= '<section class="chapter" id="ch-' . e($c['cslug']) . '"><header><span class="no">' . sprintf('%02d', $i) . '</span><h2>' . e($nm) . '</h2></header>';
            foreach ($items as $p) {
                $main .= '<a class="prop-row" href="' . e(url('proposition/' . $p['slug'])) . '" data-track="program_click" data-track-ref="' . e($p['slug']) . '"><div><h3>' . e($p['title']) . '</h3><p>' . e(trunc((string)$p['summary'], 160)) . '</p></div><span class="badge">' . e(type_label((string)$p['type'])) . '</span></a>';
            }
            $main .= '</section>';
        }
        $body .= '<div class="prog-layout">' . $toc . '</aside><div>' . $main . '</div></div>';
    }
    $body .= '</div></section>' . newsletter_band();
    page(['title' => t('prog_title'), 'desc' => t('prog_lead'), 'path' => 'programme', 'body' => $body, 'jsonld' => ['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => t('prog_title'), 'url' => site_url('programme'), 'author' => ['@type' => 'Person', 'name' => 'Roosevelt Forestal']]]);
}

/* ------------------------------------------------------------ PROPOSITIONS */
function r_props(): void {
    $page = max(1, (int)($_GET['page'] ?? 1)); $qv = trim((string)($_GET['q'] ?? '')); $cat = (string)($_GET['cat'] ?? ''); $type = (string)($_GET['type'] ?? '');
    if (!in_array($type, PROPOSAL_TYPES, true)) $type = '';
    $more = false; $total = 0;
    $items = list_content('proposal', ['q' => $qv, 'cat' => $cat, 'type' => $type], 12, ($page - 1) * 12, 'recent', $more, $total);
    if ($qv !== '' && $page === 1) { log_search($qv); stat_add('search', 'propositions'); }
    $keep = array_filter(['q' => $qv, 'cat' => $cat, 'type' => $type]);
    $body = hero(t('prog_kicker'), t('props_title'), t('props_lead'));
    $body .= '<section style="padding-top:20px"><div class="wrap"><div class="toolbar">' . search_box('propositions', $qv, array_filter(['cat' => $cat, 'type' => $type])) . '<a class="btn btn-ghost" href="' . e(url('programme')) . '">' . e(t('prog_title')) . ' →</a></div>';
    $body .= cat_chips(categories('theme'), $cat, 'propositions', array_filter(['q' => $qv, 'type' => $type])) . '<div style="height:12px"></div>' . type_chips(PROPOSAL_TYPES, $type, 'propositions', array_filter(['q' => $qv, 'cat' => $cat])) . '<div style="height:34px"></div>';
    if (!$items) $body .= '<div class="empty"><h3>' . e(t('no_result')) . '</h3></div>';
    else { $body .= '<div class="grid c3">'; foreach ($items as $r) $body .= card($r); $body .= '</div>' . pager($page, $more, $keep, 'propositions'); }
    $body .= '</div></section>';
    page(['title' => t('props_title'), 'desc' => t('props_lead'), 'path' => 'propositions', 'body' => $body, 'robots' => $qv !== '' || $page > 1 ? 'noindex,follow' : 'index,follow']);
}
function r_proposition(string $slug): void {
    $admin = isset($_COOKIE['rfadm']) && admin_user();
    $r = get_content('proposal', $slug, (bool)$admin); if (!$r) not_found();
    if ($r['status'] === 'published') bump_views((int)$r['id']);
    $lang = lang(); $x = extra_of($r); $path = 'proposition/' . $r['slug'];
    $cover = !empty($r['cover']) ? $r['cover'] : null;
    $body = draft_banner($r) . '<section class="post-head"><div class="wrap">' . breadcrumbs([t('props_title') => 'propositions', $r['title'] => '']) . '<h1>' . e($r['title']) . '</h1>' . meta_line($r) . '</div></section>';
    $body .= '<section style="padding-top:0"><div class="wrap">';
    if ($cover) $body .= media_box(url($cover), $r['title'], 'RF', 'post-cover');
    if ($r['summary']) $body .= '<p class="lead" style="margin:0 auto 34px;max-width:46em">' . e($r['summary']) . '</p>';
    $body .= '<div class="prop-grid">';
    $full = ['problem', 'analysis', 'solution'];
    foreach (PROPOSAL_FIELDS as $f) { if (empty(trim((string)($x[$f] ?? '')))) continue; $body .= '<div class="prop-block' . (in_array($f, $full, true) ? ' full' : '') . '"><h2>' . e(t($f)) . '</h2><div class="prose">' . md($x[$f]) . '</div></div>'; }
    $body .= '</div>';
    if (trim((string)$r['body']) !== '') $body .= '<div class="prose">' . md($r['body']) . '</div>';
    $body .= '<div class="hero-actions" style="margin-top:34px"><a class="btn btn-gold" href="' . e(url($path . '.pdf')) . '" data-track="program_pdf" data-track-ref="' . e($r['slug']) . '">' . e(t('dl_prop')) . '</a><button class="btn btn-ghost" onclick="window.print()">' . e(t('print')) . '</button></div>';
    $body .= share_bar($path, $r['title'], (string)$r['summary'], $cover, 'proposal:' . $r['slug']) . tag_chips($r['tags'], 'propositions');
    $rel = list_content('proposal', ['cat' => (string)$r['cat_slug'], 'exclude' => $r['id']], 3);
    if ($rel) { $body .= '<h2 class="mt-3" style="font-size:2rem">' . e(t('related')) . '</h2><div class="grid c3 mt-1">'; foreach ($rel as $q2) $body .= card($q2); $body .= '</div>'; }
    $body .= '</div></section>';
    page(['title' => $r['seo_title'] ?: $r['title'], 'desc' => $r['seo_desc'] ?: ($r['summary'] ?: $r['title']), 'path' => $path, 'image' => $cover, 'type' => 'article', 'body' => $body, 'robots' => $r['status'] === 'published' ? 'index,follow' : 'noindex',
        'jsonld' => ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $r['title'], 'description' => $r['summary'], 'datePublished' => gmdate('c', strtotime(($r['publish_at'] ?: $r['created_at']) . ' UTC')), 'dateModified' => gmdate('c', strtotime($r['updated_at'] . ' UTC')), 'image' => abs_asset($cover), 'mainEntityOfPage' => site_url($path), 'author' => ['@type' => 'Person', 'name' => 'Roosevelt Forestal'], 'publisher' => ['@type' => 'Person', 'name' => 'Roosevelt Forestal']]]);
}
function r_proposition_pdf(string $slug): void {
    $admin = isset($_COOKIE['rfadm']) && admin_user();
    $r = get_content('proposal', $slug, (bool)$admin); if (!$r) not_found();
    require_once ROOT . '/app/pdf.php'; pdf_send(pdf_proposal($r), 'proposition-' . $r['slug'] . '.pdf');
}

/* ------------------------------------------------------------ ACTUALITÉS */
function r_news(): void {
    $page = max(1, (int)($_GET['page'] ?? 1)); $qv = trim((string)($_GET['q'] ?? '')); $cat = (string)($_GET['cat'] ?? ''); $type = (string)($_GET['type'] ?? '');
    if (!in_array($type, ARTICLE_TYPES, true)) $type = '';
    $filtered = $qv !== '' || $cat !== '' || $type !== '' || $page > 1;
    $more = false; $total = 0;
    $items = list_content('article', ['q' => $qv, 'cat' => $cat, 'type' => $type], 9, ($page - 1) * 9, 'recent', $more, $total);
    if ($qv !== '' && $page === 1) { log_search($qv); stat_add('search', 'actualites'); }
    $body = hero(t('news_kicker'), t('news_title'), t('news_lead'));
    $body .= '<section style="padding-top:20px"><div class="wrap"><div class="toolbar">' . search_box('actualites', $qv, array_filter(['cat' => $cat, 'type' => $type])) . '</div>';
    $body .= type_chips(ARTICLE_TYPES, $type, 'actualites', array_filter(['q' => $qv, 'cat' => $cat])) . '<div style="height:12px"></div>' . cat_chips(categories('theme'), $cat, 'actualites', array_filter(['q' => $qv, 'type' => $type])) . '<div style="height:34px"></div>';
    if (!$filtered) {
        $feat = list_content('article', ['featured' => 1], 2);
        if ($feat) { $body .= '<h2 style="font-size:1.6rem;margin-bottom:20px">' . e(t('featured')) . '</h2><div class="grid c2" style="margin-bottom:50px">'; foreach ($feat as $f) $body .= card($f, false); $body .= '</div>'; }
    }
    $body .= '<div class="news-layout"><div>';
    if (!$items) $body .= '<div class="empty"><h3>' . e(t('no_result')) . '</h3></div>';
    else { $body .= '<h2 style="font-size:1.6rem;margin-bottom:20px">' . e(t('latest')) . '</h2><div class="grid c2">'; foreach ($items as $r) $body .= card($r); $body .= '</div>' . pager($page, $more, array_filter(['q' => $qv, 'cat' => $cat, 'type' => $type]), 'actualites'); }
    $body .= '</div><aside>';
    $top = rows("SELECT c.title,c.slug,c.views FROM content c WHERE c.kind='article' AND " . visible_sql('c') . ' AND c.views>0 ORDER BY c.views DESC LIMIT 5');
    if ($top) { $body .= '<div class="side-box"><h3>' . e(t('most_read')) . '</h3><ol>'; foreach ($top as $t2) $body .= '<li><a href="' . e(url('article/' . $t2['slug'])) . '">' . e($t2['title']) . '</a></li>'; $body .= '</ol></div>'; }
    $rec = rows("SELECT c.title,c.slug FROM content c WHERE c.kind='article' AND " . visible_sql('c') . ' ORDER BY RANDOM() LIMIT 4');
    if ($rec) { $body .= '<div class="side-box"><h3>' . e(t('recommended')) . '</h3><ul>'; foreach ($rec as $t2) $body .= '<li><a href="' . e(url('article/' . $t2['slug'])) . '">' . e($t2['title']) . '</a></li>'; $body .= '</ul></div>'; }
    $body .= '</aside></div></div></section>' . newsletter_band();
    page(['title' => t('news_title'), 'desc' => t('news_lead'), 'path' => 'actualites', 'body' => $body, 'robots' => $filtered ? 'noindex,follow' : 'index,follow', 'head' => '<link rel="alternate" type="application/rss+xml" title="Roosevelt Forestal" href="' . e(site_url('rss.xml')) . '">']);
}
function r_article(string $slug): void {
    $admin = isset($_COOKIE['rfadm']) && admin_user();
    $r = get_content('article', $slug, (bool)$admin); if (!$r) not_found();
    if ($r['status'] === 'published') bump_views((int)$r['id']);
    $path = 'article/' . $r['slug']; $cover = !empty($r['cover']) ? $r['cover'] : ($r['youtube_id'] ? 'https://i.ytimg.com/vi/' . $r['youtube_id'] . '/hqdefault.jpg' : null);
    $body = draft_banner($r) . '<section class="post-head"><div class="wrap">' . breadcrumbs([t('news_title') => 'actualites', $r['title'] => '']) . '<h1>' . e($r['title']) . '</h1>' . meta_line($r) . '</div></section><section style="padding-top:0"><div class="wrap">';
    if (!empty($r['cover'])) $body .= media_box(url($r['cover']), $r['title'], 'RF', 'post-cover');
    if ($r['summary']) $body .= '<p class="lead" style="margin:0 auto 30px;max-width:740px;font-size:1.3rem">' . e($r['summary']) . '</p>';
    if ($r['youtube_id']) $body .= '<div style="max-width:900px;margin:0 auto 40px">' . video_player($r['youtube_id'], $r['title']) . '</div>';
    $body .= '<div class="prose">' . md($r['body']) . '</div><div style="max-width:740px;margin:0 auto">' . share_bar($path, $r['title'], (string)$r['summary'], $cover, 'article:' . $r['slug']) . tag_chips($r['tags'], 'actualites') . '</div>';
    $rel = list_content('article', ['cat' => (string)$r['cat_slug'], 'exclude' => $r['id']], 3);
    if (count($rel) < 3) { $rel2 = list_content('article', ['type' => (string)$r['type'], 'exclude' => $r['id']], 3); $ids = array_column($rel, 'id'); foreach ($rel2 as $x2) if (count($rel) < 3 && !in_array($x2['id'], $ids)) $rel[] = $x2; }
    if ($rel) { $body .= '<h2 class="mt-3" style="font-size:2rem">' . e(t('related')) . '</h2><div class="grid c3 mt-1">'; foreach ($rel as $q2) $body .= card($q2); $body .= '</div>'; }
    $body .= '</div></section>' . newsletter_band();
    page(['title' => $r['seo_title'] ?: $r['title'], 'desc' => $r['seo_desc'] ?: ($r['summary'] ?: $r['title']), 'path' => $path, 'image' => $cover, 'type' => 'article', 'body' => $body, 'scripts' => $r['youtube_id'] ? video_script() : '', 'robots' => $r['status'] === 'published' ? 'index,follow' : 'noindex',
        'jsonld' => ['@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => $r['title'], 'description' => $r['summary'], 'datePublished' => gmdate('c', strtotime(($r['publish_at'] ?: $r['created_at']) . ' UTC')), 'dateModified' => gmdate('c', strtotime($r['updated_at'] . ' UTC')), 'image' => abs_asset($cover), 'mainEntityOfPage' => site_url($path), 'author' => ['@type' => 'Person', 'name' => 'Roosevelt Forestal'], 'publisher' => ['@type' => 'Person', 'name' => 'Roosevelt Forestal']]]);
}

/* ------------------------------------------------------------ VIDÉOS */
function r_videos(): void {
    $page = max(1, (int)($_GET['page'] ?? 1)); $qv = trim((string)($_GET['q'] ?? '')); $cat = (string)($_GET['cat'] ?? '');
    $more = false; $total = 0; $items = list_content('video', ['q' => $qv, 'cat' => $cat], 9, ($page - 1) * 9, 'recent', $more, $total);
    if ($qv !== '' && $page === 1) { log_search($qv); stat_add('search', 'videos'); }
    $filtered = $qv !== '' || $cat !== '' || $page > 1;
    $body = hero(t('videos_title'), t('videos_title'), t('videos_lead'));
    $body .= '<section style="padding-top:20px"><div class="wrap">';
    if (!$filtered) { $f = list_content('video', ['featured' => 1], 1); if ($f) $body .= '<div class="two-col" style="margin-bottom:56px"><div>' . video_player($f[0]['youtube_id'], $f[0]['title']) . '</div><div><span class="badge">' . e(t('featured')) . '</span><h2 class="mt-1" style="font-size:2.2rem"><a href="' . e(url('video/' . $f[0]['slug'])) . '">' . e($f[0]['title']) . '</a></h2><p class="lead mt-1">' . e(trunc((string)$f[0]['summary'], 220)) . '</p></div></div>'; }
    $body .= '<div class="toolbar">' . search_box('videos', $qv, array_filter(['cat' => $cat])) . '</div>' . cat_chips(categories('video'), $cat, 'videos', array_filter(['q' => $qv])) . '<div style="height:34px"></div>';
    if (!$items) $body .= '<div class="empty"><h3>' . e(t('no_result')) . '</h3></div>';
    else { $body .= '<div class="grid c3">'; foreach ($items as $r) $body .= card($r); $body .= '</div>' . pager($page, $more, array_filter(['q' => $qv, 'cat' => $cat]), 'videos'); }
    $body .= '</div></section>' . newsletter_band();
    page(['title' => t('videos_title'), 'desc' => t('videos_lead'), 'path' => 'videos', 'body' => $body, 'scripts' => video_script(), 'robots' => $filtered ? 'noindex,follow' : 'index,follow']);
}
function r_video(string $slug): void {
    $admin = isset($_COOKIE['rfadm']) && admin_user();
    $r = get_content('video', $slug, (bool)$admin); if (!$r) not_found();
    if ($r['status'] === 'published') bump_views((int)$r['id']);
    $path = 'video/' . $r['slug']; $thumb = !empty($r['cover']) ? $r['cover'] : 'https://i.ytimg.com/vi/' . $r['youtube_id'] . '/maxresdefault.jpg';
    $body = draft_banner($r) . '<section class="post-head"><div class="wrap">' . breadcrumbs([t('videos_title') => 'videos', $r['title'] => '']) . '<h1>' . e($r['title']) . '</h1>' . meta_line($r) . '</div></section><section style="padding-top:0"><div class="wrap">';
    $body .= '<div style="max-width:980px;margin:0 auto">' . video_player((string)$r['youtube_id'], $r['title']) . '<p class="f-hint">' . e(t('yt_note')) . '</p></div>';
    $body .= '<div class="prose mt-2">' . ($r['summary'] ? '<p>' . e($r['summary']) . '</p>' : '') . md($r['body']) . '</div>';
    if (trim((string)$r['transcript']) !== '') $body .= '<details class="prose mt-2" style="border:1px solid var(--line);border-radius:14px;padding:16px 22px"><summary style="cursor:pointer;font-weight:700">' . e(t('transcript')) . '</summary><div class="mt-1">' . md($r['transcript']) . '</div></details>';
    $body .= '<div style="max-width:740px;margin:0 auto">' . share_bar($path, $r['title'], (string)$r['summary'], !empty($r['cover']) ? $r['cover'] : null, 'video:' . $r['slug']) . tag_chips($r['tags'], 'videos') . '</div>';
    $rel = list_content('video', ['cat' => (string)$r['cat_slug'], 'exclude' => $r['id']], 3);
    if ($rel) { $body .= '<h2 class="mt-3" style="font-size:2rem">' . e(t('video_related')) . '</h2><div class="grid c3 mt-1">'; foreach ($rel as $q2) $body .= card($q2); $body .= '</div>'; }
    $body .= '</div></section>';
    page(['title' => $r['seo_title'] ?: $r['title'], 'desc' => $r['seo_desc'] ?: ($r['summary'] ?: $r['title']), 'path' => $path, 'image' => $thumb, 'type' => 'video.other', 'body' => $body, 'scripts' => video_script(), 'robots' => $r['status'] === 'published' ? 'index,follow' : 'noindex',
        'jsonld' => ['@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => $r['title'], 'description' => $r['summary'] ?: $r['title'], 'thumbnailUrl' => abs_asset($thumb), 'uploadDate' => gmdate('c', strtotime(($r['publish_at'] ?: $r['created_at']) . ' UTC')), 'embedUrl' => 'https://www.youtube.com/embed/' . $r['youtube_id'], 'contentUrl' => 'https://www.youtube.com/watch?v=' . $r['youtube_id']]]);
}

/* ------------------------------------------------------------ RECHERCHE GLOBALE */
function r_search(): void {
    $qv = trim((string)($_GET['q'] ?? '')); $body = hero(t('search_title'), $qv !== '' ? t('results_for') . ' « ' . $qv . ' »' : t('search_title'));
    $body .= '<section style="padding-top:10px"><div class="wrap"><div class="toolbar">' . search_box('recherche', $qv) . '</div>';
    if ($qv !== '') {
        log_search($qv); stat_add('search', 'global'); $any = false;
        foreach (['proposal' => ['props', 'propositions'], 'article' => ['articles', 'actualites'], 'video' => ['videos', 'videos']] as $kind => $m) {
            $more = false; $tot = 0; $res = list_content($kind, ['q' => $qv], 6, 0, 'recent', $more, $tot);
            if (!$res) continue; $any = true;
            $body .= '<h2 class="mt-3" style="font-size:1.8rem">' . e(t($m[0])) . ' <small style="color:var(--gold);font-size:1rem">(' . $tot . ')</small></h2><div class="grid c3 mt-1">';
            foreach ($res as $r) $body .= card($r); $body .= '</div>';
            if ($more) $body .= '<p class="mt-1"><a class="btn btn-ghost" href="' . e(url($m[1]) . '?q=' . rawurlencode($qv)) . '">' . e(t('see_all')) . ' →</a></p>';
        }
        if (!$any) $body .= '<div class="empty"><h3>' . e(t('no_result')) . '</h3></div>';
    }
    page(['title' => t('search_title'), 'path' => 'recherche', 'body' => $body . '</div></section>', 'robots' => 'noindex,follow']);
}

/* ------------------------------------------------------------ ESPACE CITOYEN */
const CITIZEN_TYPES = ['idea' => 't_idea', 'reform' => 't_reform', 'problem' => 't_problem', 'commune' => 't_commune', 'topic' => 't_topic', 'message' => 't_message', 'consultation' => 't_consult'];
const DEPARTMENTS = ['Ouest', 'Nord', 'Nord-Est', 'Nord-Ouest', 'Artibonite', 'Centre', 'Sud', 'Sud-Est', "Grand'Anse", 'Nippes'];

function r_citizen(): void {
    $type = (string)($_GET['type'] ?? $_POST['type'] ?? 'idea'); if (!isset(CITIZEN_TYPES[$type])) $type = 'idea';
    $err = ''; $old = ['name' => '', 'dept' => '', 'commune' => '', 'contact' => '', 'category' => '', 'message' => ''];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $old = array_merge($old, array_map(fn($v) => is_string($v) ? $v : '', array_intersect_key($_POST, $old)));
        if (!same_origin() || trim((string)($_POST['website'] ?? '')) !== '' || !form_token_ok((string)($_POST['t'] ?? ''))) $err = t('err_spam');
        elseif (!throttle_ok('cit:' . ip_hash(), 6, 3600) || !throttle_ok('cit:all', 300, 86400)) $err = t('err_rate');
        elseif (empty($_POST['consent'])) $err = t('err_consent');
        elseif (mb_strlen(trim($old['message'])) < 10) $err = t('err_msg');
        if (!$err) {
            $loc = trim(trim(mb_substr($old['dept'], 0, 40) . ' / ' . mb_substr($old['commune'], 0, 60)), ' /');
            $cat = ''; if ($old['category'] !== '') { $c = row("SELECT name FROM categories WHERE kind='theme' AND slug=?", [$old['category']]); $cat = $c ? $c['name'] : ''; }
            q('INSERT INTO submissions(type,name,location,contact,category,message,consent,status,lang,created_at) VALUES(?,?,?,?,?,?,1,\'new\',?,?)', [$type, mb_substr(trim($old['name']), 0, 80), $loc, mb_substr(trim($old['contact']), 0, 120), $cat, mb_substr(trim($old['message']), 0, 3000), lang(), now()]);
            stat_add('citizen_submit', $type);
            send_mail((string)setting('admin_email'), 'Nouveau message citoyen (' . $type . ')', "Type : $type\nNom : " . trim($old['name']) . "\nLieu : $loc\nContact : " . trim($old['contact']) . "\nCatégorie : $cat\n\n" . trim($old['message']) . "\n\nGérer : " . site_url('admin/submissions'));
            redirect(url('espace-citoyen') . '?type=' . $type . '&ok=1#form');
        }
    }
    $ok = isset($_GET['ok']);
    $tabs = '<nav class="tabs" aria-label="Type">'; foreach (CITIZEN_TYPES as $k => $lbl) $tabs .= '<a href="' . e(url('espace-citoyen') . '?type=' . $k . '#form') . '" class="' . ($k === $type ? 'on' : '') . '">' . e(t($lbl)) . '</a>'; $tabs .= '</nav>';
    $f = '<div class="form-card" id="form">';
    if ($ok) $f .= '<div class="alert ok" role="status">' . e(t('thanks')) . '</div>';
    if ($err) $f .= '<div class="alert err" role="alert">' . e($err) . '</div>';
    if ($type === 'consultation') { $ct = (string)setting('consultation_title'); $cq = (string)setting('consultation_question'); $f .= $ct !== '' ? '<h3>' . e($ct) . '</h3><p class="lead mt-1" style="margin-bottom:22px">' . e($cq) . '</p>' : '<p class="lead" style="margin-bottom:22px">' . e(t('consult_none')) . '</p>'; }
    $f .= '<form method="post" action="' . e(url('espace-citoyen') . '?type=' . $type . '#form') . '" novalidate><input type="hidden" name="type" value="' . e($type) . '"><input type="hidden" name="t" value="' . e(form_token()) . '"><div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
    $f .= '<div class="f-row"><div class="f-group"><label for="c-name">' . e(t('f_name')) . '</label><input id="c-name" name="name" maxlength="80" value="' . e($old['name']) . '" autocomplete="name"></div><div class="f-group"><label for="c-contact">' . e(t('f_contact')) . '</label><input id="c-contact" name="contact" maxlength="120" value="' . e($old['contact']) . '"></div></div>';
    $f .= '<div class="f-row"><div class="f-group"><label for="c-dept">' . e(t('f_dept')) . '</label><select id="c-dept" name="dept"><option value="">—</option>';
    foreach (DEPARTMENTS as $d) $f .= '<option' . ($old['dept'] === $d ? ' selected' : '') . '>' . e($d) . '</option>';
    $f .= '</select></div><div class="f-group"><label for="c-commune">' . e(t('f_commune')) . '</label><input id="c-commune" name="commune" maxlength="60" value="' . e($old['commune']) . '"></div></div>';
    $f .= '<div class="f-group"><label for="c-cat">' . e(t('f_cat')) . '</label><select id="c-cat" name="category"><option value="">—</option>'; foreach (categories('theme') as $c) $f .= '<option value="' . e($c['slug']) . '"' . ($old['category'] === $c['slug'] ? ' selected' : '') . '>' . e(cat_name($c, lang())) . '</option>'; $f .= '</select></div>';
    $f .= '<div class="f-group"><label for="c-msg">' . e(t('f_msg')) . ' *</label><textarea id="c-msg" name="message" required minlength="10" maxlength="3000">' . e($old['message']) . '</textarea></div>';
    $f .= '<label class="f-check"><input type="checkbox" name="consent" value="1" required><span>' . e(t('f_consent')) . '</span></label><button class="btn btn-gold" type="submit">' . e(t('send')) . '</button><p class="f-hint mt-1">🔒 ' . e(t('privacy')) . '</p></form></div>';
    $body = hero(t('citizen_kicker'), t('citizen_title'), t('citizen_lead')) . '<section style="padding-top:10px"><div class="wrap" style="max-width:860px">' . $tabs . $f . '</div></section>';
    page(['title' => t('citizen_title'), 'desc' => t('citizen_lead'), 'path' => 'espace-citoyen', 'body' => $body, 'robots' => $_SERVER['REQUEST_METHOD'] === 'POST' ? 'noindex' : 'index,follow']);
}

/* ------------------------------------------------------------ NEWSLETTER */
function r_newsletter(): void {
    $msg = ''; $kind = 'ok';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!same_origin() || trim((string)($_POST['website'] ?? '')) !== '' || !form_token_ok((string)($_POST['t'] ?? ''), 2)) { $msg = t('err_spam'); $kind = 'err'; }
        elseif (!throttle_ok('nl:' . ip_hash(), 5, 3600)) { $msg = t('err_rate'); $kind = 'err'; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) { $msg = t('nl_err'); $kind = 'err'; }
        elseif (empty($_POST['consent'])) { $msg = t('err_consent'); $kind = 'err'; }
        else {
            $tok = bin2hex(random_bytes(16)); $ex = row('SELECT * FROM subscribers WHERE email=?', [$email]);
            if (!$ex) q('INSERT INTO subscribers(email,status,token,lang,source,consent_at,created_at) VALUES(?,?,?,?,?,?,?)', [$email, 'pending', $tok, lang(), substr(preg_replace('/[^a-z]/', '', (string)($_POST['src'] ?? 'page')), 0, 12), now(), now()]);
            elseif ($ex['status'] !== 'confirmed') q("UPDATE subscribers SET token=?, status='pending', consent_at=? WHERE id=?", [$tok, now(), $ex['id']]);
            else $tok = '';
            if ($tok !== '') send_mail($email, 'Confirmez votre inscription — Roosevelt Forestal', "Bonjour,\n\nPour confirmer votre inscription à la newsletter de Roosevelt Forestal, ouvrez ce lien :\n" . site_url('newsletter/confirmer/' . $tok) . "\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n");
            $msg = t('nl_sent');
        }
    }
    $body = hero(t('nl_title'), t('nl_title'), t('nl_lead')) . '<section style="padding-top:10px"><div class="wrap" style="max-width:680px">' . ($msg ? '<div class="alert ' . $kind . '" role="status">' . e($msg) . '</div>' : '') . newsletter_form('page', filter_var((string)($_GET['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string)$_GET['email'] : '') . '<p class="f-hint mt-2">🔒 ' . e(t('privacy')) . '</p></div></section>';
    page(['title' => t('nl_title'), 'desc' => t('nl_lead'), 'path' => 'newsletter', 'body' => $body, 'robots' => $_SERVER['REQUEST_METHOD'] === 'POST' ? 'noindex' : 'index,follow']);
}
function r_newsletter_action(string $action, string $tok): void {
    $s = preg_match('/^[a-f0-9]{32}$/', $tok) ? row('SELECT * FROM subscribers WHERE token=?', [$tok]) : null; $ok = false;
    if ($s && $action === 'confirmer') { q("UPDATE subscribers SET status='confirmed', confirmed_at=? WHERE id=?", [now(), $s['id']]); stat_add('newsletter_signup'); $ok = true; $m = t('nl_confirmed'); }
    elseif ($s && $action === 'desinscription') { q("UPDATE subscribers SET status='unsubscribed', unsub_at=? WHERE id=?", [now(), $s['id']]); $ok = true; $m = t('nl_unsub'); }
    page(['title' => t('nl_title'), 'path' => 'newsletter', 'robots' => 'noindex', 'body' => hero(t('nl_title'), $ok ? $m : t('nl_bad')) . '<section style="padding-top:0"><div class="wrap"><a class="btn btn-gold" href="' . e(url()) . '">' . e(t('home')) . '</a></div></section>']);
}

/* ------------------------------------------------------------ PDF programme / dossier de presse */
function r_program_pdf(): void {
    require_once ROOT . '/app/pdf.php';
    $custom = (string)setting('program_pdf');
    if ($custom !== '' && is_file(ROOT . '/' . $custom)) { stat_add('program_pdf_dl', 'official'); pdf_send(file_get_contents(ROOT . '/' . $custom), 'programme-roosevelt-forestal.pdf'); }
    stat_add('program_pdf_dl', 'generated');
    pdf_send(pdf_program(), 'programme-roosevelt-forestal.pdf');
}
function r_press_pdf(): void { require_once ROOT . '/app/pdf.php'; pdf_send(pdf_press_kit(), 'dossier-presse-roosevelt-forestal.pdf'); }

/* ------------------------------------------------------------ SEO : sitemap + RSS */
function r_sitemap(): void {
    header('Content-Type: application/xml; charset=utf-8');
    $u = ['' => '1.0', 'about.html' => '0.8', 'ecosystem.html' => '0.8', 'speaking.html' => '0.7', 'media.html' => '0.7', 'contact.html' => '0.6', 'programme' => '0.9', 'propositions' => '0.8', 'actualites' => '0.8', 'videos' => '0.8', 'espace-citoyen' => '0.7', 'newsletter' => '0.5', 'nou-pa-nan-bel-diskou-anko' => '0.9'];
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($u as $p => $pr) echo '<url><loc>' . e(site_url($p)) . '</loc><priority>' . $pr . '</priority></url>';
    foreach (rows('SELECT kind,slug,updated_at FROM content c WHERE ' . visible_sql('c')) as $r) echo '<url><loc>' . e(site_url(content_path($r))) . '</loc><lastmod>' . gmdate('Y-m-d', strtotime($r['updated_at'] . ' UTC')) . '</lastmod><priority>0.7</priority></url>';
    echo '</urlset>'; exit;
}
function r_rss(): void {
    header('Content-Type: application/rss+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Roosevelt Forestal</title><link>' . e(site_url()) . '</link><description>Actualités et publications</description><language>fr</language>';
    foreach (rows("SELECT * FROM content c WHERE c.kind='article' AND " . visible_sql('c') . ' ORDER BY COALESCE(c.publish_at,c.created_at) DESC LIMIT 20') as $r)
        echo '<item><title>' . e($r['title']) . '</title><link>' . e(site_url(content_path($r))) . '</link><guid>' . e(site_url(content_path($r))) . '</guid><pubDate>' . gmdate('r', strtotime(($r['publish_at'] ?: $r['created_at']) . ' UTC')) . '</pubDate><description>' . e((string)$r['summary']) . '</description></item>';
    echo '</channel></rss>'; exit;
}

/* ------------------------------------------------------------ PRESSE & MEDIA */
function r_press(): void {
    $photos = [['hero-portrait.jpg', 'Portrait officiel'], ['about-portrait.jpg', 'Portrait studio'], ['bio-portrait.jpg', 'Portrait drapeau'], ['speaking-hero.jpg', 'Sur scène à DevExpo']];
    $b = hero('Presse & Media', 'Presse & Media', 'Biographies, photos officielles, dossier de presse, communiqués et contact presse.');
    $b .= '<section style="padding-top:20px"><div class="wrap"><p class="kicker">Biographie courte</p><p class="bio-short">' . nl2br(e((string)setting('bio_short'))) . '</p>';
    $b .= '<div class="facts mt-3"><div class="fact"><small>Fonction</small>Fondateur &amp; CEO, GOVIBE Innovation Hub</div><div class="fact"><small>Profil</small>Entrepreneur · Ingénieur informatique</div><div class="fact"><small>Projets</small>GOVIBE · TAGTOA · Tchekela · Klasyo · HOSPIFY</div><div class="fact"><small>Langues</small>Français · Kreyòl · English</div></div>';
    $b .= '<div class="pdf-cta" style="margin-top:40px"><div><h3>Dossier de presse</h3><p>Biographies, repères et contact au format PDF, prêt pour l\'impression et la diffusion.</p></div><a class="btn btn-gold" href="' . e(url('dossier-presse.pdf')) . '">Télécharger le dossier (PDF)</a></div></div></section>';
    $b .= '<section class="on-light"><div class="wrap"><div class="section-head"><p class="kicker">Biographie complète</p><h2>Parcours</h2></div><div class="prose">' . md((string)setting('bio_long')) . '</div></div></section>';
    $b .= '<section><div class="wrap"><div class="section-head"><p class="kicker">Photos officielles</p><h2>Visuels pour la presse</h2><p class="lead mt-1">Libres d\'usage pour illustrer un article ou annoncer un événement, avec la mention « Roosevelt Forestal ».</p></div><div class="press-photos">';
    foreach ($photos as [$f, $l]) $b .= '<figure>' . media_box(url('assets/img/' . $f), $l, 'RF') . '<a class="dl" href="' . e(url('assets/img/' . $f)) . '" download>Télécharger</a></figure>';
    $b .= '</div><div class="mt-2"><a class="btn btn-ghost" href="' . e(url('assets/img/govibe-logo.png')) . '" download>Logo GOVIBE (PNG)</a></div></div></section>';
    $b .= '<section class="on-ink"><div class="wrap"><div class="section-head"><p class="kicker">Publications</p><h2>Communiqués, discours &amp; interviews</h2></div>';
    $any = false;
    foreach (['communique' => 'Communiqués', 'discours' => 'Discours', 'interview' => 'Interviews', 'rapport' => 'Rapports'] as $ty => $lbl) {
        $items = list_content('article', ['type' => $ty], 3); if (!$items) continue; $any = true;
        $b .= '<h3 class="mt-3" style="margin-bottom:16px">' . e($lbl) . '</h3><div class="grid c3">'; foreach ($items as $r) $b .= card($r); $b .= '</div><p class="mt-1"><a class="btn btn-ghost" href="' . e(url('actualites?type=' . $ty)) . '">' . e(t('see_all')) . ' →</a></p>';
    }
    if (!$any) $b .= '<div class="empty"><h3>Bientôt disponible</h3><p>Les communiqués, discours et interviews seront publiés ici.</p></div>';
    $b .= '</div></section><section><div class="wrap"><div class="section-head"><p class="kicker">Galerie</p><h2>La vie de l\'écosystème</h2></div><div class="gallery">';
    for ($i = 1; $i <= 7; $i++) $b .= media_box(url("assets/img/gallery-$i.jpg"), 'Photo ' . $i, sprintf('%02d', $i));
    $b .= '</div></div></section><section class="cta-band"><div class="wrap"><h2>Contact presse</h2><p class="lead">Interviews, demandes d\'images, invitations : <a href="mailto:' . e((string)setting('press_email')) . '" style="color:var(--gold-2);text-decoration:underline">' . e((string)setting('press_email')) . '</a></p><div class="hero-actions"><a class="btn btn-gold" href="' . e(url('speaking.html#book')) . '">Planifier une interview</a><a class="btn btn-ghost" href="' . e(url('contact.html')) . '">Page contact</a></div></div></section>';
    page(['title' => 'Presse & Media', 'desc' => 'Biographies, photos officielles, dossier de presse, communiqués, discours, interviews et contact presse de Roosevelt Forestal.', 'path' => 'media.html', 'body' => $b, 'type' => 'profile']);
}
