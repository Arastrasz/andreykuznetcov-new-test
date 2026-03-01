<?php
/* ============================================================
   Message API — handles contact form submissions and replies
   Supports Visual Creation metadata for vc requests
   ============================================================ */
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
if (!verifyCsrf()) {
    jsonResponse(['error' => 'Invalid security token.'], 403);
}

$user = currentUser();
$db = getDB();

$action = $_POST['action'] ?? 'send';

switch ($action) {

    // ── Send new message ──────────────────────────────────────
    case 'send':
        $category = trim($_POST['category'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $gameId = trim($_POST['game_id'] ?? '');
        $senderName = $user ? $user['username'] : trim($_POST['sender_name'] ?? '');
        $senderEmail = $user ? $user['email'] : trim($_POST['sender_email'] ?? '');
        $receiverId = (int)($_POST['receiver_id'] ?? ADMIN_USER_ID);

        // Validate game ID with @ prefix
        if ($gameId) {
            try {
                $gameId = validateGameId($gameId);
            } catch (InvalidArgumentException $e) {
                jsonResponse(['error' => $e->getMessage()], 400);
            }
        }

        // Validate required fields
        $validCategories = ['review', 'problem', 'visual_creation', 'collaboration', 'other'];
        if (!in_array($category, $validCategories)) {
            jsonResponse(['error' => 'Please select a valid category.'], 400);
        }
        if (mb_strlen($subject) < 3) {
            jsonResponse(['error' => 'Subject must be at least 3 characters.'], 400);
        }
        if (mb_strlen($message) < 10) {
            jsonResponse(['error' => 'Message must be at least 10 characters.'], 400);
        }
        if (!$user && empty($senderName)) {
            jsonResponse(['error' => 'Please enter your name.'], 400);
        }
        if (!$user && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Please enter a valid email.'], 400);
        }

        // Build Visual Creation metadata
        $vcMeta = null;
        if ($category === 'visual_creation') {
            $vcServer = trim($_POST['vc_server'] ?? '');
            $vcHouse = trim($_POST['vc_house'] ?? '');

            if (empty($vcServer)) {
                jsonResponse(['error' => 'Please select your server for Visual Creation requests.'], 400);
            }
            if (empty($vcHouse)) {
                jsonResponse(['error' => 'Please enter the house name.'], 400);
            }

            $vcMeta = json_encode([
                'server'      => $vcServer,
                'house'       => $vcHouse,
                'camera'      => trim($_POST['vc_camera'] ?? '') ?: null,
                'areas'       => trim($_POST['vc_areas'] ?? '') ?: null,
                'duration'    => trim($_POST['vc_duration'] ?? '') ?: null,
                'secret_room' => trim($_POST['vc_secret'] ?? '') ?: null,
                'reshade'     => isset($_POST['vc_no_reshade']) ? 'disabled' : 'default',
            ]);
        }

        // Save message
        $stmt = $db->prepare('
            INSERT INTO messages (sender_id, receiver_id, sender_name, sender_email, game_id, category, subject, message, vc_meta, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ');
        $stmt->execute([
            $user ? $user['id'] : null,
            $receiverId,
            $senderName,
            $senderEmail,
            $gameId ?: null,
            $category,
            $subject,
            sanitizeHtml($message),
            $vcMeta,
        ]);

        $messageId = (int) $db->lastInsertId();

        // Notify recipient via email
        $recipient = null;
        if ($receiverId) {
            $stmt = $db->prepare('SELECT email FROM users WHERE id = ?');
            $stmt->execute([$receiverId]);
            $recipient = $stmt->fetch();
        }
        if ($recipient) {
            sendMessageNotificationEmail(
                $recipient['email'],
                $senderName ?: 'Anonymous',
                $subject,
                $message
            );
        }

        jsonResponse([
            'success' => true,
            'message' => 'Message sent successfully.',
            'id' => $messageId,
        ]);
        break;

    // ── Reply to message ──────────────────────────────────────
    case 'reply':
        $user = requireAuth();
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if (!$parentId) {
            jsonResponse(['error' => 'Invalid message thread.'], 400);
        }
        if (mb_strlen($message) < 2) {
            jsonResponse(['error' => 'Reply must be at least 2 characters.'], 400);
        }

        // Get parent message to determine receiver
        $stmt = $db->prepare('SELECT * FROM messages WHERE id = ?');
        $stmt->execute([$parentId]);
        $parent = $stmt->fetch();

        if (!$parent) {
            jsonResponse(['error' => 'Message not found.'], 404);
        }

        // Determine receiver (reply goes to the other party)
        $receiverId = ($parent['sender_id'] == $user['id'])
            ? $parent['receiver_id']
            : $parent['sender_id'];

        // Save reply
        $stmt = $db->prepare('
            INSERT INTO messages (sender_id, receiver_id, parent_id, category, subject, message, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ');
        $stmt->execute([
            $user['id'],
            $receiverId,
            $parentId,
            $parent['category'],
            'Re: ' . $parent['subject'],
            sanitizeHtml($message),
        ]);

        // Notify recipient
        if ($receiverId) {
            $stmt = $db->prepare('SELECT email, username FROM users WHERE id = ?');
            $stmt->execute([$receiverId]);
            $recipient = $stmt->fetch();
            if ($recipient) {
                sendMessageNotificationEmail(
                    $recipient['email'],
                    $user['username'],
                    'Re: ' . $parent['subject'],
                    $message
                );
            }
        }

        jsonResponse([
            'success' => true,
            'message' => 'Reply sent.',
            'id' => (int) $db->lastInsertId(),
        ]);
        break;

    // ── Mark message as read ──────────────────────────────────
    case 'mark_read':
        $user = requireAuth();
        $messageId = (int)($_POST['message_id'] ?? 0);

        if ($messageId) {
            $db->prepare('UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?')
               ->execute([$messageId, $user['id']]);
        }

        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action.'], 400);
}
