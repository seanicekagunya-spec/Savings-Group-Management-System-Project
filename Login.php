<?php
/**
 * POST /sgms/api/users/login.php             (FR-02)
 * Body: { "identifier": "phone or email", "password": "..." }
 */
require_once __DIR__ . '/auth.php';

requireMethod('POST');
$in = getJsonInput();

$identifier = clean($in['identifier'] ?? '');
$password   = is_string($in['password'] ?? null) ? $in['password'] : '';
if ($identifier === '' || $password === '') {
    jsonResponse(false, 'Enter your phone number or email, and your password.', [], 422);
}

// The identifier may be a phone (in any common format) or an email.
$phone = normalizePhone($identifier);
$db    = getDB();
$stmt  = $db->prepare('SELECT user_id, password_hash, status FROM USER WHERE email = ? OR phone = ? LIMIT 1');
$stmt->execute([strtolower($identifier), $phone ?? $identifier]);
$row = $stmt->fetch();

// One vague message for "no such user" AND "wrong password", so an attacker
// cannot use this form to find out which phones/emails are registered.
if (!$row || !password_verify($password, $row['password_hash'])) {
    jsonResponse(false, 'Incorrect phone/email or password.', [], 401);
}
if ($row['status'] !== 'Active') {
    jsonResponse(false, 'This account is suspended. Please contact your administrator.', [], 403);
}

// Success: hand out the ticket.
startSecureSession();
session_regenerate_id(true);   // brand-new session id: blocks "session fixation"
$_SESSION = ['user_id' => (int)$row['user_id'], 'last_activity' => time()];

jsonResponse(true, 'Login successful.', loadCurrentUser($db, (int)$row['user_id']));