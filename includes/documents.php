<?php
declare(strict_types=1);

function document_storage_path(string $filename): string
{
    if (!preg_match('/\A[a-f0-9]{64}\.(pdf|doc|docx|jpg|jpeg|png)\z/', $filename)) {
        throw new RuntimeException('Invalid stored document filename.');
    }
    return rtrim(app_config()['document_storage'], '/\\') . DIRECTORY_SEPARATOR . $filename;
}

/** Call only after checking the authenticated user's permission for this document. */
function send_document_attachment(array $document): never
{
    $path = document_storage_path($document['stored_filename']);
    if (!is_file($path)) { http_response_code(404); exit('Document not found.'); }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="document.' . pathinfo($document['stored_filename'], PATHINFO_EXTENSION) . '"; filename*=UTF-8\'\'' . rawurlencode($document['original_filename']));
    header('Content-Length: ' . filesize($path));
    session_write_close();
    readfile($path);
    exit;
}

function validate_document_upload(array $file): array
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

