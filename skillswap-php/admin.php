<?php
/* =============================================================
   admin.php — the whole database seen from above.

   Four listings share one page: students, the skill catalogue,
   every exchange request read straight out of the v_request_details
   view, and the reviews queue. The tab and both search boxes are
   GET parameters that become WHERE clauses inside admin_students(),
   admin_skills() and admin_requests(), so nothing is filtered in
   the browser. The delete buttons are here to show the referential
   actions the schema declares: removing a student cascades through
   four tables, while removing a skill an exchange request still
   points at is refused by ON DELETE RESTRICT.
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';
require_login();

$tab    = in_array(q('tab', 'users'), ['users', 'skills', 'requests', 'reviews'], true)
        ? q('tab', 'users') : 'users';
$userQ  = q('us', '');
$skillQ = q('ss', '');
$status = q('status', '');
$maxRat = in_array(q('max', '5'), ['2', '3', '5'], true) ? (int) q('max', '5') : 5;

$stats = platform_stats();
$me    = me_id();

page_open('Admin console', 'admin');
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
  <div>
    <h1 style="font-size:1.4rem;margin-bottom:2px">
      <i class="bi bi-shield-lock me-2" style="color:var(--brand)"></i>Admin console</h1>
    <p class="text-muted-2 small mb-0">Signed in as <strong><?= e(me()['name']) ?></strong>.
      Deleting a student cascades to their skills, requests, sessions and reviews.</p>
  </div>
  <a class="btn btn-sm btn-quiet" href="dashboard.php">
    <i class="bi bi-box-arrow-left me-1"></i>Back to student view</a>
</div>

<div class="row g-2 mb-4">
  <?php foreach ([
      [$stats['students'],  'Students'],
      [$stats['skills'],    'Skills'],
      [$stats['pending'],   'Pending requests'],
      [$stats['upcoming'],  'Upcoming sessions'],
      [$stats['avg'] !== null ? $stats['avg'] : '—', 'Average rating'],
      [$stats['lowrated'],  'Low ratings to check'],
  ] as [$num, $label]): ?>
    <div class="col-6 col-md-4 col-xl-2"><div class="stat">
      <div class="stat-num" style="font-size:1.25rem"><?= e($num) ?></div>
      <div class="stat-label"><?= e($label) ?></div></div></div>
  <?php endforeach; ?>
</div>

<ul class="nav nav-pills mb-3">
  <?php foreach (['users' => 'Students', 'skills' => 'Skills',
                  'requests' => 'Requests', 'reviews' => 'Review moderation'] as $k => $label): ?>
    <li class="nav-item">
      <a class="nav-link btn-sm <?= $tab === $k ? 'active' : '' ?>"
         href="admin.php?tab=<?= e($k) ?>"><?= e($label) ?></a>
    </li>
  <?php endforeach; ?>
</ul>

<section class="panel">
<?php if ($tab === 'users'):

  /* Every student with their two skill counts, their exchange count
     and their rating, all assembled by admin_students(). */
  $students = admin_students($userQ);
?>
  <div class="panel-head flex-wrap">
    <h2><i class="bi bi-people me-2"></i>Students (<?= count($students) ?>)</h2>
    <form method="get" action="admin.php" class="d-flex gap-2">
      <input type="hidden" name="tab" value="users">
      <input class="form-control form-control-sm w-auto" name="us" value="<?= e($userQ) ?>"
             placeholder="Search name, email or department">
      <button class="btn btn-sm btn-quiet" type="submit"><i class="bi bi-search"></i>
        <span class="visually-hidden">Search</span></button>
      <?php if ($userQ !== ''): ?>
        <a class="btn btn-sm btn-quiet" href="admin.php?tab=users">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$students): ?>
    <div class="panel-body"><?= empty_state('people',
          'No student matches ' . e($userQ) . '.',
          '<a class="btn btn-sm btn-quiet" href="admin.php?tab=users">Show everyone</a>') ?></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-clean">
    <thead><tr><th>#</th><th>Student</th><th>Department</th><th>Skills</th>
      <th>Exchanges</th><th>Rating</th><th>Joined</th><th class="text-end">Action</th></tr></thead>
    <tbody>
      <?php foreach ($students as $u):
        $uid    = (int) $u['user_id'];
        $count  = (int) $u['total_reviews'];
        $teach  = (int) $u['teaches'];
        $learn  = (int) $u['learns'];
      ?>
      <tr>
        <td class="text-muted-2"><?= $uid ?></td>
        <td><div class="d-flex gap-2 align-items-center">
              <?= avatar(['user_id' => $uid, 'name' => $u['name']], 32) ?>
              <div class="lh-sm min-w-0">
                <a href="profile.php?id=<?= $uid ?>" class="fw-semibold text-reset"><?= e($u['name']) ?></a>
                <div class="small text-muted-2 text-truncate" style="max-width:190px"><?= e($u['email']) ?></div>
              </div></div></td>
        <td><?= e($u['department']) ?></td>
        <td class="small"><?= $teach ?> teach<br><span class="text-muted-2"><?= $learn ?> learn</span></td>
        <td><?= (int) $u['exchanges'] ?></td>
        <td><?php if ($count): ?>
              <span class="rating-num"><?= e($u['avg_rating']) ?></span>
              <span class="small text-muted-2">(<?= $count ?>)</span>
            <?php else: ?>
              <span class="small text-muted-2">&mdash;</span>
            <?php endif; ?></td>
        <td class="text-muted-2 small"><?= e(fmt_date($u['created_at'])) ?></td>
        <td class="text-end">
          <?= $uid === $me
              ? '<span class="small text-muted-2">Signed in</span>'
              : action_button('admin.user.delete', ['user_id' => $uid],
                    'Delete', 'trash', 'btn-quiet text-danger',
                    'Delete ' . $u['name'] . '? ON DELETE CASCADE takes their skills, '
                  . 'requests, sessions and reviews with the row, in one DELETE statement.') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>

<?php elseif ($tab === 'skills'):

  /* The catalogue, plus the set of skills an exchange request still
     names. Those are the rows ON DELETE RESTRICT will not let go. */
  $skills = admin_skills($skillQ);
  $used   = [];
  foreach (rows('SELECT DISTINCT teach_skill AS skill_id FROM exchangerequests
                 UNION
                 SELECT DISTINCT learn_skill FROM exchangerequests') as $r) {
      $used[(int) $r['skill_id']] = true;
  }
?>
  <div class="panel-head flex-wrap">
    <h2><i class="bi bi-mortarboard me-2"></i>Skill catalogue (<?= count($skills) ?>)</h2>
    <div class="d-flex gap-2">
      <form method="get" action="admin.php" class="d-flex gap-2">
        <input type="hidden" name="tab" value="skills">
        <input class="form-control form-control-sm w-auto" name="ss" value="<?= e($skillQ) ?>"
               placeholder="Search skill or category">
        <button class="btn btn-sm btn-quiet" type="submit"><i class="bi bi-search"></i>
          <span class="visually-hidden">Search</span></button>
        <?php if ($skillQ !== ''): ?>
          <a class="btn btn-sm btn-quiet" href="admin.php?tab=skills">Clear</a>
        <?php endif; ?>
      </form>
      <button class="btn btn-sm btn-brand" data-bs-toggle="collapse" data-bs-target="#addSkillBox">
        <i class="bi bi-plus-lg me-1"></i>Add skill</button>
    </div>
  </div>

  <div class="collapse" id="addSkillBox">
    <div class="panel-body border-bottom" style="border-color:var(--line)!important">
      <form method="post" action="actions.php" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="admin.skill.create">
        <input type="hidden" name="back" value="<?= e(current_url()) ?>">
        <div class="col-sm-4">
          <label class="form-label" for="nsName">Skill name</label>
          <input class="form-control form-control-sm" id="nsName" name="skill_name"
                 placeholder="e.g. Rust" required>
        </div>
        <div class="col-sm-3">
          <label class="form-label" for="nsCat">Category</label>
          <select class="form-select form-select-sm" id="nsCat" name="category">
            <?php foreach (all_categories() as $c): ?>
              <option><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-3">
          <label class="form-label" for="nsDesc">Description</label>
          <input class="form-control form-control-sm" id="nsDesc" name="description"
                 placeholder="One line">
        </div>
        <div class="col-sm-2 d-grid">
          <button class="btn btn-sm btn-brand" type="submit">Save</button>
        </div>
        <div class="col-12"><div class="small text-muted-2">The UNIQUE key on
          <code>skills.skill_name</code> rejects a name the catalogue already holds.</div></div>
      </form>
    </div>
  </div>

  <?php if (!$skills): ?>
    <div class="panel-body"><?= empty_state('mortarboard',
          'No skill matches ' . e($skillQ) . '.',
          '<a class="btn btn-sm btn-quiet" href="admin.php?tab=skills">Show the whole catalogue</a>') ?></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-clean">
    <thead><tr><th>#</th><th>Skill</th><th>Category</th><th>Description</th>
      <th>Teachers</th><th>Learners</th><th class="text-end">Action</th></tr></thead>
    <tbody>
      <?php foreach ($skills as $s):
        $sid    = (int) $s['skill_id'];
        $inUse  = isset($used[$sid]);
      ?>
      <tr>
        <td class="text-muted-2"><?= $sid ?></td>
        <td class="fw-semibold"><?= e($s['skill_name']) ?></td>
        <td><span class="pill pill-online"><?= e($s['category']) ?></span></td>
        <td class="small text-muted-2" style="max-width:260px"><?= e($s['description']) ?></td>
        <td><?= (int) $s['teaches'] ?></td>
        <td><?= (int) $s['learns'] ?></td>
        <td class="text-end">
          <?= $inUse
              ? action_button('admin.skill.delete', ['skill_id' => $sid],
                    'In use', 'lock', 'btn-quiet',
                    $s['skill_name'] . ' is named by an exchange request, so ON DELETE RESTRICT '
                  . 'will refuse this delete. Send it anyway and watch the constraint answer?')
              : action_button('admin.skill.delete', ['skill_id' => $sid],
                    'Delete', 'trash', 'btn-quiet text-danger',
                    'Remove ' . $s['skill_name'] . ' from the catalogue?') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>

  <div class="panel-body tight py-2 small text-muted-2">
    A skill an exchange request still points at cannot be deleted &mdash; the foreign key
    on <code>exchangerequests</code> is declared ON DELETE RESTRICT, and it refuses the
    statement rather than leaving a request pointing at nothing.</div>

<?php elseif ($tab === 'requests'):

  /* G2 — the whole request list read out of v_request_details, where
     the four joins are already done inside the view. The ids and the
     session count are not in the view, so they arrive separately and
     are matched up by request_id. */
  $reqs   = admin_requests($status);
  $shown  = array_slice($reqs, 0, 30);

  $meta = [];
  foreach (rows('SELECT   er.request_id, er.sender_id, er.receiver_id,
                          (SELECT COUNT(*) FROM sessions se
                            WHERE se.request_id = er.request_id) AS sessions
                 FROM     exchangerequests er') as $m) {
      $meta[(int) $m['request_id']] = $m;
  }

  $counts = [];
  foreach (rows('SELECT status, COUNT(*) AS n FROM exchangerequests GROUP BY status') as $c) {
      $counts[$c['status']] = (int) $c['n'];
  }
?>
  <div class="panel-head flex-wrap">
    <h2><i class="bi bi-arrow-left-right me-2"></i>All exchange requests</h2>
    <div class="d-flex flex-wrap gap-1 align-items-center">
      <?php foreach ($counts as $st => $n): ?>
        <?= pill($st) ?><span class="small ms-1 me-2"><?= $n ?></span>
      <?php endforeach; ?>
      <form method="get" action="admin.php">
        <input type="hidden" name="tab" value="requests">
        <select class="form-select form-select-sm w-auto" name="status" onchange="this.form.submit()">
          <option value="">Every status</option>
          <?php foreach (['Pending', 'Accepted', 'Completed', 'Rejected', 'Cancelled'] as $st): ?>
            <option <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if (!$shown): ?>
    <div class="panel-body"><?= empty_state('inbox',
          'No ' . strtolower($status) . ' request on the platform.',
          '<a class="btn btn-sm btn-quiet" href="admin.php?tab=requests">Show every status</a>') ?></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-clean">
    <thead><tr><th>#</th><th>Sender</th><th>Receiver</th><th>Offered</th>
      <th>Requested</th><th>Status</th><th>Sessions</th><th>Opened</th></tr></thead>
    <tbody>
      <?php foreach ($shown as $r):
        $rid  = (int) $r['request_id'];
        $m    = $meta[$rid] ?? ['sender_id' => 0, 'receiver_id' => 0, 'sessions' => 0];
      ?>
      <tr>
        <td class="text-muted-2"><?= $rid ?></td>
        <td><a href="profile.php?id=<?= (int) $m['sender_id'] ?>" class="text-reset fw-semibold">
              <?= e($r['sender_name']) ?></a></td>
        <td><a href="profile.php?id=<?= (int) $m['receiver_id'] ?>" class="text-reset fw-semibold">
              <?= e($r['receiver_name']) ?></a></td>
        <td><?= e($r['offered_skill']) ?></td>
        <td><?= e($r['requested_skill']) ?></td>
        <td><?= pill($r['status']) ?></td>
        <td><?= (int) $m['sessions'] ?></td>
        <td class="text-muted-2 small"><?= e(fmt_date($r['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <div class="panel-body tight py-2 small text-muted-2">
    Showing the <?= count($shown) ?> most recent of <?= count($reqs) ?>
    <?= $status ? e(strtolower($status)) . ' ' : '' ?>requests, straight from
    <code>v_request_details</code>.</div>
  <?php endif; ?>

<?php else:

  /* The moderation queue: reviews at or below the chosen rating,
     lowest first, so the complaints sit at the top. */
  $reviews = admin_reviews($maxRat);
?>
  <div class="panel-head flex-wrap">
    <h2><i class="bi bi-flag me-2"></i>Reviews (<?= count($reviews) ?>)</h2>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="small text-muted-2">Sorted lowest first so problems surface at the top.</span>
      <form method="get" action="admin.php">
        <input type="hidden" name="tab" value="reviews">
        <select class="form-select form-select-sm w-auto" name="max" onchange="this.form.submit()">
          <?php foreach ([2 => '2 stars and below', 3 => '3 stars and below',
                          5 => 'Every rating'] as $v => $label): ?>
            <option value="<?= $v ?>" <?= $maxRat === $v ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if (!$reviews): ?>
    <div class="panel-body"><?= empty_state('flag',
          'Nothing at or below that rating. The queue is clear.') ?></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-clean">
    <thead><tr><th>#</th><th>Reviewer</th><th>About</th><th>Rating</th>
      <th>Comment</th><th>Date</th><th class="text-end">Action</th></tr></thead>
    <tbody>
      <?php foreach ($reviews as $r): ?>
      <tr>
        <td class="text-muted-2"><?= (int) $r['review_id'] ?></td>
        <td><a href="profile.php?id=<?= (int) $r['reviewer_id'] ?>" class="text-reset">
              <?= e($r['reviewer_name']) ?></a></td>
        <td><a href="profile.php?id=<?= (int) $r['reviewee_id'] ?>" class="text-reset fw-semibold">
              <?= e($r['reviewee_name']) ?></a></td>
        <td><?= stars($r['rating']) ?></td>
        <td class="small" style="max-width:320px"><?= e($r['comment']) ?></td>
        <td class="text-muted-2 small"><?= e(fmt_date($r['created_at'])) ?></td>
        <td class="text-end">
          <?= action_button('admin.review.delete', ['review_id' => (int) $r['review_id']],
                'Delete', 'trash', 'btn-quiet text-danger',
                'Remove review #' . (int) $r['review_id'] . '? The average rating in '
              . 'v_user_ratings recomputes itself the next time the view is read.') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>

<?php endif; ?>
</section>

<?php page_close(); ?>
