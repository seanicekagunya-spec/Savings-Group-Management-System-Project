<?php
/**
 * POST /sgms/api/users/register.php          (FR-01)
 * Body: { "full_name", "phone", "email", "password" }
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/database.php';

requireMethod('POST');
$in = getJsonInput();

// 1. Read and tidy the input. We NEVER read a role from the request:
//    new accounts always get the database defaults (User, Active).
$fullName = clean($in['full_name'] ?? '');
$phone    = normalizePhone(clean($in['phone'] ?? ''));
$email    = strtolower(clean($in['email'] ?? ''));
$password = is_string($in['password'] ?? null) ? $in['password'] : '';   // never trim a password

// 2. Validate. Collect every problem so the form can show them all at once.
$errors = [];
if (!preg_match("/^\p{L}[\p{L}\s.'-]{1,119}$/u", $fullName)) {
    $errors['full_name'] = 'Enter your full name (letters, spaces, hyphens and apostrophes only).';
}
if ($phone === null) {
    $errors['phone'] = 'Enter a valid Kenyan number such as 0712345678 or +254712345678.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
    $errors['email'] = 'Enter a valid email address.';
}
if (strlen($password) < 8 || strlen($password) > 72) {   // bcrypt only reads the first 72 bytes
    $errors['password'] = 'Password must be between 8 and 72 characters.';
}
if ($errors) {
    jsonResponse(false, 'Please correct the details and try again.', ['errors' => $errors], 422);
}

// 3. Friendly duplicate check (FR-01: existing phone/email is rejected).
$db   = getDB();
$stmt = $db->prepare('SELECT phone, email FROM USER WHERE phone = ? OR email = ?');
$stmt->execute([$phone, $email]);
$duplicates = [];
foreach ($stmt->fetchAll() as $row) {
    if ($row['phone'] === $phone)            $duplicates['phone'] = 'This phone number is already registered.';
    if (strtolower($row['email']) === $email) $duplicates['email'] = 'This email is already registered.';
}
if ($duplicates) {
    jsonResponse(false, 'An account with these details already exists.', ['errors' => $duplicates], 409);
}

// 4. Save. We store a bcrypt HASH, never the password (FR-02, NFR-03).
$hash = password_hash($password, PASSWORD_BCRYPT);
try {
    $stmt = $db->prepare('INSERT INTO USER (full_name, phone, email, password_hash) VALUES (?, ?, ?, ?)');
    $stmt->execute([$fullName, $phone, $email, $hash]);
} catch (PDOException $e) {
    // Safety net: two people registering at the same instant. The UNIQUE
    // keys in the database still stop the second one (error code 1062).
    if ((int)$e->errorInfo[1] === 1062) {
        jsonResponse(false, 'An account with these details already exists.', [], 409);
    }
    throw $e;
}

jsonResponse(true, 'Account created. You can now log in.', ['user_id' => (int)$db->lastInsertId()], 201);