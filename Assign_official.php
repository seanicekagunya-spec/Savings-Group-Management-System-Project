<?php
/**
 * POST /sgms/api/groups/assign_official.php           (FR-04: Administrator only)
 * Body: { "group_id": 1, "identifier": "phone or email", "group_role": "Chairperson"|"Treasurer" }
 *
 * The person must already have registered. A group has ONE Chairperson and
 * ONE Treasurer, so whoever held the role before becomes an ordinary Member.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/groups.php';

requireMethod('POST');
requireRole([ROLE_ADMIN]);
$in = getJsonInput();

$identifier = clean($in['identifier'] ?? '');
$role       = clean($in['group_role'] ?? '');
if ($identifier === '' || !in_array($role, [ROLE_CHAIR, ROLE_TREASURER], true)) {
    jsonResponse(false, 'Send identifier (phone or email) and group_role (Chairperson or Treasurer).', [], 422);
}

$db    = getDB();
$group = getGroup($db, (int)clean($in['group_id'] ?? ''));
if (!$group) {
    jsonResponse(false, 'Group not found.', [], 404);
}
if ($group['status'] !== STATUS_ACTIVE) {
    jsonResponse(false, 'This group is inactive. Reactivate it first.', [], 409);
}

$person = findUserByIdentifier($db, $identifier);
if (!$person) {
    jsonResponse(false, 'No registered user has that phone number or email. They must register first.', [], 404);
}
if ($person['status'] !== STATUS_ACTIVE) {
    jsonResponse(false, 'That user account is suspended.', [], 409);
}

// Is this person already in the group?
$stmt = $db->prepare('SELECT membership_id, group_role, status FROM MEMBERSHIP WHERE user_id = ? AND group_id = ?');
$stmt->execute([$person['user_id'], $group['group_id']]);
$existing = $stmt->fetch();

// One person cannot hold both offices in the same group.
$otherRole = $role === ROLE_CHAIR ? ROLE_TREASURER : ROLE_CHAIR;
if ($existing && $existing['status'] === STATUS_ACTIVE && $existing['group_role'] === $otherRole) {
    jsonResponse(false, "This person is currently the $otherRole of the group. Assign that office to someone else first.", [], 409);
}

// Two changes that must succeed TOGETHER or not at all (NFR-11), otherwise
// the group could end up with no Chairperson. That is what a transaction is.
$db->beginTransaction();
try {
    // 1. The previous holder (if any, and not the same person) becomes a Member.
    $db->prepare(
        'UPDATE MEMBERSHIP SET group_role = ?
          WHERE group_id = ? AND group_role = ? AND status = ? AND user_id <> ?'
    )->execute([ROLE_MEMBER, $group['group_id'], $role, STATUS_ACTIVE, $person['user_id']]);

    // 2. Promote the chosen person, adding them to the group if needed.
    if ($existing) {
        $db->prepare('UPDATE MEMBERSHIP SET group_role = ?, status = ? WHERE membership_id = ?')
           ->execute([$role, STATUS_ACTIVE, $existing['membership_id']]);
        $membershipId = (int)$existing['membership_id'];
    } else {
        $db->prepare('INSERT INTO MEMBERSHIP (user_id, group_id, group_role, join_date) VALUES (?, ?, ?, ?)')
           ->execute([$person['user_id'], $group['group_id'], $role, date('Y-m-d')]);
        $membershipId = (int)$db->lastInsertId();
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();   // undo step 1 if step 2 failed
    throw $e;
}

jsonResponse(true, "{$person['full_name']} is now the $role of {$group['group_name']}.", [
    'membership_id' => $membershipId,
    'user_id'       => (int)$person['user_id'],
    'group_id'      => (int)$group['group_id'],
    'group_role'    => $role,
]);