<?php
/**
 * PDF professionnels (FPDF, polices standard Helvetica) :
 *  - programme complet : couverture, sommaire paginé, chapitres, numéros de page
 *  - fiche d'une proposition
 *  - dossier de presse
 */
declare(strict_types=1);
require_once ROOT . '/app/vendor/fpdf/fpdf.php';

class RFPDF extends FPDF {
    public $footerText = 'rooseveltforestal.com';
    public $skipPages = [];
    function Footer() {
        if (in_array($this->PageNo(), $this->skipPages, true)) return;
        $this->SetY(-14); $this->SetDrawColor(220, 220, 225); $this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
        $this->SetFont('Helvetica', '', 8); $this->SetTextColor(120, 120, 130);
        $this->Cell(0, 8, pdf_txt($this->footerText), 0, 0, 'L');
        $this->Cell(0, 8, 'Page ' . $this->PageNo(), 0, 0, 'R');
    }
}
function pdf_txt($s): string {
    $s = (string)$s;
    $o = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
    return $o === false ? utf8_decode($s) : $o;
}
function pdf_send(string $bytes, string $name): void {
    header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($bytes)); header('Cache-Control: private, max-age=0, must-revalidate'); echo $bytes; exit;
}
function pdf_new(string $title): RFPDF {
    $p = new RFPDF('P', 'mm', 'A4'); $p->SetMargins(22, 22, 22); $p->SetAutoPageBreak(true, 20);
    $p->SetTitle(pdf_txt($title), false); $p->SetAuthor('Roosevelt Forestal', false); $p->SetCreator('rooseveltforestal.com', false);
    $p->AliasNbPages(); return $p;
}
function pdf_cover(RFPDF $p, string $kicker, string $title, string $sub): void {
    $p->AddPage(); $p->skipPages[] = $p->PageNo();
    $p->SetFillColor(6, 15, 51); $p->Rect(0, 0, 210, 297, 'F');
    $p->SetFillColor(210, 16, 52); $p->Rect(0, 0, 210, 6, 'F'); $p->Rect(22, 96, 36, 2.2, 'F');
    $p->SetFillColor(0, 32, 159); $p->Rect(0, 291, 105, 6, 'F'); $p->SetFillColor(210, 16, 52); $p->Rect(105, 291, 105, 6, 'F');
    $p->SetTextColor(255, 140, 160); $p->SetFont('Helvetica', 'B', 10); $p->SetXY(22, 70); $p->Cell(0, 6, pdf_txt(mb_strtoupper($kicker)));
    $p->SetTextColor(255, 255, 255); $p->SetFont('Helvetica', 'B', 38); $p->SetXY(22, 104); $p->MultiCell(166, 16, pdf_txt(mb_strtoupper($title)), 0, 'L');
    $p->SetFont('Helvetica', '', 14); $p->SetTextColor(215, 220, 240); $p->SetX(22); $p->Ln(4); $p->MultiCell(166, 7, pdf_txt($sub), 0, 'L');
    $p->SetXY(22, 250); $p->SetFont('Helvetica', 'B', 13); $p->SetTextColor(255, 255, 255); $p->Cell(0, 7, 'Roosevelt Forestal');
    $p->SetXY(22, 258); $p->SetFont('Helvetica', '', 10); $p->SetTextColor(200, 205, 230); $p->Cell(0, 6, 'rooseveltforestal.com');
    $p->SetXY(22, 265); $p->Cell(0, 6, pdf_txt(fmt_date(now(), lang())));
}
function pdf_h1(RFPDF $p, string $t): void { $p->SetTextColor(6, 15, 51); $p->SetFont('Helvetica', 'B', 22); $p->MultiCell(0, 10, pdf_txt($t)); $p->SetFillColor(210, 16, 52); $p->Rect($p->GetX(), $p->GetY() + 1, 22, 1.6, 'F'); $p->Ln(8); }
function pdf_label(RFPDF $p, string $t): void { if ($p->GetY() > 255) $p->AddPage(); $p->Ln(2); $p->SetFont('Helvetica', 'B', 9); $p->SetTextColor(210, 16, 52); $p->Cell(0, 6, pdf_txt(mb_strtoupper($t)), 0, 1); }
function pdf_body(RFPDF $p, string $md): void {
    $p->SetTextColor(35, 35, 45);
    foreach (md_blocks($md) as [$k, $tx]) {
        if ($k === 'h') { $p->SetFont('Helvetica', 'B', 11); $p->Ln(1); $p->MultiCell(0, 5.8, pdf_txt($tx)); continue; }
        $p->SetFont('Helvetica', '', 10.5);
        if ($k === 'li') { $x = $p->GetX(); $p->SetX($x + 4); $p->Cell(4, 5.6, chr(149)); $p->MultiCell(0, 5.6, pdf_txt($tx)); $p->SetX($x); }
        else { $p->MultiCell(0, 5.6, pdf_txt($tx)); $p->Ln(1.2); }
    }
}
function pdf_proposal_block(RFPDF $p, array $r): void {
    $x = extra_of($r);
    if ($p->GetY() > 235) $p->AddPage();
    $p->SetTextColor(6, 15, 51); $p->SetFont('Helvetica', 'B', 15); $p->MultiCell(0, 7.2, pdf_txt($r['title']));
    $p->SetFont('Helvetica', '', 9); $p->SetTextColor(120, 120, 130);
    $p->Cell(0, 6, pdf_txt(type_label((string)$r['type']) . '  |  ' . fmt_date($r['publish_at'] ?: $r['created_at'], lang())), 0, 1);
    if (trim((string)$r['summary']) !== '') { $p->SetFont('Helvetica', 'I', 11); $p->SetTextColor(60, 60, 75); $p->MultiCell(0, 5.8, pdf_txt($r['summary'])); $p->Ln(1); }
    foreach (PROPOSAL_FIELDS as $f) { if (trim((string)($x[$f] ?? '')) === '') continue; pdf_label($p, t($f)); pdf_body($p, (string)$x[$f]); }
    if (trim((string)$r['body']) !== '') { $p->Ln(2); pdf_body($p, (string)$r['body']); }
    $p->Ln(4); $p->SetDrawColor(225, 225, 230); $p->Line($p->GetX(), $p->GetY(), 188, $p->GetY()); $p->Ln(6);
}

/* ---------------------------------------------------------------- programme complet */
function pdf_program(): string {
    $lang = lang();
    $all = rows("SELECT c.*, cat.name AS cname, cat.name_ht AS cht, cat.name_en AS cen, cat.slug AS cslug FROM content c JOIN categories cat ON cat.id=c.category_id WHERE c.kind='proposal' AND " . visible_sql('c') . ' ORDER BY cat.sort, c.featured DESC, COALESCE(c.publish_at,c.created_at)');
    $chapters = []; foreach ($all as $r) $chapters[$r['category_id']][] = $r;
    $build = function (array $pageMap) use ($chapters, $lang): array {
        $p = pdf_new(t('prog_title') . ' — Roosevelt Forestal'); $p->footerText = 'Roosevelt Forestal — ' . t('prog_title') . ' — rooseveltforestal.com';
        pdf_cover($p, t('prog_kicker'), t('prog_title'), 'Roosevelt Forestal');
        $map = [];
        // sommaire
        $p->AddPage(); pdf_h1($p, 'Sommaire');
        $i = 0;
        foreach ($chapters as $items) {
            $i++; $c = $items[0]; $nm = cat_name(['name' => $c['cname'], 'name_ht' => $c['cht'], 'name_en' => $c['cen']], $lang);
            if ($p->GetY() > 255) $p->AddPage();
            $p->SetFont('Helvetica', 'B', 11); $p->SetTextColor(6, 15, 51);
            $label = sprintf('%02d  ', $i) . $nm; $num = (string)($pageMap['c' . $c['category_id']] ?? '');
            $p->Cell(150, 7, pdf_txt($label)); $p->Cell(0, 7, $num, 0, 1, 'R');
            foreach ($items as $r) {
                if ($p->GetY() > 262) $p->AddPage();
                $p->SetFont('Helvetica', '', 9.5); $p->SetTextColor(70, 70, 85); $p->SetX(32);
                $p->Cell(140, 5.6, pdf_txt(trunc($r['title'], 88))); $p->Cell(0, 5.6, (string)($pageMap['p' . $r['id']] ?? ''), 0, 1, 'R');
            }
            $p->Ln(1.5);
        }
        if (!$chapters) { $p->SetFont('Helvetica', '', 11); $p->MultiCell(0, 6, pdf_txt(t('prog_empty'))); }
        // chapitres
        $i = 0;
        foreach ($chapters as $items) {
            $i++; $c = $items[0]; $nm = cat_name(['name' => $c['cname'], 'name_ht' => $c['cht'], 'name_en' => $c['cen']], $lang);
            $p->AddPage(); $map['c' . $c['category_id']] = $p->PageNo();
            $p->SetFont('Helvetica', 'B', 11); $p->SetTextColor(210, 16, 52); $p->Cell(0, 8, pdf_txt('CHAPITRE ' . sprintf('%02d', $i)), 0, 1);
            pdf_h1($p, $nm);
            foreach ($items as $r) { $map['p' . $r['id']] = $p->PageNo(); pdf_proposal_block($p, $r); }
        }
        return [$p, $map];
    };
    [, $map] = $build([]);             // passe 1 : mesure des pages
    [$p] = $build($map);               // passe 2 : sommaire complet
    return $p->Output('S');
}

/* ---------------------------------------------------------------- fiche proposition */
function pdf_proposal(array $r): string {
    $p = pdf_new($r['title']); $p->footerText = 'Roosevelt Forestal — rooseveltforestal.com/proposition/' . $r['slug'];
    $p->AddPage();
    $p->SetFillColor(6, 15, 51); $p->Rect(0, 0, 210, 44, 'F'); $p->SetFillColor(210, 16, 52); $p->Rect(0, 44, 210, 2, 'F');
    $p->SetXY(22, 12); $p->SetFont('Helvetica', 'B', 9); $p->SetTextColor(255, 140, 160);
    $cat = $r['cat_name'] ? cat_name(['name' => $r['cat_name'], 'name_ht' => $r['cat_ht'] ?? '', 'name_en' => $r['cat_en'] ?? ''], lang()) : '';
    $p->Cell(0, 6, pdf_txt(mb_strtoupper($cat . ($cat ? '  |  ' : '') . type_label((string)$r['type']))), 0, 1);
    $p->SetX(22); $p->SetFont('Helvetica', 'B', 17); $p->SetTextColor(255, 255, 255); $p->MultiCell(166, 8, pdf_txt($r['title']));
    $p->SetY(56); pdf_proposal_block($p, ['title' => '', 'summary' => $r['summary'], 'extra' => $r['extra'], 'body' => $r['body'], 'type' => $r['type'], 'publish_at' => $r['publish_at'], 'created_at' => $r['created_at']] + $r);
    return $p->Output('S');
}

/* ---------------------------------------------------------------- dossier de presse */
function pdf_press_kit(): string {
    $p = pdf_new('Dossier de presse — Roosevelt Forestal'); $p->footerText = 'Dossier de presse — Roosevelt Forestal — rooseveltforestal.com';
    pdf_cover($p, 'Presse & Media', 'Dossier de presse', 'Roosevelt Forestal — Entrepreneur · Ingénieur informatique · Fondateur de GOVIBE Innovation Hub');
    $p->AddPage(); pdf_h1($p, 'Biographie courte'); pdf_body($p, (string)setting('bio_short'));
    $p->Ln(4); pdf_h1($p, 'Biographie complète'); pdf_body($p, (string)setting('bio_long'));
    $p->AddPage(); pdf_h1($p, 'Repères');
    foreach ([['Fonction', 'Fondateur & CEO, GOVIBE Innovation Hub'], ['Profil', 'Entrepreneur · Ingénieur informatique · Leader public'], ['Projets', 'GOVIBE · TAGTOA · Tchekela.com · Klasyo.org · HOSPIFY'], ['Domaines', 'Intelligence artificielle · Transformation digitale · Éducation · Entrepreneuriat'], ['Langues', 'Français · Kreyòl ayisyen · English']] as [$k, $v]) {
        $p->SetFont('Helvetica', 'B', 10); $p->SetTextColor(210, 16, 52); $p->Cell(38, 7, pdf_txt($k)); $p->SetFont('Helvetica', '', 10.5); $p->SetTextColor(35, 35, 45); $p->MultiCell(0, 7, pdf_txt($v));
    }
    $p->Ln(6); pdf_h1($p, 'Contact presse');
    $p->SetFont('Helvetica', '', 11); $p->SetTextColor(35, 35, 45);
    $p->MultiCell(0, 7, pdf_txt("E-mail : " . setting('press_email') . "\nSite : rooseveltforestal.com\nLinkedIn : linkedin.com/in/roosevelt-forestal-530864145"));
    return $p->Output('S');
}
