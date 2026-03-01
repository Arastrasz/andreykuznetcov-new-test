<?php
/* ============================================================
   THE ARCHIVES OF CLAN LAR — Configuration & Helpers
   Central hub: DB connection, auth, translations, notifications
   ============================================================ */

// ── Error handling ───────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ── Session ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Constants ────────────────────────────────────────────────
define('SITE_NAME', 'The Archives of Clan Lar');

// ── Database credentials (Beget) ─────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'arkh574q_andrey');   // adjust if different
define('DB_USER', 'arkh574q_andrey');   // adjust if different
define('DB_PASS', '');                  // ← fill in your password

// ============================================================
// DATABASE CONNECTION
// ============================================================

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ============================================================
// HTML / OUTPUT HELPERS
// ============================================================

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// AUTHENTICATION
// ============================================================

function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;

    static $user = false;
    if ($user !== false) return $user;

    $stmt = getDB()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function requireLogin(): array {
    $user = currentUser();
    if (!$user) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            jsonResponse(['error' => 'Not authenticated'], 401);
        }
        header('Location: /login');
        exit;
    }
    return $user;
}

function requireAdmin(): array {
    $user = requireLogin();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

// ============================================================
// CSRF PROTECTION
// ============================================================

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): bool {
    $token = $_POST['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? '';
    if (empty($token)) {
        // Try JSON body
        $json = json_decode(file_get_contents('php://input'), true);
        $token = $json['csrf_token'] ?? '';
    }
    return hash_equals(csrfToken(), $token);
}

// ============================================================
// HONEYPOT (anti-bot)
// ============================================================

function honeypotField(): string {
    return '<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
        <input type="text" name="website_url" tabindex="-1" autocomplete="off">
    </div>';
}

function isHoneypotTriggered(): bool {
    return !empty($_POST['website_url']);
}

// ============================================================
// RATE LIMITING
// ============================================================

function checkRateLimit(string $action, int $maxPerHour = 5): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $db = getDB();

    // Clean old entries (older than 1 hour)
    $db->prepare('DELETE FROM rate_limits WHERE action_type = ? AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)')
       ->execute([$action]);

    // Count recent
    $stmt = $db->prepare('SELECT COUNT(*) FROM rate_limits WHERE ip_address = ? AND action_type = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
    $stmt->execute([$ip, $action]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $maxPerHour) {
        return false;
    }

    // Record this attempt
    $db->prepare('INSERT INTO rate_limits (ip_address, action_type) VALUES (?, ?)')
       ->execute([$ip, $action]);

    return true;
}

// ============================================================
// LANGUAGE / TRANSLATION (Phase 2)
// ============================================================

function getLang(): string {
    // Priority: session → user record → default
    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], ['en', 'ru'])) {
        return $_SESSION['lang'];
    }
    $user = currentUser();
    if ($user && !empty($user['language'])) {
        $_SESSION['lang'] = $user['language'];
        return $user['language'];
    }
    return 'en';
}

function setLang(string $lang): void {
    if (!in_array($lang, ['en', 'ru'])) return;

    $_SESSION['lang'] = $lang;

    // Persist to user record if logged in
    $user = currentUser();
    if ($user) {
        getDB()->prepare('UPDATE users SET language = ? WHERE id = ?')
               ->execute([$lang, (int)$user['id']]);
    }
}

/**
 * Translate a dot-notated key: t('nav.houses') → 'Houses'
 */
function t(string $key, string $fallback = ''): string {
    static $strings = null;
    if ($strings === null) {
        $lang = getLang();
        $file = __DIR__ . '/../lang/' . $lang . '.php';
        if (file_exists($file)) {
            $strings = require $file;
        } else {
            // Fallback to English
            $enFile = __DIR__ . '/../lang/en.php';
            $strings = file_exists($enFile) ? require $enFile : [];
        }
    }

    // Navigate nested array: 'nav.houses' → $strings['nav']['houses']
    $parts = explode('.', $key);
    $value = $strings;
    foreach ($parts as $part) {
        if (!is_array($value) || !isset($value[$part])) {
            return $fallback ?: $key;
        }
        $value = $value[$part];
    }
    return is_string($value) ? $value : ($fallback ?: $key);
}

// ============================================================
// MESSAGES
// ============================================================

function getUnreadCount(int $userId): int {
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Route messages to the correct recipient based on contact tab.
 * Returns user ID of the person who should receive the message.
 */
function getRecipientForTab(string $tab): int {
    $db = getDB();

    if ($tab === 'card') {
        // House card requests → visual_creator role (or admin fallback)
        $stmt = $db->prepare("SELECT id FROM users WHERE role = 'visual_creator' LIMIT 1");
        $stmt->execute();
        $creator = $stmt->fetchColumn();
        if ($creator) return (int)$creator;
    }

    // Default: send to admin (first admin user)
    $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

// ============================================================
// NOTIFICATIONS (Phase 2)
// ============================================================

function createNotification(int $userId, string $type, string $title, string $message = '', string $link = ''): void {
    getDB()->prepare('INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)')
           ->execute([$userId, $type, $title, $message, $link]);
}

function getNotifications(int $userId, int $limit = 20): array {
    $stmt = getDB()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function getUnreadNotificationCount(int $userId): int {
    $stmt = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function markNotificationsRead(int $userId): void {
    getDB()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
           ->execute([$userId]);
}

// ============================================================
// TIME HELPERS
// ============================================================

function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);

    if ($diff->y > 0) return $diff->y . 'y ago';
    if ($diff->m > 0) return $diff->m . 'mo ago';
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}

// ============================================================
// EMAIL NOTIFICATIONS
// ============================================================

function sendMessageNotificationEmail(string $to, string $senderName, string $subject, string $preview): void {
    $siteName = SITE_NAME;
    $preview  = mb_substr(strip_tags($preview), 0, 300);

    $headers  = "From: noreply@andreykuznetcoveso.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $body = "
    <div style='font-family:Georgia,serif; max-width:500px; margin:0 auto; padding:2rem; background:#0e0e12; color:#b0aca4;'>
        <h2 style='color:#d4d0c8; font-size:1.1rem;'>New message on {$siteName}</h2>
        <p><strong style='color:#6a9eb4;'>{$senderName}</strong> sent you a message:</p>
        <p style='color:#8a867e;'><em>{$subject}</em></p>
        <p style='font-size:0.9rem;'>{$preview}...</p>
        <p style='margin-top:1.5rem;'><a href='https://andreykuznetcoveso.com/cabinet' style='color:#6a9eb4;'>View in Cabinet →</a></p>
    </div>";

    @mail($to, "New message: {$subject}", $body, $headers);
}

// ============================================================
// USER BACKGROUND
// ============================================================

function getBackgroundCss(string $bg): string {
    if (empty($bg)) return '';

    // If it's a URL
    if (str_starts_with($bg, 'http') || str_starts_with($bg, '/')) {
        return "background-image:url('" . e($bg) . "'); background-size:cover; background-position:center; background-attachment:fixed;";
    }

    // If it's a color/gradient
    return "background:" . e($bg) . ";";
}

// ============================================================
// CATEGORY HELPERS
// ============================================================

function getCategoryIcon(string $cat): string {
    $icons = [
        'review'          => '☙',
        'problem'         => '⚠',
        'visual_creation' => '✦',
        'house_card'      => '✦',
        'collaboration'   => '◇',
        'other'           => '◆',
    ];
    return $icons[$cat] ?? '◆';
}
