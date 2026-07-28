<?php
/* =============================================================
   dashboard.php — the signed-in student's home.

   The page is one screen made of eight small questions put to the
   database, and the interesting one is matches_for(): a join of
   userskills against itself that finds the students who teach
   something on my learn list and want something on my teach list.
   Everything else here is a count, a LIMIT or an ORDER BY that the
   static build used to do in JavaScript after loading every row.
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';
require_login();

$me   = me_id();
$user = me();

$teach = skills_of($me, 'Teach');
$learn = skills_of($me, 'Learn');
$rate  = rating_of($me);
$count = request_status_counts($me);

/* The two-way matches, then thinned down to three partners: one row
   comes back per skill pair, and a partner already in an open
   exchange with me is dropped the way the static build dropped them. */
$matches = [];
foreach (matches_for($me, 24) as $m) {
    $pid = (int) $m['user_id'];
    if (isset($matches[$pid]) || existing_request($me, $pid)) {
        continue;
    }
    $matches[$pid] = $m;
    if (count($matches) === 3) {
        break;
    }
}
$matches = array_values($matches);

$pending = requests_of($me, 'received', 'Pending');

/* The next three scheduled sessions. requests.php reads the newest
   first; a diary has to read the other way, so the ORDER BY and the
   LIMIT are written here rather than reversed in PHP afterwards. */
$next = rows(
    'SELECT     se.session_id, se.session_date, se.session_time, se.duration,
                se.mode, se.location, se.meeting_link, se.status,
                CASE WHEN er.sender_id = ? THEN ts.skill_name ELSE ls.skill_name END AS skill_name,
                CASE WHEN er.sender_id = ? THEN rcv.name      ELSE snd.name       END AS partner_name
     FROM       sessions se
     INNER JOIN exchangerequests er ON er.request_id = se.request_id
     INNER JOIN users  snd ON snd.user_id = er.sender_id
     INNER JOIN users  rcv ON rcv.user_id = er.receiver_id
     INNER JOIN skills ts  ON ts.skill_id = er.teach_skill
     INNER JOIN skills ls  ON ls.skill_id = er.learn_skill
     WHERE      se.status = \'Scheduled\'
       AND      (er.sender_id = ? OR er.receiver_id = ?)
     ORDER BY   se.session_date, se.session_time
     LIMIT      ?',
    [$me, $me, $me, $me, 3]);

$recent = array_slice(reviews_received($me), 0, 3);

/* Top rated, minus my own row — the panel is for finding other people. */
$best = [];
foreach (top_rated(6) as $t) {
    if ((int) $t['user_id'] !== $me) {
        $best[] = $t;
    }
}
$best = array_slice($best, 0, 5);

/* Skills in demand: one GROUP BY over the Learn half of userskills. */
$demand = rows(
    'SELECT     s.skill_name, COUNT(*) AS n
     FROM       userskills us
     INNER JOIN skills s ON s.skill_id = us.skill_id
     WHERE      us.skill_type = \'Learn\'
     GROUP BY   s.skill_id, s.skill_name
     ORDER BY   n DESC, s.skill_name
     LIMIT      ?', [8]);

$first = explode(' ', trim($user['name']))[0];
$hello = (int) date('G') < 12 ? 'Good morning' : 'Good afternoon';

page_open('Home', 'dashboard');
?>

<div class="row g-4">

  <!-- ---------------------- main column ---------------------- -->
  <div class="col-lg-8">

    <div class="mb-3">
      <h1 style="font-size:1.4rem"><?= $hello ?>, <?= e($first) ?></h1>
      <p class="text-muted-2 mb-0">You are teaching <strong><?= count($teach) ?></strong>
        skill<?= count($teach) === 1 ? '' : 's' ?> and learning
        <strong><?= count($learn) ?></strong>.</p>
    </div>

    <div class="row g-2 mb-4">
      <?php foreach ([
        ['inbox',          pending_received($me),  'Requests to answer',  'requests.php'],
        ['calendar-week',  upcoming_count($me),    'Upcoming sessions',   'sessions.php'],
        ['patch-check',    $count['Completed'],    'Completed exchanges', 'requests.php'],
        ['star-fill',      $rate['avg'] ?? '—',    'Your rating',         'reviews.php'],
      ] as [$icon, $n, $label, $href]): ?>
        <div class="col-6 col-xl-3">
          <a class="stat d-block text-reset text-decoration-none hover-lift" href="<?= $href ?>">
            <i class="bi bi-<?= $icon ?> mb-1" style="color:var(--brand)"></i>
            <div class="stat-num" style="font-size:1.35rem"><?= e($n) ?></div>
            <div class="stat-label"><?= $label ?></div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- suggested matches: the core of the product -->
    <section class="panel mb-4">
      <div class="panel-head">
        <h2><i class="bi bi-arrow-left-right me-2" style="color:var(--brand)"></i>Matches for you</h2>
        <a href="search.php" class="small">Browse everyone</a>
      </div>
      <div class="panel-body">
        <?php if (!$matches): ?>
          <?= empty_state('search',
                'No two-way match left right now. Adding another skill you want to learn widens the pool.',
                '<a class="btn btn-sm btn-brand" href="edit-profile.php">Add a skill</a>') ?>
        <?php else: ?>
          <?php foreach ($matches as $i => $m):
            $pid  = (int) $m['user_id'];
            $r    = rating_of($pid);
            $last = $i === count($matches) - 1;
          ?>
          <article class="<?= $last ? 'mb-0' : 'mb-3 pb-3 border-bottom' ?>"
                   style="<?= $last ? '' : 'border-color:var(--line)!important' ?>">
            <div class="d-flex gap-2 align-items-center mb-2">
              <?= avatar(['user_id' => $pid, 'name' => $m['name']], 40) ?>
              <div class="lh-sm flex-grow-1 min-w-0">
                <a href="profile.php?id=<?= $pid ?>" class="fw-semibold text-reset"><?= e($m['name']) ?></a>
                <div class="small text-muted-2"><?= e($m['department']) ?></div>
              </div>
              <div class="text-end small">
                <?= stars($r['avg']) ?>
                <div class="text-muted-2" style="font-size:11.5px">
                  <?= $r['count'] ? $r['count'] . ' review' . ($r['count'] === 1 ? '' : 's') : 'New here' ?></div>
              </div>
            </div>

            <?= swap_card('You teach', $m['i_teach'],    'They listed it as a goal',
                          'You learn', $m['they_teach'], $m['they_level'] . ' level') ?>

            <div class="d-flex gap-2 mt-2">
              <?= action_button('request.create',
                    ['receiver_id' => $pid,
                     'teach_skill' => (int) $m['i_teach_id'],
                     'learn_skill' => (int) $m['they_teach_id']],
                    'Send request', 'send', 'btn-brand') ?>
              <a class="btn btn-sm btn-quiet" href="profile.php?id=<?= $pid ?>">View profile</a>
            </div>
          </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <!-- requests waiting on me -->
    <section class="panel mb-4">
      <div class="panel-head">
        <h2><i class="bi bi-inbox me-2" style="color:var(--brand)"></i>Waiting for your answer</h2>
        <a href="requests.php" class="small">All requests</a>
      </div>
      <div class="panel-body">
        <?php if (!$pending): ?>
          <?= empty_state('inbox', 'Nothing waiting on you right now.') ?>
        <?php else: ?>
          <?php foreach ($pending as $r):
            $sid = (int) $r['sender_id'];
            $rid = (int) $r['request_id'];
          ?>
          <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
            <div class="d-flex gap-2 align-items-center min-w-0">
              <?= avatar(['user_id' => $sid, 'name' => $r['sender_name']], 32) ?>
              <div class="lh-sm small">
                <a href="profile.php?id=<?= $sid ?>" class="fw-semibold text-reset"><?= e($r['sender_name']) ?></a>
                <div class="text-muted-2"><?= e(fmt_date($r['created_at'])) ?></div>
              </div>
            </div>
            <div class="d-flex gap-2">
              <?= action_button('request.status', ['request_id' => $rid, 'status' => 'Accepted'],
                    'Accept', '', 'btn-brand') ?>
              <?= action_button('request.status', ['request_id' => $rid, 'status' => 'Rejected'],
                    'Decline', '', 'btn-quiet') ?>
            </div>
            <div class="w-100">
              <?= swap_card('They teach', $r['teach_name'], $r['sender_dept'],
                            'You teach',  $r['learn_name'], 'Your listed skill') ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <!-- next session -->
    <section class="panel mb-4">
      <div class="panel-head">
        <h2><i class="bi bi-calendar-event me-2" style="color:var(--brand)"></i>Your next sessions</h2>
        <a href="sessions.php" class="small">All sessions</a>
      </div>
      <div class="panel-body">
        <?php if (!$next): ?>
          <?= empty_state('calendar-x',
                'No session booked. Accept a request, then pick a time that suits you both.',
                '<a class="btn btn-sm btn-brand" href="requests.php">See requests</a>') ?>
        <?php else: ?>
          <?php foreach ($next as $s): ?>
          <div class="d-flex gap-3 align-items-start mb-3">
            <div class="text-center flex-shrink-0" style="width:52px">
              <div class="small-label mb-0"><?= e(date('M', strtotime($s['session_date']))) ?></div>
              <div style="font-family:var(--display);font-weight:800;font-size:1.35rem;color:var(--brand-700);line-height:1">
                <?= (int) substr($s['session_date'], 8, 2) ?></div>
            </div>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold" style="font-size:.94rem"><?= e($s['skill_name']) ?>
                <span class="text-muted-2 fw-normal">with <?= e($s['partner_name']) ?></span></div>
              <div class="small text-muted-2">
                <?= e(fmt_time($s['session_time'])) ?> &middot; <?= (int) $s['duration'] ?> min &middot;
                <?= e($s['mode'] === 'Online' ? $s['meeting_link'] : $s['location']) ?>
              </div>
              <div class="mt-1 d-flex gap-1"><?= mode_pill($s['mode']) . pill($s['status']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <!-- reviews about me -->
    <section class="panel">
      <div class="panel-head">
        <h2><i class="bi bi-chat-quote me-2" style="color:var(--brand)"></i>What partners said</h2>
        <a href="reviews.php" class="small">All reviews</a>
      </div>
      <div class="panel-body">
        <?php if (!$recent): ?>
          <?= empty_state('chat-quote', 'No review yet. Finish a session and your partner can rate you.') ?>
        <?php else: ?>
          <?php foreach ($recent as $rv): $rid = (int) $rv['reviewer_id']; ?>
          <div class="d-flex gap-2 mb-3">
            <?= avatar(['user_id' => $rid, 'name' => $rv['reviewer_name']], 32) ?>
            <div class="min-w-0">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="profile.php?id=<?= $rid ?>" class="fw-semibold text-reset small"><?= e($rv['reviewer_name']) ?></a>
                <?= stars($rv['rating']) ?>
                <span class="small text-muted-2"><?= e(fmt_date($rv['created_at'])) ?></span>
              </div>
              <p class="small mb-0 text-muted-2"><?= e($rv['comment']) ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <!-- ---------------------- right rail ---------------------- -->
  <div class="col-lg-4">
    <div class="rail-sticky">

      <section class="panel">
        <div class="panel-head">
          <h3><i class="bi bi-list-check me-2"></i>Your skills</h3>
          <a href="edit-profile.php" class="small">Edit</a>
        </div>
        <div class="panel-body tight py-3">
          <?php foreach ([['I can teach', $teach, 'Teach'], ['I want to learn', $learn, 'Learn']]
                         as [$label, $list, $kind]): ?>
            <div class="small-label mb-2"><?= $label ?></div>
            <div class="d-flex flex-wrap gap-1 mb-3">
              <?php if (!$list): ?>
                <span class="small text-muted-2">Nothing listed yet</span>
              <?php else: ?>
                <?php foreach ($list as $s) { echo skill_chip($s, $kind); } ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h3><i class="bi bi-trophy me-2"></i>Top rated students</h3></div>
        <div class="panel-body tight py-2">
          <?php foreach ($best as $b): $bid = (int) $b['user_id']; ?>
            <a class="d-flex gap-2 align-items-center py-2 text-reset text-decoration-none"
               href="profile.php?id=<?= $bid ?>">
              <?= avatar(['user_id' => $bid, 'name' => $b['name']], 32) ?>
              <div class="lh-sm min-w-0 flex-grow-1">
                <div class="fw-semibold text-truncate" style="font-size:13px"><?= e($b['name']) ?></div>
                <div class="small text-muted-2 text-truncate" style="font-size:11.5px"><?= e($b['department']) ?></div>
              </div>
              <span class="small"><span class="rating-num"><?= e($b['avg_rating']) ?></span>
                <i class="bi bi-star-fill" style="color:var(--star);font-size:10px"></i></span>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h3><i class="bi bi-fire me-2"></i>Skills in demand</h3></div>
        <div class="panel-body tight py-3">
          <div class="d-flex flex-wrap gap-1">
            <?php foreach ($demand as $d): ?>
              <a class="chip" href="search.php?skill=<?= urlencode($d['skill_name']) ?>">
                <?= e($d['skill_name']) ?> <span class="lvl"><?= (int) $d['n'] ?></span></a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>

<?php page_close(); ?>
