<?php
/**
 * POST /sgms/api/members/set_status.php               (FR-05: Chairperson, Treasurer)
 * Body: { "membership_id": 5, "status": "Active"|"Inactive" }
 * Deactivating keeps all the member's records (BR-11: nothing is deleted).
 * Officials can only change ordinary Members; the Administrator manages officials.
 */
require_once __DIR__ . '/auth.php';

requireMethod('POST');
$user    = requireRole([ROLE_CHAIR, ROLE_TREASURER]);
$groupId = resolveGroupId($user);
$in      = getJsonInput();

$membershipId = (int)clean($in['membership_id'] ?? '');
$status       = clean($in['status'] ?? '');
if ($membershipId <= 0 || !in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE], true)) {
    jsonResponse(false, 'Send membership_id and status (Active or Inactive).', [], 422);
}

// "AND group_id = ?" is the data-isolation rule (BR-12): a membership from
// another group is simply "not found", so its existence is not revealed.
$db   = getDB();
$stmt = $db->prepare('SELECT membership_id, group_role FROM MEMBERSHIP WHERE membership_id = ? AND group_id = ?');
$stmt->execute([$membershipId, $groupId]);
$target = $stmt->fetch();
if (!$target) {
    jsonResponse(false, 'Member not found in your group.', [], 404);
}
if ($target['group_role'] !== ROLE_MEMBER) {
    jsonResponse(false, 'Only the administrator can change an official.', [], 403);
}

$db->prepare('UPDATE MEMBERSHIP SET status = ? WHERE membership_id = ?')->execute([$status, $membershipId]);

jsonResponse(true, "Member is now $status.", ['membership_id' => $membershipId, 'status' => $status]);