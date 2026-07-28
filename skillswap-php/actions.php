<?php
/* =============================================================
   SkillSwap NSU  —  actions.php
   -------------------------------------------------------------
   Every write in the application arrives here as a POST, runs one
   parameterised statement (or one transaction), records a message
   naming the SQL it ran, and redirects back to the page it came
   from. Nothing on this site changes data through a GET.

   The pattern is POST → redirect → GET, so refreshing a page
   after an action never repeats it.
   ============================================================= */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';

require_login();

$back = $_POST['back'] ?? 'dashboard.php';
$back = basename(parse_url($back, PHP_URL_PATH) ?: 'dashboard.php')
      . (($qs = parse_url($back, PHP_URL_QUERY)) ? '?' . $qs : '');

function bounce(string $to): never
{
    header('Location: ' . $to);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    flash('That action could not be verified. Please try again.', '', 'bad');
    bounce($back);
}

$me     = me_id();
$action = $_POST['action'] ?? '';
$int    = fn(string $k): int => (int) ($_POST[$k] ?? 0);
$str    = fn(string $k): string => trim((string) ($_POST[$k] ?? ''));

switch ($action) {

/* ---------------------------------------------------------------
   Exchange requests
   --------------------------------------------------------------- */

case 'request.create': {
    $receiver = $int('receiver_id');
    $teach    = $int('teach_skill');
    $learn    = $int('learn_skill');

    if ($receiver === $me) {
        flash('You cannot send a request to yourself.', '', 'bad');
        bounce($back);
    }
    if (!$teach || !$learn || $teach === $learn) {
        flash('Pick two different skills — one to teach, one to learn.', '', 'bad');
        bounce($back);
    }

    run('INSERT INTO exchangerequests (sender_id, receiver_id, teach_skill, learn_skill, status)
         VALUES (?, ?, ?, ?, \'Pending\')', [$me, $receiver, $teach, $learn]);

    $name = user_by_id($receiver)['name'] ?? 'that student';
    flash("Request sent to $name.",
          'INSERT INTO exchangerequests (sender_id, receiver_id, teach_skill, learn_skill, status) '
        . "VALUES ($me, $receiver, $teach, $learn, 'Pending')");
    bounce($back);
}

case 'request.status': {
    $id     = $int('request_id');
    $status = $str('status');

    if (!in_array($status, ['Accepted', 'Rejected', 'Cancelled', 'Completed'], true)) {
        flash('Unknown status.', '', 'bad');
        bounce($back);
    }

    $r = request_by_id($id);
    if (!$r || ((int) $r['sender_id'] !== $me && (int) $r['receiver_id'] !== $me)) {
        flash('That request is not yours to change.', '', 'bad');
        bounce($back);
    }

    run('UPDATE exchangerequests SET status = ? WHERE request_id = ?', [$status, $id]);
    flash("Request #$id is now $status.",
          "UPDATE exchangerequests SET status = '$status' WHERE request_id = $id");
    bounce($back);
}

/* ---------------------------------------------------------------
   Sessions
   --------------------------------------------------------------- */

case 'session.create': {
    $req      = $int('request_id');
    $date     = $str('session_date');
    $time     = $str('session_time');
    $duration = $int('duration') ?: 60;
    $mode     = $str('mode') === 'Online' ? 'Online' : 'Offline';
    $where    = $str('where');

    $r = request_by_id($req);
    if (!$r || ((int) $r['sender_id'] !== $me && (int) $r['receiver_id'] !== $me)) {
        flash('That request is not yours to book against.', '', 'bad');
        bounce($back);
    }
    if ($r['status'] !== 'Accepted') {
        flash('A session can only be booked against an accepted request.', '', 'bad');
        bounce($back);
    }
    if ($duration < 15 || $duration > 480) {
        flash('Duration must be between 15 and 480 minutes — the schema enforces it.', '', 'bad');
        bounce($back);
    }

    /* The UNIQUE (request_id, session_date, session_time) key stops
       the same slot being booked twice; report it in plain English. */
    try {
        if ($mode === 'Online') {
            run('INSERT INTO sessions (request_id, session_date, session_time, duration, mode, meeting_link)
                 VALUES (?, ?, ?, ?, ?, ?)', [$req, $date, $time, $duration, $mode, $where ?: null]);
        } else {
            run('INSERT INTO sessions (request_id, session_date, session_time, duration, mode, location)
                 VALUES (?, ?, ?, ?, ?, ?)', [$req, $date, $time, $duration, $mode, $where ?: null]);
        }
    } catch (mysqli_sql_exception $e) {
        flash('That slot is already booked for this exchange — UNIQUE (request_id, session_date, session_time) rejected it.',
              '', 'bad');
        bounce($back);
    }

    $id = last_id();
    flash('Session #' . $id . ' booked for ' . fmt_date($date) . ' at ' . fmt_time($time) . '.',
          'INSERT INTO sessions (request_id, session_date, session_time, duration, mode, '
        . ($mode === 'Online' ? 'meeting_link' : 'location') . ') VALUES (?, ?, ?, ?, ?, ?)');
    bounce($back);
}

case 'session.edit': {
    $id   = $int('session_id');
    $date = $str('session_date');
    $time = $str('session_time');

    $s = row('SELECT se.* FROM sessions se
              INNER JOIN exchangerequests er ON er.request_id = se.request_id
              WHERE se.session_id = ? AND (er.sender_id = ? OR er.receiver_id = ?)',
             [$id, $me, $me]);
    if (!$s) {
        flash('That session is not yours to move.', '', 'bad');
        bounce($back);
    }

    run('UPDATE sessions SET session_date = ?, session_time = ? WHERE session_id = ?',
        [$date, $time, $id]);
    flash('Session #' . $id . ' moved to ' . fmt_date($date) . ' at ' . fmt_time($time) . '.',
          'UPDATE sessions SET session_date = ?, session_time = ? WHERE session_id = ' . $id);
    bounce($back);
}

case 'session.status': {
    $id     = $int('session_id');
    $status = $str('status');

    if (!in_array($status, ['Completed', 'Cancelled', 'Scheduled'], true)) {
        flash('Unknown session status.', '', 'bad');
        bounce($back);
    }

    $s = row('SELECT se.*, er.request_id AS req FROM sessions se
              INNER JOIN exchangerequests er ON er.request_id = se.request_id
              WHERE se.session_id = ? AND (er.sender_id = ? OR er.receiver_id = ?)',
             [$id, $me, $me]);
    if (!$s) {
        flash('That session is not yours to change.', '', 'bad');
        bounce($back);
    }

    /* Completing the last open session finishes the exchange too, so
       the two statements travel together or not at all. */
    tx_begin();
    try {
        run('UPDATE sessions SET status = ? WHERE session_id = ?', [$status, $id]);

        $note = "UPDATE sessions SET status = '$status' WHERE session_id = $id";

        if ($status === 'Completed') {
            $open = (int) val('SELECT COUNT(*) FROM sessions
                                WHERE request_id = ? AND status = \'Scheduled\'',
                              [(int) $s['request_id']]);
            if ($open === 0) {
                run('UPDATE exchangerequests SET status = \'Completed\' WHERE request_id = ?',
                    [(int) $s['request_id']]);
                $note .= '; UPDATE exchangerequests SET status = \'Completed\' WHERE request_id = '
                       . (int) $s['request_id'];
            }
        }
        tx_commit();
        flash("Session #$id is now $status.", $note);
    } catch (Throwable $e) {
        tx_undo();
        flash('The change was rolled back: ' . $e->getMessage(), '', 'bad');
    }
    bounce($back);
}

/* ---------------------------------------------------------------
   Reviews
   --------------------------------------------------------------- */

case 'review.create': {
    $sessionId = $int('session_id');
    $reviewee  = $int('reviewee_id');
    $rating    = $int('rating');
    $comment   = $str('comment');

    if ($rating < 1 || $rating > 5) {
        flash('Rating must be between 1 and 5 — the CHECK constraint enforces it.', '', 'bad');
        bounce($back);
    }
    if ($reviewee === $me) {
        flash('CHECK (reviewer_id <> reviewee_id) blocks self-reviews.', '', 'bad');
        bounce($back);
    }

    try {
        run('INSERT INTO reviews (session_id, reviewer_id, reviewee_id, rating, comment)
             VALUES (?, ?, ?, ?, ?)',
            [$sessionId, $me, $reviewee, $rating, $comment ?: null]);
    } catch (mysqli_sql_exception $e) {
        flash('You have already reviewed this session — UNIQUE (session_id, reviewer_id) rejected it.',
              '', 'bad');
        bounce($back);
    }

    flash('Review published.',
          'INSERT INTO reviews (session_id, reviewer_id, reviewee_id, rating, comment) '
        . "VALUES ($sessionId, $me, $reviewee, $rating, ?)");
    bounce($back);
}

case 'review.delete': {
    $id = $int('review_id');
    $n  = run('DELETE FROM reviews WHERE review_id = ? AND reviewer_id = ?', [$id, $me]);

    if ($n) {
        flash("Review #$id withdrawn.",
              "DELETE FROM reviews WHERE review_id = $id AND reviewer_id = $me");
    } else {
        flash('You can only withdraw a review you wrote.', '', 'bad');
    }
    bounce($back);
}

/* ---------------------------------------------------------------
   Profile and skills
   --------------------------------------------------------------- */

case 'profile.update': {
    $name = $str('name');
    $dept = $str('department');
    $bio  = $str('bio');

    if ($name === '' || $dept === '') {
        flash('Name and department are NOT NULL.', '', 'bad');
        bounce($back);
    }

    run('UPDATE users SET name = ?, department = ?, bio = ? WHERE user_id = ?',
        [$name, $dept, $bio ?: null, $me]);
    flash('Profile saved.',
          "UPDATE users SET name = ?, department = ?, bio = ? WHERE user_id = $me");
    bounce($back);
}

case 'password.change': {
    $current = (string) ($_POST['current'] ?? '');
    $fresh   = (string) ($_POST['fresh'] ?? '');
    $again   = (string) ($_POST['again'] ?? '');

    $stored = (string) val('SELECT password FROM users WHERE user_id = ?', [$me]);

    if (!password_check($current, $stored)) {
        flash('The current password does not match the stored hash.', '', 'bad');
        bounce($back);
    }
    if (strlen($fresh) < 6) {
        flash('Choose a password of at least six characters.', '', 'bad');
        bounce($back);
    }
    if ($fresh !== $again) {
        flash('The two new passwords do not match.', '', 'bad');
        bounce($back);
    }

    run('UPDATE users SET password = ? WHERE user_id = ?', [password_make($fresh), $me]);
    flash('Password changed — the new PBKDF2 hash is stored, never the password itself.',
          "UPDATE users SET password = ? WHERE user_id = $me");
    bounce($back);
}

case 'skill.add': {
    $skillId = $int('skill_id');
    $type    = $str('skill_type') === 'Learn' ? 'Learn' : 'Teach';
    $level   = $str('proficiency') ?: 'Beginner';

    if (!in_array($level, ['Beginner', 'Intermediate', 'Advanced', 'Expert'], true)) {
        $level = 'Beginner';
    }
    if (!$skillId) {
        flash('Choose a skill from the catalogue first.', '', 'bad');
        bounce($back);
    }

    try {
        run('INSERT INTO userskills (user_id, skill_id, skill_type, proficiency)
             VALUES (?, ?, ?, ?)', [$me, $skillId, $type, $level]);
    } catch (mysqli_sql_exception $e) {
        flash('That skill is already on your list under this type — UNIQUE (user_id, skill_id, skill_type) rejected it.',
              '', 'bad');
        bounce($back);
    }

    $name = skill_by_id($skillId)['skill_name'] ?? '';
    flash("$name added to your $type list.",
          'INSERT INTO userskills (user_id, skill_id, skill_type, proficiency) '
        . "VALUES ($me, $skillId, '$type', '$level')");
    bounce($back);
}

case 'skill.level': {
    $id    = $int('user_skill_id');
    $level = $str('proficiency');

    if (!in_array($level, ['Beginner', 'Intermediate', 'Advanced', 'Expert'], true)) {
        flash('Unknown proficiency level.', '', 'bad');
        bounce($back);
    }

    run('UPDATE userskills SET proficiency = ? WHERE user_skill_id = ? AND user_id = ?',
        [$level, $id, $me]);
    flash("Level set to $level.",
          "UPDATE userskills SET proficiency = '$level' WHERE user_skill_id = $id");
    bounce($back);
}

case 'skill.remove': {
    $id = $int('user_skill_id');
    $n  = run('DELETE FROM userskills WHERE user_skill_id = ? AND user_id = ?', [$id, $me]);

    if ($n) {
        flash('Skill removed from your list.',
              "DELETE FROM userskills WHERE user_skill_id = $id AND user_id = $me");
    } else {
        flash('That skill row is not yours.', '', 'bad');
    }
    bounce($back);
}

/* ---------------------------------------------------------------
   Admin console
   --------------------------------------------------------------- */

case 'admin.user.delete': {
    $id = $int('user_id');

    if ($id === $me) {
        flash('Deleting the account you are signed in as would end the demo — pick another row.',
              '', 'bad');
        bounce($back);
    }

    $u = user_by_id($id);
    if (!$u) {
        flash('That student is already gone.', '', 'bad');
        bounce($back);
    }

    /* One statement. ON DELETE CASCADE removes the student's skills,
       requests, sessions and reviews without any further SQL. */
    $skills = (int) val('SELECT COUNT(*) FROM userskills WHERE user_id = ?', [$id]);
    $reqs   = (int) val('SELECT COUNT(*) FROM exchangerequests
                          WHERE sender_id = ? OR receiver_id = ?', [$id, $id]);

    run('DELETE FROM users WHERE user_id = ?', [$id]);
    flash($u['name'] . " deleted — $skills skill rows and $reqs requests went with it, by cascade.",
          "DELETE FROM users WHERE user_id = $id");
    bounce($back);
}

case 'admin.skill.create': {
    $name     = $str('skill_name');
    $category = $str('category');
    $desc     = $str('description');

    if ($name === '' || $category === '') {
        flash('A skill needs a name and a category.', '', 'bad');
        bounce($back);
    }

    try {
        run('INSERT INTO skills (skill_name, category, description) VALUES (?, ?, ?)',
            [$name, $category, $desc ?: null]);
    } catch (mysqli_sql_exception $e) {
        flash("\"$name\" is already in the catalogue — UNIQUE (skill_name) rejected it.", '', 'bad');
        bounce($back);
    }

    flash("$name added to the catalogue.",
          'INSERT INTO skills (skill_name, category, description) VALUES (?, ?, ?)');
    bounce($back);
}

case 'admin.skill.delete': {
    $id = $int('skill_id');
    $s  = skill_by_id($id);

    if (!$s) {
        flash('That skill is already gone.', '', 'bad');
        bounce($back);
    }

    /* ON DELETE RESTRICT: a skill an exchange request still points at
       cannot be removed. Report that rather than swallowing it. */
    try {
        run('DELETE FROM skills WHERE skill_id = ?', [$id]);
    } catch (mysqli_sql_exception $e) {
        flash($s['skill_name'] . ' is still referenced by an exchange request, so ON DELETE RESTRICT '
            . 'refused the delete. That is the constraint doing its job.', '', 'bad');
        bounce($back);
    }

    flash($s['skill_name'] . ' removed from the catalogue.',
          "DELETE FROM skills WHERE skill_id = $id");
    bounce($back);
}

case 'admin.review.delete': {
    $id = $int('review_id');
    $n  = run('DELETE FROM reviews WHERE review_id = ?', [$id]);

    flash($n ? "Review #$id removed by moderation." : 'That review is already gone.',
          $n ? "DELETE FROM reviews WHERE review_id = $id" : '',
          $n ? 'ok' : 'bad');
    bounce($back);
}

default:
    flash('Unknown action.', '', 'bad');
    bounce($back);
}
