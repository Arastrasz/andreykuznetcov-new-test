<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
if (!verifyCsrf()) {
    jsonResponse(['error' => 'Invalid security token. Refresh the page.'], 403);
}

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($login) || empty($password)) {
    jsonResponse(['error' => 'Please fill in all fields.'], 400);
}

$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
$stmt->execute([$login, $login]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(['error' => 'Invalid username or password.'], 401);
}

// Check if verified
if (!$user['verified']) {
    // Set pending session so they can access verify.php
    $_SESSION['pending_user_id'] = $user['id'];
    $_SESSION['pending_email'] = $user['email'];

    // Generate a fresh code
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    $db->prepare('INSERT INTO verification_codes (user_id, code, expires_at) VALUES (?, ?, ?)')->execute([$user['id'], $code, $expires]);
    sendVerificationEmail($user['email'], $user['username'], $code);

    jsonResponse([
        'success' => false,
        'error' => 'Your account has not been verified yet. A new verification code has been sent to your email.',
        'redirect' => '/verify'
    ], 403);
}

// Login successful
$_SESSION['user_id'] = $user['id'];
$db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

// Check if personalization is needed
$redirect = '/cabinet';
if ($user['personalization_step'] < 4 && $user['role'] !== 'admin') {
    $redirect = '/cabinet?setup=1';
}

jsonResponse([
    'success' => true,
    'message' => 'Welcome back, ' . e($user['display_name'] ?? $user['username']) . '.',
    'redirect' => $redirect
]);
