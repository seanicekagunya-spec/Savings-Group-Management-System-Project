<?php
/* HTTP layer shared by every endpoint: session, 15-minute idle timeout (NFR-04), JSON in/out, login check, one error handler. */
class Api {
    public static function run(callable $handler, string $method = 'GET', bool $auth = false): void {
        header('Content-Type: application/json; charset=utf-8');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
        try {
            if ($_SERVER['REQUEST_METHOD'] !== $method) throw new AppException('Method not allowed.', 405);
            $limit = (int)(getenv('SGMS_IDLE_SECONDS') ?: 900);
            $expired = isset($_SESSION['user_id']) && time() - ($_SESSION['last_seen'] ?? 0) > $limit;
            if ($expired) $_SESSION = [];
            $user = null;
            if ($auth) {
                $user = (new AuthService(new UserRepository()))->userById((int)($_SESSION['user_id'] ?? 0));
                if (!$user) throw new AppException($expired ? 'Your session expired after 15 minutes of inactivity. Please log in again.' : 'Please log in.', 401);
                $_SESSION['last_seen'] = time();
            }
            echo json_encode($handler(self::input(), $user) ?? ['ok' => true]);
        } catch (AppException $e) {
            http_response_code($e->status()); echo json_encode(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log((string)$e); http_response_code(500); echo json_encode(['error' => 'Something went wrong on the server.']);
        }
    }
    private static function input(): array { $d = json_decode(file_get_contents('php://input'), true); return is_array($d) ? $d : []; }
    public static function startSession(User $u): void { session_regenerate_id(true); $_SESSION['user_id'] = $u->id; $_SESSION['last_seen'] = time(); }
}