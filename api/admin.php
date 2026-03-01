<?php
/* ============================================================
   Admin API — handles admin actions (save news, delete news)
   ============================================================ */
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
if (!verifyCsrf()) {
    jsonResponse(['error' => 'Invalid security token.'], 403);
}

$user = requireAdmin();
$db = getDB();

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'save_news':
        $newsId = !empty($_POST['news_id']) ? (int) $_POST['news_id'] : null;
        $title = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $content = $_POST['content'] ?? '';
        $published = isset($_POST['published']) ? 1 : 0;

        if (mb_strlen($title) < 2) {
            jsonResponse(['error' => 'Title must be at least 2 characters.'], 400);
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
        $slug = trim($slug, '-');
        $content = sanitizeHtml($content);

        if ($newsId) {
            $stmt = $db->prepare('UPDATE news SET title = ?, slug = ?, excerpt = ?, image = ?, content = ?, published = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$title, $slug, $excerpt ?: null, $image ?: null, $content, $published, $newsId]);
            jsonResponse(['success' => true, 'message' => 'Post updated.', 'slug' => $slug]);
        } else {
            $stmt = $db->prepare('INSERT INTO news (title, slug, excerpt, image, content, published, author_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$title, $slug, $excerpt ?: null, $image ?: null, $content, $published, $user['id']]);
            jsonResponse(['success' => true, 'message' => 'Post created.', 'slug' => $slug, 'id' => (int) $db->lastInsertId()]);
        }
        break;

    case 'delete_news':
        $newsId = (int) ($_POST['news_id'] ?? 0);
        if (!$newsId) jsonResponse(['error' => 'Invalid news ID.'], 400);
        $db->prepare('DELETE FROM comments WHERE news_id = ?')->execute([$newsId]);
        $db->prepare('DELETE FROM news WHERE id = ?')->execute([$newsId]);
        jsonResponse(['success' => true, 'message' => 'Post deleted.']);
        break;

    default:
        jsonResponse(['error' => 'Unknown action.'], 400);
}
