<?php
/**
 * GET /sgms/api/members/list.php                      (FR-05)
 * Chairperson / Treasurer: the members of their OWN group.
 * Administrator: must add ?group_id=1 to choose a group.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/groups.php';

requireMethod('GET');
$user    = requireRole([ROLE_ADMIN, ROLE_CHAIR, ROLE_TREASURER]);
$groupId = resolveGroupId($user, $_GET['group_id'] ?? null);   // officials: the ?group_id= is ignored

$db    = getDB();
$group = getGroup($db, $groupId);
if (!$group) {
    jsonResponse(false, 'Group not found.', [], 404);
}

$stmt = $db->prepare(
    'SELECT m.membership_id, u.user_id, u.full_name, u.phone, u.email,
            m.group_role, m.join_date, m.status,
            u.next_of_kin_name, u.next_of_kin_phone
       FROM MEMBERSHIP m
       JOIN USER u ON u.user_id = m.user_id
      WHERE m.group_id = ?
      ORDER BY FIELD(m.group_role, \'Chairperson\', \'Treasurer\', \'Member\'), u.full_name'
);
$stmt->execute([$groupId]);

jsonResponse(true, 'Members loaded.', ['group' => $group, 'members' => $stmt->fetchAll()]);