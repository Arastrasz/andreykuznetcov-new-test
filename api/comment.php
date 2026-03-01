<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
if (!verifyCsrf()) {
    jsonResponse(['error' => 'Invalid security token.'], 403);
}

$user = requireAuth();
$db = getDB();

$newsId = (int) ($_POST['news_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if (!$newsId) jsonResponse(['error' => 'Invalid news post.'], 400);
if (mb_strlen($content) < 2 || mb_strlen($content) > 2000) {
    jsonResponse(['error' => 'Comment must be between 2 and 2000 characters.'], 400);
}

// Verify news exists and is published
$stmt = $db->prepare('SELECT id FROM news WHERE id = ? AND published = 1');
$stmt->execute([$newsId]);
if (!$stmt->fetch()) jsonResponse(['error' => 'News post not found.'], 404);

// Rate limit: 5 comments per minute
$stmt = $db->prepare('SELECT COUNT(*) FROM comments WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
$stmt->execute([$user['id']]);
if ((int) $stmt->fetchColumn() >= 5) {
    jsonResponse(['error' => 'Too many comments. Please wait a moment.'], 429);
}

$sanitized = e($content);
$stmt = $db->prepare('INSERT INTO comments (news_id, user_id, content) VALUES (?, ?, ?)');
$stmt->execute([$newsId, $user['id'], $sanitized]);

jsonResponse([
    'success' => true,
    'comment' => [
        'id' => (int) $db->lastInsertId(),
        'username' => $user['username'],
        'display_name' => $user['display_name'] ?? $user['username'],
        'avatar' => getAvatarSymbol($user['avatar']),
        'title_name' => $user['title_name'] ?? null,
        'title_color' => $user['title_color'] ?? null,
        'content' => $sanitized,
        'time' => 'just now'
    ]
]);
