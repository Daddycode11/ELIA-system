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
    notify_request_message($request, request_label($status), in_array($status, ['submitted','resubmitted'], true));
}

function notify_request_message(array $request, string $text, bool $notifyAdmins = false, bool $notifyClient = true): void
{
    $message = mb_substr(($request['reference_no'] ?: 'Request #' . $request['id']) . ': ' . $text, 0, 500);
    if ($notifyAdmins) {
        $statement = database()->prepare("INSERT INTO notifications (user_id, request_id, message) SELECT id, :request, :message FROM users WHERE role = 'admin' AND is_active = 1");
        $statement->execute(['request' => $request['id'], 'message' => $message]);
    }
    if ($notifyClient) {
        $statement = database()->prepare('INSERT INTO notifications (user_id, request_id, message) VALUES (:user, :request, :message)');
        $statement->execute(['user' => $request['user_id'], 'request' => $request['id'], 'message' => $message]);
    }
}
