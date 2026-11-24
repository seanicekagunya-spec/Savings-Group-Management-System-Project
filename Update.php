<?php
/**
 * POST /sgms/api/groups/update.php                    (FR-04: Administrator only)
 * Body: { "group_id": 1, "group_name": "..." (optional), "status": "Active"|"Inactive" (optional) }
 * status "Inactive" = deactivate the group. Nothing is ever deleted.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/groups.php';

requireMethod('POST');
requireRole([ROLE_ADMIN]);
$in = getJsonInput();

$db    = getDB();
$group = getGroup($db, (int)clean($in['group_id'] ?? ''));
if (!$group) {
    jsonResponse(false, 'Group not found.', [], 404);
}

// We build the SET list from OUR OWN fixed text, never from request keys,
// so a caller cannot choose which columns get changed.
$sets = [];
$params = [];

if (array_key_exists('group_name', $in)) {
    $name = clean($in['group_name']);
    if (charLength($name) < 3 || charLength($name) > 100) {
        jsonResponse(false, 'Group name must be 3 to 100 characters.', ['errors' => ['group_name' => 'Invalid length.']], 422);
    }
    $stmt = $db->prepare('SELECT 1 FROM SAVINGS_GROUP WHERE group_name = ? AND group_id <> ?');
    $stmt->execute([$name, $group['group_id']]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Another group already uses this name.', ['errors' => ['group_name' => 'Name already used.']], 409);
    }
    $sets[] = 'group_name = ?';
    $params[] = $name;
}

if (array_key_exists('status', $in)) {
    $status = clean($in['status']);
    if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE], true)) {
        jsonResponse(false, 'Status must be Active or Inactive.', ['errors' => ['status' => 'Invalid status.']], 422);
    }
    $sets[] = 'status = ?';
    $params[] = $status;
}

if (!$sets) {
    jsonResponse(false, 'Nothing to update. Send group_name and/or status.', [], 422);
}

$params[] = $group['group_id'];
$db->prepare('UPDATE SAVINGS_GROUP SET ' . implode(', ', $sets) . ' WHERE group_id = ?')->execute($params);

jsonResponse(true, 'Group updated.', ['group' => getGroup($db, (int)$group['group_id'])]);