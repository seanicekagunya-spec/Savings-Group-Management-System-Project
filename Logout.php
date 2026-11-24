<?php
/** POST /sgms/api/users/logout.php */
require_once __DIR__ . '/auth.php';

requireMethod('POST');
startSecureSession();
$_SESSION = [];          // forget who this was
session_destroy();       // throw the ticket away

jsonResponse(true, 'You have been logged out.');