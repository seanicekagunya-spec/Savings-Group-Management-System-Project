<?php
/**
 * GET /sgms/api/users/me.php
 * "Who am I?" Pages call this on load: 401 means "send them to the login page";
 * otherwise data.role tells the page which menu and dashboard to show.
 */
require_once __DIR__ . '/auth.php';

requireMethod('GET');
$user = requireLogin();

jsonResponse(true, 'Logged in.', $user);