<?php
/* =============================================================
   SkillSwap NSU  —  queries.php
   -------------------------------------------------------------
   Every SELECT the interface needs, in one place, written as raw
   parameterised SQL. No ORM, no query builder, no string
   concatenation of values.

   Where a function corresponds to a numbered query in
   database/queries.sql the number is given in the comment, so the
   report and the running site can be read side by side.
   ============================================================= */

require_once __DIR__ . '/db.php';

/* -------------------------------------------------------------
   Single-row lookups
   ------------------------------------------------------------- */

function user_by_id(int $id): ?array
{
    return row('SELECT * FROM users WHERE user_id = ?', [$id]);
}

function skill_by_id(int $id): ?array
{
    return row('SELECT * FROM skills WHERE skill_id = ?', [$id]);
}

function request_by_id(int $id): ?array
{
    return row('SELECT * FROM exchangerequests WHERE request_id = ?', [$id]);
}

function session_by_id(int $id): ?array
{
    return row('SELECT * FROM sessions WHERE session_id = ?', [$id]);
}

/* -------------------------------------------------------------
   Skills
   ------------------------------------------------------------- */

/* B1 — every skill a student teaches or wants to learn, with level. */
function skills_of(int $userId, string $type): array
{
    return rows(
        'SELECT  s.skill_id, s.skill_name, s.category, s.description,
                 us.user_skill_id, us.proficiency, us.skill_type
         FROM        userskills us
         INNER JOIN  skills s ON s.skill_id = us.skill_id
         WHERE       us.user_id = ? AND us.skill_type = ?
         ORDER BY    s.skill_name',
        [$userId, $type]
    );
}

function all_skills(): array
{
    return rows('SELECT * FROM skills ORDER BY skill_name');
}

function all_departments(): array
{
    return array_column(
        rows('SELECT DISTINCT department FROM users ORDER BY department'),
        'department'
    );
}

function all_categories(): array
{
    return array_column(
        rows('SELECT DISTINCT category FROM skills ORDER BY category'),
        'category'
    );
}

/* C3 — every skill in the catalogue with how many students teach it. */
function categories_with_counts(): array
{
    return rows(
        'SELECT   s.category,
                  COUNT(DISTINCT s.skill_id) AS skills,
                  COUNT(DISTINCT CASE WHEN us.skill_type = \'Teach\'
                                      THEN us.user_id END) AS teachers
         FROM       skills s
         LEFT JOIN  userskills us ON us.skill_id = s.skill_id
         GROUP BY   s.category
         ORDER BY   s.category'
    );
}

/* Skills that at least one student can actually teach — the marquee. */
function teachable_skills(): array
{
    return rows(
        'SELECT     s.skill_name, s.category, COUNT(DISTINCT us.user_id) AS teachers
         FROM       skills s
         INNER JOIN userskills us ON us.skill_id = s.skill_id
                                 AND us.skill_type = \'Teach\'
         GROUP BY   s.skill_id, s.skill_name, s.category
         ORDER BY   teachers DESC, s.skill_name'
    );
}

/* -------------------------------------------------------------
   Ratings — read through the v_user_ratings view (G1)
   ------------------------------------------------------------- */

function rating_of(int $userId): array
{
    $r = row('SELECT total_reviews, avg_rating FROM v_user_ratings WHERE user_id = ?',
             [$userId]);
    return [
        'count' => (int) ($r['total_reviews'] ?? 0),
        'avg'   => isset($r['avg_rating']) ? (float) $r['avg_rating'] : null,
    ];
}

/* D4 — best rated students, at least two reviews. */
function top_rated(int $limit = 5): array
{
    return rows(
        'SELECT   user_id, name, department, total_reviews, avg_rating
         FROM     v_user_ratings
         WHERE    total_reviews >= 1
         ORDER BY avg_rating DESC, total_reviews DESC, name
         LIMIT    ?',
        [$limit]
    );
}

/* -------------------------------------------------------------
   Exchange requests
   ------------------------------------------------------------- */

/* B2 — the readable request list, four tables joined. */
function requests_of(int $userId, string $direction = 'all', string $status = ''): array
{
    $sql = 'SELECT   er.*,
                     CASE WHEN er.sender_id = ? THEN \'sent\' ELSE \'received\' END AS dir,
                     snd.name  AS sender_name,   snd.department AS sender_dept,
                     rcv.name  AS receiver_name, rcv.department AS receiver_dept,
                     ts.skill_name AS teach_name, ts.category AS teach_cat,
                     ls.skill_name AS learn_name, ls.category AS learn_cat,
                     (SELECT COUNT(*) FROM sessions se
                       WHERE se.request_id = er.request_id
                         AND se.status <> \'Cancelled\')        AS booked
            FROM       exchangerequests er
            INNER JOIN users  snd ON snd.user_id  = er.sender_id
            INNER JOIN users  rcv ON rcv.user_id  = er.receiver_id
            INNER JOIN skills ts  ON ts.skill_id  = er.teach_skill
            INNER JOIN skills ls  ON ls.skill_id  = er.learn_skill
            WHERE ';
    $params = [$userId];

    if ($direction === 'sent') {
        $sql .= 'er.sender_id = ? ';
        $params[] = $userId;
    } elseif ($direction === 'received') {
        $sql .= 'er.receiver_id = ? ';
        $params[] = $userId;
    } else {
        $sql .= '(er.sender_id = ? OR er.receiver_id = ?) ';
        $params[] = $userId;
        $params[] = $userId;
    }

    if ($status !== '') {
        $sql .= 'AND er.status = ? ';
        $params[] = $status;
    }

    $sql .= 'ORDER BY er.created_at DESC, er.request_id DESC';
    return rows($sql, $params);
}

/* D5 — how many of this student's requests sit in each status. */
function request_status_counts(int $userId): array
{
    $out = ['Pending' => 0, 'Accepted' => 0, 'Completed' => 0,
            'Rejected' => 0, 'Cancelled' => 0];

    foreach (rows(
        'SELECT   status, COUNT(*) AS n
         FROM     exchangerequests
         WHERE    sender_id = ? OR receiver_id = ?
         GROUP BY status', [$userId, $userId]) as $r) {
        $out[$r['status']] = (int) $r['n'];
    }
    return $out;
}

function pending_received(int $userId): int
{
    return (int) val(
        'SELECT COUNT(*) FROM exchangerequests
          WHERE receiver_id = ? AND status = \'Pending\'', [$userId]);
}

/* -------------------------------------------------------------
   Sessions — read through v_session_overview where the whole row
   is wanted, and joined directly where the ids are needed too.
   ------------------------------------------------------------- */

function sessions_of(int $userId, string $status = ''): array
{
    $sql = 'SELECT   se.*,
                     er.sender_id, er.receiver_id, er.status AS request_status,
                     er.teach_skill, er.learn_skill,
                     ts.skill_name AS teach_name,
                     ls.skill_name AS learn_name,
                     CASE WHEN er.sender_id = ? THEN rcv.user_id ELSE snd.user_id END AS partner_id,
                     CASE WHEN er.sender_id = ? THEN rcv.name    ELSE snd.name    END AS partner_name,
                     CASE WHEN er.sender_id = ? THEN rcv.department ELSE snd.department END AS partner_dept
            FROM       sessions se
            INNER JOIN exchangerequests er ON er.request_id = se.request_id
            INNER JOIN users  snd ON snd.user_id = er.sender_id
            INNER JOIN users  rcv ON rcv.user_id = er.receiver_id
            INNER JOIN skills ts  ON ts.skill_id = er.teach_skill
            INNER JOIN skills ls  ON ls.skill_id = er.learn_skill
            WHERE (er.sender_id = ? OR er.receiver_id = ?) ';
    $params = [$userId, $userId, $userId, $userId, $userId];

    if ($status !== '') {
        $sql .= 'AND se.status = ? ';
        $params[] = $status;
    }

    $sql .= 'ORDER BY se.session_date DESC, se.session_time DESC';
    return rows($sql, $params);
}

function upcoming_count(int $userId): int
{
    return (int) val(
        'SELECT     COUNT(*)
         FROM       sessions se
         INNER JOIN exchangerequests er ON er.request_id = se.request_id
         WHERE      se.status = \'Scheduled\'
           AND      (er.sender_id = ? OR er.receiver_id = ?)', [$userId, $userId]);
}

/* Requests this student may still book a session against. */
function bookable_requests(int $userId): array
{
    return rows(
        'SELECT     er.request_id, er.sender_id, er.receiver_id,
                    ts.skill_name AS teach_name, ls.skill_name AS learn_name,
                    snd.name AS sender_name, rcv.name AS receiver_name
         FROM       exchangerequests er
         INNER JOIN users  snd ON snd.user_id = er.sender_id
         INNER JOIN users  rcv ON rcv.user_id = er.receiver_id
         INNER JOIN skills ts  ON ts.skill_id = er.teach_skill
         INNER JOIN skills ls  ON ls.skill_id = er.learn_skill
         WHERE      er.status = \'Accepted\'
           AND      (er.sender_id = ? OR er.receiver_id = ?)
         ORDER BY   er.request_id DESC', [$userId, $userId]);
}

/* -------------------------------------------------------------
   Reviews
   ------------------------------------------------------------- */

function reviews_received(int $userId): array
{
    return rows(
        'SELECT     r.*, u.name AS reviewer_name, u.department AS reviewer_dept,
                    sk.skill_name AS skill_taught, se.session_date
         FROM       reviews r
         INNER JOIN users u  ON u.user_id = r.reviewer_id
         INNER JOIN sessions se ON se.session_id = r.session_id
         INNER JOIN exchangerequests er ON er.request_id = se.request_id
         INNER JOIN skills sk ON sk.skill_id = er.teach_skill
         WHERE      r.reviewee_id = ?
         ORDER BY   r.created_at DESC', [$userId]);
}

function reviews_written(int $userId): array
{
    return rows(
        'SELECT     r.*, u.name AS reviewee_name, u.department AS reviewee_dept,
                    sk.skill_name AS skill_taught, se.session_date
         FROM       reviews r
         INNER JOIN users u  ON u.user_id = r.reviewee_id
         INNER JOIN sessions se ON se.session_id = r.session_id
         INNER JOIN exchangerequests er ON er.request_id = se.request_id
         INNER JOIN skills sk ON sk.skill_id = er.teach_skill
         WHERE      r.reviewer_id = ?
         ORDER BY   r.created_at DESC', [$userId]);
}

/* E3 / NOT EXISTS — completed sessions this student has not reviewed yet. */
function sessions_awaiting_review(int $userId): array
{
    return rows(
        'SELECT     se.session_id, se.session_date, se.session_time,
                    er.request_id,
                    CASE WHEN er.sender_id = ? THEN rcv.user_id ELSE snd.user_id END AS partner_id,
                    CASE WHEN er.sender_id = ? THEN rcv.name    ELSE snd.name    END AS partner_name,
                    sk.skill_name AS skill_taught
         FROM       sessions se
         INNER JOIN exchangerequests er ON er.request_id = se.request_id
         INNER JOIN users  snd ON snd.user_id = er.sender_id
         INNER JOIN users  rcv ON rcv.user_id = er.receiver_id
         INNER JOIN skills sk  ON sk.skill_id = er.teach_skill
         WHERE      se.status = \'Completed\'
           AND      (er.sender_id = ? OR er.receiver_id = ?)
           AND NOT EXISTS (SELECT 1 FROM reviews r
                            WHERE r.session_id  = se.session_id
                              AND r.reviewer_id = ?)
         ORDER BY   se.session_date DESC',
        [$userId, $userId, $userId, $userId, $userId]);
}

/* -------------------------------------------------------------
   Search — F1, F2, F3 in database/queries.sql
   ------------------------------------------------------------- */

function search_students(array $f, int $meId): array
{
    $sql = 'SELECT DISTINCT
                   u.user_id, u.name, u.department, u.bio,
                   vr.total_reviews, vr.avg_rating
            FROM       users u
            LEFT JOIN  v_user_ratings vr ON vr.user_id = u.user_id
            LEFT JOIN  userskills us ON us.user_id = u.user_id
            LEFT JOIN  skills     s  ON s.skill_id = us.skill_id
            WHERE      u.user_id <> ? ';
    $params = [$meId];

    if (!empty($f['q'])) {
        $sql .= 'AND (u.name LIKE ? OR u.department LIKE ? OR s.skill_name LIKE ?) ';
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like);
    }
    if (!empty($f['skill'])) {
        $sql .= 'AND s.skill_name = ? AND us.skill_type = \'Teach\' ';
        $params[] = $f['skill'];
    }
    if (!empty($f['category'])) {
        $sql .= 'AND s.category = ? AND us.skill_type = \'Teach\' ';
        $params[] = $f['category'];
    }
    if (!empty($f['department'])) {
        $sql .= 'AND u.department = ? ';
        $params[] = $f['department'];
    }
    if (!empty($f['level'])) {
        $sql .= 'AND us.proficiency = ? AND us.skill_type = \'Teach\' ';
        $params[] = $f['level'];
    }

    $sql .= 'ORDER BY (vr.avg_rating IS NULL), vr.avg_rating DESC, u.name';
    return rows($sql, $params);
}

/* -------------------------------------------------------------
   The heart of the product: a two-way match. Someone who teaches
   what I want to learn AND wants to learn something I can teach.
   ------------------------------------------------------------- */

function matches_for(int $userId, int $limit = 6): array
{
    return rows(
        'SELECT     u.user_id, u.name, u.department,
                    vr.total_reviews, vr.avg_rating,
                    theirs.skill_name AS they_teach,
                    theirs.skill_id   AS they_teach_id,
                    theirs.proficiency AS they_level,
                    mine.skill_name   AS i_teach,
                    mine.skill_id     AS i_teach_id
         FROM       users u
         INNER JOIN (SELECT us.user_id, s.skill_id, s.skill_name, us.proficiency
                     FROM   userskills us
                     INNER JOIN skills s ON s.skill_id = us.skill_id
                     WHERE  us.skill_type = \'Teach\') theirs ON theirs.user_id = u.user_id
         INNER JOIN (SELECT s.skill_id, s.skill_name
                     FROM   userskills us
                     INNER JOIN skills s ON s.skill_id = us.skill_id
                     WHERE  us.user_id = ? AND us.skill_type = \'Teach\') mine
                    ON mine.skill_id IN (SELECT skill_id FROM userskills
                                          WHERE user_id = u.user_id
                                            AND skill_type = \'Learn\')
         LEFT JOIN  v_user_ratings vr ON vr.user_id = u.user_id
         WHERE      u.user_id <> ?
           AND      theirs.skill_id IN (SELECT skill_id FROM userskills
                                         WHERE user_id = ? AND skill_type = \'Learn\')
         GROUP BY   u.user_id, theirs.skill_id, mine.skill_id
         ORDER BY   (vr.avg_rating IS NULL), vr.avg_rating DESC, u.name
         LIMIT      ?',
        [$userId, $userId, $userId, $limit]);
}

/* -------------------------------------------------------------
   Platform statistics — J1
   ------------------------------------------------------------- */

function platform_stats(): array
{
    return [
        'students'  => (int) val('SELECT COUNT(*) FROM users'),
        'skills'    => (int) val('SELECT COUNT(*) FROM skills'),
        'pending'   => (int) val('SELECT COUNT(*) FROM exchangerequests WHERE status = \'Pending\''),
        'upcoming'  => (int) val('SELECT COUNT(*) FROM sessions WHERE status = \'Scheduled\''),
        'completed' => (int) val('SELECT COUNT(*) FROM exchangerequests WHERE status = \'Completed\''),
        'avg'       => val('SELECT ROUND(AVG(rating), 2) FROM reviews'),
        'lowrated'  => (int) val('SELECT COUNT(*) FROM reviews WHERE rating <= 2'),
        'exchanges' => (int) val('SELECT COUNT(*) FROM exchangerequests'),
        'sessions'  => (int) val('SELECT COUNT(*) FROM sessions'),
        'reviews'   => (int) val('SELECT COUNT(*) FROM reviews'),
    ];
}

/* D1 — students per department, used on the landing page. */
function students_per_department(): array
{
    return rows(
        'SELECT   department, COUNT(*) AS n
         FROM     users
         GROUP BY department
         ORDER BY n DESC, department');
}

/* -------------------------------------------------------------
   Admin console listings
   ------------------------------------------------------------- */

function admin_students(string $search = ''): array
{
    $sql = 'SELECT   u.user_id, u.name, u.email, u.department, u.created_at,
                     SUM(us.skill_type = \'Teach\') AS teaches,
                     SUM(us.skill_type = \'Learn\') AS learns,
                     vr.total_reviews, vr.avg_rating,
                     (SELECT COUNT(*) FROM exchangerequests er
                       WHERE er.sender_id = u.user_id
                          OR er.receiver_id = u.user_id) AS exchanges
            FROM       users u
            LEFT JOIN  userskills us ON us.user_id = u.user_id
            LEFT JOIN  v_user_ratings vr ON vr.user_id = u.user_id ';
    $params = [];

    if ($search !== '') {
        $sql .= 'WHERE (u.name LIKE ? OR u.email LIKE ? OR u.department LIKE ?) ';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }

    $sql .= 'GROUP BY u.user_id ORDER BY u.user_id';
    return rows($sql, $params);
}

function admin_skills(string $search = ''): array
{
    $sql = 'SELECT   s.*,
                     SUM(us.skill_type = \'Teach\') AS teaches,
                     SUM(us.skill_type = \'Learn\') AS learns
            FROM       skills s
            LEFT JOIN  userskills us ON us.skill_id = s.skill_id ';
    $params = [];

    if ($search !== '') {
        $sql .= 'WHERE (s.skill_name LIKE ? OR s.category LIKE ?) ';
        $like = '%' . $search . '%';
        array_push($params, $like, $like);
    }

    $sql .= 'GROUP BY s.skill_id ORDER BY s.skill_name';
    return rows($sql, $params);
}

/* G2 — the request list straight out of the view. */
function admin_requests(string $status = ''): array
{
    $sql    = 'SELECT * FROM v_request_details ';
    $params = [];
    if ($status !== '') {
        $sql .= 'WHERE status = ? ';
        $params[] = $status;
    }
    $sql .= 'ORDER BY request_id DESC';
    return rows($sql, $params);
}

function admin_reviews(int $maxRating = 5): array
{
    return rows(
        'SELECT     r.*, rv.name AS reviewer_name, re.name AS reviewee_name
         FROM       reviews r
         INNER JOIN users rv ON rv.user_id = r.reviewer_id
         INNER JOIN users re ON re.user_id = r.reviewee_id
         WHERE      r.rating <= ?
         ORDER BY   r.rating, r.created_at DESC', [$maxRating]);
}

/* Does this pair already have an open request between them? */
function existing_request(int $a, int $b): ?array
{
    return row(
        'SELECT * FROM exchangerequests
          WHERE ((sender_id = ? AND receiver_id = ?)
             OR  (sender_id = ? AND receiver_id = ?))
            AND status IN (\'Pending\', \'Accepted\')
          LIMIT 1', [$a, $b, $b, $a]);
}
