<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = requireAdmin(); require_post(); verify_csrf();
require __DIR__ . '/../../includes/requests/workflow.php';
$operation = post_string('operation');
$typeId = $operation === 'new_type' ? 0 : request_id(post_string('type_id'));
$pdo = database();
try {
    $name = trim(post_string('name'));
    $limit = $operation === 'template' ? 180 : 150;
    if ($name === '' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > $limit || str_contains($name, "\0")) { throw new DomainException('Enter a valid name.'); }
    $description = request_remarks(post_string('description'));
    $pdo->beginTransaction();
    if ($operation === 'new_type') {
        $statement = $pdo->prepare('INSERT INTO request_types (name) VALUES (:name)'); $statement->execute(['name'=>$name]);
        $typeId = (int) $pdo->lastInsertId();
    } else {
        $statement = $pdo->prepare('SELECT id FROM request_types WHERE id=:id FOR UPDATE'); $statement->execute(['id'=>$typeId]);
        if (!$statement->fetch()) { throw new DomainException('Request type not found.'); }
        $status = post_string('status');
        if (!in_array($status, ['active','inactive'], true)) { throw new DomainException('Invalid configuration status.'); }
        if ($operation === 'type') {
            $statement = $pdo->prepare('UPDATE request_types SET name=:name, description=:description, status=:status, checklist_ready=:ready, pre_departure_ready=:pre_ready, post_travel_ready=:post_ready WHERE id=:id');
            $statement->execute(['name'=>$name,'description'=>$description,'status'=>$status,'ready'=>(int) (post_string('checklist_ready') === '1'),'pre_ready'=>(int) (post_string('pre_departure_ready') === '1'),'post_ready'=>(int) (post_string('post_travel_ready') === '1'),'id'=>$typeId]);
        } elseif ($operation === 'template') {
            $sort = filter_var(post_string('sort_order'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>9999]]);
            if ($sort === false) { throw new DomainException('Sort order must be between 0 and 9999.'); }
            $templateId = post_string('template_id') === '0' ? 0 : request_id(post_string('template_id'));
            $stage = post_string('stage') ?: 'submission';
            $readyColumns = ['submission'=>'checklist_ready','pre_departure'=>'pre_departure_ready','post_travel'=>'post_travel_ready'];
            if (!isset($readyColumns[$stage])) { throw new DomainException('Invalid checklist stage.'); }
            $previousStage = $stage;
            $params = ['type'=>$typeId,'name'=>$name,'description'=>$description,'status'=>$status,'required'=>(int) (post_string('is_required') === '1'),'sort'=>$sort,'stage'=>$stage];
            if ($templateId) {
                $statement = $pdo->prepare('SELECT stage FROM requirement_templates WHERE id=:id AND request_type_id=:type');
                $statement->execute(['id'=>$templateId,'type'=>$typeId]);
                $previousStage = $statement->fetchColumn();
                if ($previousStage === false) { throw new DomainException('Requirement not found for this type.'); }
                $statement = $pdo->prepare('UPDATE requirement_templates SET requirement_name=:name, description=:description, status=:status, is_required=:required, sort_order=:sort, stage=:stage WHERE id=:id AND request_type_id=:type');
                $statement->execute($params + ['id'=>$templateId]);
            } else {
                $statement = $pdo->prepare('INSERT INTO requirement_templates (request_type_id, requirement_name, description, status, is_required, sort_order, stage) VALUES (:type,:name,:description,:status,:required,:sort,:stage)'); $statement->execute($params);
            }
            $columns = array_unique([$readyColumns[$stage], $readyColumns[$previousStage]]);
            $statement = $pdo->prepare('UPDATE request_types SET ' . implode(', ', array_map(fn ($column) => $column . '=0', $columns)) . ' WHERE id=:id'); $statement->execute(['id'=>$typeId]);
        } else { throw new DomainException('Unknown configuration action.'); }
    }
    $pdo->commit();
    flash('success', 'Configuration saved. After editing requirements, review the checklist and mark the type ready. Existing requests are unchanged.');
} catch (DomainException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) !== 1062) { throw $exception; }
    flash('danger', $exception instanceof PDOException ? 'That name already exists.' : $exception->getMessage());
}
redirect('admin/request-types.php' . ($typeId ? '?id=' . $typeId : ''));
