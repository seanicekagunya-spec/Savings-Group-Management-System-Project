<?php
/**
 * includes/helpers.php
 * Small tools that every endpoint shares, so all answers look identical.
 *
 * EVERY endpoint replies in this one shape (the "API contract"):
 *   { "success": true|false, "message": "plain-language text", "data": { ... } }
 * with these HTTP status codes:
 *   200 OK, 201 Created, 401 not logged in, 403 not allowed, 404 not found,
 *   405 wrong method, 409 conflict/duplicate, 422 invalid input, 500 server error.
 */

// Never print PHP errors into the response: a stray warning would be mixed
// into our JSON and break the front end's response.json().
// Errors go to the log file instead (XAMPP: C:\xampp\php\logs\php_error_log).
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// All dates in the system use Kenyan time, whatever the server's php.ini says.
date_default_timezone_set('Africa/Nairobi');

// Safety net: if anything unexpected crashes, we still answer in JSON.
set_exception_handler(function ($e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Something went wrong on the server. Please try again.', [], 500);
});

/** Send the answer and stop. Nothing may be printed after it. */
function jsonResponse(bool $success, string $message, array $data = [], int $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        // "data" is always an object ({}), never a bare list; lists live
        // inside a key, e.g. data.members = [...]
        'data'    => $data === [] ? new stdClass() : $data,
    ]);
    exit;
}

/** Refuse the request unless it uses the expected HTTP method. */
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        jsonResponse(false, "This endpoint only accepts $method requests.", [], 405);
    }
}

/**
 * Read the JSON body that fetch() sends.
 * NOTE: $_POST stays EMPTY for JSON bodies, so we read the raw input instead.
 */
function getJsonInput(): array
{
    $decoded = json_decode(file_get_contents('php://input'), true);
    return is_array($decoded) ? $decoded : [];
}

/** Turn any input value into a trimmed string (arrays and nulls become ''). */
function clean($value): string
{
    return is_scalar($value) ? trim((string)$value) : '';
}

/**
 * Turns 0712345678, 254712345678 or "+254 712 345 678" into +254712345678
 * (the format required by the SRS). Returns null if it is not a valid
 * Kenyan mobile number.
 */
function normalizePhone(string $phone): ?string
{
    $phone = preg_replace('/[\s\-()]/', '', $phone);        // drop spaces, dashes, brackets
    if (preg_match('/^0([17]\d{8})$/', $phone, $m)) {
        $phone = '+254' . $m[1];
    } elseif (preg_match('/^254([17]\d{8})$/', $phone, $m)) {
        $phone = '+254' . $m[1];
    }
    return preg_match('/^\+254[17]\d{8}$/', $phone) ? $phone : null;
}

/** True only for a real calendar date written as YYYY-MM-DD (SRS 2.5). */
function validDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

/**
 * Checks an amount of money (SRS: KES with two decimals).
 * Accepts 1000, "1000" or "1000.5"; returns "1000.50", or null when it is
 * not a positive amount with at most 2 decimals.
 * Money is passed around as a STRING so no floating-point rounding creeps in.
 */
function parseMoney($value): ?string
{
    if (!(is_string($value) || is_int($value) || is_float($value))
        || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', trim((string)$value))) {
        return null;
    }
    $amount = number_format((float)$value, 2, '.', '');
    return (float)$amount > 0 ? $amount : null;
}

/**
 * Number of CHARACTERS (not bytes) in a text, so "Wanjiku" and "Wanjiků"
 * both count 7. Uses a plain regex, so it needs no extra PHP extension.
 * Returns 0 for text that is not valid UTF-8.
 */
function charLength(string $text): int
{
    $count = preg_match_all('/./us', $text);
    return $count === false ? 0 : $count;
}