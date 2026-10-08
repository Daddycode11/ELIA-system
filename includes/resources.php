<?php
declare(strict_types=1);
require_once __DIR__ . '/documents.php';

/** Downloadable forms, guidelines, and reports published by the ELIA Office. */

function resource_categories(): array
{
    return ['form' => 'Form', 'report' => 'Report', 'guideline' => 'Guideline', 'other' => 'Other'];
}

function resource_add(string $title, string $description, string $category, bool $published, array $file, int $actor): int
{
    $title = trim($title);
    $description = trim($description);
    foreach ([$title, $description] as $text) {
        if (!mb_check_encoding($text, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/u', $text)) {
            throw new DomainException('Text contains invalid characters.');
        }
    }
    if ($title === '' || mb_strlen($title) > 200) { throw new DomainException('Enter a title up to 200 characters.'); }
    if (mb_strlen($description) > 500) { throw new DomainException('The description can be up to 500 characters.'); }
    if (!isset(resource_categories()[$category])) { throw new DomainException('Select a valid category.'); }
    $metadata = validate_document_upload($file);
    $path = document_storage_path($metadata['stored_filename']);
    if (!move_uploaded_file($file['tmp_name'], $path)) { throw new RuntimeException('Unable to store the file.'); }
    try {
        $statement = database()->prepare('INSERT INTO resources (title, description, category, original_filename, stored_filename, mime_type, file_size, is_published, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$title, $description, $category, $metadata['original_filename'], $metadata['stored_filename'],
            $metadata['mime_type'], $metadata['file_size'], (int) $published, $actor]);
        return (int) database()->lastInsertId();
    } catch (Throwable $exception) {
        if (is_file($path)) { unlink($path); }
        throw $exception;
    }
}

function resource_set_published(int $id, bool $published): void
{
    if (!resource_find($id)) { throw new DomainException('Resource not found.'); }
    $statement = database()->prepare('UPDATE resources SET is_published = ? WHERE id = ?');
    $statement->execute([(int) $published, $id]);
}

function resource_find(int $id): ?array
{
    $statement = database()->prepare('SELECT * FROM resources WHERE id = ?');
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
}

function resource_admin_list(): array
{
    return database()->query('SELECT r.*, u.full_name AS uploader FROM resources r JOIN users u ON u.id = r.created_by ORDER BY r.id DESC')->fetchAll();
}

function resource_published_list(): array
{
    return database()->query('SELECT id, title, description, category, original_filename, file_size FROM resources WHERE is_published = 1 ORDER BY id DESC')->fetchAll();
}

function resource_extension(array $resource): string
{
    return strtoupper(pathinfo((string) $resource['original_filename'], PATHINFO_EXTENSION));
}

function resource_size_label(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}
