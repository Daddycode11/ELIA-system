<?php
declare(strict_types=1);

function document_storage_path(string $filename): string
{
    if (!preg_match('/\A[a-f0-9]{64}\.(pdf|doc|docx|jpg|jpeg|png)\z/', $filename)) {
        throw new RuntimeException('Invalid stored document filename.');
    }
    return rtrim(app_config()['document_storage'], '/\\') . DIRECTORY_SEPARATOR . $filename;
}

function validate_request_upload(array $file): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
        || !is_uploaded_file($file['tmp_name']) || !is_string($file['name'] ?? null)) {
        throw new DomainException('Upload failed. Select a file within the upload limit and try again.');
    }
    $size = filesize($file['tmp_name']);
    if ($size === false || $size < 1 || $size > app_config()['document_max_bytes']) {
        throw new DomainException('Files must be nonempty and no larger than 10 MB.');
    }
    $name = basename(str_replace('\\', '/', $file['name']));
    if (!mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 255 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
        throw new DomainException('Invalid filename.');
    }
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
    ];
    if (!isset($allowed[$extension]) || !in_array($mime, $allowed[$extension], true)) {
        throw new DomainException('File content must match PDF, DOC, DOCX, JPG, JPEG, or PNG.');
    }
    if (in_array($extension, ['jpg','jpeg','png'], true) && @getimagesize($file['tmp_name']) === false) {
        throw new DomainException('The image is not valid.');
    }
    if ($extension === 'doc') {
        $content = file_get_contents($file['tmp_name']);
        if (!str_starts_with($content, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") || !str_contains($content, "W\0o\0r\0d\0D\0o\0c\0u\0m\0e\0n\0t\0")) {
            throw new DomainException('The file is not a Word DOC document.');
        }
    }
    if ($extension === 'docx') {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) { throw new DomainException('Invalid DOCX archive.'); }
        try {
            if ($zip->numFiles > 2000 || $zip->locateName('[Content_Types].xml') === false || $zip->locateName('word/document.xml') === false) {
                throw new DomainException('Invalid DOCX document structure.');
            }
            $expanded = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $expanded += $entry['size'];
                if ($expanded > 64 * 1024 * 1024 || preg_match('/vbaProject|\.\.[\/\\\\]|\.(exe|js|php|bin)$/i', $entry['name'])) {
                    throw new DomainException('Unsupported DOCX contents. Use a document without macros or embedded executables.');
                }
            }
        } finally { $zip->close(); }
    }
    return ['original_filename' => $name, 'mime_type' => $mime, 'file_size' => $size,
        'stored_filename' => bin2hex(random_bytes(32)) . '.' . $extension];
}

function upload_request_document(array $user, int $id, int $requirementId, int $revision, array $file): void
{
    $pdo = database(); $pdo->beginTransaction(); $newPath = null;
    try {
        $request = request_record($id, $user, true);
        require_request_revision($request, $revision);
        if (!in_array($request['status'], ['draft','for_revision'], true)) {
            throw new DomainException('Documents can only be uploaded to drafts or requests for revision.');
        }
        $requirement = null;
        foreach (request_requirements($id) as $item) { if ((int) $item['id'] === $requirementId) { $requirement = $item; break; } }
        if (!$requirement) { throw new DomainException('Requirement does not belong to this request.'); }
        if ($request['status'] === 'for_revision' && $requirement['document_id'] && !in_array($requirement['document_status'], ['for_revision','rejected'], true)) {
            throw new DomainException('Only requirements marked for revision or rejected can be replaced at this stage.');
        }
        $metadata = validate_request_upload($file);
        $newPath = document_storage_path($metadata['stored_filename']);
        if (!move_uploaded_file($file['tmp_name'], $newPath)) { throw new RuntimeException('Unable to store uploaded document.'); }
        $statement = $pdo->prepare('INSERT INTO request_documents (request_id, request_requirement_id, version, original_filename, stored_filename, mime_type, file_size, uploaded_by) VALUES (:request, :requirement, :version, :original_filename, :stored_filename, :mime_type, :file_size, :user)');
        $statement->execute($metadata + ['request' => $id, 'requirement' => $requirementId, 'version' => (int) $requirement['version'] + 1, 'user' => $user['id']]);
        $statement = $pdo->prepare('UPDATE requests SET revision=revision+1 WHERE id=:id'); $statement->execute(['id' => $id]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($newPath !== null && is_file($newPath)) { unlink($newPath); }
        throw $exception;
    }
}

function review_request_document(array $user, int $id, int $documentId, string $status, string $remarks, int $revision): void
{
    $pdo = database(); $pdo->beginTransaction();
    try {
        $request = request_record($id, $user, true);
        require_request_revision($request, $revision);
        if ($request['status'] !== 'under_review' || !in_array($status, ['verified','for_revision','rejected'], true)) {
            throw new DomainException('Documents can only be reviewed while the request is under review.');
        }
        $remarks = request_remarks($remarks);
        if ($status !== 'verified' && $remarks === '') { throw new DomainException('Explain what needs to be corrected in the document remarks.'); }
        $document = null;
        foreach (request_requirements($id) as $item) { if ((int) $item['document_id'] === $documentId) { $document = $item; break; } }
        if (!$document || $document['document_status'] !== 'pending') {
            throw new DomainException('Only the latest pending document can be reviewed. Previous review decisions are preserved.');
        }
        $statement = $pdo->prepare('UPDATE request_documents SET status=:status, admin_remarks=:remarks, verified_by=:user, verified_at=NOW() WHERE id=:id AND request_id=:request');
        $statement->execute(['status' => $status, 'remarks' => $remarks, 'user' => $user['id'], 'id' => $documentId, 'request' => $id]);
        $statement = $pdo->prepare('UPDATE requests SET revision=revision+1 WHERE id=:id'); $statement->execute(['id' => $id]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}
