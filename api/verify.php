<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = $_SESSION['pending_user_id'] ?? null;
if (!$userId) {
    jsonResponse(['error' => 'No pending verification. Please register first.'], 400);
}

$code = trim($_POST['code'] ?? '');
if (!preg_match('/^[0-9]{6}$/', $code)) {
    jsonResponse(['error' => 'Please enter a valid 6-digit code.'], 400);
}

$db = getDB();
$stmt = $db->prepare('
    SELECT * FROM verification_codes
    WHERE user_id = ? AND code = ? AND used = 0 AND expires_at > NOW()
    ORDER BY id DESC LIMIT 1
');
$stmt->execute([$userId, $code]);
$record = $stmt->fetch();

if (!$record) {
    jsonResponse(['error' => 'Invalid or expired code. Please request a new one.'], 400);
}

// Mark code as used
$db->prepare('UPDATE verification_codes SET used = 1 WHERE id = ?')->execute([$record['id']]);

// Verify user
$db->prepare('UPDATE users SET verified = 1, last_login = NOW() WHERE id = ?')->execute([$userId]);

// Log user in
$_SESSION['user_id'] = $userId;
unset($_SESSION['pending_user_id']);
unset($_SESSION['pending_email']);

jsonResponse([
    'success' => true,
    'message' => 'Account verified! Welcome to the Archives.',
    'redirect' => '/cabinet?setup=1'
]);
