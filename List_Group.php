<?php
/**
 * GET /sgms/api/groups/list.php                       (FR-04: Administrator only)
 * All groups with their member count, Chairperson and Treasurer.
 */
require_once __DIR__ . '/auth.php';

requireMethod('GET');
requireRole([ROLE_ADMIN]);

// Each (SELECT ...) below is a small question asked once per group row.
$stmt = getDB()->prepare(
    'SELECT g.group_id, g.group_name, g.date_formed, g.contribution_amount,
            g.contribution_frequency, g.status,
            (SELECT COUNT(*) FROM MEMBERSHIP m
              WHERE m.group_id = g.group_id AND m.status = ?) AS member_count,
            (SELECT u.full_name FROM MEMBERSHIP m JOIN USER u ON u.user_id = m.user_id
              WHERE m.group_id = g.group_id AND m.status = ? AND m.group_role = ? LIMIT 1) AS chairperson,
            (SELECT u.full_name FROM MEMBERSHIP m JOIN USER u ON u.user_id = m.user_id
              WHERE m.group_id = g.group_id AND m.status = ? AND m.group_role = ? LIMIT 1) AS treasurer
       FROM SAVINGS_GROUP g
      ORDER BY g.group_name'
);
// The values for the ? marks, in the same order as they appear above.
$stmt->execute([STATUS_ACTIVE, STATUS_ACTIVE, ROLE_CHAIR, STATUS_ACTIVE, ROLE_TREASURER]);

jsonResponse(true, 'Groups loaded.', ['groups' => $stmt->fetchAll()]);