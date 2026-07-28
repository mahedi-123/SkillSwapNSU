<?php
/* =============================================================
   requests.php — every exchange request this student is part of.

   Filtering happens in SQL, not in the browser: the direction tab
   and the status dropdown become WHERE clauses in requests_of().
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';
require_login();

$me     = me_id();
$dir    = in_array(q('dir', 'received'), ['received', 'sent', 'all'], true) ? q('dir', 'received') : 'received';
$status = q('status', '');

$counts = request_status_counts($me);
$list   = requests_of($me, $dir, $status);

page_open('Requests', 'requests');
?>

<div class="mb-3">
  <h1 style="font-size:1.4rem;margin-bottom:2px">Exchange requests</h1>
  <p class="text-muted-2 small mb-0">Every request carries one status. Accepting
    one is what unlocks session booking.</p>
</div>

<div class="row g-2 mb-3">
  <?php foreach (['Pending', 'Accepted', 'Completed', 'Rejected', 'Cancelled'] as $st): ?>
    <div class="col-4 col-md">
      <a class="stat w-100 text-start border-0 p-2 px-3 d-block text-decoration-none"
         href="<?= e(url_with(['status' => $status === $st ? null : $st], 'requests.php')) ?>">
        <div class="stat-num" style="font-size:1.25rem"><?= (int) $counts[$st] ?></div>
        <div class="stat-label"><?= $st ?></div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<section class="panel">
  <div class="panel-head flex-wrap">
    <ul class="nav nav-pills">
      <?php foreach (['received' => 'Received', 'sent' => 'Sent', 'all' => 'All'] as $k => $label): ?>
        <li class="nav-item">
          <a class="nav-link btn-sm <?= $dir === $k ? 'active' : '' ?>"
             href="<?= e(url_with(['dir' => $k], 'requests.php')) ?>"><?= $label ?></a>
        </li>
      <?php endforeach; ?>
    </ul>

    <form method="get" action="requests.php">
      <input type="hidden" name="dir" value="<?= e($dir) ?>">
      <select class="form-select form-select-sm w-auto" name="status"
              onchange="this.form.submit()">
        <option value="">Every status</option>
        <?php foreach (['Pending', 'Accepted', 'Completed', 'Rejected', 'Cancelled'] as $st): ?>
          <option <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="panel-body">
    <?php if (!$list): ?>
      <?= empty_state('inbox',
            'No ' . ($status ? strtolower($status) . ' ' : '') . 'request in this list.',
            '<a class="btn btn-sm btn-brand" href="search.php">Find a partner</a>') ?>
    <?php else: ?>
      <?php foreach ($list as $i => $r):
        $sent    = $r['dir'] === 'sent';
        $pid     = (int) ($sent ? $r['receiver_id'] : $r['sender_id']);
        $pname   = $sent ? $r['receiver_name'] : $r['sender_name'];
        $pdept   = $sent ? $r['receiver_dept'] : $r['sender_dept'];
        $rate    = rating_of($pid);
        /* teach_skill always belongs to the sender */
        $giveN   = $sent ? $r['teach_name'] : $r['learn_name'];
        $giveC   = $sent ? $r['teach_cat']  : $r['learn_cat'];
        $takeN   = $sent ? $r['learn_name'] : $r['teach_name'];
        $takeC   = $sent ? $r['learn_cat']  : $r['teach_cat'];
        $last    = $i === count($list) - 1;
        $rid     = (int) $r['request_id'];
      ?>
      <article class="<?= $last ? '' : 'mb-4 pb-4 border-bottom' ?>"
               style="<?= $last ? '' : 'border-color:var(--line)!important' ?>">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
          <div class="d-flex gap-2 align-items-center min-w-0">
            <?= avatar(['user_id' => $pid, 'name' => $pname], 40) ?>
            <div class="lh-sm min-w-0">
              <a href="profile.php?id=<?= $pid ?>" class="fw-semibold text-reset"><?= e($pname) ?></a>
              <div class="small text-muted-2"><?= e($pdept) ?> &middot;
                <?= $sent ? 'you sent this' : 'sent to you' ?> on <?= e(fmt_date($r['created_at'])) ?></div>
            </div>
          </div>
          <div class="text-end">
            <?= pill($r['status']) ?>
            <div class="small text-muted-2 mt-1">
              <?= $rate['count'] ? e($rate['avg']) . ' out of 5' : 'No rating yet' ?></div>
          </div>
        </div>

        <?= swap_card('You teach', $giveN, $giveC, 'You learn', $takeN, $takeC) ?>

        <div class="d-flex flex-wrap gap-2 mt-2">
          <?php
          if ($r['status'] === 'Pending' && !$sent) {
              echo action_button('request.status',
                    ['request_id' => $rid, 'status' => 'Accepted'],
                    'Accept', 'check-lg', 'btn-brand');
              echo action_button('request.status',
                    ['request_id' => $rid, 'status' => 'Rejected'],
                    'Decline', 'x-lg', 'btn-quiet');

          } elseif ($r['status'] === 'Pending' && $sent) {
              echo '<span class="small text-muted-2 me-2 align-self-center">Waiting for a reply</span>';
              echo action_button('request.status',
                    ['request_id' => $rid, 'status' => 'Cancelled'],
                    'Cancel request', 'slash-circle', 'btn-quiet');

          } elseif ($r['status'] === 'Accepted') {
              $booked = (int) $r['booked'];
              if ($booked) {
                  echo '<a class="btn btn-sm btn-quiet" href="sessions.php">'
                     . '<i class="bi bi-calendar-check me-1"></i>' . $booked
                     . ' session' . ($booked === 1 ? '' : 's') . ' booked</a>';
              } else {
                  echo '<a class="btn btn-sm btn-brand" href="sessions.php?book=' . $rid . '">'
                     . '<i class="bi bi-calendar-plus me-1"></i>Schedule a session</a>';
              }
              echo action_button('request.status',
                    ['request_id' => $rid, 'status' => 'Cancelled'],
                    'Cancel', 'slash-circle', 'btn-quiet');

          } elseif ($r['status'] === 'Completed') {
              echo '<a class="btn btn-sm btn-quiet" href="reviews.php">'
                 . '<i class="bi bi-star me-1"></i>Reviews</a>';

          } elseif ($r['status'] === 'Rejected' && $sent) {
              echo '<span class="small text-muted-2 align-self-center">They declined this one. '
                 . '<a href="search.php">Find someone else</a></span>';

          } else {
              echo '<span class="small text-muted-2 align-self-center">No action available</span>';
          }
          ?>
        </div>
      </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php page_close(); ?>
