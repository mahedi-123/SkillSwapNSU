<?php
/* =============================================================
   index.php — the public landing page. Nothing here is typed in
   by hand: the headline figures are COUNT(*) and AVG() over the
   six tables, the marquee and the category menu are GROUP BY
   roll-ups of userskills and skills, and the sample record in the
   hero is one completed exchange joined across five tables.
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';

/* The five numbers in the proof band, plus the row counts further
   down the page. J1 in database/queries.sql. */
$stats      = platform_stats();
$userskills = (int) val('SELECT COUNT(*) FROM userskills');

/* Only finished sessions count as time actually traded. */
$minutes = (int) val('SELECT COALESCE(SUM(duration), 0) FROM sessions WHERE status = ?',
                     ['Completed']);

/* One real completed exchange, with the sessions and the rating
   that followed it. This is the card in the hero. */
$demo = row(
    'SELECT     er.request_id, er.created_at,
                snd.name AS sender_name,   snd.department AS sender_dept,
                rcv.name AS receiver_name, rcv.department AS receiver_dept,
                ts.skill_name AS teach_name, ls.skill_name AS learn_name,
                (SELECT COUNT(*) FROM sessions se
                  WHERE se.request_id = er.request_id)                 AS session_count,
                (SELECT COALESCE(SUM(se.duration), 0) FROM sessions se
                  WHERE se.request_id = er.request_id)                 AS minutes,
                (SELECT ROUND(AVG(r.rating), 1)
                   FROM reviews r
                   INNER JOIN sessions se ON se.session_id = r.session_id
                  WHERE se.request_id = er.request_id)                 AS avg_rating
     FROM       exchangerequests er
     INNER JOIN users  snd ON snd.user_id = er.sender_id
     INNER JOIN users  rcv ON rcv.user_id = er.receiver_id
     INNER JOIN skills ts  ON ts.skill_id = er.teach_skill
     INNER JOIN skills ls  ON ls.skill_id = er.learn_skill
     WHERE      er.status = ?
     ORDER BY   er.request_id
     LIMIT      1',
    ['Completed']);

/* Every skill at least one student teaches — the marquee, and the
   pool the dice draws from. */
$taught     = teachable_skills();
$diceSkills = array_column($taught, 'skill_name');

/* The eleven categories with a live count behind each one (C3). */
$categories = categories_with_counts();

/* What the most students are asking for, with how many can supply
   it. The first four also fill the suggestion chips under the ask
   box, so the demand figure is queried once and used twice. */
$demand = rows(
    'SELECT   s.skill_id, s.skill_name, s.category, s.description,
              COUNT(DISTINCT CASE WHEN us.skill_type = ? THEN us.user_id END) AS learners,
              COUNT(DISTINCT CASE WHEN us.skill_type = ? THEN us.user_id END) AS teachers
     FROM       skills s
     INNER JOIN userskills us ON us.skill_id = s.skill_id
     GROUP BY   s.skill_id, s.skill_name, s.category, s.description
     ORDER BY   learners DESC, s.skill_name
     LIMIT      ?',
    ['Learn', 'Teach', 6]);

$departments = students_per_department();

/* The review slider. A comment only exists because a session was
   completed first, which is the join this query walks backwards. */
$voices = rows(
    'SELECT     r.review_id, r.rating, r.comment,
                rv.user_id AS reviewer_id, rv.name AS reviewer_name,
                re.name AS reviewee_name,
                sk.skill_name AS skill_taught
     FROM       reviews r
     INNER JOIN users    rv ON rv.user_id    = r.reviewer_id
     INNER JOIN users    re ON re.user_id    = r.reviewee_id
     INNER JOIN sessions se ON se.session_id = r.session_id
     INNER JOIN exchangerequests er ON er.request_id = se.request_id
     INNER JOIN skills   sk ON sk.skill_id   = er.teach_skill
     WHERE      r.comment IS NOT NULL
       AND      CHAR_LENGTH(r.comment) > ?
     ORDER BY   r.rating DESC, r.review_id
     LIMIT      ?',
    [24, 14]);

/* Split so the two rails never carry the same quote side by side. */
$half   = (int) ceil(count($voices) / 2);
$railA  = array_slice($voices, 0, $half);
$railB  = array_slice($voices, $half);

/* One glyph per category, so the menu reads at a glance. */
$CAT_ICON = [
    'Programming' => 'code-slash', 'Design'     => 'palette',
    'Business'    => 'briefcase',  'Language'   => 'translate',
    'Mathematics' => 'calculator', 'Music'      => 'music-note-beamed',
    'Photography' => 'camera',     'Marketing'  => 'megaphone',
    'Writing'     => 'pencil',     'Video Editing' => 'film',
    'Public Speaking' => 'mic',
];

/* One review card, used by both rails. */
function voice_card(array $r): string
{
    $who = ['user_id' => $r['reviewer_id'], 'name' => $r['reviewer_name']];
    $first = explode(' ', trim((string) $r['reviewee_name']))[0];
    return '
    <figure class="review-card">
      ' . stars($r['rating']) . '
      <blockquote>&ldquo;' . e($r['comment']) . '&rdquo;</blockquote>
      <figcaption>
        ' . avatar($who, 32) . '
        <span class="min-w-0">
          <span class="who d-block">' . e($r['reviewer_name']) . '</span>
          <span class="ctx d-block">on ' . e($first) . ' &middot; ' . e($r['skill_taught']) . '</span>
        </span>
      </figcaption>
    </figure>';
}

page_head('Trade what you know for what you want to learn');
?>

<div class="scroll-progress" aria-hidden="true"><span id="scrollBar"></span></div>

<!-- ============================== nav ============================== -->
<nav class="marketing-nav">
  <div class="container d-flex align-items-center gap-3 py-2">
    <a class="brand-mark" href="index.php">
      <span class="brand-glyph"><i class="bi bi-arrow-left-right"></i></span>
      SkillSwap <span class="brand-tag">NSU</span>
    </a>

    <div class="d-none d-lg-flex align-items-center gap-1 ms-3">
      <a class="nav-item-link" href="#how">How it works</a>
      <span class="megawrap">
        <a class="nav-item-link has-mega" href="#catalogue" id="catToggle"
           aria-expanded="false" aria-controls="catMenu">Skills
           <i class="bi bi-chevron-down" style="font-size:.6rem"></i></a>
        <div class="mega" id="catMenu" role="menu" aria-labelledby="catToggle">
          <?php foreach ($categories as $c): ?>
            <a role="menuitem" href="search.php?category=<?= urlencode($c['category']) ?>"
               title="<?= (int) $c['teachers'] ?> students teach in this category">
              <i class="bi bi-<?= e($CAT_ICON[$c['category']] ?? 'dot') ?>"></i>
              <span><?= e($c['category']) ?></span>
              <span class="n"><?= (int) $c['skills'] ?></span></a>
          <?php endforeach; ?>
        </div>
      </span>
      <a class="nav-item-link" href="#build">The build</a>
      <a class="nav-item-link" href="#faq">FAQ</a>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="login.php" class="btn btn-sm btn-quiet">Sign in</a>
      <a href="dashboard.php" class="btn btn-sm btn-brand">Open the demo</a>
    </div>
  </div>
</nav>

<!-- ============================== hero ============================== -->
<header class="hero" id="hero">
  <div class="container hero-inner py-5">
    <div class="row align-items-center g-5 py-lg-4">

      <div class="col-lg-6 rise">
        <span class="eyebrow"><span class="dot"></span>North South University</span>

        <h1 class="mt-4">Teach one skill.<br>
          Learn <span class="ink-swap">another</span>.<br>
          Pay nothing.</h1>

        <p class="lead mt-4">Somebody on campus already knows the thing you are
          stuck on, and wants something you can teach. SkillSwap finds that pair,
          books the session, and keeps the record.</p>

        <form class="ask mt-4" role="search" action="search.php" method="get">
          <label for="askInput" class="visually-hidden">What do you want to learn?</label>
          <input id="askInput" name="skill" type="search" autocomplete="off"
                 placeholder="What do you want to learn?">
          <button type="button" class="ask-dice" id="dice"
                  data-skills="<?= e(json_encode($diceSkills, JSON_UNESCAPED_UNICODE)) ?>"
                  title="Surprise me with a skill" aria-label="Fill in a random skill">
            <i class="bi bi-dice-5"></i></button>
          <button type="submit" class="ask-go">Find a teacher</button>
        </form>

        <div class="ask-suggest">
          <span class="small-label align-self-center me-1">Popular</span>
          <?php foreach (array_slice($demand, 0, 4) as $d): ?>
            <a class="chip" href="search.php?skill=<?= urlencode($d['skill_name']) ?>"><?= e($d['skill_name']) ?></a>
          <?php endforeach; ?>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
          <a href="dashboard.php" class="btn btn-brand btn-pill btn-lg-cta">
            Open the demo <i class="bi bi-arrow-right ms-1"></i></a>
          <a href="register.php" class="btn btn-outline-brand btn-pill btn-lg-cta">Create an account</a>
        </div>

        <a class="scroll-cue mt-4 d-none d-lg-inline-flex" href="#how">
          <span class="track"></span>
          <span>Scroll to see how a trade works</span>
        </a>
      </div>

      <!-- signature element: a real exchange, straight out of MySQL -->
      <div class="col-lg-6 rise rise-2">
        <div class="panel p-3 p-sm-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="small-label">Live record</span>
            <?= pill('Completed') ?>
          </div>

          <?php if ($demo): ?>
            <?= swap_card(
                  'Teaches', $demo['teach_name'],
                  $demo['sender_name'] . ' · ' . $demo['sender_dept'],
                  'In return', $demo['learn_name'],
                  $demo['receiver_name'] . ' · ' . $demo['receiver_dept'],
                  true) ?>

            <div class="d-flex flex-wrap gap-3 mt-3 pt-3 border-top"
                 style="border-color:var(--line)!important">
              <div>
                <div class="small-label">Opened</div>
                <div class="fw-semibold" style="font-size:13.5px"><?= e(fmt_date($demo['created_at'])) ?></div>
              </div>
              <div>
                <div class="small-label">Sessions</div>
                <div class="fw-semibold" style="font-size:13.5px">
                  <?= (int) $demo['session_count'] ?> &middot; <?= (int) $demo['minutes'] ?> min</div>
              </div>
              <div>
                <div class="small-label">Rated</div>
                <div class="fw-semibold" style="font-size:13.5px">
                  <?= $demo['avg_rating'] !== null ? e($demo['avg_rating']) . ' / 5' : 'not yet' ?></div>
              </div>
            </div>
          <?php else: ?>
            <?= empty_state('inbox', 'No completed exchange in the database yet.') ?>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</header>

<!-- =============================== marquee =============================== -->
<div class="marquee" aria-label="Skills currently being taught on campus">
  <div class="marquee-track">
    <?php for ($copy = 0; $copy < 2; $copy++): ?>
      <span class="d-flex gap-2"<?= $copy ? ' aria-hidden="true"' : '' ?>>
        <?php foreach ($taught as $t): ?>
          <a class="marquee-item" href="search.php?skill=<?= urlencode($t['skill_name']) ?>">
            <?= e($t['skill_name']) ?> <span class="n"><?= (int) $t['teachers'] ?></span></a>
        <?php endforeach; ?>
      </span>
    <?php endfor; ?>
  </div>
</div>

<!-- ============================= proof band ============================= -->
<section class="proof-band">
  <div class="container">
    <div class="row g-0">
      <div class="col-6 col-md">
        <div class="proof">
          <div class="proof-num" data-count="<?= $stats['students'] ?>"><?= $stats['students'] ?></div>
          <div class="proof-label">Students</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="proof">
          <div class="proof-num" data-count="<?= $stats['skills'] ?>"><?= $stats['skills'] ?></div>
          <div class="proof-label">Skills listed</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="proof">
          <div class="proof-num" data-count="<?= $stats['completed'] ?>"><?= $stats['completed'] ?></div>
          <div class="proof-label">Exchanges done</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="proof">
          <div class="proof-num" data-count="<?= (int) round($minutes / 60) ?>"><?= (int) round($minutes / 60) ?></div>
          <div class="proof-label">Hours traded</div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="proof">
          <div class="proof-num"><?= e($stats['avg'] ?? '—') ?></div>
          <div class="proof-label">Average rating</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================ how it works ============================ -->
<section id="how" class="py-5 my-lg-4">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <div class="rail-sticky">
          <span class="small-label">The exchange</span>
          <h2 class="section-title mt-2 reveal">Four steps, one status column</h2>
          <p class="section-lede mt-3">Every trade walks the same path, which is
            why a single <code>status</code> field on
            <code>exchangerequests</code> can describe the whole life cycle.</p>
          <a href="search.php" class="btn btn-outline-brand btn-sm mt-2">
            Browse students <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="step reveal">
          <div class="step-index">01 / Pending</div>
          <div>
            <h3>List what you can teach and what you want</h3>
            <p>Each skill is tagged Teach or Learn with a level from Beginner to
              Expert. Those two lists are the whole matching engine &mdash; a
              self-join on <code>userskills</code> finds people whose lists are
              the mirror of yours.</p>
          </div>
        </div>

        <div class="step reveal">
          <div class="step-index">02 / Accepted</div>
          <div>
            <h3>Send a trade, get an answer</h3>
            <p>A request names both halves at once: the skill you give and the
              skill you get. Your partner accepts or declines, and nothing can be
              scheduled until they do.</p>
          </div>
        </div>

        <div class="step reveal">
          <div class="step-index">03 / Scheduled</div>
          <div>
            <h3>Book the session</h3>
            <p>Pick a date, a time and a length. Online sessions carry a meeting
              link; offline ones carry a spot on campus. A unique key on the
              slot stops the same pair from double booking.</p>
          </div>
        </div>

        <div class="step reveal">
          <div class="step-index">04 / Completed</div>
          <div>
            <h3>Rate each other</h3>
            <p>Once a session is marked complete, both partners may leave one
              rating out of five. Averages are derived from those rows, never
              stored on the profile.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================== features ============================== -->
<section class="py-5 bg-white border-top border-bottom" style="border-color:var(--line)!important">
  <div class="container">
    <div class="row align-items-end g-3 mb-4">
      <div class="col-lg-7">
        <span class="small-label">What you get</span>
        <h2 class="section-title mt-2 reveal">Built around the trade, not a marketplace</h2>
      </div>
      <div class="col-lg-5">
        <p class="section-lede mb-0">No wallet, no checkout, no listings that sit
          unanswered. Every screen is about two students and one agreement.</p>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-6 col-lg-4">
        <div class="feature reveal tilt">
          <div class="feature-glyph"><i class="bi bi-arrow-left-right"></i></div>
          <h3>Two-way matching</h3>
          <p>The dashboard only suggests people who teach something on your learn
            list and want something on your teach list. Those requests get
            accepted far more often.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature reveal tilt">
          <div class="feature-glyph amber"><i class="bi bi-sliders"></i></div>
          <h3>Search that narrows</h3>
          <p>Filter by skill, category, department, experience level and name at
            once, then sort by best match or highest rated.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature reveal tilt">
          <div class="feature-glyph"><i class="bi bi-calendar-event"></i></div>
          <h3>Sessions with detail</h3>
          <p>Date, time, duration, online or offline, and where. Reschedule,
            complete or cancel from the same card.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature reveal tilt">
          <div class="feature-glyph amber"><i class="bi bi-star"></i></div>
          <h3>Ratings that mean something</h3>
          <p>A review can only follow a completed session, and each partner may
            write exactly one. No drive-by scores.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature reveal tilt">
          <div class="feature-glyph ink"><i class="bi bi-shield-lock"></i></div>
          <h3>Admin console</h3>
          <p>Manage students and the skill catalogue, moderate reviews, and watch
            referential rules block the deletes they should.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature reveal tilt">
          <div class="feature-glyph ink"><i class="bi bi-database-check"></i></div>
          <h3>Raw SQL throughout</h3>
          <p>Six normalised tables, parameterised queries, no ORM. Every button
            names the statement it stands for.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================== statement ============================== -->
<section class="py-5 my-lg-3">
  <div class="container">
    <div class="statement reveal">
      <div class="row align-items-center g-4">
        <div class="col-lg-7">
          <span class="small-label" style="color:var(--brand-400)">The rule</span>
          <h2 class="mt-2">No money moves. That is the whole point.</h2>
          <p class="mt-3 mb-0">Tutoring costs money most students do not have, and
            plenty of them are already good at something worth trading. SkillSwap
            settles in skills, so the price of learning is teaching.</p>
        </div>
        <div class="col-lg-5">
          <div class="d-grid gap-2">
            <a href="register.php" class="btn btn-light btn-pill btn-lg-cta">
              Create an account</a>
            <a href="dashboard.php" class="btn btn-outline-light btn-pill btn-lg-cta">
              Look around first</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================== catalogue ============================== -->
<section id="catalogue" class="py-5 bg-white border-top border-bottom" style="border-color:var(--line)!important">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
      <div>
        <span class="small-label">Most requested</span>
        <h2 class="section-title mt-2 mb-0 reveal">What students want to learn</h2>
      </div>
      <a href="search.php" class="btn btn-sm btn-quiet">
        All <?= $stats['skills'] ?> skills <i class="bi bi-arrow-right ms-1"></i></a>
    </div>

    <div class="row g-3">
      <?php foreach ($demand as $d): ?>
        <div class="col-md-6 col-lg-4">
          <a class="feature d-block text-reset text-decoration-none"
             href="search.php?skill=<?= urlencode($d['skill_name']) ?>">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <h3 class="mb-0"><?= e($d['skill_name']) ?></h3>
              <span class="pill pill-online"><?= e($d['category']) ?></span>
            </div>
            <p class="mb-3"><?= e($d['description']) ?></p>
            <div class="d-flex gap-3 small" style="font-family:var(--data);font-size:11.5px">
              <span style="color:var(--brand-600)"><?= (int) $d['teachers'] ?> can teach</span>
              <span style="color:var(--gold-700)"><?= (int) $d['learners'] ?> want to learn</span>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-5 pt-4 border-top" style="border-color:var(--line)!important">
      <div class="rule-label"><span class="small-label">Departments taking part</span></div>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($departments as $dep): ?>
          <a class="chip" href="search.php?department=<?= urlencode($dep['department']) ?>">
            <?= e($dep['department']) ?> <span class="lvl"><?= (int) $dep['n'] ?></span></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- =============================== the build =============================== -->
<section id="build" class="py-5 my-lg-3">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-5">
        <span class="small-label">Submission</span>
        <h2 class="section-title mt-2 reveal">CSE311L Database Systems Lab</h2>
        <p class="section-lede mt-3">Six normalised tables in MySQL with primary
          keys, foreign keys, unique keys, CHECK constraints, indexes and three
          views, driven end to end by hand-written parameterised SQL.</p>
        <p class="small text-muted-2 mt-3 mb-0">This is the PHP build, so the
          counts on the right were read out of the <code>skillexchange</code>
          database when the page was requested. Reload it after a write and they
          move.</p>
      </div>

      <div class="col-lg-7">
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="panel h-100">
              <div class="panel-head"><h3><i class="bi bi-hdd-stack me-2"></i>Tables</h3></div>
              <div class="panel-body tight py-3">
                <table class="table table-sm table-borderless small mb-0">
                  <?php foreach ([
                      ['users',            $stats['students']],
                      ['skills',           $stats['skills']],
                      ['userskills',       $userskills],
                      ['exchangerequests', $stats['exchanges']],
                      ['sessions',         $stats['sessions']],
                      ['reviews',          $stats['reviews']],
                  ] as [$table, $n]): ?>
                    <tr><td class="ps-0"><code><?= $table ?></code></td>
                        <td class="text-end pe-0 fw-semibold"
                            style="font-family:var(--data)"><?= (int) $n ?></td></tr>
                  <?php endforeach; ?>
                </table>
              </div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="panel h-100">
              <div class="panel-head"><h3><i class="bi bi-braces me-2"></i>Stack</h3></div>
              <div class="panel-body tight py-3">
                <ul class="list-unstyled small mb-0 d-grid gap-2">
                  <li><i class="bi bi-check2 me-2" style="color:var(--brand)"></i>PHP 8 on Apache</li>
                  <li><i class="bi bi-check2 me-2" style="color:var(--brand)"></i>MySQL on XAMPP</li>
                  <li><i class="bi bi-check2 me-2" style="color:var(--brand)"></i>mysqli prepared statements, raw SQL</li>
                  <li><i class="bi bi-check2 me-2" style="color:var(--brand)"></i>PBKDF2 password hashing</li>
                  <li><i class="bi bi-check2 me-2" style="color:var(--brand)"></i>HTML5, CSS3, Bootstrap 5.3</li>
                  <li><i class="bi bi-check2 me-2" style="color:var(--brand)"></i>Vanilla JavaScript, no framework</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================== reviews ============================== -->
<?php if (count($voices) >= 4): ?>
<section class="py-5" id="voices">
  <div class="container">
    <div class="row g-4 align-items-end mb-4">
      <div class="col-lg-7">
        <span class="small-label">From finished sessions</span>
        <h2 class="section-title mt-2 reveal">What partners said afterwards</h2>
      </div>
      <div class="col-lg-5">
        <p class="section-lede mb-0">Every card is a row in the
          <code>reviews</code> table, written by one student about another once a
          session was marked complete. Hover to hold the slider still.</p>
      </div>
    </div>
  </div>

  <div class="review-rail mb-3" aria-label="Reviews from completed sessions">
    <div class="review-track">
      <span class="d-flex gap-3"><?php foreach ($railA as $r) { echo voice_card($r); } ?></span>
      <span class="d-flex gap-3" aria-hidden="true"><?php foreach ($railA as $r) { echo voice_card($r); } ?></span>
    </div>
  </div>
  <div class="review-rail" aria-hidden="true">
    <div class="review-track back">
      <span class="d-flex gap-3"><?php foreach ($railB as $r) { echo voice_card($r); } ?></span>
      <span class="d-flex gap-3"><?php foreach ($railB as $r) { echo voice_card($r); } ?></span>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================================= FAQ ================================= -->
<section id="faq" class="py-5 bg-white border-top" style="border-color:var(--line)!important">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <span class="small-label">Questions</span>
        <h2 class="section-title mt-2 reveal">How the exchange works</h2>
      </div>
      <div class="col-lg-8">
        <div class="accordion faq" id="faqList">

          <div class="accordion-item reveal">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                      data-bs-target="#q1">Does anybody pay for a session?</button>
            </h3>
            <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqList">
              <div class="accordion-body">No. There is no price field anywhere in
                the database. A request is only valid when both students name a
                skill, so the settlement is always skill for skill.</div>
            </div>
          </div>

          <div class="accordion-item reveal">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                      data-bs-target="#q2">What makes a match two-way?</button>
            </h3>
            <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqList">
              <div class="accordion-body">They teach something on your learn list
                and want something on your teach list. You can still propose a
                trade to anyone, but the dashboard leads with mutual matches
                because those get accepted.</div>
            </div>
          </div>

          <div class="accordion-item reveal">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                      data-bs-target="#q3">Can I meet on campus instead of online?</button>
            </h3>
            <div id="q3" class="accordion-collapse collapse" data-bs-parent="#faqList">
              <div class="accordion-body">Yes. A session is either Online with a
                meeting link or Offline with a place, and the form asks for
                whichever one applies.</div>
            </div>
          </div>

          <div class="accordion-item reveal">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                      data-bs-target="#q4">Who can review whom?</button>
            </h3>
            <div id="q4" class="accordion-collapse collapse" data-bs-parent="#faqList">
              <div class="accordion-body">Only the two people in a completed
                session, and only once each. A unique key on session and reviewer
                enforces it, and a check constraint keeps ratings between one and
                five.</div>
            </div>
          </div>

          <div class="accordion-item reveal">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                      data-bs-target="#q5">Which departments can join?</button>
            </h3>
            <div id="q5" class="accordion-collapse collapse" data-bs-parent="#faqList">
              <div class="accordion-body">All <?= count($departments) ?> listed on
                this page. Skills travel across departments more than inside them,
                which is the point of opening it beyond one course.</div>
            </div>
          </div>

          <div class="accordion-item reveal">
            <h3 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                      data-bs-target="#q6">Are the buttons on this build real?</button>
            </h3>
            <div id="q6" class="accordion-collapse collapse" data-bs-parent="#faqList">
              <div class="accordion-body">Yes. Every button runs an INSERT, an
                UPDATE or a DELETE against MySQL and names the statement it
                stands for, and the rows stay there after the browser is closed.</div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================================ footer ================================ -->
<footer class="site-footer pt-5 pb-4">
  <div class="container">
    <div class="row g-4 pb-4">
      <div class="col-lg-4">
        <a class="brand-mark" style="color:var(--fg-0)" href="index.php">
          <span class="brand-glyph" style="background:transparent;border:1px solid var(--line-strong);color:var(--fg-2)">
            <i class="bi bi-arrow-left-right"></i></span>
          SkillSwap <span class="brand-tag">NSU</span>
        </a>
        <p class="mt-3 mb-0" style="max-width:22rem">A student skill exchange for
          North South University. Built as a Database Systems Lab project.</p>
      </div>

      <div class="col-6 col-lg-2">
        <div class="col-title">Product</div>
        <ul class="footer-list">
          <li><a href="dashboard.php">Dashboard</a></li>
          <li><a href="search.php">Find a partner</a></li>
          <li><a href="requests.php">Requests</a></li>
          <li><a href="sessions.php">Sessions</a></li>
          <li><a href="reviews.php">Reviews</a></li>
        </ul>
      </div>

      <div class="col-6 col-lg-2">
        <div class="col-title">Account</div>
        <ul class="footer-list">
          <li><a href="login.php">Sign in</a></li>
          <li><a href="register.php">Create account</a></li>
          <li><a href="edit-profile.php">Edit profile</a></li>
          <li><a href="admin.php">Admin console</a></li>
        </ul>
      </div>

      <div class="col-6 col-lg-2">
        <div class="col-title">Project</div>
        <ul class="footer-list">
          <li><a href="#how">How it works</a></li>
          <li><a href="#build">The build</a></li>
          <li><a href="#faq">FAQ</a></li>
        </ul>
      </div>

      <div class="col-6 col-lg-2">
        <div class="col-title">Course</div>
        <ul class="footer-list">
          <li>CSE311L</li>
          <li>Database Systems Lab</li>
          <li>North South University</li>
        </ul>
      </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between gap-2 pt-4 border-top"
         style="border-color:var(--line)!important">
      <span>&copy; <span data-year><?= date('Y') ?></span> SkillSwap NSU project team</span>
      <span class="small-label" style="color:var(--ink-100)">Seeded with
        <?= $stats['students'] ?> students</span>
    </div>
  </div>
</footer>

<script src="static/vendor/bootstrap.bundle.min.js"></script>
<script src="static/js/app.js"></script>
</body>
</html>
