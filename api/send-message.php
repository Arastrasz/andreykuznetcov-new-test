<?php
/* ============================================================
   THE ARCHIVES OF CLAN LAR — Send Message API
   Contact form handler: validates, routes, stores, notifies
   POST only — returns JSON
   ============================================================ */
require_once __DIR__ . '/../config/db.php';

// ── Only POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// ── CSRF ─────────────────────────────────────────────────────
if (!verifyCsrf()) {
    jsonResponse(['error' => 'Invalid security token. Please reload the page and try again.'], 403);
}

// ── Honeypot ─────────────────────────────────────────────────
if (isHoneypotTriggered()) {
    // Silently accept to confuse bots
    jsonResponse(['success' => true, 'message' => t('contact.success')]);
}

// ── Rate limit (3 per hour) ──────────────────────────────────
if (!checkRateLimit('contact_message', 3)) {
    jsonResponse(['error' => t('contact.error_rate')], 429);
}

// ── Collect & sanitize inputs ────────────────────────────────
$user      = currentUser();
$tabSource = in_array($_POST['tab_source'] ?? '', ['gif', 'card']) ? $_POST['tab_source'] : 'gif';
$server    = in_array($_POST['server'] ?? '', ['PC-EU', 'PC-NA']) ? $_POST['server'] : '';
$handle    = trim($_POST['game_handle'] ?? '');
$houseName = trim($_POST['house_name'] ?? '');
$subject   = trim($_POST['subject'] ?? '');
$details   = trim($_POST['details'] ?? '');

// Tab-specific fields
$cameraPerspective = trim($_POST['camera_perspective'] ?? '');
$areasToCapture    = trim($_POST['areas_to_capture'] ?? '');
$tourDuration      = trim($_POST['tour_duration'] ?? '');
$secretRoom        = trim($_POST['secret_room'] ?? '');
$disableReshade    = !empty($_POST['disable_reshade']) ? 1 : 0;
$designDetails     = trim($_POST['design_details'] ?? '');

// Guest fields
$senderName  = trim($_POST['sender_name'] ?? '');
$senderEmail = trim($_POST['sender_email'] ?? '');

// ── Validate required fields ─────────────────────────────────
$errors = [];

if (empty($server)) {
    $errors[] = 'Server is required.';
}

if (empty($handle) || $handle === '@') {
    $errors[] = 'In-game @Handle is required.';
}

if (empty($houseName)) {
    $errors[] = 'House name is required.';
}

// Validate handle format
if (!empty($handle)) {
    if (!str_starts_with($handle, '@')) {
        $handle = '@' . $handle;
    }
    if (!preg_match('/^@[\w.\-]+$/u', $handle)) {
        $errors[] = 'Invalid @Handle format.';
    }
}

// Guest validation
if (!$user) {
    if (empty($senderName)) {
        $errors[] = 'Your name is required.';
    }
    if (empty($senderEmail) || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
}

if (!empty($errors)) {
    jsonResponse(['error' => implode(' ', $errors)], 422);
}

// ── Build message body ───────────────────────────────────────
$category = ($tabSource === 'gif') ? 'visual_creation' : 'house_card';
$recipientId = getRecipientForTab($tabSource);

// Auto-generate subject if empty
if (empty($subject)) {
    $subject = ($tabSource === 'gif') ? 'GIF Visual Creation Request' : 'House Card Request';
}

// Compose the structured message body
$bodyParts = [];
$bodyParts[] = "<p><strong>Server:</strong> " . e($server) . "</p>";
$bodyParts[] = "<p><strong>@Handle:</strong> " . e($handle) . "</p>";
$bodyParts[] = "<p><strong>House Name:</strong> " . e($houseName) . "</p>";

if ($tabSource === 'gif') {
    if (!empty($cameraPerspective)) {
        $camLabels = ['first' => 'First Person', 'third' => 'Third Person'];
        $bodyParts[] = "<p><strong>Camera:</strong> " . e($camLabels[$cameraPerspective] ?? $cameraPerspective) . "</p>";
    }
    if (!empty($areasToCapture)) {
        $bodyParts[] = "<p><strong>Areas to Capture:</strong> " . e($areasToCapture) . "</p>";
    }
    if (!empty($tourDuration)) {
        $bodyParts[] = "<p><strong>Tour Duration:</strong> " . e($tourDuration) . "</p>";
    }
    if (!empty($secretRoom)) {
        $bodyParts[] = "<p><strong>Secret Room:</strong> " . e($secretRoom) . "</p>";
    }
    if ($disableReshade) {
        $bodyParts[] = "<p><strong>Reshade:</strong> Disabled (per request)</p>";
    }
} else {
    // House card tab
    if (!empty($designDetails)) {
        $bodyParts[] = "<p><strong>Design Details:</strong> " . e($designDetails) . "</p>";
    }
}

if (!empty($details)) {
    $bodyParts[] = "<hr style='border:none;border-top:1px solid #22212c;margin:1rem 0;'>";
    $bodyParts[] = "<p><strong>Additional Details:</strong></p>";
    $bodyParts[] = "<p>" . nl2br(e($details)) . "</p>";
}

$messageBody = implode("\n", $bodyParts);

// ── Determine sender info ────────────────────────────────────
$senderId   = $user ? (int)$user['id'] : null;
$senderDisp = $user ? ($user['username'] ?? 'User') : $senderName;

// ── Insert into messages table ───────────────────────────────
$db = getDB();

try {
    $db->beginTransaction();

    // Insert message
    $msgStmt = $db->prepare('
        INSERT INTO messages (sender_id, receiver_id, subject, body, category, tab_source, sender_name, sender_email, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
    ');
    $msgStmt->execute([
        $senderId,
        $recipientId,
        $subject,
        $messageBody,
        $category,
        $tabSource,
        $user ? null : $senderName,
        $user ? null : $senderEmail,
    ]);
    $messageId = (int)$db->lastInsertId();

    // Insert visual_requests record
    $vrStmt = $db->prepare('
        INSERT INTO visual_requests
            (message_id, request_type, server, game_handle, house_name, camera_perspective, areas_to_capture, tour_duration, secret_room, disable_reshade, design_details, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $vrStmt->execute([
        $messageId,
        ($tabSource === 'gif') ? 'gif' : 'house_card',
        $server,
        $handle,
        $houseName,
        !empty($cameraPerspective) ? $cameraPerspective : null,
        !empty($areasToCapture) ? $areasToCapture : null,
        !empty($tourDuration) ? $tourDuration : null,
        !empty($secretRoom) ? $secretRoom : null,
        $disableReshade,
        !empty($designDetails) ? $designDetails : null,
        'pending',
    ]);

    $db->commit();

    // ── Send email notification to recipient ─────────────────
    $recipientStmt = $db->prepare('SELECT email, username FROM users WHERE id = ?');
    $recipientStmt->execute([$recipientId]);
    $recipient = $recipientStmt->fetch();

    if ($recipient && !empty($recipient['email'])) {
        $preview = strip_tags($messageBody);
        sendMessageNotificationEmail(
            $recipient['email'],
            $senderDisp,
            $subject,
            $preview
        );
    }

    // ── Create in-app notification for recipient ─────────────
    $notifTitle = ($tabSource === 'gif')
        ? "New GIF request from {$senderDisp}"
        : "New house card request from {$senderDisp}";

    createNotification(
        $recipientId,
        'new_message',
        $notifTitle,
        mb_substr(strip_tags($messageBody), 0, 150) . '...',
        '/cabinet'
    );

    // ── Success ──────────────────────────────────────────────
    jsonResponse([
        'success' => true,
        'message' => t('contact.success'),
    ]);

} catch (\Throwable $ex) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('send-message.php error: ' . $ex->getMessage());
    jsonResponse(['error' => t('common.error')], 500);
}
