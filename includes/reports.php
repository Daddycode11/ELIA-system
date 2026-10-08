<?php
declare(strict_types=1);
require_once __DIR__ . '/requests/repository.php';
require_once __DIR__ . '/partnerships.php';

/* ---------- Filters ---------- */

function report_date(mixed $value): ?string
{
    if (!is_string($value)) { return null; }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : null;
}

function report_like(string $text): string
{
    return '%' . strtr($text, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
}

function report_request_filters(array $query): array
{
    $today = partnership_today();
    $from = report_date($query['from'] ?? null) ?? substr($today, 0, 4) . '-01-01';
    $to = report_date($query['to'] ?? null) ?? $today;
    if ($to < $from) { $to = $from; }
    $typeId = filter_var($query['type_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $status = is_string($query['status'] ?? null) && in_array($query['status'], array_diff(request_statuses(), ['draft']), true) ? $query['status'] : '';
    $country = is_string($query['country'] ?? null) ? mb_substr(trim($query['country']), 0, 100) : '';
    return ['from' => $from, 'to' => $to, 'type_id' => $typeId, 'status' => $status, 'country' => $country];
}

function report_agreement_filters(array $query): array
{
    $status = is_string($query['status'] ?? null) && in_array($query['status'], ['draft', 'upcoming', 'active', 'expired', 'terminated', 'archived'], true) ? $query['status'] : '';
    $type = is_string($query['type'] ?? null) && in_array($query['type'], ['MOU', 'MOA'], true) ? $query['type'] : '';
    $country = is_string($query['country'] ?? null) ? mb_substr(trim($query['country']), 0, 100) : '';
    return ['status' => $status, 'type' => $type, 'country' => $country];
}

/* ---------- Requests ---------- */

const REPORT_DATE_EXPR = 'COALESCE(r.submitted_at, r.created_at)';

function report_request_where(array $f): array
{
    $sql = " WHERE r.status <> 'draft' AND " . REPORT_DATE_EXPR . ' >= ? AND ' . REPORT_DATE_EXPR . ' <= ?';
    $params = [$f['from'] . ' 00:00:00', $f['to'] . ' 23:59:59'];
    if ($f['type_id']) { $sql .= ' AND r.request_type_id = ?'; $params[] = $f['type_id']; }
    if ($f['status'] !== '') { $sql .= ' AND r.status = ?'; $params[] = $f['status']; }
    if ($f['country'] !== '') { $sql .= ' AND r.country LIKE ?'; $params[] = report_like($f['country']); }
    return [$sql, $params];
}

function report_requests(array $f, int $limit = 0): array
{
    [$where, $params] = report_request_where($f);
    $sql = 'SELECT r.id, r.reference_no, r.title, u.full_name AS client, t.name AS type_name, r.destination, r.country,
                   r.start_date, r.end_date, r.status, r.submitted_at, r.approved_at, r.completed_at
            FROM requests r JOIN users u ON u.id = r.user_id JOIN request_types t ON t.id = r.request_type_id'
        . $where . ' ORDER BY ' . REPORT_DATE_EXPR . ' DESC, r.id DESC' . ($limit > 0 ? ' LIMIT ' . $limit : '');
    $statement = database()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function report_request_summary(array $f): array
{
    [$where, $params] = report_request_where($f);
    $base = ' FROM requests r JOIN request_types t ON t.id = r.request_type_id' . $where;
    $pdo = database();
    $run = static function (string $sql) use ($pdo, $params): array {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    };
    $byStatus = array_column($run('SELECT r.status AS k, COUNT(*) AS n' . $base . ' GROUP BY r.status'), 'n', 'k');
    $byType = array_column($run('SELECT t.name AS k, COUNT(*) AS n' . $base . ' GROUP BY t.id, t.name ORDER BY n DESC'), 'n', 'k');
    $byMonth = array_column($run('SELECT DATE_FORMAT(' . REPORT_DATE_EXPR . ", '%Y-%m') AS k, COUNT(*) AS n" . $base . ' GROUP BY k ORDER BY k'), 'n', 'k');
    $byCountry = array_column($run("SELECT r.country AS k, COUNT(*) AS n" . $base . " AND r.country <> '' GROUP BY r.country ORDER BY n DESC, r.country LIMIT 10"), 'n', 'k');
    $avg = $run('SELECT AVG(DATEDIFF(r.completed_at, r.submitted_at)) AS d' . $base . ' AND r.completed_at IS NOT NULL AND r.submitted_at IS NOT NULL');
    return [
        'total' => array_sum($byStatus),
        'by_status' => array_map('intval', $byStatus),
        'by_type' => array_map('intval', $byType),
        'by_month' => array_map('intval', $byMonth),
        'by_country' => array_map('intval', $byCountry),
        'avg_days' => $avg[0]['d'] !== null ? round((float) $avg[0]['d'], 1) : null,
    ];
}

/* ---------- MOU / MOA ---------- */

function report_agreements(array $f): array
{
    $today = partnership_today();
    $sql = 'SELECT * FROM (SELECT a.reference_no, a.title, a.agreement_type, p.name AS partner_name, p.country,
                   a.signed_date, a.start_date, a.end_date, (' . agreement_state_sql() . ') AS state
            FROM partnership_agreements a JOIN partners p ON p.id = a.partner_id) x WHERE 1 = 1';
    $params = [$today, $today];
    if ($f['status'] !== '') { $sql .= ' AND x.state = ?'; $params[] = $f['status']; }
    if ($f['type'] !== '') { $sql .= ' AND x.agreement_type = ?'; $params[] = $f['type']; }
    if ($f['country'] !== '') { $sql .= ' AND x.country LIKE ?'; $params[] = report_like($f['country']); }
    $sql .= ' ORDER BY x.partner_name, x.reference_no';
    $statement = database()->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function report_agreement_summary(array $rows): array
{
    $byState = [];
    $byCountry = [];
    $partners = [];
    $expiring = 0;
    $limit = (new DateTimeImmutable(partnership_today()))->modify('+90 days')->format('Y-m-d');
    foreach ($rows as $row) {
        $byState[$row['state']] = ($byState[$row['state']] ?? 0) + 1;
        $byCountry[$row['country']] = ($byCountry[$row['country']] ?? 0) + 1;
        $partners[$row['partner_name'] . '|' . $row['country']] = true;
        if ($row['state'] === 'active' && $row['end_date'] !== null && $row['end_date'] <= $limit) { $expiring++; }
    }
    arsort($byCountry);
    return ['total' => count($rows), 'partners' => count($partners), 'by_state' => $byState, 'by_country' => array_slice($byCountry, 0, 10, true), 'expiring' => $expiring];
}

/* ---------- CSV ---------- */

/** Prevents spreadsheet formula injection when a cell starts with = + - @ tab or CR. */
function report_csv_cell(mixed $value): string
{
    $text = (string) ($value ?? '');
    return preg_match('/^[=+\-@\t\r]/', $text) ? "'" . $text : $text;
}

function report_csv_send(string $filename, array $header, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map('report_csv_cell', $row), ',', '"', '');
    }
    fclose($out);
    exit;
}
