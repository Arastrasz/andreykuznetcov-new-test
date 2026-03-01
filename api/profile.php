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

$action = $_POST['action'] ?? 'update_profile';

switch ($action) {
    case 'update_profile':
        $displayName = trim(mb_substr($_POST['display_name'] ?? '', 0, 100));
        $bio = trim(mb_substr($_POST['bio'] ?? '', 0, 500));
        $avatar = $_POST['avatar'] ?? 'default';
        $discord = trim(mb_substr($_POST['discord'] ?? '', 0, 100));
        $gameId = trim(mb_substr($_POST['game_id'] ?? '', 0, 100));
        $guildId = !empty($_POST['guild_id']) ? (int) $_POST['guild_id'] : null;
        $titleId = !empty($_POST['title_id']) ? (int) $_POST['title_id'] : null;

        // Validate avatar
        $validAvatars = array_keys(avatarSymbols());
        if (!in_array($avatar, $validAvatars)) $avatar = 'default';

        // Validate title belongs to user
        if ($titleId) {
            $stmt = $db->prepare('SELECT id FROM user_titles WHERE user_id = ? AND title_id = ?');
            $stmt->execute([$user['id'], $titleId]);
            if (!$stmt->fetch()) $titleId = $user['title_id'];
        }

        // Validate guild exists
        if ($guildId) {
            $stmt = $db->prepare('SELECT id FROM guilds WHERE id = ?');
            $stmt->execute([$guildId]);
            if (!$stmt->fetch()) $guildId = null;
        }

        $stmt = $db->prepare('UPDATE users SET display_name = ?, bio = ?, avatar = ?, discord = ?, game_id = ?, guild_id = ?, title_id = ? WHERE id = ?');
        $stmt->execute([$displayName ?: null, $bio ?: null, $avatar, $discord ?: null, $gameId ?: null, $guildId, $titleId, $user['id']]);

        jsonResponse(['success' => true, 'message' => 'Profile updated.']);
        break;

    case 'update_background':
        $bg = $_POST['background'] ?? 'default';
        $validBgs = array_keys(backgroundOptions());
        if (!in_array($bg, $validBgs)) $bg = 'default';

        $db->prepare('UPDATE users SET background = ? WHERE id = ?')->execute([$bg, $user['id']]);
        jsonResponse(['success' => true, 'message' => 'Background updated.']);
        break;

    case 'complete_setup_step':
        $step = (int) ($_POST['step'] ?? 0);
        if ($step > $user['personalization_step'] && $step <= 4) {
            $db->prepare('UPDATE users SET personalization_step = ? WHERE id = ?')->execute([$step, $user['id']]);
        }
        jsonResponse(['success' => true, 'step' => $step]);
        break;

    case 'skip_setup':
        $db->prepare('UPDATE users SET personalization_step = 99 WHERE id = ?')->execute([$user['id']]);
        jsonResponse(['success' => true, 'message' => 'Setup skipped. You can customize anytime from your profile.']);
        break;

    default:
        jsonResponse(['error' => 'Unknown action.'], 400);
}
