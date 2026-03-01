<?php
/* ============================================================
   THE ARCHIVES OF CLAN LAR — Notifications API
   GET  → fetch notifications + unread count
   POST → mark all as read
   ============================================================ */
require_once __DIR__ . '/../config/db.php';

$user = currentUser();
if (!$user) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

$userId = (int)$user['id'];

// ── GET: Fetch notifications ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
    $notifications = getNotifications($userId, $limit);
    $unreadCount   = getUnreadNotificationCount($userId);

    // Format for frontend
    $formatted = array_map(function ($n) {
        return [
            'id'         => (int)$n['id'],
            'type'       => $n['type'],
            'title'      => $n['title'],
            'message'    => $n['message'],
            'link'       => $n['link'],
            'is_read'    => (bool)$n['is_read'],
            'created_at' => $n['created_at'],
            'time_ago'   => timeAgo($n['created_at']),
        ];
    }, $notifications);

    jsonResponse([
        'success'       => true,
        'notifications' => $formatted,
        'unread_count'  => $unreadCount,
    ]);
}

// ── POST: Actions ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // Accept JSON body too
    if (empty($action)) {
        $jsonBody = json_decode(file_get_contents('php://input'), true);
        $action = $jsonBody['action'] ?? '';
    }

    switch ($action) {
        case 'mark_read':
            markNotificationsRead($userId);
            jsonResponse([
                'success'      => true,
                'unread_count' => 0,
            ]);
            break;

        case 'mark_one_read':
            $notifId = (int)($_POST['notification_id'] ?? $jsonBody['notification_id'] ?? 0);
            if ($notifId > 0) {
                getDB()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
                       ->execute([$notifId, $userId]);
            }
            jsonResponse([
                'success'      => true,
                'unread_count' => getUnreadNotificationCount($userId),
            ]);
            break;

        default:
            jsonResponse(['error' => 'Unknown action'], 400);
    }
}

// ── Anything else ────────────────────────────────────────────
jsonResponse(['error' => 'Method not allowed'], 405);
