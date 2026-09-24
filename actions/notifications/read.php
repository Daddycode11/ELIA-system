<?php
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
$user = current_user(); if (!$user) { redirect('login.php'); }
require_post(); verify_csrf();
require __DIR__ . '/../../includes/requests/repository.php';
$statement = database()->prepare('UPDATE notifications SET read_at=COALESCE(read_at, NOW()) WHERE id=:id AND user_id=:user');
$statement->execute(['id'=>request_id(post_string('id')),'user'=>$user['id']]);
redirect('notifications.php');
