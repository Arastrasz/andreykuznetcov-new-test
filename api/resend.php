<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = $_SESSION['pending_user_id'] ?? null;
if (!$userId) {
    jsonResponse(['error' => 'No pending verification.'], 400);
}

// Rate limit: 1 resend per 2 minutes
$db = getDB();
$stmt = $db->prepare('SELECT created_at FROM verification_codes WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$userId]);
$last = $stmt->fetch();
// Simple rate limit check using expires_at minus 28 minutes
$stmt = $db->prepare('SELECT COUNT(*) FROM verification_codes WHERE user_id = ? AND expires_at > DATE_ADD(NOW(), INTERVAL 28 MINUTE)');
$stmt->execute([$userId]);
if ((int) $stmt->fetchColumn() > 0) {
    jsonResponse(['error' => 'Please wait at least 2 minutes before requesting a new code.'], 429);
}

// Get user email
$stmt = $db->prepare('SELECT email, username FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    jsonResponse(['error' => 'User not found.'], 404);
}

// Generate new code
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
$db->prepare('INSERT INTO verification_codes (user_id, code, expires_at) VALUES (?, ?, ?)')->execute([$userId, $code, $expires]);

sendVerificationEmail($user['email'], $user['username'], $code);

$masked = substr($user['email'], 0, 3) . '***' . strstr($user['email'], '@');
jsonResponse([
    'success' => true,
    'message' => "A new code has been sent to {$masked}."
]);
