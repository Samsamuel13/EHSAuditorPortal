<?php
/**
 * client-management/api/export_client_pdf.php?id=X
 *
 * Streams a PDF of one client's full certification history — company info,
 * every certification (active, expired, withdrawn — full history, not
 * just current), and the list of uploaded document names. Uses Dompdf,
 * the same dependency/pattern as the scheduling system's api/export_pdf.php.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/cm_helpers.php';

$user = ehs_require_role(['super_admin', 'admin'], true);

$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    cm_json_error('Export dependencies are not installed. Run "composer install" on the server.', 500);
}
require_once $vendorAutoload;

$db = get_db();
$clientId = (int) ($_GET['id'] ?? 0);
if ($clientId <= 0) { http_response_code(422); die('A valid client id is required.'); }

$clientStmt = $db->prepare('SELECT * FROM cm_clients WHERE id = :id LIMIT 1');
$clientStmt->execute(['id' => $clientId]);
$client = $clientStmt->fetch();
if (!$client) { http_response_code(404); die('Client not found.'); }

$certStmt = $db->prepare(
    "SELECT cert.*, st.name AS scheme_name, st.category AS scheme_category,
            COALESCE(u.name, cert.responsible_person_name) AS responsible_person
     FROM cm_certifications cert
     JOIN cm_scheme_types st ON st.id = cert.cm_scheme_type_id
     LEFT JOIN users u ON u.id = cert.responsible_person_id
     WHERE cert.cm_client_id = :id
     ORDER BY cert.expiry_date IS NULL, cert.expiry_date DESC"
);
$certStmt->execute(['id' => $clientId]);
$certs = $certStmt->fetchAll();

$certIds = array_column($certs, 'id');
$docsByCert = [];
if ($certIds) {
    $placeholders = implode(',', array_fill(0, count($certIds), '?'));
    $docStmt = $db->prepare("SELECT cm_certification_id, doc_type, original_filename FROM cm_certification_documents WHERE cm_certification_id IN ($placeholders) ORDER BY uploaded_at");
    $docStmt->execute($certIds);
    foreach ($docStmt->fetchAll() as $doc) {
        $docsByCert[$doc['cm_certification_id']][] = $doc;
    }
}

function cm_pdf_escape(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
function cm_pdf_label(string $enumValue): string
{
    return ucwords(str_replace('_', ' ', $enumValue));
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1f2937; }
    h1 { font-size: 16px; margin-bottom: 2px; color: #283891; }
    .subtitle { color: #6b7280; margin-bottom: 16px; }
    .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .info-grid td { padding: 3px 6px; vertical-align: top; }
    .info-grid .label { color: #6b7280; width: 130px; }
    table.certs { border-collapse: collapse; width: 100%; margin-bottom: 10px; }
    table.certs th, table.certs td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
    table.certs th { background: #f3f4f6; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 8px; }
    .badge-active { background:#e6f4ea; color:#1e7e34; }
    .badge-expired { background:#fdecea; color:#b91c1c; }
    .badge-pending { background:#fff4e5; color:#b45309; }
    .badge-suspended { background:#fff4e5; color:#b45309; }
    .badge-withdrawn { background:#f1f2f4; color:#6b7280; }
    .docs { font-size: 8px; color: #6b7280; margin-top: 3px; }
    .footer-note { margin-top: 20px; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>
    <h1><?= cm_pdf_escape($client['company_name']) ?></h1>
    <div class="subtitle">Certification History — generated <?= date('j M Y, g:i A') ?></div>

    <table class="info-grid">
        <tr><td class="label">UEN</td><td><?= cm_pdf_escape($client['uen_registration_no'] ?? '—') ?></td>
            <td class="label">Industry</td><td><?= cm_pdf_escape($client['industry_sector'] ?? '—') ?></td></tr>
        <tr><td class="label">Contact</td><td><?= cm_pdf_escape($client['contact_person'] ?? '—') ?></td>
            <td class="label">Status</td><td><?= cm_pdf_escape(ucfirst($client['status'])) ?></td></tr>
        <tr><td class="label">Phone</td><td><?= cm_pdf_escape($client['phone'] ?? '—') ?></td>
            <td class="label">Email</td><td><?= cm_pdf_escape($client['email'] ?? '—') ?></td></tr>
        <tr><td class="label">Address</td><td colspan="3"><?= cm_pdf_escape($client['address'] ?? '—') ?></td></tr>
    </table>

    <table class="certs">
        <thead>
            <tr>
                <th>Scheme</th><th>Cert #</th><th>Accreditation Body</th>
                <th>1st Cert</th><th>Surv. 1</th><th>Surv. 2</th><th>Recert.</th>
                <th>Status</th><th>Responsible</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$certs): ?>
                <tr><td colspan="9">No certifications on file.</td></tr>
            <?php endif; ?>
            <?php foreach ($certs as $c): ?>
                <tr>
                    <td><?= cm_pdf_escape($c['scheme_name']) ?> (<?= cm_pdf_escape($c['scheme_category']) ?>)</td>
                    <td><?= cm_pdf_escape($c['certificate_number'] ?? '—') ?></td>
                    <td><?= cm_pdf_escape($c['accreditation_body'] ?? '—') ?></td>
                    <td><?= cm_pdf_escape($c['issue_date'] ?? '—') ?></td>
                    <td><?= cm_pdf_escape($c['surveillance_1_date'] ?? '—') ?></td>
                    <td><?= cm_pdf_escape($c['surveillance_2_date'] ?? '—') ?></td>
                    <td><?= cm_pdf_escape($c['expiry_date'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= cm_pdf_escape($c['status']) ?>"><?= cm_pdf_escape(ucfirst($c['status'])) ?></span></td>
                    <td>
                        <?= cm_pdf_escape($c['responsible_person'] ?? '—') ?>
                        <?php if (!empty($docsByCert[$c['id']])): ?>
                            <div class="docs">Docs: <?= cm_pdf_escape(implode(', ', array_column($docsByCert[$c['id']], 'original_filename'))) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer-note">EHS Universal — Client & Certification Management. This report reflects all certification records on file, including expired/withdrawn history.</div>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', false);
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

cm_log_activity($user['id'], 'export_client_pdf', 'cm_client', $clientId, $client['company_name']);

$safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $client['company_name']);
$filename = 'cm_certification_history_' . $safeName . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;