<?php
/* =============================================================
   SkillSwap NSU  —  auth.php
   -------------------------------------------------------------
   Sign in, sign out, and "who is reading this page".

   The seeded rows hold Werkzeug PBKDF2 hashes, exactly as the
   schema documents, and PHP verifies them directly with
   hash_pbkdf2 — so the sample data is never modified to suit the
   language. New registrations are written back in the same
   Werkzeug format, which keeps every row in the table uniform.
   ============================================================= */

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* -------------------------------------------------------------
   Password hashing, Werkzeug format:
     pbkdf2:sha256:<iterations>$<salt>$<hex digest>
   ------------------------------------------------------------- */

function password_check(string $plain, string $stored): bool
{
    $bits = explode('$', $stored, 3);
    if (count($bits) !== 3) {
        return false;
    }
    [$method, $salt, $digest] = $bits;

    $m = explode(':', $method);              // pbkdf2 : sha256 : 260000
    if (count($m) !== 3 || $m[0] !== 'pbkdf2') {
        return false;
    }

    $calc = hash_pbkdf2($m[1], $plain, $salt, (int) $m[2], 0, false);
    return hash_equals($digest, $calc);
}

function password_make(string $plain): string
{
    $iterations = 260000;
    $salt       = substr(str_replace(['+', '/', '='], '',
                    base64_encode(random_bytes(24))), 0, 16);
    $digest     = hash_pbkdf2('sha256', $plain, $salt, $iterations, 0, false);
    return "pbkdf2:sha256:$iterations\$$salt\$$digest";
}

/* -------------------------------------------------------------
   Session
   ------------------------------------------------------------- */

/* SQL: SELECT user_id, password FROM users WHERE email = ? */
function attempt_login(string $email, string $plain): ?array
{
    $u = row('SELECT user_id, name, password FROM users WHERE email = ?',
             [trim($email)]);

    if (!$u || !password_check($plain, $u['password'])) {
        return null;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $u['user_id'];
    return $u;
}

function sign_in_as(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function sign_out(): void
{
    $_SESSION = [];
    session_destroy();
}

function current_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function logged_in(): bool
{
    return current_id() !== null;
}

/** The signed-in student's full row, fetched once per request. */
function me(): ?array
{
    static $cached = null;
    static $forId  = null;

    $id = current_id();
    if ($id === null) {
        return null;
    }
    if ($cached !== null && $forId === $id) {
        return $cached;
    }

    $cached = row('SELECT * FROM users WHERE user_id = ?', [$id]);
    $forId  = $id;

    /* The account was deleted while its session was still open. */
    if (!$cached) {
        sign_out();
        return null;
    }
    return $cached;
}

function me_id(): int
{
    return (int) (me()['user_id'] ?? 0);
}

/** Guard at the top of every signed-in page. */
function require_login(): void
{
    if (!me()) {
        header('Location: login.php?next=' . urlencode(basename($_SERVER['PHP_SELF'])));
        exit;
    }
}

/* -------------------------------------------------------------
   Flash messages — how a write action reports back after the
   redirect that follows it (POST, redirect, GET).
   ------------------------------------------------------------- */

function flash(string $text, string $sql = '', string $kind = 'ok'): void
{
    $_SESSION['flash'][] = ['text' => $text, 'sql' => $sql, 'kind' => $kind];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* Cross-site request forgery token for every write form. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}
