<?php
/* =============================================================
   sessions.php — the meetings booked against accepted requests.

   The three tabs are one SELECT with a different value bound to
   the same status placeholder, and the counters above them are a
   single GROUP BY over the same join, so the tab bar and the
   numbers can never disagree.
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';
require_login();

$me   = me_id();
$tabs = ['Scheduled' => 'Upcoming', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];

$status = q('status', 'Scheduled');
if (!isset($tabs[$status])) {
    $status = 'Scheduled';
}

$list  = sessions_of($me, $status);
$book  = (int) q('book', 0);
$open  = $book > 0;

/* One pass over this student's sessions gives every tile on the
   strip: how many sit in each status, how long they ran and how
   many of them happened over a link rather than on campus. */
$tally = ['Scheduled' => 0, 'Completed' => 0, 'Cancelled' => 0];
$mins  = 0;
$onl   = 0;
$off   = 0;
foreach (rows(
    'SELECT     se.status,
                COUNT(*)                        AS n,
                SUM(se.duration)                AS mins,
                SUM(se.mode = \'Online\')       AS online,
                SUM(se.mode = \'Offline\')      AS offline
     FROM       sessions se
     INNER JOIN exchangerequests er ON er.request_id = se.request_id
     WHERE      er.sender_id = ? OR er.receiver_id = ?
     GROUP BY   se.status', [$me, $me]) as $t) {
    $tally[$t['status']] = (int) $t['n'];
    $onl += (int) $t['online'];
    $off += (int) $t['offline'];
    if ($t['status'] === 'Completed') {
        $mins = (int) $t['mins'];
    }
}
$hours = round($mins / 60, 1);

/* Which completed sessions already carry my review — one SELECT
   instead of one per card. */
$reviewed = array_flip(array_column(
    rows('SELECT session_id FROM reviews WHERE reviewer_id = ?', [$me]), 'session_id'));

$openReqs = bookable_requests($me);

page_open('Sessions', 'sessions');
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
  <div>
    <h1 style="font-size:1.4rem;margin-bottom:2px">Sessions</h1>
    <p class="text-muted-2 small mb-0">A session belongs to one accepted request.
      Online sessions carry a link, offline ones a spot on campus.</p>
  </div>
  <button class="btn btn-sm btn-brand" type="button" data-bs-toggle="collapse"
          data-bs-target="#bookPanel" aria-expanded="<?= $open ? 'true' : 'false' ?>"
          aria-controls="bookPanel">
    <i class="bi bi-calendar-plus me-1"></i>Schedule a session</button>
</div>

<div class="row g-2 mb-3">
  <?php foreach ($tabs as $st => $label): ?>
    <div class="col-6 col-md">
      <a class="stat w-100 text-start border-0 p-2 px-3 d-block text-decoration-none"
         href="<?= e(url_with(['status' => $st, 'book' => null], 'sessions.php')) ?>">
        <div class="stat-num" style="font-size:1.25rem"><?= (int) $tally[$st] ?></div>
        <div class="stat-label"><?= $label ?></div>
      </a>
    </div>
  <?php endforeach; ?>
  <div class="col-6 col-md">
    <div class="stat p-2 px-3">
      <div class="stat-num" style="font-size:1.25rem"><?= e($hours) ?> hrs</div>
      <div class="stat-label">Time exchanged</div>
    </div>
  </div>
  <div class="col-6 col-md">
    <div class="stat p-2 px-3">
      <div class="stat-num" style="font-size:1.25rem"><?= $onl ?> / <?= $off ?></div>
      <div class="stat-label">Online / Offline</div>
    </div>
  </div>
</div>

<!-- ===================== schedule panel ===================== -->
<section class="panel collapse <?= $open ? 'show' : '' ?>" id="bookPanel">
  <div class="panel-head">
    <h2><i class="bi bi-calendar-plus me-2" style="color:var(--brand)"></i>Schedule a session</h2>
  </div>
  <div class="panel-body">
    <?php if (!$openReqs): ?>
      <?= empty_state('calendar-plus',
            'You have no accepted request to book against yet.',
            '<a class="btn btn-sm btn-brand" href="requests.php">Open requests</a>') ?>
    <?php else: ?>
      <form method="post" action="actions.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="session.create">
        <input type="hidden" name="back" value="<?= e(current_url()) ?>">

        <div class="mb-3">
          <label class="form-label" for="bReq">Accepted exchange</label>
          <select class="form-select" id="bReq" name="request_id" required>
            <?php foreach ($openReqs as $r):
              $rid   = (int) $r['request_id'];
              $other = (int) $r['sender_id'] === $me ? $r['receiver_name'] : $r['sender_name'];
            ?>
              <option value="<?= $rid ?>" <?= $book === $rid ? 'selected' : '' ?>>
                #<?= $rid ?> &mdash; with <?= e($other) ?>
                (<?= e($r['teach_name']) ?> &harr; <?= e($r['learn_name']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Only accepted requests can be booked &mdash;
            <code>sessions.request_id</code> is a foreign key.</div>
        </div>

        <div class="row g-3">
          <div class="col-6">
            <label class="form-label" for="bDate">Date</label>
            <input type="date" class="form-control" id="bDate" name="session_date"
                   min="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label" for="bTime">Start time</label>
            <input type="time" class="form-control" id="bTime" name="session_time"
                   value="16:00" required>
          </div>
          <div class="col-6">
            <label class="form-label" for="bDur">Duration (minutes)</label>
            <select class="form-select" id="bDur" name="duration">
              <?php foreach ([30, 45, 60, 90, 120] as $d): ?>
                <option <?= $d === 60 ? 'selected' : '' ?>><?= $d ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text"><code>CHECK (duration BETWEEN 15 AND 480)</code></div>
          </div>
          <div class="col-6">
            <label class="form-label" for="bMode">Mode</label>
            <select class="form-select" id="bMode" name="mode" data-mode-switch>
              <option>Online</option><option>Offline</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label" for="bWhere" data-where-label>Meeting link</label>
            <input class="form-control" id="bWhere" name="where" data-where
                   placeholder="https://meet.example.com/abc-defg">
            <div class="form-text">An online session stores this in
              <code>meeting_link</code>, an offline one in <code>location</code>.</div>
          </div>
        </div>

        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-sm btn-brand" type="submit">
            <i class="bi bi-calendar-plus me-1"></i>Book the session</button>
          <a class="btn btn-sm btn-quiet" href="sessions.php">Cancel</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>

<!-- ===================== the list ===================== -->
<section class="panel">
  <div class="panel-head flex-wrap">
    <ul class="nav nav-pills">
      <?php foreach ($tabs as $st => $label): ?>
        <li class="nav-item">
          <a class="nav-link btn-sm <?= $status === $st ? 'active' : '' ?>"
             href="<?= e(url_with(['status' => $st, 'book' => null], 'sessions.php')) ?>"><?= $label ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
    <span class="small text-muted-2"><?= count($list) ?>
      session<?= count($list) === 1 ? '' : 's' ?></span>
  </div>

  <?php if (!$list): ?>
    <div class="panel-body">
      <?php
      $msg = ['Scheduled' => 'Nothing booked. Accept a request first, then pick a time.',
              'Completed' => 'No completed session yet.',
              'Cancelled' => 'Nothing cancelled. Good sign.'][$status];
      echo empty_state('calendar-x', $msg,
            $status === 'Scheduled'
              ? '<a class="btn btn-sm btn-brand" href="requests.php">Open requests</a>' : '');
      ?>
    </div>
  <?php else: ?>
    <div class="panel-body d-grid gap-3">
      <?php foreach ($list as $s):
        $sid     = (int) $s['session_id'];
        $day     = strtotime($s['session_date']);
        $sender  = (int) $s['sender_id'] === $me;
        /* teach_skill always belongs to the sender */
        $iTeach  = $sender ? $s['teach_name'] : $s['learn_name'];
        $iLearn  = $sender ? $s['learn_name'] : $s['teach_name'];
        $pid     = (int) $s['partner_id'];
        $online  = $s['mode'] === 'Online';
        $where   = $online ? $s['meeting_link'] : $s['location'];
      ?>
      <article class="panel p-3 hover-lift">
        <div class="d-flex gap-3">
          <div class="text-center flex-shrink-0" style="width:56px">
            <div class="small-label mb-0"><?= date('M', $day) ?></div>
            <div style="font-family:var(--display);font-weight:800;font-size:1.6rem;color:var(--brand-700);line-height:1"><?= date('j', $day) ?></div>
            <div class="small text-muted-2" style="font-size:11px"><?= date('Y', $day) ?></div>
          </div>

          <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap justify-content-between gap-2">
              <div class="min-w-0">
                <div class="fw-semibold"><?= e($iTeach) ?>
                  <span class="text-muted-2 fw-normal">&harr;</span>
                  <?= e($iLearn) ?></div>
                <div class="small text-muted-2">with
                  <a href="profile.php?id=<?= $pid ?>" class="text-reset fw-semibold"><?= e($s['partner_name']) ?></a>
                  &middot; <?= e($s['partner_dept']) ?></div>
              </div>
              <div class="d-flex gap-1 align-items-start"><?= mode_pill($s['mode']) ?><?= pill($s['status']) ?></div>
            </div>

            <div class="small text-muted-2 mt-2 d-flex flex-wrap gap-3">
              <span><i class="bi bi-clock me-1"></i><?= e(fmt_time($s['session_time'])) ?>
                &middot; <?= (int) $s['duration'] ?> min</span>
              <span><i class="bi bi-<?= $online ? 'camera-video' : 'geo-alt' ?> me-1"></i>
                <?php if ($online && $where): ?>
                  <a href="<?= e($where) ?>" target="_blank" rel="noopener"><?= e($where) ?></a>
                <?php else: ?>
                  <?= e($where ?: 'Not given') ?>
                <?php endif; ?></span>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
              <?php
              if ($s['status'] === 'Scheduled') {
                  echo action_button('session.status',
                        ['session_id' => $sid, 'status' => 'Completed'],
                        'Mark completed', 'check-lg', 'btn-brand');
                  echo action_button('session.status',
                        ['session_id' => $sid, 'status' => 'Cancelled'],
                        'Cancel', 'x-lg', 'btn-quiet',
                        'Cancel this session?');

              } elseif ($s['status'] === 'Completed') {
                  if (isset($reviewed[$sid])) {
                      echo '<span class="small text-muted-2 align-self-center">'
                         . '<i class="bi bi-check2-circle me-1" style="color:var(--ok)"></i>'
                         . 'You reviewed this session</span>';
                  } else {
                      echo '<a class="btn btn-sm btn-brand" href="reviews.php#session-' . $sid . '">'
                         . '<i class="bi bi-star me-1"></i>Leave a review</a>';
                  }

              } else {
                  echo '<span class="small text-muted-2 align-self-center">This session was cancelled.</span>';
              }
              ?>
            </div>

            <?php if ($s['status'] === 'Scheduled'): ?>
              <!-- moving a booking is one UPDATE against two columns -->
              <form method="post" action="actions.php"
                    class="d-flex flex-wrap align-items-end gap-2 mt-3 pt-3 border-top"
                    style="border-color:var(--line)!important">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="session.edit">
                <input type="hidden" name="back" value="<?= e(current_url()) ?>">
                <input type="hidden" name="session_id" value="<?= $sid ?>">
                <div>
                  <label class="small-label mb-0" for="d<?= $sid ?>">New date</label>
                  <input type="date" class="form-control form-control-sm" id="d<?= $sid ?>"
                         name="session_date" value="<?= e($s['session_date']) ?>" required>
                </div>
                <div>
                  <label class="small-label mb-0" for="t<?= $sid ?>">New time</label>
                  <input type="time" class="form-control form-control-sm" id="t<?= $sid ?>"
                         name="session_time" value="<?= e(substr((string) $s['session_time'], 0, 5)) ?>" required>
                </div>
                <button class="btn btn-sm btn-quiet" type="submit">
                  <i class="bi bi-pencil me-1"></i>Reschedule</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php page_close(); ?>
