<?php
declare(strict_types=1);
require_once __DIR__ . '/../documents.php';

function validate_request_upload(array $file): array
{
    return validate_document_upload($file);
}

function upload_request_document(array $user, int $id, int $requirementId, int $revision, array $file): void
{
    $pdo = database(); $pdo->beginTransaction(); $newPath = null;
    try {
        $request = request_record($id, $user, true);
        require_request_revision($request, $revision);
        $requirement = null;
        foreach (request_requirements($id) as $item) { if ((int) $item['id'] === $requirementId) { $requirement = $item; break; } }
        if (!$requirement) { throw new DomainException('Requirement does not belong to this request.'); }
        if (!requirement_upload_allowed($request, $requirement)) {
            throw new DomainException('This document cannot be uploaded or replaced at the current stage. Verified documents are locked.');
        }
        $metadata = validate_request_upload($file);
        $newPath = document_storage_path($metadata['stored_filename']);
        if (!move_uploaded_file($file['tmp_name'], $newPath)) { throw new RuntimeException('Unable to store uploaded document.'); }
        $statement = $pdo->prepare('INSERT INTO request_documents (request_id, request_requirement_id, version, original_filename, stored_filename, mime_type, file_size, uploaded_by) VALUES (:request, :requirement, :version, :original_filename, :stored_filename, :mime_type, :file_size, :user)');
        $statement->execute($metadata + ['request' => $id, 'requirement' => $requirementId, 'version' => (int) $requirement['version'] + 1, 'user' => $user['id']]);
        $statement = $pdo->prepare('UPDATE requests SET revision=revision+1 WHERE id=:id'); $statement->execute(['id' => $id]);
        if ($requirement['stage'] !== 'submission') {
            notify_request_message($request, monitoring_stages()[$requirement['stage']] . ': ' . $requirement['requirement_name'] . ' uploaded; review required.', true, false);
        }
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
        if (!in_array($status, ['verified','for_revision','rejected'], true)) {
            throw new DomainException('Invalid document review decision.');
        }
        $remarks = request_remarks($remarks);
        if ($status !== 'verified' && $remarks === '') { throw new DomainException('Explain what needs to be corrected in the document remarks.'); }
        $document = null;
        foreach (request_requirements($id) as $item) { if ((int) $item['document_id'] === $documentId) { $document = $item; break; } }
        if (!$document || !requirement_review_allowed($request, $document)) {
            throw new DomainException('Only the latest pending document in an open review stage can be reviewed. Previous decisions are preserved.');
        }
        $statement = $pdo->prepare('UPDATE request_documents SET status=:status, admin_remarks=:remarks, verified_by=:user, verified_at=NOW() WHERE id=:id AND request_id=:request');
        $statement->execute(['status' => $status, 'remarks' => $remarks, 'user' => $user['id'], 'id' => $documentId, 'request' => $id]);
        $statement = $pdo->prepare('UPDATE requests SET revision=revision+1 WHERE id=:id'); $statement->execute(['id' => $id]);
        if ($document['stage'] !== 'submission') {
            notify_request_message($request, monitoring_stages()[$document['stage']] . ': ' . $document['requirement_name'] . ' — ' . request_label($status) . '. ' . $remarks);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}
