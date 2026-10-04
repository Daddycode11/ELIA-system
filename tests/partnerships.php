<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/partnerships.php';
require __DIR__ . '/http.php';
$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8080/elia-system', '/');
if (!in_array(parse_url($baseUrl, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) { exit("Local servers only.\n"); }
$pdo = database(); $checks = 0; $accounts = $handles = $partnerIds = $agreementIds = [];
$suffix = bin2hex(random_bytes(8)); $password = bin2hex(random_bytes(16));
$runtime = __DIR__ . '/.runtime'; if (!is_dir($runtime)) { mkdir($runtime, 0700, true); }
$fixture = $runtime . '/partnership-' . $suffix . '.pdf';
$bad = $runtime . '/partnership-' . $suffix . '.txt';
file_put_contents($fixture, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
file_put_contents($bad, '<?php echo "no"; ?>');
try {
    foreach (['admin', 'client'] as $role) {
        $statement = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $statement->execute(['Partnership fixture', "$role.partners.$suffix@example.test", password_hash($password, PASSWORD_DEFAULT), $role]);
        $accounts[$role] = (int) $pdo->lastInsertId();
        $handles[$role] = browser();
        request($handles[$role], 'actions/login.php', ['csrf_token' => token(request($handles[$role], 'login.php')), 'email' => "$role.partners.$suffix@example.test", 'password' => $password]);
    }
    $guest = $handles['guest'] = browser(); $admin = $handles['admin'];
    foreach (['admin/partnerships/index.php', 'admin/partnerships/form.php', 'actions/partnerships/save.php', 'actions/partnerships/upload.php', 'actions/partnerships/download.php'] as $path) {
        check(request($guest, $path)['status'] === 303, "Guest blocked: $path");
        check(request($handles['client'], $path)['status'] === 403, "Client blocked: $path");
    }
    $csrf = token(request($admin, 'admin/partnerships/index.php'));
    foreach (['save', 'upload'] as $action) {
        check(request($admin, "actions/partnerships/$action.php")['status'] === 405, "$action requires POST");
        check(request($admin, "actions/partnerships/$action.php", [])['status'] === 403, "$action requires CSRF");
    }
    check(request($admin, 'admin/partnerships/form.php?entity=agreement&partner_id=999999999')['status'] === 404, 'Unknown partner rejected');
    $partnerFields = ['csrf_token' => $csrf, 'entity' => 'partner', 'id' => '0', 'revision' => '0', 'name' => '<script>Partner</script> ' . $suffix, 'country' => 'Test country', 'address' => '', 'contact_name' => 'Contact', 'contact_email' => 'contact@example.test', 'website' => 'https://example.test', 'notes' => 'Fixture', 'is_archived' => '0'];
    $response = request($admin, 'actions/partnerships/save.php', $partnerFields);
    $statement = $pdo->prepare('SELECT id FROM partners WHERE name = ?'); $statement->execute([$partnerFields['name']]);
    $partnerId = (int) $statement->fetchColumn(); $partnerIds[] = $partnerId;
    check($response['status'] === 303 && $partnerId > 0, 'Admin creates partner institution');
    check(str_contains(request($admin, 'admin/partnerships/index.php?q=' . $suffix)['body'], '&lt;script&gt;Partner&lt;/script&gt;'), 'Partner names are escaped');
    request($admin, 'actions/partnerships/save.php', $partnerFields);
    check(str_contains(request($admin, 'admin/partnerships/form.php?entity=partner')['body'], 'already exist'), 'Duplicate institution/country handled safely');
    foreach ([['name' => ''], ['contact_email' => 'bad'], ['website' => 'javascript:alert(1)'], ['country' => ['bad']], ['is_archived' => '2']] as $invalid) {
        request($admin, 'actions/partnerships/save.php', array_replace($partnerFields, $invalid));
        $statement = $pdo->prepare('SELECT COUNT(*) FROM partners WHERE created_by = ?'); $statement->execute([$accounts['admin']]);
        check((int) $statement->fetchColumn() === 1, 'Invalid partner input cannot create records');
    }
    $updatePartner = function (array $changes = []) use ($partnerId, $admin, $csrf): array {
        $record = partnership_record('partner', $partnerId);
        return request($admin, 'actions/partnerships/save.php', array_replace($record, ['csrf_token' => $csrf, 'entity' => 'partner'], $changes));
    };
    $updatePartner(['notes' => 'Updated notes']);
    check(partnership_record('partner', $partnerId)['notes'] === 'Updated notes', 'Partner metadata updates');
    $updatePartner(['notes' => 'Stale notes', 'revision' => '1']);
    check(partnership_record('partner', $partnerId)['notes'] === 'Updated notes', 'Stale partner edits rejected');
    $today = partnership_today();
    $agreementFields = ['csrf_token' => $csrf, 'entity' => 'agreement', 'id' => '0', 'partner_id' => (string) $partnerId, 'revision' => '0', 'reference_no' => 'MOU-' . $suffix, 'title' => 'Test agreement', 'agreement_type' => 'MOU', 'status' => 'signed', 'signed_date' => $today, 'start_date' => $today, 'end_date' => $today, 'notes' => 'Scope', 'is_archived' => '0'];
    foreach ([['start_date' => ''], ['end_date' => '2020-02-30'], ['start_date' => '2030-01-01', 'end_date' => '2020-01-01'], ['agreement_type' => 'OTHER'], ['status' => 'approved']] as $invalid) {
        request($admin, 'actions/partnerships/save.php', array_replace($agreementFields, $invalid));
        $statement = $pdo->prepare('SELECT COUNT(*) FROM partnership_agreements WHERE partner_id = ?'); $statement->execute([$partnerId]);
        check((int) $statement->fetchColumn() === 0, 'Invalid agreement data rejected');
    }
    request($admin, 'actions/partnerships/save.php', $agreementFields);
    $statement = $pdo->prepare('SELECT id FROM partnership_agreements WHERE reference_no = ?'); $statement->execute([$agreementFields['reference_no']]);
    $agreementId = (int) $statement->fetchColumn(); $agreementIds[] = $agreementId;
    check($agreementId > 0, 'Agreement linked to partner');
    check(agreement_state(partnership_record('agreement', $agreementId), partnership_record('partner', $partnerId)) === 'active', 'End date is inclusive in current status');
    check(str_contains(request($admin, 'admin/partnerships/index.php?entity=agreement&q=' . $suffix . '&status=active&type=MOU')['body'], $agreementFields['reference_no']), 'Agreement search/type/status filters combine');
    request($admin, 'actions/partnerships/save.php', $agreementFields);
    check(str_contains(request($admin, 'admin/partnerships/form.php?entity=agreement&partner_id=' . $partnerId)['body'], 'reference already exists'), 'Duplicate agreement reference rejected');
    $updateAgreement = function (array $changes = []) use ($agreementId, $admin, $csrf): array {
        return request($admin, 'actions/partnerships/save.php', array_replace(partnership_record('agreement', $agreementId), ['csrf_token' => $csrf, 'entity' => 'agreement'], $changes));
    };
    $updateAgreement(['title' => 'Updated agreement']);
    check(partnership_record('agreement', $agreementId)['title'] === 'Updated agreement', 'Agreement metadata updates');
    $updateAgreement(['title' => 'Stale title', 'revision' => '1']);
    check(partnership_record('agreement', $agreementId)['title'] === 'Updated agreement', 'Stale agreement edits rejected');
    $upload = function (string $file, string $name = 'agreement.pdf', ?int $revision = null) use ($admin, $csrf, $agreementId): array {
        return request($admin, 'actions/partnerships/upload.php', ['csrf_token' => $csrf, 'id' => (string) $agreementId,
            'revision' => (string) ($revision ?? partnership_record('agreement', $agreementId)['revision']), 'description' => 'Signed agreement', 'document' => new CURLFile($file, 'application/pdf', $name)]);
    };
    $upload($bad);
    check(count(partnership_documents($agreementId)) === 0, 'Spoofed document content rejected');
    $upload($fixture, 'malware.php');
    check(count(partnership_documents($agreementId)) === 0, 'Executable extension rejected');
    $upload($fixture);
    $documents = partnership_documents($agreementId);
    check(count($documents) === 1 && $documents[0]['version'] == 1, 'Secure agreement document upload succeeds');
    $first = $documents[0];
    $download = 'actions/partnerships/download.php?agreement_id=' . $agreementId . '&id=' . $first['id'];
    $response = request($admin, $download);
    check($response['body'] === file_get_contents($fixture) && str_contains($response['headers'], 'attachment;'), 'Authorized download returns exact attachment');
    check(request($handles['client'], $download)['status'] === 403, 'Client cannot download Admin partnership records');
    check(request($admin, 'actions/partnerships/download.php?agreement_id=999999999&id=' . $first['id'])['status'] === 404, 'Download is bound to agreement');
    check(request($guest, 'uploads/' . $first['stored_filename'])['status'] === 403, 'Direct storage URL denied');
    $upload($fixture, 'agreement.pdf', 1);
    check(count(partnership_documents($agreementId)) === 1, 'Stale upload cannot create a new file');
    $upload($fixture, 'revised.pdf');
    $documents = partnership_documents($agreementId);
    check(count($documents) === 2 && $documents[0]['version'] == 2 && $documents[1]['stored_filename'] === $first['stored_filename'], 'New upload retains earlier file and sequence');
    $updateAgreement(['is_archived' => '1']);
    $upload($fixture);
    check(count(partnership_documents($agreementId)) === 2 && request($admin, $download)['status'] === 200, 'Archived agreement blocks uploads and retains downloads');
    $updateAgreement(['is_archived' => '0']);
    $updatePartner(['is_archived' => '1']);
    request($admin, 'actions/partnerships/save.php', array_replace($agreementFields, ['reference_no' => 'NEW-' . $suffix]));
    $statement = $pdo->prepare('SELECT COUNT(*) FROM partnership_agreements WHERE partner_id = ?'); $statement->execute([$partnerId]);
    check((int) $statement->fetchColumn() === 1, 'Archived partner cannot receive agreements');
    $upload($fixture);
    check(count(partnership_documents($agreementId)) === 2, 'Archived partner cannot receive uploads');
    check(str_contains(request($admin, 'admin/partnerships/index.php?entity=agreement&q=' . $suffix . '&status=archived')['body'], $agreementFields['reference_no']), 'Partner archive propagates to effective agreement filter');
    $updatePartner(['is_archived' => '0']);
    $updateAgreement(['start_date' => '2000-01-01', 'end_date' => '2001-01-01']);
    check(str_contains(request($admin, 'admin/partnerships/index.php?entity=agreement&q=' . $suffix . '&status=expired')['body'], $agreementFields['reference_no']), 'Expired agreement filter uses effective dates');
    $updateAgreement(['start_date' => '2099-01-01', 'end_date' => '2099-12-31']);
    check(str_contains(request($admin, 'admin/partnerships/index.php?entity=agreement&q=' . $suffix . '&status=upcoming')['body'], $agreementFields['reference_no']), 'Future effective agreements are upcoming');
    $updateAgreement(['status' => 'terminated']);
    check(agreement_state(partnership_record('agreement', $agreementId), partnership_record('partner', $partnerId)) === 'terminated', 'Termination takes precedence over dates');
    for ($i = 0; $i < 21; $i++) {
        $values = array_replace($partnerFields, ['name' => "Pagination $i $suffix"]);
        $partnerIds[] = save_partnership('partner', 0, 0, 0, $values, $accounts['admin']);
    }
    check(str_contains(request($admin, 'admin/partnerships/index.php?q=' . $suffix . '&page=2')['body'], 'Page 2 of 2'), 'Partner list paginates');
    check(str_contains(request($admin, 'admin/partnerships/index.php?q=missing-' . $suffix)['body'], 'No matching records.'), 'Empty list provides guidance');
    check(!str_contains(request($handles['client'], 'client/dashboard.php')['body'], 'admin/partnerships/'), 'Client navigation hides partnership administration');
    echo "Completed $checks partnership checks.\n";
} finally {
    foreach ($handles as $handle) { curl_close($handle); }
    foreach ($agreementIds as $id) {
        foreach (partnership_documents($id) as $document) { $path = document_storage_path($document['stored_filename']); if (is_file($path)) { unlink($path); } }
        $pdo->prepare('DELETE FROM partnership_documents WHERE agreement_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM partnership_agreements WHERE id = ?')->execute([$id]);
    }
    foreach (array_reverse($partnerIds) as $id) { $pdo->prepare('DELETE FROM partners WHERE id = ?')->execute([$id]); }
    foreach ($accounts as $id) { $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]); }
    foreach ([$fixture, $bad] as $path) { if (is_file($path)) { unlink($path); } }
}
