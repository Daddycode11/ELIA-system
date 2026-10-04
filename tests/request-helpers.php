<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function row(int $id): array
{
    $statement = database()->prepare('SELECT * FROM requests WHERE id=:id'); $statement->execute(['id'=>$id]); return $statement->fetch();
}
function details(CurlHandle $browser, string $role, int $id): array
{
    $response = request($browser, "$role/requests/view.php?id=$id");
    if ($response['status'] !== 200) { throw new RuntimeException('Details page unavailable: ' . $response['status']); }
    return $response;
}
function action(CurlHandle $browser, string $role, int $id, string $path, array $fields): array
{
    $page = details($browser, $role, $id);
    return request($browser, 'actions/requests/' . $path, $fields + ['id'=>$id, 'revision'=>row($id)['revision'], 'csrf_token'=>token($page)]);
}
function state(CurlHandle $browser, string $role, int $id, string $next, string $remarks = ''): array
{
    return action($browser, $role, $id, 'status.php', ['status'=>$next,'remarks'=>$remarks]);
}
function upload(CurlHandle $browser, int $id, int $requirement, string $path, string $name = 'document.pdf'): array
{
    return action($browser, 'client', $id, 'upload.php', ['requirement_id'=>$requirement,'document'=>new CURLFile($path, 'application/octet-stream', $name)]);
}

