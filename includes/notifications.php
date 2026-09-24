<?php
declare(strict_types=1);

function unread_notifications(int $userId): int
{
    $statement = database()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user AND read_at IS NULL');
    $statement->execute(['user' => $userId]);
    return (int) $statement->fetchColumn();
}

function notify_request(array $request, string $status): void
{
    $message = ($request['reference_no'] ?: 'Request #' . $request['id']) . ': ' . request_label($status);
    if (in_array($status, ['submitted','resubmitted'], true)) {
        $statement = database()->prepare("INSERT INTO notifications (user_id, request_id, message) SELECT id, :request, :message FROM users WHERE role = 'admin' AND is_active = 1");
        $statement->execute(['request' => $request['id'], 'message' => $message]);
    }
    $statement = database()->prepare('INSERT INTO notifications (user_id, request_id, message) VALUES (:user, :request, :message)');
    $statement->execute(['user' => $request['user_id'], 'request' => $request['id'], 'message' => $message]);
}
