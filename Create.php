<?php
/**
 * POST /sgms/api/groups/create.php                    (FR-04: Administrator only)
 * Body: { "group_name", "contribution_amount", "contribution_frequency": "Weekly"|"Monthly",
 *         "date_formed": "YYYY-MM-DD" (optional, default today) }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/groups.php';

requireMethod('POST');
requireRole([ROLE_ADMIN]);
$in = getJsonInput();

$name      = clean($in['group_name'] ?? '');
$formed    = clean($in['date_formed'] ?? '') ?: date('Y-m-d');
$amount    = parseMoney($in['contribution_amount'] ?? null);
$frequency = clean($in['contribution_frequency'] ?? 'Monthly');

$errors = [];
if (charLength($name) < 3 || charLength($name) > 100) {
    $errors['group_name'] = 'Group name must be 3 to 100 characters.';
}
if (!validDate($formed) || $formed > date('Y-m-d')) {
    $errors['date_formed'] = 'Use a real date (YYYY-MM-DD) that is not in the future.';
}
if ($amount === null) {
    $errors['contribution_amount'] = 'Enter an amount above zero with at most 2 decimals.';
}
if (!in_array($frequency, ['Weekly', 'Monthly'], true)) {
    $errors['contribution_frequency'] = 'Frequency must be Weekly or Monthly.';
}
if ($errors) {
    jsonResponse(false, 'Please correct the details and try again.', ['errors' => $errors], 422);
}

$db   = getDB();
$stmt = $db->prepare('SELECT 1 FROM SAVINGS_GROUP WHERE group_name = ?');
$stmt->execute([$name]);
if ($stmt->fetch()) {
    jsonResponse(false, 'A group with this name already exists.', ['errors' => ['group_name' => 'Name already used.']], 409);
}

// Every other setting (due day, penalty, interest rate...) takes the
// database default; the Chairperson adjusts them later.
$stmt = $db->prepare(
    'INSERT INTO SAVINGS_GROUP (group_name, date_formed, contribution_amount, contribution_frequency)
     VALUES (?, ?, ?, ?)'
);
$stmt->execute([$name, $formed, $amount, $frequency]);

jsonResponse(true, 'Group created.', ['group' => getGroup($db, (int)$db->lastInsertId())], 201);