<?php
/* ============================================================
   THE ARCHIVES OF CLAN LAR — Comment Vote API
   POST with comment_id → toggle vote, return new count
   ============================================================ */
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user = currentUser();
if (!$user) {
    jsonResponse(['error' => 'You must be logged in to vote.'], 401);
}

// Accept form data or JSON
$commentId = (int)($_POST['comment_id'] ?? 0);
if ($commentId === 0) {
    $jsonBody = json_decode(file_get_contents('php://input'), true);
    $commentId = (int)($jsonBody['comment_id'] ?? 0);
}

if ($commentId <= 0) {
    jsonResponse(['error' => 'Invalid comment.'], 400);
}

$userId = (int)$user['id'];
$db = getDB();

// Verify comment exists
$commentStmt = $db->prepare('SELECT id FROM comments WHERE id = ?');
$commentStmt->execute([$commentId]);
if (!$commentStmt->fetch()) {
    jsonResponse(['error' => 'Comment not found.'], 404);
}

// Check existing vote
$voteStmt = $db->prepare('SELECT id FROM comment_votes WHERE comment_id = ? AND user_id = ?');
$voteStmt->execute([$commentId, $userId]);
$existingVote = $voteStmt->fetch();

try {
    $db->beginTransaction();

    if ($existingVote) {
        // Remove vote (toggle off)
        $db->prepare('DELETE FROM comment_votes WHERE comment_id = ? AND user_id = ?')
           ->execute([$commentId, $userId]);
        $voted = false;
    } else {
        // Add vote (toggle on)
        $db->prepare('INSERT INTO comment_votes (comment_id, user_id, vote) VALUES (?, ?, 1)')
           ->execute([$commentId, $userId]);
        $voted = true;
    }

    // Update cached vote_count on comments table
    $db->prepare('UPDATE comments SET vote_count = (SELECT COUNT(*) FROM comment_votes WHERE comment_id = ?) WHERE id = ?')
       ->execute([$commentId, $commentId]);

    $db->commit();

    // Fetch new count
    $countStmt = $db->prepare('SELECT vote_count FROM comments WHERE id = ?');
    $countStmt->execute([$commentId]);
    $newCount = (int)$countStmt->fetchColumn();

    jsonResponse([
        'success'    => true,
        'voted'      => $voted,
        'vote_count' => $newCount,
    ]);

} catch (\Throwable $ex) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('vote-comment.php error: ' . $ex->getMessage());
    jsonResponse(['error' => t('common.error')], 500);
}
