<?php
declare(strict_types=1);

/** Announcements / initiatives shown on the landing page and the client information page. */

function announcement_categories(): array
{
    return ['announcement' => 'Announcement', 'initiative' => 'Initiative', 'advisory' => 'Advisory', 'event' => 'Event'];
}

function announcement_statuses(): array
{
    return ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];
}

function announcement_id(mixed $value, bool $allowZero = false): int
{
    $id = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $allowZero ? 0 : 1]]) : false;
    if ($id === false) { throw new DomainException('Invalid record.'); }
    return $id;
}

function announcement_validate(array $input): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $summary = trim((string) ($input['summary'] ?? ''));
    $body = trim((string) ($input['body'] ?? ''));
    $category = (string) ($input['category'] ?? '');
    $status = (string) ($input['status'] ?? '');
    foreach ([$title, $summary, $body] as $text) {
        if (!mb_check_encoding($text, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
            throw new DomainException('Text contains invalid characters.');
        }
    }
    if ($title === '' || mb_strlen($title) > 200) { throw new DomainException('Enter a title up to 200 characters.'); }
    if (mb_strlen($summary) > 300) { throw new DomainException('The short summary can be up to 300 characters.'); }
    if ($body === '' || mb_strlen($body) > 20000) { throw new DomainException('Enter the content (up to 20,000 characters).'); }
    if (!isset(announcement_categories()[$category])) { throw new DomainException('Select a valid category.'); }
    if (!isset(announcement_statuses()[$status])) { throw new DomainException('Select a valid status.'); }
    return ['title' => $title, 'summary' => $summary, 'body' => $body, 'category' => $category, 'status' => $status];
}

function announcement_find(int $id): ?array
{
    $statement = database()->prepare('SELECT * FROM announcements WHERE id = ?');
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
}

function announcement_save(?int $id, int $revision, array $input, int $actor): int
{
    $values = announcement_validate($input);
    $pdo = database();
    $pdo->beginTransaction();
    try {
        if ($id === null) {
            $statement = $pdo->prepare('INSERT INTO announcements (title, summary, body, category, status, published_at, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$values['title'], $values['summary'], $values['body'], $values['category'], $values['status'],
                $values['status'] === 'published' ? date('Y-m-d H:i:s') : null, $actor, $actor]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $statement = $pdo->prepare('SELECT * FROM announcements WHERE id = ? FOR UPDATE');
            $statement->execute([$id]);
            $current = $statement->fetch();
            if (!$current) { throw new DomainException('Announcement not found.'); }
            if ((int) $current['revision'] !== $revision) { throw new DomainException('This announcement was changed by someone else. Reload it and try again.'); }
            $publishedAt = $current['published_at'];
            if ($values['status'] === 'published' && $publishedAt === null) { $publishedAt = date('Y-m-d H:i:s'); }
            $statement = $pdo->prepare('UPDATE announcements SET title=?, summary=?, body=?, category=?, status=?, published_at=?, revision=revision+1, updated_by=? WHERE id=?');
            $statement->execute([$values['title'], $values['summary'], $values['body'], $values['category'], $values['status'], $publishedAt, $actor, $id]);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}

function announcement_admin_list(string $search, string $status, int $page): array
{
    $term = '%' . strtr($search, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
    $from = ' FROM announcements a JOIN users u ON u.id = a.created_by WHERE (a.title LIKE ? OR a.summary LIKE ?)';
    $params = [$term, $term];
    if (isset(announcement_statuses()[$status])) { $from .= ' AND a.status = ?'; $params[] = $status; }
    $statement = database()->prepare('SELECT COUNT(*)' . $from);
    $statement->execute($params);
    $total = (int) $statement->fetchColumn();
    $pages = max(1, (int) ceil($total / 20));
    $page = max(1, min($page, $pages));
    $statement = database()->prepare('SELECT a.*, u.full_name AS author' . $from . ' ORDER BY a.id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20));
    $statement->execute($params);
    return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function announcement_public_count(): int
{
    return (int) database()->query("SELECT COUNT(*) FROM announcements WHERE status = 'published' AND published_at <= NOW()")->fetchColumn();
}

function announcement_public_list(int $limit, int $offset = 0): array
{
    $limit = max(1, min($limit, 50));
    $offset = max(0, $offset);
    $statement = database()->prepare("SELECT id, title, summary, body, category, published_at FROM announcements WHERE status = 'published' AND published_at <= NOW() ORDER BY published_at DESC, id DESC LIMIT $limit OFFSET $offset");
    $statement->execute();
    return $statement->fetchAll();
}

function announcement_public_find(int $id): ?array
{
    $statement = database()->prepare("SELECT id, title, summary, body, category, published_at FROM announcements WHERE id = ? AND status = 'published' AND published_at <= NOW()");
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
}

/** Short text for cards: the summary, or the start of the body. */
function announcement_excerpt(array $announcement, int $length = 160): string
{
    $text = $announcement['summary'] !== '' ? $announcement['summary'] : preg_replace('/\s+/', ' ', $announcement['body']);
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
}

function announcement_date(?string $value): string
{
    return $value ? (new DateTimeImmutable($value))->format('F j, Y') : '';
}
