<?php
declare(strict_types=1);

function request_statuses(): array
{
    return ['draft','submitted','under_review','for_revision','resubmitted','approved','in_progress','post_travel','completed','rejected','cancelled'];
}

function request_label(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function request_badge(string $status): string
{
    $color = match ($status) {
        'approved', 'completed', 'verified' => 'success',
        'rejected', 'cancelled' => 'danger',
        'for_revision' => 'warning',
        'submitted', 'resubmitted', 'under_review', 'in_progress', 'post_travel' => 'primary',
        default => 'secondary',
    };
    return '<span class="badge text-bg-' . $color . '">' . escape(request_label($status)) . '</span>';
}

function request_id(mixed $value): int
{
    $id = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    if ($id === false) {
        http_response_code(404);
        exit('Record not found.');
    }
    return $id;
}

function request_record(int $id, array $user, bool $lock = false): array
{
    $sql = 'SELECT r.*, t.name AS type_name, u.full_name AS client_name, u.email AS client_email FROM requests r JOIN request_types t ON t.id = r.request_type_id JOIN users u ON u.id = r.user_id WHERE r.id = :id';
    $params = ['id' => $id];
    if ($user['role'] === 'client') {
        $sql .= ' AND r.user_id = :owner';
        $params['owner'] = $user['id'];
    } elseif ($user['role'] !== 'admin') {
        throw new LogicException('Invalid request actor.');
    }
    $statement = database()->prepare($sql . ($lock ? ' FOR UPDATE' : ''));
    $statement->execute($params);
    $request = $statement->fetch();
    if (!$request) {
        if (database()->inTransaction()) {
            database()->rollBack();
        }
        http_response_code(404);
        exit('Request not found.');
    }
    return $request;
}

function request_types(bool $activeOnly = true): array
{
    return database()->query('SELECT * FROM request_types' . ($activeOnly ? " WHERE status = 'active'" : '') . ' ORDER BY id')->fetchAll();
}

function request_templates(int $typeId): array
{
    $statement = database()->prepare("SELECT * FROM requirement_templates WHERE request_type_id = :type AND status = 'active' ORDER BY sort_order, id");
    $statement->execute(['type' => $typeId]);
    return $statement->fetchAll();
}

// Call only after loading an authorized request_record in the same request.
function request_requirements(int $id): array
{
    $statement = database()->prepare('SELECT q.*, d.id AS document_id, d.version, d.original_filename, d.stored_filename, d.status AS document_status, d.admin_remarks, d.uploaded_at FROM request_requirements q LEFT JOIN request_documents d ON d.request_requirement_id = q.id AND d.version = (SELECT MAX(v.version) FROM request_documents v WHERE v.request_requirement_id = q.id) WHERE q.request_id = :id ORDER BY q.sort_order, q.id');
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function request_history(int $id): array
{
    $statement = database()->prepare('SELECT h.*, u.full_name AS actor FROM request_status_history h JOIN users u ON u.id = h.changed_by WHERE h.request_id = :id ORDER BY h.id');
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function request_versions(int $id): array
{
    $statement = database()->prepare('SELECT d.*, q.requirement_name, u.full_name AS reviewer FROM request_documents d JOIN request_requirements q ON q.id = d.request_requirement_id LEFT JOIN users u ON u.id = d.verified_by WHERE d.request_id = :id ORDER BY q.sort_order, q.id, d.version DESC');
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function request_list(array $user, array $filters, int $page): array
{
    $where = []; $params = [];
    if ($user['role'] === 'client') {
        $where[] = 'r.user_id = :owner'; $params['owner'] = $user['id'];
    } else {
        $where[] = "r.status <> 'draft'";
        if ($filters['client'] !== '') {
            $where[] = '(u.full_name LIKE :client_name OR u.email LIKE :client_email)';
            $params['client_name'] = $params['client_email'] = '%' . $filters['client'] . '%';
        }
    }
    if ($filters['search'] !== '') {
        $where[] = '(r.title LIKE :title OR r.reference_no LIKE :reference)';
        $params['title'] = $params['reference'] = '%' . $filters['search'] . '%';
    }
    if (in_array($filters['status'], request_statuses(), true)) {
        $where[] = 'r.status = :status'; $params['status'] = $filters['status'];
    }
    if (ctype_digit($filters['type'])) {
        $where[] = 'r.request_type_id = :type'; $params['type'] = $filters['type'];
    }
    foreach (['from' => '>=', 'to' => '<='] as $field => $operator) {
        if ($filters[$field] !== '' && valid_request_date($filters[$field])) {
            $where[] = 'r.submitted_at ' . $operator . ' :' . $field;
            $params[$field] = $filters[$field] . ($field === 'from' ? ' 00:00:00' : ' 23:59:59');
        }
    }
    $from = ' FROM requests r JOIN users u ON u.id = r.user_id JOIN request_types t ON t.id = r.request_type_id WHERE ' . implode(' AND ', $where);
    $statement = database()->prepare('SELECT COUNT(*)' . $from);
    $statement->execute($params);
    $total = (int) $statement->fetchColumn();
    $page = max(1, min($page, max(1, (int) ceil($total / 20))));
    $statement = database()->prepare('SELECT r.*, t.name AS type_name, u.full_name AS client_name' . $from . ' ORDER BY r.id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20));
    $statement->execute($params);
    return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / 20))];
}
