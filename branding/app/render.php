<?php
/** Rendu : traductions serveur, mise en page, Markdown sûr, cartes, partage, pagination. */
declare(strict_types=1);

/* ------------------------------------------------------------ traductions (FR = référence) */
$GLOBALS['T'] = [
'fr' => [
 'read_more'=>'Lire la suite','see_all'=>'Tout voir','search'=>'Rechercher','search_ph'=>'Rechercher un mot-clé, un titre…','no_result'=>'Aucun résultat pour le moment.','home'=>'Accueil','all'=>'Tout',
 'published'=>'Publié le','category'=>'Catégorie','type'=>'Type','tags'=>'Mots-clés','related'=>'À lire aussi','share'=>'Partager','back'=>'Retour','page'=>'Page','next'=>'Suivant','prev'=>'Précédent',
 'prog_title'=>'Programme','prog_kicker'=>'Programme politique','prog_lead'=>"Des propositions concrètes, organisées par chapitres, pour changer la vie des Haïtiens. Lisez-les en ligne ou téléchargez le programme complet.",
 'read_online'=>'Lire en ligne','download_pdf'=>'Télécharger le programme (PDF)','pdf_note'=>'Version imprimable avec couverture, sommaire et pagination.','chapters'=>'Chapitres','proposals_n'=>'proposition(s)',
 'prog_empty_t'=>'Le programme est en cours de rédaction','prog_empty'=>"Les propositions seront publiées ici dès leur validation. En attendant, participez : votre voix compte.",
 'props_title'=>'Propositions','props_lead'=>"Réformes, projets de loi, initiatives : chaque proposition expose le problème, l'analyse et la solution.",
 'problem'=>'Problème identifié','analysis'=>'Analyse','solution'=>'Solution proposée','objectives'=>'Objectifs','beneficiaries'=>'Bénéficiaires','impact'=>'Impact attendu','implementation'=>'Mise en œuvre',
 'dl_prop'=>'Télécharger cette proposition (PDF)','print'=>'Imprimer',
 'news_title'=>'Actualités','news_kicker'=>'Actualités & publications','news_lead'=>'Communiqués, analyses, tribunes, interviews et discours.','featured'=>'À la une','latest'=>'Dernières publications','most_read'=>'Les plus consultés','recommended'=>'Recommandés',
 'videos_title'=>'Vidéothèque','videos_lead'=>'Discours, interviews, explications et activités en vidéo.','video_related'=>'Vidéos associées','transcript'=>'Résumé / transcription','play'=>'Lire la vidéo','yt_note'=>'La vidéo est hébergée sur YouTube.',
 'citizen_title'=>'Espace citoyen','citizen_kicker'=>'Participer','citizen_lead'=>"Une idée, un problème dans votre commune, une réforme à proposer ? Écrivez-nous. Votre message est lu et classé.",
 't_idea'=>'Proposer une idée','t_reform'=>'Proposer une réforme','t_problem'=>'Signaler un problème','t_commune'=>'Ma commune','t_topic'=>'Proposer un sujet','t_message'=>'Envoyer un message','t_consult'=>'Consultation',
 'f_name'=>'Nom (facultatif)','f_dept'=>'Département (facultatif)','f_commune'=>'Commune (facultatif)','f_contact'=>'E-mail ou téléphone (facultatif)','f_cat'=>'Catégorie','f_msg'=>'Votre message','f_consent'=>"J'accepte que mon message soit lu et traité par l'équipe. Mes informations personnelles ne seront jamais affichées publiquement.",
 'send'=>'Envoyer','thanks'=>'Merci ! Votre message a bien été reçu.','err_msg'=>'Votre message est trop court (10 caractères minimum).','err_consent'=>'Veuillez accepter le consentement.','err_spam'=>"Envoi bloqué. Réessayez dans quelques secondes.",'err_rate'=>'Trop de messages envoyés. Réessayez plus tard.','privacy'=>'Vos données sont protégées et jamais publiées.',
 'consult_none'=>"Aucune consultation n'est ouverte pour le moment. Vous pouvez tout de même envoyer un message.",
 'nl_title'=>'Newsletter','nl_lead'=>'Recevez les nouveaux articles, propositions, vidéos, activités et annonces importantes.','f_email'=>'Votre e-mail','nl_consent'=>"J'accepte de recevoir la newsletter de Roosevelt Forestal. Je peux me désinscrire à tout moment.",'nl_subscribe'=>"S'inscrire",
 'nl_sent'=>'Presque terminé ! Consultez votre boîte mail pour confirmer votre inscription.','nl_confirmed'=>'Inscription confirmée. Merci !','nl_unsub'=>'Vous êtes désinscrit(e). Vous ne recevrez plus nos messages.','nl_bad'=>'Lien invalide ou expiré.','nl_err'=>'Adresse e-mail invalide.','nl_exists'=>'Cette adresse est déjà inscrite.',
 'search_title'=>'Recherche','results_for'=>'Résultats pour','props'=>'Propositions','articles'=>'Articles','videos'=>'Vidéos',
 'nf_title'=>'Page introuvable','nf_text'=>"Cette page n'existe pas ou n'est plus disponible.",
 'campaign_cta'=>'Crée ton flyer','nl_band_t'=>'Restez informé','nl_band_p'=>'Un message quand une nouvelle proposition, vidéo ou actualité est publiée.',
 'draft'=>'BROUILLON — visible uniquement par les administrateurs','views'=>'vues',
 'type_actualite'=>'Actualité','type_communique'=>'Communiqué','type_analyse'=>'Analyse','type_opinion'=>'Opinion','type_tribune'=>'Tribune','type_proposition'=>'Proposition','type_activite'=>'Activité','type_interview'=>'Interview','type_rapport'=>'Rapport','type_discours'=>'Discours',
 'type_reforme'=>'Réforme','type_projet-de-loi'=>'Projet de loi','type_programme'=>'Programme','type_initiative'=>'Initiative',
],
'ht' => [
 'read_more'=>'Li plis','see_all'=>'Wè tout','search'=>'Chèche','search_ph'=>'Chèche yon mo, yon tit…','no_result'=>'Pa gen rezilta pou kounye a.','home'=>'Akèy','all'=>'Tout',
 'published'=>'Pibliye','category'=>'Kategori','type'=>'Kalite','tags'=>'Mo kle','related'=>'Li tou','share'=>'Pataje','back'=>'Tounen','page'=>'Paj','next'=>'Pwochen','prev'=>'Anvan',
 'prog_title'=>'Pwogram','prog_kicker'=>'Pwogram politik','prog_lead'=>'Pwopozisyon konkrè, òganize pa chapit, pou chanje lavi Ayisyen yo. Li yo sou entènèt oswa telechaje pwogram konplè a.',
 'read_online'=>'Li sou entènèt','download_pdf'=>'Telechaje pwogram nan (PDF)','pdf_note'=>'Vèsyon pou enprime ak kouvèti, sòmè ak nimewo paj.','chapters'=>'Chapit','proposals_n'=>'pwopozisyon',
 'prog_empty_t'=>'Pwogram nan ap ekri','prog_empty'=>'Pwopozisyon yo ap pibliye isit la depi yo valide. Pandan sa a, patisipe : vwa w konte.',
 'props_title'=>'Pwopozisyon','props_lead'=>'Refòm, pwojè lwa, inisyativ : chak pwopozisyon montre pwoblèm nan, analiz la ak solisyon an.',
 'problem'=>'Pwoblèm idantifye','analysis'=>'Analiz','solution'=>'Solisyon pwopoze','objectives'=>'Objektif','beneficiaries'=>'Benefisyè','impact'=>'Enpak espere','implementation'=>'Mizanwo',
 'dl_prop'=>'Telechaje pwopozisyon sa a (PDF)','print'=>'Enprime',
 'news_title'=>'Nouvèl','news_kicker'=>'Nouvèl & piblikasyon','news_lead'=>'Kominike, analiz, tribin, entèvyou ak diskou.','featured'=>'Alafwa','latest'=>'Dènye piblikasyon','most_read'=>'Pi li yo','recommended'=>'Rekòmande',
 'videos_title'=>'Videyotèk','videos_lead'=>'Diskou, entèvyou, eksplikasyon ak aktivite an videyo.','video_related'=>'Videyo ki gen rapò','transcript'=>'Rezime / transkripsyon','play'=>'Jwe videyo a','yt_note'=>'Videyo a ebèje sou YouTube.',
 'citizen_title'=>'Espas sitwayen','citizen_kicker'=>'Patisipe','citizen_lead'=>'Yon lide, yon pwoblèm nan komin ou, yon refòm pou pwopoze ? Ekri nou. Mesaj ou lèt epi klase.',
 't_idea'=>'Pwopoze yon lide','t_reform'=>'Pwopoze yon refòm','t_problem'=>'Siyale yon pwoblèm','t_commune'=>'Komin mwen','t_topic'=>'Pwopoze yon sijè','t_message'=>'Voye yon mesaj','t_consult'=>'Konsiltasyon',
 'f_name'=>'Non (opsyonèl)','f_dept'=>'Depatman (opsyonèl)','f_commune'=>'Komin (opsyonèl)','f_contact'=>'Imèl oswa telefòn (opsyonèl)','f_cat'=>'Kategori','f_msg'=>'Mesaj ou','f_consent'=>'Mwen dakò pou ekip la li epi trete mesaj mwen. Enfòmasyon pèsonèl mwen p ap janm afiche piblikman.',
 'send'=>'Voye','thanks'=>'Mèsi ! Nou resevwa mesaj ou.','err_msg'=>'Mesaj ou twò kout (omwen 10 karaktè).','err_consent'=>'Tanpri aksepte konsantman an.','err_spam'=>'Voye a bloke. Eseye ankò nan kèk segonn.','err_rate'=>'Twòp mesaj voye. Eseye pita.','privacy'=>'Done w yo pwoteje epi yo p ap janm pibliye.',
 'consult_none'=>'Pa gen okenn konsiltasyon ouvè pou kounye a. Ou ka voye yon mesaj kanmenm.',
 'nl_title'=>'Bilten','nl_lead'=>'Resevwa nouvo atik, pwopozisyon, videyo, aktivite ak anons enpòtan.','f_email'=>'Imèl ou','nl_consent'=>'Mwen dakò pou m resevwa bilten Roosevelt Forestal. Mwen ka dezabone m nenpòt ki lè.','nl_subscribe'=>'Abòne',
 'nl_sent'=>'Prèske fini ! Gade bwat imèl ou pou konfime abònman an.','nl_confirmed'=>'Abònman konfime. Mèsi !','nl_unsub'=>'Ou dezabone. Ou p ap resevwa mesaj nou yo ankò.','nl_bad'=>'Lyen an pa bon oswa li ekspire.','nl_err'=>'Adrès imèl la pa bon.','nl_exists'=>'Adrès sa a deja abòne.',
 'search_title'=>'Rechèch','results_for'=>'Rezilta pou','props'=>'Pwopozisyon','articles'=>'Atik','videos'=>'Videyo',
 'nf_title'=>'Paj la pa jwenn','nf_text'=>'Paj sa a pa egziste oswa li pa disponib ankò.',
 'campaign_cta'=>'Kreye flyer pa w','nl_band_t'=>'Rete enfòme','nl_band_p'=>'Yon mesaj lè yon nouvo pwopozisyon, videyo oswa nouvèl pibliye.',
 'draft'=>'BWOUYON — sèlman administratè yo ka wè l','views'=>'vi',
 'type_actualite'=>'Nouvèl','type_communique'=>'Kominike','type_analyse'=>'Analiz','type_opinion'=>'Opinyon','type_tribune'=>'Tribin','type_proposition'=>'Pwopozisyon','type_activite'=>'Aktivite','type_interview'=>'Entèvyou','type_rapport'=>'Rapò','type_discours'=>'Diskou',
 'type_reforme'=>'Refòm','type_projet-de-loi'=>'Pwojè lwa','type_programme'=>'Pwogram','type_initiative'=>'Inisyativ',
],
'en' => [
 'read_more'=>'Read more','see_all'=>'See all','search'=>'Search','search_ph'=>'Search a keyword, a title…','no_result'=>'No results yet.','home'=>'Home','all'=>'All',
 'published'=>'Published','category'=>'Category','type'=>'Type','tags'=>'Tags','related'=>'Related','share'=>'Share','back'=>'Back','page'=>'Page','next'=>'Next','prev'=>'Previous',
 'prog_title'=>'Programme','prog_kicker'=>'Political programme','prog_lead'=>'Concrete proposals, organised in chapters, to change the lives of Haitians. Read them online or download the full programme.',
 'read_online'=>'Read online','download_pdf'=>'Download the programme (PDF)','pdf_note'=>'Printable version with cover, table of contents and page numbers.','chapters'=>'Chapters','proposals_n'=>'proposal(s)',
 'prog_empty_t'=>'The programme is being written','prog_empty'=>'Proposals will be published here once validated. Meanwhile, take part: your voice matters.',
 'props_title'=>'Proposals','props_lead'=>'Reforms, bills, initiatives: each proposal sets out the problem, the analysis and the solution.',
 'problem'=>'Identified problem','analysis'=>'Analysis','solution'=>'Proposed solution','objectives'=>'Objectives','beneficiaries'=>'Beneficiaries','impact'=>'Expected impact','implementation'=>'Implementation',
 'dl_prop'=>'Download this proposal (PDF)','print'=>'Print',
 'news_title'=>'News','news_kicker'=>'News & publications','news_lead'=>'Press releases, analyses, op-eds, interviews and speeches.','featured'=>'Featured','latest'=>'Latest publications','most_read'=>'Most read','recommended'=>'Recommended',
 'videos_title'=>'Video library','videos_lead'=>'Speeches, interviews, explainers and activities on video.','video_related'=>'Related videos','transcript'=>'Summary / transcript','play'=>'Play video','yt_note'=>'The video is hosted on YouTube.',
 'citizen_title'=>'Citizen space','citizen_kicker'=>'Get involved','citizen_lead'=>'An idea, a problem in your commune, a reform to suggest? Write to us. Your message is read and filed.',
 't_idea'=>'Suggest an idea','t_reform'=>'Propose a reform','t_problem'=>'Report a problem','t_commune'=>'My commune','t_topic'=>'Suggest a topic','t_message'=>'Send a message','t_consult'=>'Consultation',
 'f_name'=>'Name (optional)','f_dept'=>'Department (optional)','f_commune'=>'Commune (optional)','f_contact'=>'Email or phone (optional)','f_cat'=>'Category','f_msg'=>'Your message','f_consent'=>'I agree that my message may be read and processed by the team. My personal information will never be shown publicly.',
 'send'=>'Send','thanks'=>'Thank you! Your message has been received.','err_msg'=>'Your message is too short (10 characters minimum).','err_consent'=>'Please accept the consent.','err_spam'=>'Submission blocked. Try again in a few seconds.','err_rate'=>'Too many messages sent. Try again later.','privacy'=>'Your data is protected and never published.',
 'consult_none'=>'No consultation is open right now. You can still send a message.',
 'nl_title'=>'Newsletter','nl_lead'=>'Get new articles, proposals, videos, activities and important announcements.','f_email'=>'Your email','nl_consent'=>'I agree to receive the Roosevelt Forestal newsletter. I can unsubscribe at any time.','nl_subscribe'=>'Subscribe',
 'nl_sent'=>'Almost done! Check your inbox to confirm your subscription.','nl_confirmed'=>'Subscription confirmed. Thank you!','nl_unsub'=>'You are unsubscribed. You will no longer receive our messages.','nl_bad'=>'Invalid or expired link.','nl_err'=>'Invalid email address.','nl_exists'=>'This address is already subscribed.',
 'search_title'=>'Search','results_for'=>'Results for','props'=>'Proposals','articles'=>'Articles','videos'=>'Videos',
 'nf_title'=>'Page not found','nf_text'=>'This page does not exist or is no longer available.',
 'campaign_cta'=>'Create your flyer','nl_band_t'=>'Stay informed','nl_band_p'=>'A message when a new proposal, video or news item is published.',
 'draft'=>'DRAFT — visible to administrators only','views'=>'views',
 'type_actualite'=>'News','type_communique'=>'Press release','type_analyse'=>'Analysis','type_opinion'=>'Opinion','type_tribune'=>'Op-ed','type_proposition'=>'Proposal','type_activite'=>'Activity','type_interview'=>'Interview','type_rapport'=>'Report','type_discours'=>'Speech',
 'type_reforme'=>'Reform','type_projet-de-loi'=>'Bill','type_programme'=>'Programme','type_initiative'=>'Initiative',
],
];

function lang(): string {
    static $l = null;
    if ($l !== null) return $l;
    $c = $_COOKIE['rf_lang'] ?? '';
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'ht', 'en'], true)) { $c = $_GET['lang']; if (!headers_sent()) setcookie('rf_lang', $c, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']); }
    return $l = in_array($c, ['fr', 'ht', 'en'], true) ? $c : 'fr';
}
function t(string $k): string { $T = $GLOBALS['T']; return $T[lang()][$k] ?? $T['fr'][$k] ?? $k; }
function type_label(string $type): string { return t('type_' . $type); }

/* ------------------------------------------------------------ Markdown sûr (HTML échappé d'abord) */
function md_inline(string $s): string {
    $s = e($s);
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $u = html_entity_decode($m[2], ENT_QUOTES);
        if (!preg_match('~^(https?://|mailto:|/|#)~i', $u)) return $m[0];
        $ext = preg_match('~^https?://~i', $u) ? ' target="_blank" rel="noopener noreferrer"' : '';
        return '<a href="' . e($u) . '"' . $ext . '>' . $m[1] . '</a>';
    }, $s);
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/s', '<em>$1</em>', $s);
    return $s;
}
function md(?string $text): string {
    $text = trim(str_replace(["\r\n", "\r"], "\n", (string)$text)); if ($text === '') return '';
    $out = ''; $blocks = preg_split('/\n{2,}/', $text);
    foreach ($blocks as $b) {
        $b = trim($b); if ($b === '') continue;
        if (preg_match('/^(#{2,3})\s+(.+)$/', $b, $m)) { $n = strlen($m[1]); $out .= "<h$n>" . md_inline($m[2]) . "</h$n>"; continue; }
        if (preg_match('/^---+$/', $b)) { $out .= '<hr>'; continue; }
        if (preg_match('/^>\s?/', $b)) { $out .= '<blockquote>' . md_inline(preg_replace('/^>\s?/m', '', $b)) . '</blockquote>'; continue; }
        $lines = explode("\n", $b);
        if (preg_match('/^[-*]\s+/', $lines[0])) { $out .= '<ul>'; foreach ($lines as $l) $out .= '<li>' . md_inline(preg_replace('/^[-*]\s+/', '', $l)) . '</li>'; $out .= '</ul>'; continue; }
        if (preg_match('/^\d+[.)]\s+/', $lines[0])) { $out .= '<ol>'; foreach ($lines as $l) $out .= '<li>' . md_inline(preg_replace('/^\d+[.)]\s+/', '', $l)) . '</li>'; $out .= '</ol>'; continue; }
        $out .= '<p>' . implode('<br>', array_map('md_inline', $lines)) . '</p>';
    }
    return $out;
}
/** texte brut structuré (pour PDF) : tableau de blocs ['h'|'p'|'li', texte] */
function md_blocks(?string $text): array {
    $text = trim(str_replace(["\r\n", "\r"], "\n", (string)$text)); if ($text === '') return [];
    $out = [];
    foreach (preg_split('/\n{2,}/', $text) as $b) {
        $b = trim($b); if ($b === '') continue;
        if (preg_match('/^#{2,3}\s+(.+)$/', $b, $m)) { $out[] = ['h', md_plain($m[1])]; continue; }
        foreach (explode("\n", $b) as $l) {
            if (preg_match('/^([-*]|\d+[.)])\s+(.+)$/', $l, $m)) $out[] = ['li', md_plain($m[2])];
            else $out[] = ['p', md_plain(preg_replace('/^>\s?/', '', $l))];
        }
    }
    return $out;
}
function md_plain(string $s): string { $s = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $s); return trim(preg_replace('/[*_`]+/', '', $s)); }

/* ------------------------------------------------------------ mise en page */
function version(): string { return trim((string)@file_get_contents(ROOT . '/VERSION')) ?: '1'; }
function partial(string $name): string { return str_replace(['{{b}}', '{{v}}'], [base_path() . '/', version()], (string)file_get_contents(ROOT . '/partials/' . $name . '.html')); }
function jsonld_tag(array $d): string { return $d ? '<script type="application/ld+json">' . json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' : ''; }

function page(array $o): void {
    $o += ['title' => 'Roosevelt Forestal', 'desc' => '', 'path' => '', 'image' => null, 'type' => 'website', 'body' => '', 'jsonld' => [], 'robots' => 'index,follow', 'head' => '', 'scripts' => '', 'status' => 200];
    http_response_code($o['status']);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin'); header('X-Frame-Options: SAMEORIGIN');
    $canon = site_url($o['path']);
    $fullTitle = $o['title'] === 'Roosevelt Forestal' ? $o['title'] : $o['title'] . ' | Roosevelt Forestal';
    $desc = trunc((string)$o['desc'], 200);
    $img = abs_asset($o['image']);
    $lang = lang();
    $loc = ['fr' => 'fr_FR', 'ht' => 'ht_HT', 'en' => 'en_US'][$lang];
    echo '<!DOCTYPE html><html lang="' . $lang . '" data-server="1"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . e($fullTitle) . '</title><meta name="description" content="' . e($desc) . '"><meta name="robots" content="' . e($o['robots']) . '">';
    echo '<link rel="canonical" href="' . e($canon) . '">';
    echo '<meta property="og:type" content="' . e($o['type']) . '"><meta property="og:site_name" content="Roosevelt Forestal"><meta property="og:locale" content="' . $loc . '">';
    echo '<meta property="og:title" content="' . e($o['title']) . '"><meta property="og:description" content="' . e($desc) . '"><meta property="og:url" content="' . e($canon) . '">';
    echo '<meta property="og:image" content="' . e($img) . '"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">';
    echo '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="' . e($o['title']) . '"><meta name="twitter:description" content="' . e($desc) . '"><meta name="twitter:image" content="' . e($img) . '">';
    echo jsonld_tag($o['jsonld']) . partial('head') . $o['head'] . '</head><body>';
    echo partial('header') . '<main id="main">' . $o['body'] . '</main>' . partial('footer') . partial('scripts') . $o['scripts'] . '</body></html>';
    exit;
}
function not_found(): void {
    page(['title' => t('nf_title'), 'status' => 404, 'robots' => 'noindex', 'body' => '<section class="page-hero"><div class="wrap"><h1>404</h1><p class="lead">' . e(t('nf_text')) . '</p><p class="mt-2"><a class="btn btn-gold" href="' . e(url()) . '">' . e(t('home')) . '</a></p></div></section>']);
}
function hero(string $kicker, string $h1, string $lead = '', string $extra = ''): string {
    return '<section class="page-hero"><div class="wrap"><p class="crumb">' . e($kicker) . '</p><h1>' . e($h1) . '</h1>' . ($lead ? '<p class="lead">' . e($lead) . '</p>' : '') . $extra . '</div></section>';
}

/* ------------------------------------------------------------ composants */
function cover_url(array $r): string {
    if (!empty($r['cover'])) return url($r['cover']);
    if (!empty($r['youtube_id'])) return 'https://i.ytimg.com/vi/' . $r['youtube_id'] . '/hqdefault.jpg';
    return '';
}
function media_box(string $src, string $alt, string $ph = 'RF', string $extraClass = ''): string {
    $img = $src ? '<img src="' . e($src) . '" alt="' . e($alt) . '" loading="lazy" decoding="async">' : '';
    return '<div class="media ' . ($src ? 'has-img ' : '') . $extraClass . '">' . $img . '<div class="ph"><span>' . e($ph) . '</span></div></div>';
}
function card(array $r, bool $big = false): string {
    $lang = lang(); $href = url(content_path($r));
    $label = $r['kind'] === 'video' ? ($r['cat_name'] ? cat_name(['name' => $r['cat_name'], 'name_ht' => $r['cat_ht'] ?? '', 'name_en' => $r['cat_en'] ?? ''], $lang) : '') : type_label((string)$r['type']);
    $cat = ($r['kind'] !== 'video' && $r['cat_name']) ? cat_name(['name' => $r['cat_name'], 'name_ht' => $r['cat_ht'] ?? '', 'name_en' => $r['cat_en'] ?? ''], $lang) : '';
    $cls = 'post-card' . ($big ? ' big' : '') . ($r['kind'] === 'video' ? ' vid-card' : '');
    $draft = $r['status'] !== 'published' ? ' <span class="badge draft">draft</span>' : '';
    return '<article class="' . $cls . '"><a href="' . e($href) . '" class="media-link" tabindex="-1" aria-hidden="true">' . media_box(cover_url($r), '', mb_strtoupper(mb_substr($r['title'], 0, 1))) . '</a><div class="body"><div class="chips"><span class="badge">' . e($label) . '</span>' . ($cat ? '<span class="chip">' . e($cat) . '</span>' : '') . $draft . '</div><h3><a href="' . e($href) . '">' . e($r['title']) . '</a></h3><p>' . e(trunc((string)$r['summary'], $big ? 220 : 130)) . '</p><div class="date">' . e(fmt_date($r['publish_at'] ?: $r['created_at'], $lang)) . '</div></div></article>';
}
function share_bar(string $path, string $title, string $text, ?string $image, string $ref): string {
    return '<div class="share-wrap"><span class="share-label">' . e(t('share')) . '</span><div class="share-bar" data-url="' . e(site_url($path)) . '" data-title="' . e($title) . '" data-text="' . e(trunc($text, 140)) . '" data-image="' . e($image ? abs_asset($image) : '') . '" data-ref="' . e($ref) . '"></div></div>';
}
function pager(int $page, bool $more, array $params, string $path): string {
    if ($page <= 1 && !$more) return '';
    $link = function (int $p, string $label, string $cls = '') use ($params, $path) { $q = $params; $q['page'] = $p; if ($p <= 1) unset($q['page']); $qs = http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null)); return '<a href="' . e(url($path) . ($qs ? '?' . $qs : '')) . '" class="' . $cls . '">' . $label . '</a>'; };
    $h = '<nav class="pager" aria-label="Pagination">';
    if ($page > 1) $h .= $link($page - 1, '← ' . e(t('prev')));
    $h .= '<span class="cur" aria-current="page">' . $page . '</span>';
    if ($more) $h .= $link($page + 1, e(t('next')) . ' →');
    return $h . '</nav>';
}
function search_box(string $action, string $q = '', array $hidden = []): string {
    $h = ''; foreach ($hidden as $k => $v) if ($v !== '') $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return '<form class="search-box" role="search" method="get" action="' . e(url($action)) . '">' . $h . '<input type="search" name="q" value="' . e($q) . '" placeholder="' . e(t('search_ph')) . '" aria-label="' . e(t('search')) . '" maxlength="80"><button type="submit">' . e(t('search')) . '</button></form>';
}
function newsletter_band(): string {
    return '<section class="news-band section-tight"><div class="wrap"><div class="two-col"><div><h2>' . e(t('nl_band_t')) . '</h2><p class="lead mt-1">' . e(t('nl_band_p')) . '</p></div>' . newsletter_form('band') . '</div></div></section>';
}
function newsletter_form(string $src = 'page', string $email = ''): string {
    return '<form class="news-form" method="post" action="' . e(url('newsletter')) . '"><input type="hidden" name="src" value="' . e($src) . '"><input type="hidden" name="t" value="' . e(form_token()) . '"><div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div><input type="email" name="email" value="' . e($email) . '" required placeholder="' . e(t('f_email')) . '" aria-label="' . e(t('f_email')) . '" autocomplete="email"><input type="hidden" name="consent" value="1"><button class="btn btn-gold" type="submit">' . e(t('nl_subscribe')) . '</button><p class="f-hint" style="flex-basis:100%">' . e(t('nl_consent')) . '</p></form>';
}
function video_player(string $yid, string $title): string {
    $thumb = 'https://i.ytimg.com/vi/' . $yid . '/hqdefault.jpg';
    return '<div class="video-embed" data-yt="' . e($yid) . '"><img src="' . e($thumb) . '" alt="" loading="lazy"><button type="button" class="play" aria-label="' . e(t('play')) . ' — ' . e($title) . '"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></span></button></div>';
}
function video_script(): string {
    return '<script>document.addEventListener("click",function(e){var b=e.target.closest(".video-embed .play");if(!b)return;var w=b.parentNode,id=w.getAttribute("data-yt"),f=document.createElement("iframe");f.src="https://www.youtube-nocookie.com/embed/"+id+"?autoplay=1&rel=0";f.allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture;web-share";f.allowFullscreen=true;f.title="YouTube";w.innerHTML="";w.appendChild(f);});</script>';
}
function draft_banner(array $r): string {
    return $r['status'] !== 'published' || ($r['publish_at'] && $r['publish_at'] > now()) ? '<div class="draft-banner">' . e(t('draft')) . ' — <a href="' . e(url('admin/content/edit?id=' . $r['id'])) . '">Éditer</a></div>' : '';
}
