<?php
/**
 * invoice-system/includes/inv_pdf.php — invoice PDF (Dompdf, already in vendor/).
 *
 * Layout reproduces invoice #26301 (measured from the owner's PDF, in points):
 *   letterhead box 485pt wide, logo cell 118.5pt; invoice box 491.5pt wide;
 *   columns Item 36.5 | Description 274.5 | Qty 53.5 | Rate 60 | Amount S$ 67;
 *   bill-to / date split at 311pt; Arial-metric Helvetica throughout.
 * The only intended additions vs #26301 are "Due Date" and "Payment terms"
 * (and "Attn" / "Your Ref" lines only when filled in).
 *
 * Security: every value is escaped with htmlspecialchars; Dompdf runs with
 * remote fetching, PHP and JavaScript disabled; the logo is embedded as a
 * base64 data URI read from the module folder (no URLs, no paths in HTML).
 */

require_once __DIR__ . '/inv_money.php';

const INV_PDF_PAPERS = ['A4', 'letter'];

function inv_pdf_e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape + keep line breaks. */
function inv_pdf_ml($s): string
{
    return nl2br(inv_pdf_e(str_replace(["\r\n", "\r"], "\n", (string) $s)), false);
}

/** dd/mm/yyyy from Y-m-d. */
function inv_pdf_date(?string $ymd): string
{
    if ($ymd === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) return '';
    return $m[3] . '/' . $m[2] . '/' . $m[1];
}

/**
 * Logo for the PDF, cached per request: ['uri' => data URI, 'w' => pt, 'h' => pt] or null.
 * The owner's file is never modified. When GD is available, transparent (or
 * plain white) margins are trimmed in memory so the visible logo fills the
 * same box as on #26301 (101.5 x 63.5 pt for EHS); the result is scaled to
 * fit INV_LOGO_BOX_W x INV_LOGO_BOX_H keeping its proportions.
 */
const INV_LOGO_BOX_W = 101.5;
const INV_LOGO_BOX_H = 70.0;

function inv_logo_for_pdf(?string $filename): ?array
{
    static $cache = [];
    if ($filename === null) return null;
    if (array_key_exists($filename, $cache)) return $cache[$filename];
    $cache[$filename] = null;
    $path = function_exists('inv_logo_path') ? inv_logo_path($filename) : null;
    if ($path === null || !is_file($path) || filesize($path) > 2097152) return null;
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
            // Bounding box of "ink": not (nearly) transparent and not (nearly) white.
            $minX = $wpx; $minY = $hpx; $maxX = -1; $maxY = -1;
            for ($y = 0; $y < $hpx; $y++) {
                for ($x = 0; $x < $wpx; $x++) {
                    $c = imagecolorat($im, $x, $y);
                    $a = ($c >> 24) & 0x7F;
                    if ($a >= 120) continue;
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
    $scale = min(INV_LOGO_BOX_W / $wpx, INV_LOGO_BOX_H / $hpx);
    $cache[$filename] = ['uri' => 'data:' . $mime . ';base64,' . base64_encode($bytes),
                         'w' => round($wpx * $scale, 2), 'h' => round($hpx * $scale, 2)];
    return $cache[$filename];
}

/**
 * Build the invoice HTML from a document view model:
 *   number (?string; null = draft), invoice_date, due_date, terms_days, category,
 *   customer_ref, bill_to [name, address, attention], entity [settings fields],
 *   lines [[title, description, entities[], qty_h, rate_c, amount_c]],
 *   subtotal_c, gst_c, gst_rate_h, gst_registered, total_c, words, paper.
 */
function inv_pdf_html(array $doc): string
{
    $e = $doc['entity'];
    $paper = in_array($doc['paper'] ?? 'A4', INV_PDF_PAPERS, true) ? $doc['paper'] : 'A4';
    // Same left edge as #26301 on letter (72pt); balanced on A4.
    $margins = $paper === 'letter' ? '24pt 40pt 36pt 71.5pt' : '24pt 32pt 36pt 52pt';
    $logo = inv_logo_for_pdf($e['logo_filename'] ?? null);

    $number = $doc['number'] !== null ? $doc['number'] : 'DRAFT';
    $acc = trim((string) ($e['accreditation_line'] ?? ''));
    $gstReg = (int) ($doc['gst_registered'] ?? 0) === 1;

    $h = [];
    $h[] = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
    $h[] = '@page { size: ' . ($paper === 'letter' ? 'letter' : 'A4') . ' portrait; margin: ' . $margins . '; }';
    $h[] = <<<CSS
body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; line-height: 11.9pt; letter-spacing: 0.17pt; color: #000; margin: 0; }
/* letter-spacing reproduces the per-character advance of the Word/Arial original (#26301). */
table { border-collapse: collapse; }
td, th { padding: 0; vertical-align: top; }
.lh { width: 485pt; border: 0.75pt solid #000; }
.lh td { height: 92.25pt; vertical-align: middle; }
.lh .logo { width: 117.75pt; border-right: 0.75pt solid #000; text-align: center; }
.lh .co { text-align: center; font-size: 9pt; line-height: 10.8pt; letter-spacing: 0.19pt; padding: 0 10pt; }
.lh .co .name { font-size: 11pt; line-height: 12.8pt; letter-spacing: 0.13pt; font-weight: bold; }
.lh .co a { color: #0563C1; text-decoration: underline; }
.box { width: 491.5pt; border-left: 0.75pt solid #000; border-right: 0.75pt solid #000; }
.box td, .box th { border-color: #000; border-style: solid; border-width: 0; }
.title td { border-top: 0.75pt solid #000; border-bottom: 0.75pt solid #000; height: 24.75pt; text-align: center; vertical-align: middle; font-size: 13pt; letter-spacing: 0.08pt; font-weight: bold; }
.billto td { height: 90.75pt; }
.billto .to { width: 299.7pt; padding: 0.7pt 5.4pt 0 5.4pt; border-right: 0.75pt solid #000; }
.billto .meta { padding: 12.5pt 5.4pt 0 5.4pt; height: 78.25pt; }
.billto .meta div { margin-bottom: 6pt; }
.items thead th { border-top: 0.75pt solid #000; border-bottom: 0.75pt solid #000; font-weight: bold; text-align: center; height: 10.75pt; line-height: 11.9pt; }
.items .c1 { width: 36pt; text-align: center; }
.items .c2 { width: 263pt; }
.items .c3 { width: 52.5pt; text-align: center; }
.items .c4 { width: 59pt; text-align: center; }
.items .c5 { text-align: center; }
.items .vl { border-right: 0.75pt solid #000; }
.items tbody td { font-size: 10pt; }
.items tbody td.num { font-size: 9pt; line-height: 10.4pt; letter-spacing: 0.19pt; padding-top: 11.6pt; }
.items tbody td.c1 { font-size: 9pt; line-height: 10.4pt; padding-top: 0.8pt; }
.items tbody td.c2 { padding: 0 5.1pt 0 5.4pt; line-height: 11.7pt; }
.items .ttl { font-weight: bold; }
.items .for { margin-top: 11.9pt; }
.items tr.ent td.c2 .li { padding-left: 33.8pt; text-indent: -16.9pt; }
.items tr.ent td.c2 .li.first { margin-top: 11.7pt; }
.tot td { border-top: 0.75pt solid #000; border-bottom: 0.75pt solid #000; vertical-align: middle; height: 35.75pt; }
.tot .words { width: 305.1pt; padding-left: 5.4pt; font-weight: bold; }
.tot .lbl { width: 113pt; text-align: center; }
.tot .amt { text-align: center; font-weight: bold; }
.note td { padding: 0 5.4pt 9pt 5.4pt; border-bottom: 0.75pt solid #000; }
.note .h { font-size: 8pt; line-height: 9.6pt; letter-spacing: 0.19pt; font-weight: bold; }
.note .tch { margin-top: 9.6pt; }
.note ul { margin: 0; padding: 0; list-style: none; }
.note li { font-size: 8pt; line-height: 9.7pt; letter-spacing: 0.19pt; padding-left: 16.9pt; text-indent: -16.9pt; }
.foot td { border-bottom: 0.75pt solid #000; padding: 0 5.4pt; height: 37.75pt; font-size: 11pt; line-height: 12.8pt; letter-spacing: 0.13pt; }
.keep { page-break-inside: avoid; }
CSS;
    $h[] = '</style></head><body>';

    // ---- letterhead -------------------------------------------------------
    $h[] = '<table class="lh"><tr><td class="logo">'
        . ($logo !== null ? '<img src="' . $logo['uri'] . '" style="width:' . $logo['w'] . 'pt;height:' . $logo['h'] . 'pt" alt="">' : '')
        . '</td><td class="co">'
        . '<div class="name">' . inv_pdf_e($e['legal_name']) . '</div>'
        . '<div><b>Co Reg No: ' . inv_pdf_e($e['co_reg_no']) . '</b>'
        . ($gstReg && trim((string) ($e['gst_reg_no'] ?? '')) !== '' ? '<b> &nbsp;GST Reg No: ' . inv_pdf_e($e['gst_reg_no']) . '</b>' : '')
        . '</div>'
        . '<div>' . inv_pdf_e($e['address']) . '</div>'
        . '<div>Email: <a href="mailto:' . inv_pdf_e($e['email']) . '">' . inv_pdf_e($e['email']) . '</a> Contact No: ' . inv_pdf_e($e['phone']) . '</div>'
        . ($acc !== '' ? '<div><b>' . inv_pdf_e($acc) . '</b></div>' : '')
        . '</td></tr></table>';

    $h[] = '<div style="height:23.75pt"></div>';

    // ---- title + bill-to ---------------------------------------------------
    $bt = $doc['bill_to'];
    $billLines = '<div>' . inv_pdf_e($bt['name']) . '</div>';
    if (trim((string) ($bt['attention'] ?? '')) !== '') {
        $billLines .= '<div>Attn: ' . inv_pdf_e($bt['attention']) . '</div>';
    }
    $billLines .= '<div>&nbsp;</div><div>' . inv_pdf_ml($bt['address'] ?? '') . '</div>';

    $terms = (int) $doc['terms_days'];
    $meta = '<div>Date: ' . inv_pdf_e(inv_pdf_date($doc['invoice_date'])) . '</div>'
        . '<div>Category: ' . inv_pdf_e($doc['category'] ?? '') . '</div>'
        . '<div>Due Date: ' . inv_pdf_e(inv_pdf_date($doc['due_date'])) . '</div>'
        . '<div>Payment terms: ' . ($terms === 0 ? 'Due on receipt' : $terms . ' days') . '</div>'
        . (trim((string) ($doc['customer_ref'] ?? '')) !== '' ? '<div>Your Ref: ' . inv_pdf_e($doc['customer_ref']) . '</div>' : '');

    $h[] = '<table class="box title" style="border-top:0"><tr><td>INVOICE: ' . inv_pdf_e($number) . '</td></tr></table>';
    $h[] = '<table class="box billto"><tr><td class="to">' . $billLines . '</td><td class="meta">' . $meta . '</td></tr></table>';

    // ---- items --------------------------------------------------------------
    $h[] = '<table class="box items"><thead><tr>'
        . '<th class="c1 vl">Item</th><th class="c2 vl">Description</th><th class="c3 vl">Qty</th><th class="c4 vl">Rate</th><th class="c5">Amount S$</th>'
        . '</tr></thead><tbody>';
    // One table row for the title/description (with qty, rate, amount), then
    // one borderless row per audited entity, so long lists break across pages
    // naturally (Dompdf cannot split a single table row).
    $i = 0;
    foreach ($doc['lines'] as $ln) {
        $i++;
        $desc = '<div class="ttl">' . inv_pdf_e($ln['title']) . '</div>';
        if (trim((string) ($ln['description'] ?? '')) !== '') {
            $desc .= '<div>' . inv_pdf_ml($ln['description']) . '</div>';
        }
        $ents = array_values($ln['entities'] ?? []);
        if ($ents) {
            $desc .= '<div class="for">For:</div>';
        }
        $h[] = '<tr>'
            . '<td class="c1 vl">' . $i . '</td>'
            . '<td class="c2 vl">' . $desc . '</td>'
            . '<td class="c3 vl num">' . inv_pdf_e(inv_qty_display((int) $ln['qty_h'])) . '</td>'
            . '<td class="c4 vl num">' . inv_pdf_e(inv_money_display((int) $ln['rate_c'])) . '</td>'
            . '<td class="c5 num">' . inv_pdf_e(inv_money_display((int) $ln['amount_c'])) . '</td>'
            . '</tr>';
        foreach ($ents as $n => $ent) {
            $h[] = '<tr class="ent"><td class="c1 vl"></td>'
                . '<td class="c2 vl"><div class="li' . ($n === 0 ? ' first' : '') . '">' . ($n + 1) . ')&nbsp;&nbsp;&nbsp;' . inv_pdf_e($ent) . '</div></td>'
                . '<td class="c3 vl"></td><td class="c4 vl"></td><td class="c5"></td></tr>';
        }
    }
    $h[] = '</tbody></table>';

    // ---- totals + note + T&C + footer (kept together) ------------------------
    $gstLabel = $gstReg
        ? 'GST ' . inv_pdf_e(rtrim(rtrim(inv_cents_to_decimal((int) $doc['gst_rate_h']), '0'), '.')) . '%'
        : inv_pdf_e($e['no_gst_label'] ?: 'NO GST');
    $gstAmt = $gstReg ? inv_pdf_e(inv_money_display((int) $doc['gst_c'])) : '-';

    $tnc = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) $e['tnc_text']))), 'strlen'));
    $tncHtml = '';
    foreach ($tnc as $t) {
        $tncHtml .= '<li>&#8226;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . inv_pdf_e($t) . '</li>';
    }

    $bankName = (string) $e['bank_name'];
    $note = '<div class="h">Note:</div>'
        . '<div>Kindly make all cheques payable to: ' . inv_pdf_e($e['cheque_payee']) . '</div>'
        . '<div>For online payment, kindly transfer to: Bank: ' . inv_pdf_e($bankName) . ' code is ' . inv_pdf_e($e['bank_code'])
        . ', Branch: ' . inv_pdf_e($e['bank_branch']) . ', Bank Account:&nbsp; <b>' . inv_pdf_e($e['bank_account_label'] . ' ' . $e['bank_account_no'])
        . '</b>, ' . inv_pdf_e($bankName) . '&#39;s Swift Code ' . inv_pdf_e($e['bank_swift']) . ',</div>'
        . '<div><b>Paynow</b> to UEN: ' . inv_pdf_e($e['paynow_uen']) . '</div>'
        . '<div>' . inv_pdf_e($e['payment_note']) . '</div>'
        . '<div class="h tch">Terms &amp; Conditions</div><ul>' . $tncHtml . '</ul>';

    $h[] = '<div class="keep">';
    $h[] = '<table class="box tot"><tr>'
        . '<td class="words">' . inv_pdf_e($doc['words']) . '</td>'
        . '<td class="lbl">SUB-TOTAL<br>' . $gstLabel . '<br>TOTAL (SGD)</td>'
        . '<td class="amt">' . inv_pdf_e(inv_money_display((int) $doc['subtotal_c'])) . '<br>' . $gstAmt . '<br>' . inv_pdf_e(inv_money_display((int) $doc['total_c'])) . '</td>'
        . '</tr></table>';
    $h[] = '<table class="box note"><tr><td style="width:479.95pt">' . $note . '</td></tr></table>';
    $h[] = '<table class="box foot"><tr><td style="width:479.95pt"><div style="padding-top:1pt"><b>' . inv_pdf_e($e['legal_name']) . '</b></div>'
        . '<div style="margin-top:13pt">' . inv_pdf_e($e['footer_text']) . '</div></td></tr></table>';
    $h[] = '</div>';

    $h[] = '</body></html>';
    return implode("\n", $h);
}

/**
 * Render HTML to PDF bytes. $watermark (e.g. "DRAFT, NOT FOR ISSUE") is drawn
 * diagonally across the centre of EVERY page; null for issued invoices. $pageLabel: e.g. "Invoice 26302" printed with "Page n of m" only when the
 * document has more than one page.
 */
function inv_pdf_render(string $html, string $paper, ?string $watermark, string $pageLabel): string
{
    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('PDF library (Dompdf) is not installed. Run "composer install" on the server.');
    }
    require_once $autoload;

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('isJavascriptEnabled', false);
    $options->set('defaultFont', 'Helvetica');
    $options->set('chroot', [realpath(__DIR__ . '/../assets/img') ?: __DIR__]);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper($paper === 'letter' ? 'letter' : 'A4', 'portrait');
    $dompdf->render();

    $canvas = $dompdf->getCanvas();
    $metrics = $dompdf->getFontMetrics();
    $w = $canvas->get_width();
    $hgt = $canvas->get_height();
    $font = $metrics->getFont('Helvetica');
    $bold = $metrics->getFont('Helvetica', 'bold');

    $canvas->page_script(function (int $pageNum, int $pageCount, $pdf) use ($watermark, $pageLabel, $metrics, $font, $bold, $w, $hgt) {
        if ($watermark !== null && $watermark !== '') {
            // Dompdf rotates text around its start point; angle -35 runs up-right.
            // Start = page centre minus half the text length along the
            // baseline, minus half the text height across it (calibrated).
            $size = 44;
            $tw = $metrics->getTextWidth($watermark, $bold, $size);
            $th = $metrics->getFontHeight($bold, $size);
            $rad = deg2rad(35);
            // +22.6 / -8.5: offsets measured from rendered output (Dompdf 2.0.8).
            $x = $w / 2 - cos($rad) * $tw / 2 - sin($rad) * $th / 2 + 22.6;
            $y = $hgt / 2 + sin($rad) * $tw / 2 - cos($rad) * $th / 2 - 8.5;
            $pdf->set_opacity(0.2);
            $pdf->text($x, $y, $watermark, $bold, $size, [0.7, 0.0, 0.0], 0.0, 0.0, -35);
            $pdf->set_opacity(1.0);
        }
        if ($pageCount > 1) {
            $label = $pageLabel . '  ·  Page ' . $pageNum . ' of ' . $pageCount;
            $size = 7;
            $tw = $metrics->getTextWidth($label, $font, $size);
            $pdf->text(($w - $tw) / 2, $hgt - 24, $label, $font, $size, [0.35, 0.35, 0.35]);
        }
    });

    return $dompdf->output(['compress' => 1]);
}

/** Download file name: "26302 World Health SafetyTec - RM Audit.pdf" (sample convention). */
function inv_pdf_filename(?string $number, string $party, ?string $category, bool $draft = false, int $draftId = 0): string
{
    $clean = function (string $s, int $max): string {
        $s = preg_replace('/[^A-Za-z0-9 &()._-]+/', ' ', $s);
        $s = preg_replace('/\b(Pte\.?|Ltd\.?|Private|Limited|LLP)\b/i', '', $s);
        $s = trim(preg_replace('/\s+/', ' ', $s), ' .-');
        return mb_substr($s, 0, $max);
    };
    $base = ($draft ? 'DRAFT_' . $draftId : $clean((string) $number, 30)) . ' ' . $clean($party, 60);
    if ($category !== null && trim($category) !== '') {
        $base .= ' - ' . $clean($category, 40);
    }
    return trim($base) . '.pdf';
}
