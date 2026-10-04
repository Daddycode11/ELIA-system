<?php
declare(strict_types=1);
require_once __DIR__ . '/documents.php';

function partnership_id(mixed $value, bool $allowZero = false): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $allowZero ? 0 : 1]]);
    if ($id === false) { throw new DomainException('Invalid record ID.'); }
    return $id;
}

function partnership_fields(string $entity): array
{
    if ($entity === 'partner') {
        return [
            'name' => ['Institution name', 'text', 200, true],
            'country' => ['Country', 'text', 100, true],
            'address' => ['Address', 'text', 500, false],
            'contact_name' => ['Contact person', 'text', 150, false],
            'contact_email' => ['Contact email', 'email', 254, false],
            'website' => ['Website (HTTPS or HTTP)', 'url', 500, false],
            'notes' => ['Notes', 'textarea', 5000, false],
            'is_archived' => ['Record availability', 'select', ['0' => 'Current', '1' => 'Archived'], true],
        ];
    }
    if ($entity !== 'agreement') { throw new DomainException('Invalid record type.'); }
    return [
        'reference_no' => ['Agreement reference', 'text', 100, true],
        'title' => ['Agreement title', 'text', 200, true],
        'agreement_type' => ['Type', 'select', ['MOU' => 'MOU', 'MOA' => 'MOA'], true],
        'status' => ['Recorded status', 'select', ['draft' => 'Draft', 'signed' => 'Signed', 'terminated' => 'Terminated'], true],
        'signed_date' => ['Date signed', 'date', 10, false],
        'start_date' => ['Effective from', 'date', 10, false],
        'end_date' => ['Effective until (blank if open-ended)', 'date', 10, false],
        'notes' => ['Scope / notes', 'textarea', 5000, false],
        'is_archived' => ['Record availability', 'select', ['0' => 'Current', '1' => 'Archived'], true],
    ];
}

function partnership_values(string $entity, array $input): array
{
    $values = [];
    foreach (partnership_fields($entity) as $key => [$label, $type, $limit, $required]) {
        $value = isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)
            || ($required && $value === '') || ($type !== 'select' && mb_strlen($value) > $limit)
            || ($type === 'select' && !array_key_exists($value, $limit))) {
            throw new DomainException('Enter a valid ' . strtolower($label) . '.');
        }
        if ($value !== '' && $type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Enter a valid contact email.');
        }
        if ($value !== '' && $type === 'url' && (!filter_var($value, FILTER_VALIDATE_URL)
            || !in_array(parse_url($value, PHP_URL_SCHEME), ['https', 'http'], true)
            || parse_url($value, PHP_URL_USER) !== null)) {
            throw new DomainException('Use a valid HTTP or HTTPS website without login credentials.');
        }
        if ($type === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if ($value !== '' && (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01')) {
                throw new DomainException('Enter a valid ' . strtolower($label) . '.');
            }
            $value = $value === '' ? null : $value;
        }
        $values[$key] = $value;
    }
    if ($entity === 'agreement') {
        if ($values['status'] === 'signed' && (!$values['signed_date'] || !$values['start_date'])) {
            throw new DomainException('Signed agreements require the date signed and effective start date.');
        }
        if ($values['end_date'] && (!$values['start_date'] || $values['end_date'] < $values['start_date'])) {
            throw new DomainException('Effective end date must be on or after the start date.');
        }
    }
    return $values;
}

function partnership_table(string $entity): string
{
    return match ($entity) { 'partner' => 'partners', 'agreement' => 'partnership_agreements', default => throw new DomainException('Invalid record type.') };
}

function partnership_record(string $entity, int $id, bool $lock = false): ?array
{
    $statement = database()->prepare('SELECT * FROM ' . partnership_table($entity) . ' WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
}

function partnership_today(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone(app_config()['timezone'])))->format('Y-m-d');
}

function agreement_state_sql(): string
{
    return "CASE WHEN a.is_archived = 1 OR p.is_archived = 1 THEN 'archived' WHEN a.status = 'draft' THEN 'draft' WHEN a.status = 'terminated' THEN 'terminated' WHEN a.end_date < ? THEN 'expired' WHEN a.start_date > ? THEN 'upcoming' ELSE 'active' END";
}

function agreement_state(array $agreement, array $partner): string
{
    if ($agreement['is_archived'] || $partner['is_archived']) { return 'archived'; }
    if ($agreement['status'] !== 'signed') { return $agreement['status']; }
    if ($agreement['end_date'] && $agreement['end_date'] < partnership_today()) { return 'expired'; }
    return $agreement['start_date'] > partnership_today() ? 'upcoming' : 'active';
}

function partnership_list(string $entity, string $search, string $status, string $type, int $partnerId, int $page): array
{
    $term = '%' . strtr($search, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
    if ($entity === 'partner') {
        $from = ' FROM partners p WHERE (p.name LIKE ? OR p.country LIKE ?)';
        $params = [$term, $term];
        if (in_array($status, ['current', 'archived'], true)) {
            $from .= ' AND p.is_archived = ?'; $params[] = (int) ($status === 'archived');
        }
        $select = 'p.*';
    } else {
        $from = ' FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id WHERE (a.reference_no LIKE ? OR a.title LIKE ? OR p.name LIKE ?)';
        $params = [$term, $term, $term];
        if ($partnerId) { $from .= ' AND a.partner_id = ?'; $params[] = $partnerId; }
        if (in_array($type, ['MOU', 'MOA'], true)) { $from .= ' AND a.agreement_type = ?'; $params[] = $type; }
        if (in_array($status, ['archived', 'draft', 'terminated', 'active', 'upcoming', 'expired'], true)) {
            $from .= ' AND (' . agreement_state_sql() . ') = ?';
            array_push($params, partnership_today(), partnership_today(), $status);
        }
        $select = 'a.*, p.name AS partner_name, p.is_archived AS partner_archived';
    }
    $statement = database()->prepare('SELECT COUNT(*)' . $from);
    $statement->execute($params);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / 20));
    $page = max(1, min($page, $pages));
    $statement = database()->prepare('SELECT ' . $select . $from . ' ORDER BY ' . ($entity === 'partner' ? 'p' : 'a') . '.id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20));
    $statement->execute($params);
    return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function require_partnership_admin(int $actor): void
{
    $statement = database()->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND is_active = 1 LOCK IN SHARE MODE");
    $statement->execute([$actor]);
    if (!$statement->fetchColumn()) { throw new DomainException('Administrator access is required.'); }
}

function save_partnership(string $entity, int $id, int $partnerId, int $revision, array $input, int $actor): int
{
    $values = partnership_values($entity, $input);
    $pdo = database(); $pdo->beginTransaction();
    try {
        require_partnership_admin($actor);
        if ($entity === 'agreement') {
            $partner = partnership_record('partner', $partnerId, true);
            if (!$partner) { throw new DomainException('Partner not found.'); }
            if (!$id && $partner['is_archived']) { throw new DomainException('Restore the partner before adding agreements.'); }
        }
        $table = partnership_table($entity);
        if ($id) {
            $record = partnership_record($entity, $id, true);
            if (!$record || ($entity === 'agreement' && (int) $record['partner_id'] !== $partnerId)) { throw new DomainException('Record not found for this partner.'); }
            if ((int) $record['revision'] !== $revision) { throw new DomainException('This record changed. Reload it before saving.'); }
            $sets = implode(', ', array_map(fn ($key) => $key . ' = ?', array_keys($values)));
            $statement = $pdo->prepare('UPDATE ' . $table . ' SET ' . $sets . ', updated_by = ?, revision = revision + 1 WHERE id = ?');
            $statement->execute([...array_values($values), $actor, $id]);
        } else {
            if ($entity === 'agreement') { $values['partner_id'] = $partnerId; }
            $values['created_by'] = $actor; $values['updated_by'] = $actor;
            $statement = $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(', ', array_keys($values)) . ') VALUES (' . implode(', ', array_fill(0, count($values), '?')) . ')');
            $statement->execute(array_values($values));
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->commit(); return $id;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062) {
            throw new DomainException($entity === 'partner' ? 'That institution and country already exist.' : 'That agreement reference already exists.');
        }
        throw $exception;
    }
}

function partnership_documents(int $id): array
{
    $statement = database()->prepare('SELECT d.*, u.full_name AS uploader FROM partnership_documents d JOIN users u ON u.id = d.uploaded_by WHERE d.agreement_id = ? ORDER BY d.version DESC');
    $statement->execute([$id]); return $statement->fetchAll();
}

function upload_partnership_document(int $id, int $revision, string $description, array $file, int $actor): void
{
    $description = trim($description);
    if ($description === '' || !mb_check_encoding($description, 'UTF-8') || mb_strlen($description) > 200 || preg_match('/[\x00-\x1F\x7F]/u', $description)) {
        throw new DomainException('Enter a document description up to 200 characters.');
    }
    $metadata = validate_document_upload($file);
    $pdo = database(); $pdo->beginTransaction(); $path = null;
    try {
        require_partnership_admin($actor);
        $snapshot = partnership_record('agreement', $id);
        if (!$snapshot) { throw new DomainException('Agreement not found.'); }
        $partner = partnership_record('partner', (int) $snapshot['partner_id'], true);
        $agreement = partnership_record('agreement', $id, true);
        if (!$partner || !$agreement || $partner['is_archived'] || $agreement['is_archived']) { throw new DomainException('Restore archived records before uploading.'); }
        if ((int) $agreement['revision'] !== $revision) { throw new DomainException('This agreement changed. Reload it before uploading.'); }
        $statement = $pdo->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM partnership_documents WHERE agreement_id = ?');
        $statement->execute([$id]); $version = (int) $statement->fetchColumn();
        $path = document_storage_path($metadata['stored_filename']);
        if (!move_uploaded_file($file['tmp_name'], $path)) { throw new RuntimeException('Unable to store document.'); }
        $statement = $pdo->prepare('INSERT INTO partnership_documents (agreement_id, version, description, original_filename, stored_filename, mime_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$id, $version, $description, $metadata['original_filename'], $metadata['stored_filename'], $metadata['mime_type'], $metadata['file_size'], $actor]);
        $pdo->prepare('UPDATE partnership_agreements SET revision = revision + 1, updated_by = ? WHERE id = ?')->execute([$actor, $id]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($path !== null && is_file($path)) { unlink($path); }
        throw $exception;
    }
}
