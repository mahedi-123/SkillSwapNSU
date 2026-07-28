<?php
/* =============================================================
   SkillSwap NSU  —  db.php
   -------------------------------------------------------------
   One mysqli connection plus four thin helpers. Every helper
   takes SQL with ? placeholders and an array of parameters, so
   no value is ever concatenated into a statement anywhere in
   this project. That is both the lab requirement (raw SQL, no
   ORM) and the defence against SQL injection.

     rows($sql, $p)   -> array of associative rows
     row($sql, $p)    -> the first row, or null
     val($sql, $p)    -> the first column of the first row
     run($sql, $p)    -> execute a write; returns affected rows
     last_id()        -> AUTO_INCREMENT value of the last INSERT
   ============================================================= */

require_once __DIR__ . '/config.php';

function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    } catch (Throwable $e) {
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8">'
           . '<title>Database not reachable</title>'
           . '<style>body{font:15px/1.6 system-ui,sans-serif;max-width:44rem;'
           . 'margin:12vh auto;padding:0 1.5rem;color:#222}'
           . 'code{background:#f2f2f2;padding:1px 5px;border-radius:3px}'
           . 'h1{font-size:1.4rem}li{margin:.4rem 0}</style>'
           . '<h1>SkillSwap cannot reach MySQL</h1>'
           . '<p>PHP is running, so Apache is fine. The database connection failed with:</p>'
           . '<p><code>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</code></p>'
           . '<p>The usual causes, in order:</p><ol>'
           . '<li><b>MySQL is not started.</b> Open the XAMPP Control Panel and press '
           . '<i>Start</i> next to MySQL.</li>'
           . '<li><b>The database has not been imported yet.</b> Open '
           . '<code>localhost/phpmyadmin</code>, choose <i>Import</i>, and run '
           . '<code>database/skillexchange_full.sql</code> from this folder.</li>'
           . '<li><b>Your MySQL uses a password.</b> Put it in '
           . '<code>includes/config.php</code> under <code>DB_PASS</code>.</li>'
           . '</ol>';
        exit;
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

/** Prepare, bind and execute. Internal to this file. */
function db_stmt(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p))         { $types .= 'i'; }
            elseif (is_float($p))   { $types .= 'd'; }
            else                    { $types .= 's'; }
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

function rows(string $sql, array $params = []): array
{
    $stmt = db_stmt($sql, $params);
    $out  = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $out;
}

function row(string $sql, array $params = []): ?array
{
    $stmt = db_stmt($sql, $params);
    $r    = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ?: null;
}

function val(string $sql, array $params = [])
{
    $r = row($sql, $params);
    return $r ? reset($r) : null;
}

function run(string $sql, array $params = []): int
{
    $stmt = db_stmt($sql, $params);
    $n    = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

function last_id(): int
{
    return (int) db()->insert_id;
}

/* Transactions — used by the admin console, where one action has to
   touch several tables and must not be left half done. */
function tx_begin(): void  { db()->begin_transaction(); }
function tx_commit(): void { db()->commit(); }
function tx_undo(): void   { db()->rollback(); }
