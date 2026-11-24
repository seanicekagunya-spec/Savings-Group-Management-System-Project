<?php
/**
 * includes/groups.php
 * Lookups that several group/member endpoints need.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/constants.php';

/** One savings group as an array, or null if there is no such group. */
function getGroup(PDO $db, int $groupId): ?array
{
    $stmt = $db->prepare(
        'SELECT group_id, group_name, date_formed, contribution_amount,
                contribution_frequency, status
           FROM SAVINGS_GROUP WHERE group_id = ?'
    );
    $stmt->execute([$groupId]);
    return $stmt->fetch() ?: null;
}

/** Find a registered user by phone (any common format) or email. Null if none. */
function findUserByIdentifier(PDO $db, string $identifier): ?array
{
    $phone = normalizePhone($identifier);
    $stmt  = $db->prepare(
        'SELECT user_id, full_name, phone, email, status
           FROM USER WHERE email = ? OR phone = ? LIMIT 1'
    );
    $stmt->execute([strtolower($identifier), $phone ?? $identifier]);
    return $stmt->fetch() ?: null;
}