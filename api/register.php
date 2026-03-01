<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
if (!verifyCsrf()) {
    jsonResponse(['error' => 'Invalid security token. Refresh the page.'], 403);
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

$errors = [];
if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
    $errors[] = 'Username must be 3-50 characters: letters, numbers, hyphens, underscores.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}
if (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters.';
}
if ($password !== $passwordConfirm) {
    $errors[] = 'Passwords do not match.';
}
if (!empty($errors)) {
    jsonResponse(['errors' => $errors], 400);
}

$db = getDB();

// Check uniqueness
$stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
$stmt->execute([$username, $email]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'This username or email is already taken.'], 400);
}

// Create user
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $db->prepare('INSERT INTO users (username, email, password_hash, display_name) VALUES (?, ?, ?, ?)');
$stmt->execute([$username, $email, $hash, $username]);
$userId = (int) $db->lastInsertId();

// Award "Visitor" title
$stmt = $db->prepare('SELECT id FROM titles WHERE name = "Visitor" LIMIT 1');
$stmt->execute();
$visitorTitle = $stmt->fetch();
if ($visitorTitle) {
    $db->prepare('INSERT IGNORE INTO user_titles (user_id, title_id) VALUES (?, ?)')->execute([$userId, $visitorTitle['id']]);
    $db->prepare('UPDATE users SET title_id = ? WHERE id = ?')->execute([$visitorTitle['id'], $userId]);
}

// Generate verification code
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
$stmt = $db->prepare('INSERT INTO verification_codes (user_id, code, expires_at) VALUES (?, ?, ?)');
$stmt->execute([$userId, $code, $expires]);

// Send verification email
sendVerificationEmail($email, $username, $code);

// Store pending user in session for verify page
$_SESSION['pending_user_id'] = $userId;
$_SESSION['pending_email'] = $email;

jsonResponse([
    'success' => true,
    'message' => 'Account created! Check your email for the verification code.',
    'redirect' => '/verify'
]);
