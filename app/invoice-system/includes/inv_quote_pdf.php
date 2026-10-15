<?php
/**
 * invoice-system/includes/inv_quote_pdf.php — quotation PDF (Dompdf, A4).
 *
 * Layout reproduces the owner's "Quote Sample.pdf" (Word, A4 595.32 x 841.92pt),
 * measured in points:
 *   letterhead box x 30.5-543.5, y 28.5-106, logo cell divider at x 182;
 *   text left edge x 25.2, right edge x 535.7 (body Arial 11pt, pitch 12.65);
 *   price table x 30.5-563.5, columns at 379.5 (Rate) and 478.5 (Special Rate);
 *   terms 10pt (pitch 13.2), closing 9pt (pitch 12.0), both justified;
 *   signature box x 30.5-566, divider at x 312, height 83.5.
 *   Owner decision (15/10/2026): documents are computer generated, so the
 *   issuer side of the box prints the entity name and the entity's footer
 *   text ("This is computer generated and NO SIGNATURE required") instead
 *   of "Approved Signatory" and a signatory name. The client's
 *   "Accepted by" side is kept for the client's acceptance.
 * Fonts: Helvetica (Arial metrics) as in the invoice PDF.
 *
 * Security: every value is escaped with htmlspecialchars before the two
 * formatting rules are applied (**bold** and, in payment terms only,
 * ordinal superscripts). The logo and the stamp are embedded as base64 data
 * URIs read server-side (no URLs, no paths in the HTML). The stamp is read
 * from invoice-system/private/ (deny-all) and is printed ONLY when the caller
 * passes it (finalised/sent quotations); draft previews never carry it.
 */

require_once __DIR__ . '/inv_money.php';
require_once __DIR__ . '/inv_pdf.php';

const INV_QUOTE_DRAFT_WATERMARK = 'DRAFT, NOT SENT';
const INV_QUOTE_PAPER = 'A4';          // layout measured on the A4 sample; the invoice paper option does not apply
const INV_QPDF_LOGO_W = 82.5;   // visible logo size on the sample
const INV_QPDF_LOGO_H = 59.5;
const INV_QPDF_STAMP_W = 47.0;  // visible stamp size on the sample
const INV_QPDF_STAMP_H = 47.0;

/** Escape, then **bold**, then line breaks. $ordinals: 2nd -> 2<sup>nd</sup>. */
function inv_qpdf_rich(?string $s, bool $ordinals = false): string
{
    $t = inv_pdf_e(str_replace(["\r\n", "\r"], "\n", (string) $s));
    $t = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $t);
    if ($ordinals) {
        $t = preg_replace('/\b(\d+)(st|nd|rd|th)\b/', '$1<sup>$2</sup>', $t);
    }
    return nl2br($t, false);
}

/**
 * Stamp image for the PDF, cached per request: ['uri', 'w', 'h'] in points, or null.
 * Read from the private folder only; transparent / white margins are trimmed
 * in memory (the owner's file is never modified); scaled to fit the stamp box.
 */
function inv_stamp_for_pdf(?string $filename): ?array
{
    static $cache = [];
    if ($filename === null || $filename === '') return null;
    if (array_key_exists($filename, $cache)) return $cache[$filename];
    $cache[$filename] = null;
    $path = inv_stamp_path($filename);
    if ($path === null || !is_file($path) || is_link($path) || filesize($path) > 2097152) return null;
    $info = @getimagesize($path);
    $mime = $info['mime'] ?? '';
    if (!in_array($mime, ['image/png', 'image/jpeg'], true)) return null;
    $bytes = (string) file_get_contents($path);
    $wpx = (int) $info[0];
    $hpx = (int) $info[1];
    if (function_exists('imagecreatefromstring') && $wpx * $hpx <= 4000000) {
        $im = @imagecreatefromstring($bytes);
        if ($im !== false) {
            if (!imageistruecolor($im)) imagepalettetotruecolor($im);
            $minX = $wpx; $minY = $hpx; $maxX = -1; $maxY = -1;
            for ($y = 0; $y < $hpx; $y++) {
                for ($x = 0; $x < $wpx; $x++) {
                    $c = imagecolorat($im, $x, $y);
                    if ((($c >> 24) & 0x7F) >= 120) continue;
                    if ((($c >> 16) & 0xFF) > 240 && (($c >> 8) & 0xFF) > 240 && ($c & 0xFF) > 240) continue;
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
            if ($maxX > $minX + 10 && $maxY > $minY + 10 && ($minX > 0 || $minY > 0 || $maxX < $wpx - 1 || $maxY < $hpx - 1)) {
                $crop = imagecrop($im, ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX + 1, 'height' => $maxY - $minY + 1]);
                if ($crop !== false) {
                    imagealphablending($crop, false);
                    imagesavealpha($crop, true);
                    ob_start();
                    imagepng($crop, null, 6);
                    $bytes = (string) ob_get_clean();
                    $mime = 'image/png';
                    $wpx = imagesx($crop);
                    $hpx = imagesy($crop);
                }
            }
        }
    }
    if ($wpx <= 0 || $hpx <= 0) return null;
    $scale = min(INV_QPDF_STAMP_W / $wpx, INV_QPDF_STAMP_H / $hpx);
    $cache[$filename] = ['uri' => 'data:' . $mime . ';base64,' . base64_encode($bytes),
                         'w' => round($wpx * $scale, 2), 'h' => round($hpx * $scale, 2)];
    return $cache[$filename];
}

/**
 * Quotation HTML from the view model built by inv_quote_doc_model():
 *   number (?string; null = draft), quote_date, valid_days, bill_to [name, address, attention],
 *   ref_prefix, subject, scope, intro, terms_text, payment_terms, closing_text,
 *   lines [[line_type, title, description, entities[], qty_h, rate_c, special_c, amount_c, special_amount_c]],
 *   entity [settings / snapshot], gst_registered, gst_rate, stamp (?filename), paper.
 */
function inv_quote_pdf_html(array $doc): string
{
    $e = $doc['entity'];
    $logo = inv_logo_for_pdf($e['logo_filename'] ?? null);
    if ($logo !== null) {
        $s = min(INV_QPDF_LOGO_W / $logo['w'], INV_QPDF_LOGO_H / $logo['h']);
        $logo['w'] = round($logo['w'] * $s, 2);
        $logo['h'] = round($logo['h'] * $s, 2);
    }
    $stamp = !empty($doc['stamp']) ? inv_stamp_for_pdf((string) $doc['stamp']) : null;
    $gstReg = !empty($doc['gst_registered']);
    $acc = trim((string) ($e['accreditation_line'] ?? ''));
    $number = $doc['number'] !== null ? $doc['number'] : 'DRAFT';

    $items = array_values(array_filter($doc['lines'], fn($l) => $l['line_type'] === 'item'));
    $showQty = (bool) array_filter($items, fn($l) => (int) $l['qty_h'] !== 100);

    $h = [];
    $h[] = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
    $h[] = '@page { size: A4 portrait; margin: 28.5pt 25pt 36pt 25.2pt; }';
    $h[] = <<<CSS
body { font-family: Helvetica, Arial, sans-serif; font-size: 11pt; line-height: 12.5pt; color: #000; margin: 0; }
table { border-collapse: collapse; }
td, th { padding: 0; vertical-align: top; }
p { margin: 0; }
.txt { width: 510.5pt; }
.gap { height: 12.65pt; }
.lh { margin-left: 5.3pt; width: 513pt; border: 0.75pt solid #000; }
.lh td { height: 76.75pt; vertical-align: middle; }
.lh .logo { width: 150.75pt; border-right: 0.75pt solid #000; text-align: center; }
.lh .co { text-align: center; font-size: 10pt; line-height: 11.5pt; padding: 0 6pt; }
.lh .co .in { margin-top: -1pt; }
.lh .co .name { font-size: 12pt; line-height: 13.7pt; font-weight: bold; }
.lh .co a { color: #0000FF; text-decoration: underline; }
.rd td { font-size: 11pt; }
.addr { color: #222; }
.pt { margin-left: 5.3pt; width: 533pt; }
.pt th, .pt td { border-left: 0.75pt solid #000; border-right: 0.75pt solid #000; }
.pt tr.rt td, .pt tr.rt th { border-top: 0.75pt solid #000; }
.pt tr.last td { border-bottom: 0.75pt solid #000; }
.pt th { height: 23.25pt; font-weight: bold; text-align: center; padding-top: 1.8pt; }
.pt th .t { position: relative; top: -1.84pt; }
.pt th .ng { font-size: 8pt; line-height: 9pt; font-weight: normal; font-style: italic; }
.pt td { height: 23.06pt; }
.pt .d { font-size: 10pt; line-height: 11.5pt; padding: 0 5pt 0.13pt 4.9pt; vertical-align: bottom; }
.pt .d .ds { font-size: 9pt; line-height: 10.4pt; }
.pt .sum { text-align: right; vertical-align: top; padding-top: 0; padding-bottom: 0; line-height: 13pt; }
.pt .sum .t { position: relative; top: -1.13pt; }
.pt .a { text-align: center; vertical-align: bottom; padding-bottom: 2.14pt; }
.pt .a .each { font-size: 7.5pt; line-height: 8.6pt; color: #333; }
.pt .q { text-align: center; vertical-align: bottom; padding-bottom: 2.14pt; font-size: 10pt; }
.pt tr.ent td { height: auto; }
.pt tr.ent .d { vertical-align: top; padding-top: 1pt; }
.pt tr.ent .li { padding-left: 16.9pt; text-indent: -16.9pt; margin-left: 16.9pt; }
.gstn { font-size: 10pt; line-height: 13pt; margin-top: 12.37pt; }
.terms { font-size: 10pt; line-height: 13pt; text-align: justify; margin-top: 12.37pt; }
.pay { font-size: 10pt; line-height: 13pt; margin-top: 14.8pt; }
.pay ul { margin: -1.7pt 0 0 0; padding: 0; list-style: none; }
.pay li { line-height: 13.6pt; padding-left: 36pt; text-indent: -18pt; }
.pay .bu { font-size: 12.5pt; line-height: 10pt; }
.pay sup { font-size: 6.5pt; line-height: 6pt; vertical-align: super; }
.close { font-size: 9pt; line-height: 11.8pt; text-align: justify; margin-top: 13.8pt; }
.sig { margin-left: 5.3pt; width: 535.5pt; border: 0.75pt solid #000; margin-top: 15.32pt; }
.sig td { height: 81.75pt; font-size: 12pt; line-height: 13.8pt; padding: 0.3pt 5.7pt 0 5.7pt; }
.sig .l { width: 269.55pt; border-right: 0.75pt solid #000; }
.sig .hd { font-weight: bold; }
.sig .in { position: relative; top: -0.46pt; }
.sig td.r { padding-left: 7.9pt; }
.sig .box { position: relative; height: 46pt; }
.sig .stamp { position: absolute; left: 196pt; top: -2pt; }
.sig .cg { font-size: 10pt; line-height: 12pt; }
.keep { page-break-inside: avoid; }
CSS;
    $h[] = '</style></head><body>';

    // ---- letterhead --------------------------------------------------------
    $h[] = '<table class="lh"><tr><td class="logo">'
        . ($logo !== null ? '<img src="' . $logo['uri'] . '" style="width:' . $logo['w'] . 'pt;height:' . $logo['h'] . 'pt" alt="">' : '')
        . '</td><td class="co"><div class="in">'
        . '<div class="name">' . inv_pdf_e($e['legal_name'] ?? '') . '</div>'
        . '<div><b>Co Reg No: ' . inv_pdf_e($e['co_reg_no'] ?? '') . '</b>'
        . ($gstReg && trim((string) ($e['gst_reg_no'] ?? '')) !== '' ? '<b> &nbsp;GST Reg No: ' . inv_pdf_e($e['gst_reg_no']) . '</b>' : '')
        . '</div>'
        . '<div>' . inv_pdf_e($e['address'] ?? '') . '</div>'
        . '<div>Email: <a href="mailto:' . inv_pdf_e($e['email'] ?? '') . '">' . inv_pdf_e($e['email'] ?? '') . '</a> Contact No: ' . inv_pdf_e($e['phone'] ?? '') . '</div>'
        . ($acc !== '' ? '<div><b>' . inv_pdf_e($acc) . '</b></div>' : '')
        . '</div></td></tr></table>';

    $h[] = '<div style="height:25.5pt"></div>';

    // ---- reference, addressee, subject, intro, scope ----------------------------
    $h[] = '<table class="rd txt"><tr><td style="width:403.2pt">Our Ref: ' . inv_pdf_e($number) . '</td>'
        . '<td>Date: ' . inv_pdf_e(inv_pdf_date($doc['quote_date'])) . '</td></tr></table>';
    $h[] = '<div class="gap"></div>';
    $bt = $doc['bill_to'];
    $h[] = '<div class="txt addr"><b>' . inv_pdf_e($bt['name']) . '</b></div>';
    if (trim((string) ($bt['address'] ?? '')) !== '') {
        $h[] = '<div class="txt addr">' . inv_pdf_ml($bt['address']) . '</div>';
    }
    if (trim((string) ($bt['attention'] ?? '')) !== '') {
        $h[] = '<div class="txt addr">Attn: ' . inv_pdf_e($bt['attention']) . '</div>';
    }
    $h[] = '<div class="gap"></div>';
    $h[] = '<div class="txt">Ref: ' . inv_pdf_e($doc['ref_prefix'] ?? '') . ' <b>' . inv_pdf_e($doc['subject'] ?? '') . '</b></div>';
    $h[] = '<div class="gap"></div>';
    $h[] = '<div class="txt">' . inv_qpdf_rich($doc['intro'] ?? '') . '</div>';
    if (trim((string) ($doc['scope'] ?? '')) !== '') {
        $h[] = '<div class="gap"></div>';
        $h[] = '<div class="txt"><b>Scope</b>: ' . inv_pdf_ml($doc['scope']) . '</div>';
    }
    $h[] = '<div style="height:15.65pt"></div>';

    // ---- price table ------------------------------------------------------------
    $wDesc = $showQty ? 299 : 349;
    $rateNote = $gstReg ? '(before GST)' : '(No GST)';
    $h[] = '<table class="pt"><thead><tr class="rt">'
        . '<th style="width:' . ($wDesc - 1) . 'pt"><div class="t">Description of Activity</div></th>'
        . ($showQty ? '<th style="width:48.5pt"><div class="t">Qty</div></th>' : '')
        . '<th style="width:98.5pt"><div class="t">Rate</div><div class="ng">' . $rateNote . '</div></th>'
        . '<th><div class="t">Special Rate</div></th></tr></thead><tbody>';
    $n = count($doc['lines']);
    foreach (array_values($doc['lines']) as $i => $ln) {
        $isLast = $i === $n - 1;
        $ents = array_values($ln['entities'] ?? []);
        $rowCls = 'rt' . ($isLast && !$ents ? ' last' : '');
        if ($ln['line_type'] === 'item') {
            $qty = (int) $ln['qty_h'];
            $desc = inv_pdf_e($ln['title']);
            if (trim((string) ($ln['description'] ?? '')) !== '') {
                $desc .= '<div class="ds">' . inv_pdf_ml($ln['description']) . '</div>';
            }
            $each = '';
            $eachSp = '';
            if ($qty !== 100) {
                $each = '<div class="each">' . inv_pdf_e(inv_qty_display($qty)) . ' &#215; S' . inv_pdf_e(inv_money_display((int) $ln['rate_c'])) . '</div>';
                $eachSp = '<div class="each">' . inv_pdf_e(inv_qty_display($qty)) . ' &#215; S' . inv_pdf_e(inv_money_display((int) ($ln['special_c'] ?? $ln['rate_c']))) . '</div>';
            }
            $h[] = '<tr class="' . $rowCls . '"><td class="d">' . $desc . '</td>'
                . ($showQty ? '<td class="q">' . inv_pdf_e(inv_qty_display($qty)) . '</td>' : '')
                . '<td class="a">' . $each . 'S' . inv_pdf_e(inv_money_display((int) $ln['amount_c'])) . '</td>'
                . '<td class="a">' . $eachSp . 'S' . inv_pdf_e(inv_money_display((int) $ln['special_amount_c'])) . '</td></tr>';
            // One borderless row per audited entity, so long lists can break across pages.
            foreach ($ents as $k => $ent) {
                $name = is_array($ent) ? ($ent['company_name'] ?? '') : $ent;
                $cls = 'ent' . ($isLast && $k === count($ents) - 1 ? ' last' : '');
                $h[] = '<tr class="' . $cls . '"><td class="d">' . ($k === 0 ? '<div>For:</div>' : '')
                    . '<div class="li">' . ($k + 1) . ')&nbsp;&nbsp;' . inv_pdf_e($name) . '</div></td>'
                    . ($showQty ? '<td></td>' : '') . '<td></td><td></td></tr>';
            }
        } else {
            // Sum row (automatic or manually priced): right-aligned wording, as on the sample.
            $h[] = '<tr class="' . $rowCls . '"><td class="d sum"><div class="t">' . inv_pdf_ml($ln['title'])
                . (trim((string) ($ln['description'] ?? '')) !== '' ? '<div class="ds">' . inv_pdf_ml($ln['description']) . '</div>' : '') . '</div></td>'
                . ($showQty ? '<td></td>' : '')
                . '<td class="a">S' . inv_pdf_e(inv_money_display((int) $ln['amount_c'])) . '</td>'
                . '<td class="a">S' . inv_pdf_e(inv_money_display((int) $ln['special_amount_c'])) . '</td></tr>';
        }
    }
    $h[] = '</tbody></table>';

    // ---- GST note, terms, payment terms, closing, signature -----------------------
    $h[] = '<div class="keep">';
    if ($gstReg) {
        $rate = rtrim(rtrim((string) ($doc['gst_rate'] ?? ''), '0'), '.');
        $h[] = '<div class="txt gstn">All rates are before GST. GST at ' . inv_pdf_e($rate) . '% will be added to the invoice.</div>';
    }
    $terms = str_replace('{valid_days}', (string) (int) $doc['valid_days'], (string) ($doc['terms_text'] ?? ''));
    $h[] = '<div class="txt terms">' . inv_qpdf_rich($terms) . '</div>';

    $bul = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) ($doc['payment_terms'] ?? '')))), 'strlen'));
    if ($bul) {
        $li = '';
        foreach ($bul as $b) {
            $li .= '<li><span class="bu">&#8226;</span><span style="display:inline-block;width:12.4pt"></span>' . inv_qpdf_rich($b, true) . '</li>';
        }
        $h[] = '<div class="txt pay">Payment Terms:<ul>' . $li . '</ul></div>';
    }
    $h[] = '<div class="txt close">' . inv_qpdf_rich($doc['closing_text'] ?? '') . '</div>';

    $stampHtml = $stamp !== null
        ? '<img class="stamp" src="' . $stamp['uri'] . '" style="width:' . $stamp['w'] . 'pt;height:' . $stamp['h'] . 'pt" alt="">'
        : '';
    $h[] = '<table class="sig"><tr>'
        . '<td class="l"><div class="in"><div class="hd">' . inv_pdf_e($e['legal_name'] ?? '') . '</div><div class="box">' . $stampHtml . '</div>'
        . '<div class="cg">' . inv_pdf_e($e['footer_text'] ?? '') . '</div></div></td>'
        . '<td class="r"><div class="in"><div class="hd">Accepted by /Signature/Name and Stamp;</div><div style="height:54.9pt"></div><div class="hd" style="margin-left:-3.4pt">______________________</div></div></td>'
        . '</tr></table>';
    $h[] = '</div>';

    $h[] = '</body></html>';
    return implode("\n", $h);
}

/**
 * Download file name. Quote numbers contain "/" (Q/SH26001), which is not
 * allowed in file names: it becomes "-". Example: "Q-SH26001-R1 XXX.pdf".
 */
function inv_quote_pdf_filename(?string $number, string $party, bool $draft = false, int $id = 0, string $suffix = ''): string
{
    $clean = function (string $s, int $max): string {
        $s = str_replace(['/', '\\'], '-', $s);
        $s = preg_replace('/[^A-Za-z0-9 &()._-]+/', ' ', $s);
        $s = preg_replace('/\b(Pte\.?|Ltd\.?|Private|Limited|LLP)\b/i', '', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s), ' .-');
        return mb_substr($s, 0, $max);
    };
    $base = ($draft ? 'DRAFT_Q' . $id : $clean((string) $number, 30)) . ' ' . $clean($party, 60);
    return trim(trim($base) . $suffix);
}