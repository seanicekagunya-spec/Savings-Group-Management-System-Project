<?php
/**
 * includes/auth.php
 * Sessions, "who is making this request?" and "are they allowed?".
 *
 * Analogy: logging in is like getting a numbered ticket at the door.
 * The session holds ONLY the ticket (user_id). Who the person is, and what
 * role they hold, is looked up in the database on every request, so a
 * suspended user or a changed role takes effect immediately.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';

const SESSION_TIMEOUT = 900;   // 15 minutes of inactivity (NFR-04)

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,       // cookie is dropped when the browser closes
            'path'     => '/',
            'httponly' => true,    // JavaScript cannot read the cookie
            'samesite' => 'Lax',   // not sent with requests started by other websites
            // 'secure' => true,   // switch on when the site runs on HTTPS (NFR-04)
        ]);
        session_start();
    }

    // Idle for too long? Wipe the session: the user is effectively logged out.
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        $_SESSION = ['timed_out' => true];
    }
    $_SESSION['last_activity'] = time();
}

/** The user's current ACTIVE membership in an ACTIVE group, or null. */
function loadActiveMembership(PDO $db, int $userId): ?array
{
    $stmt = $db->prepare(
        'SELECT m.membership_id, m.group_id, m.group_role, g.group_name
           FROM MEMBERSHIP m
           JOIN SAVINGS_GROUP g ON g.group_id = m.group_id
          WHERE m.user_id = ? AND m.status = ? AND g.status = ?
          ORDER BY m.membership_id
          LIMIT 1'
    );
    $stmt->execute([$userId, STATUS_ACTIVE, STATUS_ACTIVE]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;   // e.g. just registered, not added to a group yet
    }
    return [
        'membership_id' => (int)$row['membership_id'],
        'group_id'      => (int)$row['group_id'],
        'group_role'    => $row['group_role'],
        'group_name'    => $row['group_name'],
    ];
}

/**
 * Everything the system needs to know about a user, read fresh from the
 * database. Returns null if the user no longer exists or is not Active.
 * "role" is the one word the rest of the backend and front end rely on:
 * Administrator | Chairperson | Treasurer | Member | User (no group yet).
 */
function loadCurrentUser(PDO $db, int $userId): ?array
{
    $stmt = $db->prepare('SELECT user_id, full_name, system_role, status FROM USER WHERE user_id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if (!$u || $u['status'] !== STATUS_ACTIVE) {
        return null;
    }

    $membership = loadActiveMembership($db, $userId);
    $role = $u['system_role'] === ROLE_ADMIN
        ? ROLE_ADMIN
        : ($membership['group_role'] ?? ROLE_USER);

    return [
        'user_id'     => (int)$u['user_id'],
        'full_name'   => $u['full_name'],
        'system_role' => $u['system_role'],
        'role'        => $role,
        'membership'  => $membership,   // null when not in a group
    ];
}

/**
 * Put this at the top of every endpoint that needs a logged-in user.
 * Stops with 401/403 if not allowed; otherwise returns the current user.
 */
function requireLogin(): array
{
    startSecureSession();

    if (empty($_SESSION['user_id'])) {
        $message = !empty($_SESSION['timed_out'])
            ? 'Your session expired. Please log in again.'
            : 'Please log in first.';
        jsonResponse(false, $message, [], 401);
    }

    $user = loadCurrentUser(getDB(), (int)$_SESSION['user_id']);
    if ($user === null) {   // suspended or removed since they logged in
        $_SESSION = [];
        jsonResponse(false, 'Your account is not active. Please contact your administrator.', [], 403);
    }
    return $user;
}

/**
 * Like requireLogin(), but the user must also hold one of the allowed roles.
 *   requireRole([ROLE_CHAIR, ROLE_TREASURER]);   // only those two get through
 * Logged in but wrong role = 403 ("I know who you are, you may not do this").
 * Not logged in = 401 ("I don't know who you are").
 */
function requireRole(array $allowedRoles): array
{
    $user = requireLogin();
    if (!in_array($user['role'], $allowedRoles, true)) {
        jsonResponse(false, 'You do not have permission to do this.', [], 403);
    }
    return $user;
}

/**
 * Which group's data may this request touch?   (BR-12, NFR-06)
 *  - Chairperson / Treasurer / Member: ALWAYS their own group, taken from the
 *    database. A group_id sent in the request is ignored, so nobody can peek
 *    into another group by editing the request.
 *  - Administrator: belongs to no group, so they must name one.
 */
function resolveGroupId(array $user, $requestedGroupId = null): int
{
    if ($user['role'] === ROLE_ADMIN) {
        $groupId = (int)clean($requestedGroupId);
        if ($groupId <= 0) {
            jsonResponse(false, 'Please specify a group_id.', [], 422);
        }
        return $groupId;
    }
    if ($user['membership'] === null) {
        jsonResponse(false, 'You do not belong to an active group.', [], 403);
    }
    return $user['membership']['group_id'];
}