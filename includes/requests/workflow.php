<?php
declare(strict_types=1);
require_once __DIR__ . '/repository.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/documents.php';
require_once __DIR__ . '/../notifications.php';

function request_transitions(string $role, string $status): array
{
    $map = $role === 'client' ? [
        'draft' => ['submitted','cancelled'],
        'submitted' => ['cancelled'],
        'for_revision' => ['resubmitted','cancelled'],
        'resubmitted' => ['cancelled'],
    ] : [
        'submitted' => ['under_review'], 'resubmitted' => ['under_review'],
        'under_review' => ['for_revision','approved','rejected'],
        'approved' => ['in_progress'], 'in_progress' => ['post_travel'],
        'post_travel' => ['completed'],
    ];
    return $map[$status] ?? [];
}

function add_request_history(int $id, ?string $old, string $new, string $remarks, int $actor): void
{
    $statement = database()->prepare('INSERT INTO request_status_history (request_id, old_status, new_status, remarks, changed_by) VALUES (:id, :old, :new, :remarks, :actor)');
    $statement->execute(['id' => $id, 'old' => $old, 'new' => $new, 'remarks' => $remarks, 'actor' => $actor]);
}

function save_request(array $user, ?int $id, int $typeId, array $fields, int $revision): int
{
    $pdo = database();
    $pdo->beginTransaction();
    try {
        if ($id !== null) {
            $request = request_record($id, $user, true);
            require_request_revision($request, $revision);
            if (!in_array($request['status'], ['draft','for_revision'], true)) {
                throw new DomainException('Only drafts and requests for revision can be edited.');
            }
            $statement = $pdo->prepare('UPDATE requests SET title=:title, purpose=:purpose, destination=:destination, country=:country, start_date=:start_date, end_date=:end_date, revision=revision+1 WHERE id=:id AND user_id=:owner');
            $statement->execute($fields + ['id' => $id, 'owner' => $user['id']]);
        } else {
            $statement = $pdo->prepare("SELECT * FROM request_types WHERE id=:id AND status='active' FOR UPDATE");
            $statement->execute(['id' => $typeId]);
            $type = $statement->fetch();
            if (!$type || !$type['checklist_ready']) {
                throw new DomainException('This request type is awaiting ELIA checklist configuration. Please contact the office.');
            }
            $statement = $pdo->prepare('INSERT INTO requests (user_id, request_type_id, title, purpose, destination, country, start_date, end_date) VALUES (:owner, :type, :title, :purpose, :destination, :country, :start_date, :end_date)');
            $statement->execute($fields + ['owner' => $user['id'], 'type' => $typeId]);
            $id = (int) $pdo->lastInsertId();
            $statement = $pdo->prepare("INSERT INTO request_requirements (request_id, requirement_template_id, requirement_name, description, is_required, sort_order) SELECT :request, id, requirement_name, description, is_required, sort_order FROM requirement_templates WHERE request_type_id=:type AND status='active' ORDER BY sort_order, id");
            $statement->execute(['request' => $id, 'type' => $typeId]);
            add_request_history($id, null, 'draft', 'Draft created.', (int) $user['id']);
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}

function check_request_submission(array $request, array $requirements, bool $approval = false): void
{
    $missing = [];
    foreach (['title','purpose','destination','country','start_date','end_date'] as $field) {
        if (!$request[$field]) { $missing[] = request_label($field); }
    }
    foreach ($requirements as $requirement) {
        if ($requirement['is_required'] && !$requirement['document_id']) {
            $missing[] = $requirement['requirement_name'] . ' (upload required)';
        } elseif ($requirement['document_id']) {
            if (in_array($requirement['document_status'], ['for_revision','rejected'], true)) {
                $missing[] = $requirement['requirement_name'] . ' (replace document)';
            } elseif ($approval && $requirement['document_status'] !== 'verified') {
                $missing[] = $requirement['requirement_name'] . ' (verification required)';
            }
            if (!is_file(document_storage_path($requirement['stored_filename']))) {
                $missing[] = $requirement['requirement_name'] . ' (stored file unavailable)';
            }
        }
    }
    if ($missing) { throw new DomainException('Incomplete: ' . implode('; ', $missing) . '.'); }
}

function transition_request(array $user, int $id, string $next, string $remarks, int $revision): void
{
    $pdo = database(); $pdo->beginTransaction();
    try {
        $request = request_record($id, $user, true);
        require_request_revision($request, $revision);
        if (!in_array($next, request_transitions($user['role'], $request['status']), true)) {
            throw new DomainException('This status change is not allowed.');
        }
        $remarks = request_remarks($remarks);
        if (in_array($next, ['for_revision','rejected','cancelled'], true) && $remarks === '') {
            throw new DomainException('Provide remarks explaining this action.');
        }
        if (in_array($next, ['submitted','resubmitted','approved','completed'], true)) {
            check_request_submission($request, request_requirements($id), in_array($next, ['approved','completed'], true));
        }
        if ($next === 'submitted') {
            // The locked AUTO_INCREMENT primary key provides a durable unique sequence.
            $request['reference_no'] = 'ELIA-' . date('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        }
        $sql = 'UPDATE requests SET status=:status, reference_no=:reference, revision=revision+1';
        $params = ['status' => $next, 'reference' => $request['reference_no'], 'id' => $id];
        if ($user['role'] === 'admin') { $sql .= ', remarks=:remarks'; $params['remarks'] = $remarks; }
        $timestamp = ['submitted' => 'submitted_at', 'approved' => 'approved_at', 'completed' => 'completed_at'][$next] ?? null;
        if ($timestamp) { $sql .= ', ' . $timestamp . '=NOW()'; }
        $statement = $pdo->prepare($sql . ' WHERE id=:id'); $statement->execute($params);
        add_request_history($id, $request['status'], $next, $remarks, (int) $user['id']);
        notify_request($request, $next);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $exception;
    }
}
