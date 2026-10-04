<?php
declare(strict_types=1);

function monitoring_stages(): array
{
    return ['pre_departure' => 'Pre-departure', 'post_travel' => 'Post-travel'];
}

function monitoring_list(array $user, array $filters, int $page): array
{
    $where = ["r.status IN ('approved','in_progress','post_travel','completed')"];
    $params = [];
    if ($user['role'] === 'client') { $where[] = 'r.user_id=:owner'; $params['owner'] = $user['id']; }
    elseif ($user['role'] !== 'admin') { throw new LogicException('Invalid monitoring actor.'); }
    if (in_array($filters['status'], ['approved','in_progress','post_travel','completed'], true)) {
        $where[] = 'r.status=:status'; $params['status'] = $filters['status'];
    }
    if ($filters['search'] !== '') {
        $search = '(r.title LIKE :title OR r.reference_no LIKE :reference';
        $params['title'] = $params['reference'] = '%' . $filters['search'] . '%';
        if ($user['role'] === 'admin') { $search .= ' OR u.full_name LIKE :client OR u.email LIKE :email'; $params['client'] = $params['email'] = $params['title']; }
        $where[] = $search . ')';
    }
    // Aggregate latest versions once per request; no per-row PHP database queries.
    $stats = [];
    foreach (['pre'=>'pre_departure','post'=>'post_travel'] as $prefix=>$stage) {
        foreach (['required'=>'1=1','verified'=>"d.status='verified'",'missing'=>'d.id IS NULL','pending'=>"d.status='pending'",'corrections'=>"d.status IN ('for_revision','rejected')"] as $name=>$condition) {
            $stats[] = "SUM(CASE WHEN q.stage='$stage' AND q.is_required=1 AND ($condition) THEN 1 ELSE 0 END) AS {$prefix}_{$name}";
        }
    }
    $from = ' FROM requests r JOIN users u ON u.id=r.user_id LEFT JOIN (SELECT q.request_id, ' . implode(', ', $stats)
        . ' FROM request_requirements q LEFT JOIN request_documents d ON d.request_requirement_id=q.id AND d.version=(SELECT MAX(v.version) FROM request_documents v WHERE v.request_requirement_id=q.id) GROUP BY q.request_id) s ON s.request_id=r.id';
    $current = fn (string $name) => "CASE WHEN r.status IN ('post_travel','completed') THEN COALESCE(s.post_$name,0) ELSE COALESCE(s.pre_$name,0) END";
    $started = "CASE WHEN r.status IN ('post_travel','completed') THEN r.post_travel_started_at ELSE r.pre_departure_started_at END";
    $due = "CASE WHEN r.status IN ('post_travel','completed') THEN r.post_travel_due_date ELSE r.pre_departure_due_date END";
    if ($filters['attention'] !== '') { $where[] = "r.status <> 'completed'"; }
    if ($filters['attention'] === 'setup') { $where[] = '(' . $started . ') IS NULL'; }
    elseif ($filters['attention'] === 'overdue') {
        $where[] = '(' . $due . ') < :today AND (' . $current('required') . ') > (' . $current('verified') . ')'; $params['today'] = monitoring_today();
    } elseif (in_array($filters['attention'], ['missing','pending','corrections'], true)) {
        $where[] = '(' . $current($filters['attention']) . ') > 0';
    }
    $from .= ' WHERE ' . implode(' AND ', $where);
    $statement = database()->prepare('SELECT COUNT(*)' . $from); $statement->execute($params);
    $total = (int) $statement->fetchColumn(); $pages = max(1, (int) ceil($total / 20)); $page = max(1, min($page, $pages));
    $columns = [];
    foreach (['pre','post'] as $prefix) { foreach (['required','verified','missing','pending','corrections'] as $name) { $columns[] = "COALESCE(s.{$prefix}_$name,0) AS {$prefix}_$name"; } }
    $statement = database()->prepare('SELECT r.*, u.full_name AS client_name, ' . implode(', ', $columns) . $from . ' ORDER BY r.id DESC LIMIT 20 OFFSET ' . (($page-1)*20));
    $statement->execute($params);
    return ['rows'=>$statement->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
}

function monitoring_today(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone(app_config()['timezone'] ?? 'Asia/Manila')))->format('Y-m-d');
}

function monitoring_stage_open(array $request, string $stage): bool
{
    return match ($stage) {
        // In-progress access also supports records that entered travel before this module existed.
        'pre_departure' => in_array($request['status'], ['approved','in_progress','post_travel'], true),
        'post_travel' => $request['status'] === 'post_travel',
        default => false,
    };
}

function requirement_upload_allowed(array $request, array $requirement): bool
{
    if ($requirement['stage'] === 'submission') {
        return $request['status'] === 'draft' || ($request['status'] === 'for_revision'
            && (!$requirement['document_id'] || in_array($requirement['document_status'], ['for_revision','rejected'], true)));
    }
    return monitoring_stage_open($request, $requirement['stage'])
        && !empty($request[$requirement['stage'] . '_started_at'])
        && (!$requirement['document_id'] || in_array($requirement['document_status'], ['pending','for_revision','rejected'], true));
}

function requirement_review_allowed(array $request, array $requirement): bool
{
    if ($requirement['document_status'] !== 'pending') { return false; }
    return $requirement['stage'] === 'submission' ? $request['status'] === 'under_review'
        : (monitoring_stage_open($request, $requirement['stage']) && !empty($request[$requirement['stage'] . '_started_at']));
}

function monitoring_progress(array $requirements, string $stage, ?string $dueDate): array
{
    $counts = ['required' => 0, 'uploaded' => 0, 'verified' => 0, 'pending' => 0, 'corrections' => 0, 'missing' => 0];
    foreach ($requirements as $item) {
        if ($item['stage'] !== $stage || !$item['is_required']) { continue; }
        $counts['required']++;
        if (!$item['document_id']) { $counts['missing']++; continue; }
        $counts['uploaded']++;
        if ($item['document_status'] === 'verified') { $counts['verified']++; }
        elseif ($item['document_status'] === 'pending') { $counts['pending']++; }
        else { $counts['corrections']++; }
    }
    $counts['outstanding'] = $counts['required'] - $counts['verified'];
    $counts['overdue'] = $dueDate !== null && $dueDate < monitoring_today() && $counts['outstanding'] > 0;
    return $counts;
}

function require_monitoring_complete(array $request, string $stage): void
{
    if (empty($request[$stage . '_started_at'])) {
        throw new DomainException('Initialize the ' . monitoring_stages()[$stage] . ' checklist before continuing.');
    }
    $missing = [];
    foreach (request_requirements((int) $request['id'], $stage) as $item) {
        if (!$item['is_required']) { continue; }
        if (!$item['document_id'] || $item['document_status'] !== 'verified') {
            $missing[] = $item['requirement_name'] . ' (verified document required)';
        } elseif (!is_file(document_storage_path($item['stored_filename']))) {
            $missing[] = $item['requirement_name'] . ' (stored file unavailable)';
        }
    }
    if ($missing) { throw new DomainException(monitoring_stages()[$stage] . ' incomplete: ' . implode('; ', $missing) . '.'); }
}

function configure_request_monitoring(array $user, int $id, string $stage, string $dueDate, string $remarks, int $revision): void
{
    if ($user['role'] !== 'admin') { throw new DomainException('Only Admin can set monitoring requirements and deadlines.'); }
    if (!isset(monitoring_stages()[$stage])) { throw new DomainException('Invalid monitoring stage.'); }
    if ($dueDate !== '' && !valid_request_date($dueDate)) { throw new DomainException('Enter a valid deadline.'); }
    $remarks = request_remarks($remarks);
    if ($remarks === '') { throw new DomainException('Provide instructions or a reason for the deadline change.'); }
    $pdo = database(); $pdo->beginTransaction();
    try {
        $request = request_record($id, $user, true);
        require_request_revision($request, $revision);
        if (!monitoring_stage_open($request, $stage)) { throw new DomainException('This monitoring stage is not open.'); }
        $initializing = empty($request[$stage . '_started_at']);
        if ($initializing) {
            $statement = $pdo->prepare('SELECT * FROM request_types WHERE id=:id FOR UPDATE');
            $statement->execute(['id' => $request['request_type_id']]); $type = $statement->fetch();
            if (!$type[$stage . '_ready']) { throw new DomainException('Review and mark the ' . monitoring_stages()[$stage] . ' template ready under Request types & checklists first.'); }
            snapshot_request_requirements($id, (int) $request['request_type_id'], $stage);
        }
        $requirements = request_requirements($id, $stage);
        if ($dueDate === '' && array_filter($requirements, fn ($item) => (bool) $item['is_required'])) {
            throw new DomainException('Set a deadline for the required documents.');
        }
        if (!$initializing && ($request[$stage . '_due_date'] ?? '') === $dueDate) {
            throw new DomainException('The deadline is unchanged.');
        }
        // Stage is allowlisted above; every value is bound separately.
        $statement = $pdo->prepare('UPDATE requests SET ' . $stage . '_started_at=COALESCE(' . $stage . '_started_at,NOW()), ' . $stage . '_due_date=:due, revision=revision+1 WHERE id=:id');
        $statement->execute(['due' => $dueDate ?: null, 'id' => $id]);
        $event = monitoring_stages()[$stage] . ($initializing ? ' checklist opened.' : ' deadline changed from ' . ($request[$stage . '_due_date'] ?? 'none') . '.');
        $event .= ' Deadline: ' . ($dueDate ?: 'none (no required documents)') . '. ' . $remarks;
        add_request_history($id, $request['status'], $request['status'], $event, (int) $user['id']);
        notify_request_message($request, $event);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}
