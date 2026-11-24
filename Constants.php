<?php
/**
 * includes/constants.php
 * The exact words stored in the database's ENUM columns, in ONE place.
 *
 * PHP must spell them exactly as the SQL script does ('Treasurer', 'Active'...).
 * A constant name that is mistyped gets flagged by PHP, whereas a mistyped
 * string such as 'Treasurrer' would silently never match anything.
 */
const ROLE_ADMIN     = 'Administrator';
const ROLE_CHAIR     = 'Chairperson';
const ROLE_TREASURER = 'Treasurer';
const ROLE_MEMBER    = 'Member';
const ROLE_USER      = 'User';        // registered, but not in any group yet

const STATUS_ACTIVE    = 'Active';
const STATUS_INACTIVE  = 'Inactive';
const STATUS_SUSPENDED = 'Suspended';