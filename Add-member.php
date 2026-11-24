<?php
/**
 * POST /sgms/api/members/add.php                      (FR-05: Chairperson, Treasurer)
 * Body: { "identifier": "phone or email of a REGISTERED user",
 *         "join_date": "YYYY-MM-DD" (optional, default today),
 *         "next_of_kin_name": "...", "next_of_kin_phone": "..." (both optional) }
 * The member is always added to the CALLER'S OWN group.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/groups.php';

requireMethod('POST');
$user    = requireRole([ROLE_CHAIR, ROLE_TREASURER]);
$groupId = resolveGroupId($user);          // from the database, not from the request
$in      = getJsonInput();

$identifier = clean($in['identifier'] ?? '');
$joinDate   = clean($in['join_date'] ?? '') ?: date('Y-m-d');
$kinName    = clean($in['next_of_kin_name'] ?? '');
$kinPhoneIn = clean($in['next_of_kin_phone'] ?? '');
$kinPhone   = $kinPhoneIn === '' ? null : normalizePhone($kinPhoneIn);

$errors = [];
if ($identifier === '') {
    $errors['identifier'] = 'Enter the phone number or email the person registered with.';
}
if (!validDate($joinDate) || $joinDate > date('Y-m-d')) {
    $errors['join_date'] = 'Use a real date (YYYY-MM-DD) that is not in the future.';
}
if ($kinName !== '' && !preg_match("/^\p{L}[\p{L}\s.'-]{1,119}$/u", $kinName)) {
    $errors['next_of_kin_name'] = 'Letters, spaces, hyphens and apostrophes only.';
}
if ($kinPhoneIn !== '' && $kinPhone === null) {
    $errors['next_of_kin_phone'] = 'Enter a valid Kenyan number.';
}
if ($errors) {
    jsonResponse(false, 'Please correct the details and try again.', ['errors' => $errors], 422);
}

$db     = getDB();
$person = findUserByIdentifier($db, $identifier);
if (!$person) {
    jsonResponse(false, 'No registered user has that phone number or email. Ask them to register first.', [], 404);
}
if ($person['status'] !== STATUS_ACTIVE) {
    jsonResponse(false, 'That user account is suspended.', [], 409);
}

$stmt = $db->prepare('SELECT membership_id, group_role, status FROM MEMBERSHIP WHERE user_id = ? AND group_id = ?');
$stmt->execute([$person['user_id'], $groupId]);
$existing = $stmt->fetch();

if ($existing && $existing['status'] === STATUS_ACTIVE) {
    jsonResponse(false, 'This person is already a member of your group.', [], 409);
}
// Re-activating a former OFFICIAL would hand back their powers: only the Administrator may do that.
if ($existing && $existing['group_role'] !== ROLE_MEMBER) {
    jsonResponse(false, 'This person was an official of the group. Ask the administrator to reinstate them.', [], 403);
}

$db->beginTransaction();
try {
    if ($existing) {                       // returning member: switch back on, keep original join date
        $db->prepare('UPDATE MEMBERSHIP SET status = ? WHERE membership_id = ?')
           ->execute([STATUS_ACTIVE, $existing['membership_id']]);
        $membershipId = (int)$existing['membership_id'];
        $joinDate = null;
    } else {
        $db->prepare('INSERT INTO MEMBERSHIP (user_id, group_id, group_role, join_date) VALUES (?, ?, ?, ?)')
           ->execute([$person['user_id'], $groupId, ROLE_MEMBER, $joinDate]);
        $membershipId = (int)$db->lastInsertId();
    }

    // Next of kin lives on the USER row. COALESCE keeps the old value when none is sent.
    if ($kinName !== '' || $kinPhone !== null) {
        $db->prepare(
            'UPDATE USER SET next_of_kin_name = COALESCE(?, next_of_kin_name),
                             next_of_kin_phone = COALESCE(?, next_of_kin_phone)
              WHERE user_id = ?'
        )->execute([$kinName === '' ? null : $kinName, $kinPhone, $person['user_id']]);
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}

jsonResponse(true, "{$person['full_name']} has been added to your group.", [
    'membership_id' => $membershipId,
    'user_id'       => (int)$person['user_id'],
    'full_name'     => $person['full_name'],
    'group_role'    => ROLE_MEMBER,
], 201);