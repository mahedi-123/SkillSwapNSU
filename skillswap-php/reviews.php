<?php
/* =============================================================
   reviews.php — the ratings this student has collected, written
   and still owes.

   The interesting statement here is the third list: completed
   sessions where NOT EXISTS a review by me. An anti-join is what
   turns "everything I did" into "everything I have left to do".
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';
require_login();

$me = me_id();

$rate     = rating_of($me);
$received = reviews_received($me);
$written  = reviews_written($me);
$waiting  = sessions_awaiting_review($me);

/* How the scores about me are spread, counted by MySQL rather
   than by walking the list in PHP. */
$spread = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach (rows(
    'SELECT   rating, COUNT(*) AS n
     FROM     reviews
     WHERE    reviewee_id = ?
     GROUP BY rating', [$me]) as $s) {
    $spread[(int) $s['rating']] = (int) $s['n'];
}

page_open('Reviews', 'reviews');
?>

<div class="mb-3">
  <h1 style="font-size:1.4rem;margin-bottom:2px">Reviews</h1>
  <p class="text-muted-2 small mb-0">A review can only be written after a
    completed session, and each partner may write one per session.</p>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat p-2 px-3">
      <div class="stat-num" style="font-size:1.25rem"><?= $rate['count'] ? e($rate['avg']) : '&mdash;' ?></div>
      <div class="stat-label">Average rating</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat p-2 px-3">
      <div class="stat-num" style="font-size:1.25rem"><?= (int) $rate['count'] ?></div>
      <div class="stat-label">About you</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat p-2 px-3">
      <div class="stat-num" style="font-size:1.25rem"><?= count($written) ?></div>
      <div class="stat-label">Written by you</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat p-2 px-3">
      <div class="stat-num" style="font-size:1.25rem"><?= count($waiting) ?></div>
      <div class="stat-label">Waiting on you</div>
    </div>
  </div>
</div>

<!-- ---------- what other students said about me ---------- -->
<section class="panel">
  <div class="panel-head flex-wrap">
    <h2><i class="bi bi-chat-quote me-2" style="color:var(--brand)"></i>About you</h2>
    <span class="small text-muted-2"><?= count($received) ?>
      review<?= count($received) === 1 ? '' : 's' ?></span>
  </div>
  <div class="panel-body">
    <?php if (!$received): ?>
      <?= empty_state('chat-quote',
            'Nobody has reviewed you yet. Finish a session and your partner can rate you.',
            '<a class="btn btn-sm btn-brand" href="sessions.php">Open sessions</a>') ?>
    <?php else: ?>
      <div class="row g-3 mb-3 pb-3 border-bottom" style="border-color:var(--line)!important">
        <div class="col-sm-4 text-center">
          <div class="stat-num" style="font-size:2.4rem"><?= e($rate['avg']) ?></div>
          <?= stars($rate['avg']) ?>
          <div class="small text-muted-2 mt-1"><?= (int) $rate['count'] ?>
            review<?= (int) $rate['count'] === 1 ? '' : 's' ?></div>
        </div>
        <div class="col-sm-8">
          <?php foreach ($spread as $n => $c): ?>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="small text-muted-2" style="width:12px"><?= $n ?></span>
              <i class="bi bi-star-fill" style="color:var(--star);font-size:10px"></i>
              <div class="progress flex-grow-1" style="height:6px">
                <div class="progress-bar"
                     style="width:<?= count($received) ? round(100 * $c / count($received), 1) : 0 ?>%;background:var(--brand)"></div>
              </div>
              <span class="small text-muted-2" style="width:18px"><?= $c ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php foreach ($received as $i => $r):
        $last = $i === count($received) - 1;
        $rid  = (int) $r['reviewer_id'];
      ?>
        <div class="d-flex gap-2 <?= $last ? '' : 'mb-3 pb-3 border-bottom' ?>"
             style="<?= $last ? '' : 'border-color:var(--line)!important' ?>">
          <?= avatar(['user_id' => $rid, 'name' => $r['reviewer_name']], 40) ?>
          <div class="min-w-0 flex-grow-1">
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <a href="profile.php?id=<?= $rid ?>" class="fw-semibold text-reset"><?= e($r['reviewer_name']) ?></a>
              <?= stars($r['rating']) ?>
              <span class="small text-muted-2"><?= e(fmt_date($r['created_at'])) ?></span>
            </div>
            <div class="small text-muted-2">Reviewed you after a
              <?= e($r['skill_taught']) ?> session &middot; session #<?= (int) $r['session_id'] ?></div>
            <p class="mb-0 mt-1"><?= e($r['comment']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- ---------- what I said about other students ---------- -->
<section class="panel">
  <div class="panel-head flex-wrap">
    <h2><i class="bi bi-pen me-2" style="color:var(--brand)"></i>Written by you</h2>
    <span class="small text-muted-2"><?= count($written) ?>
      review<?= count($written) === 1 ? '' : 's' ?></span>
  </div>
  <div class="panel-body">
    <?php if (!$written): ?>
      <?= empty_state('chat-quote', 'You have not written a review yet.',
            '<a class="btn btn-sm btn-brand" href="sessions.php">Open sessions</a>') ?>
    <?php else: ?>
      <?php foreach ($written as $i => $r):
        $last = $i === count($written) - 1;
        $uid  = (int) $r['reviewee_id'];
      ?>
        <div class="d-flex gap-2 <?= $last ? '' : 'mb-3 pb-3 border-bottom' ?>"
             style="<?= $last ? '' : 'border-color:var(--line)!important' ?>">
          <?= avatar(['user_id' => $uid, 'name' => $r['reviewee_name']], 40) ?>
          <div class="min-w-0 flex-grow-1">
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <a href="profile.php?id=<?= $uid ?>" class="fw-semibold text-reset"><?= e($r['reviewee_name']) ?></a>
              <?= stars($r['rating']) ?>
              <span class="small text-muted-2"><?= e(fmt_date($r['created_at'])) ?></span>
            </div>
            <div class="small text-muted-2">You reviewed them after a
              <?= e($r['skill_taught']) ?> session &middot; session #<?= (int) $r['session_id'] ?></div>
            <p class="mb-0 mt-1"><?= e($r['comment']) ?></p>
          </div>
          <div class="align-self-start">
            <?= action_button('review.delete', ['review_id' => (int) $r['review_id']],
                  'Withdraw', 'trash', 'btn-quiet',
                  'Withdraw this review? The row is deleted for good.') ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- ---------- completed sessions with no review from me ---------- -->
<section class="panel">
  <div class="panel-head">
    <h2><i class="bi bi-pencil-square me-2" style="color:var(--brand)"></i>Waiting for your review</h2>
  </div>
  <div class="panel-body">
    <?php if (!$waiting): ?>
      <?= empty_state('check2-circle',
            'You have reviewed every completed session. Nothing pending.') ?>
    <?php else: ?>
      <?php foreach ($waiting as $i => $w):
        $sid   = (int) $w['session_id'];
        $pid   = (int) $w['partner_id'];
        $last  = $i === count($waiting) - 1;
        $first = explode(' ', trim((string) $w['partner_name']))[0];
      ?>
        <div id="session-<?= $sid ?>" class="<?= $last ? '' : 'mb-4 pb-4 border-bottom' ?>"
             style="<?= $last ? '' : 'border-color:var(--line)!important' ?>">
          <div class="d-flex gap-2 align-items-center min-w-0 mb-3">
            <?= avatar(['user_id' => $pid, 'name' => $w['partner_name']], 40) ?>
            <div class="lh-sm min-w-0">
              <a href="profile.php?id=<?= $pid ?>" class="fw-semibold text-reset"><?= e($w['partner_name']) ?></a>
              <div class="small text-muted-2"><?= e($w['skill_taught']) ?> &middot;
                <?= e(fmt_date($w['session_date'])) ?> &middot;
                <?= e(fmt_time($w['session_time'])) ?> &middot; session #<?= $sid ?></div>
            </div>
          </div>

          <form method="post" action="actions.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="review.create">
            <input type="hidden" name="back" value="<?= e(current_url()) ?>">
            <input type="hidden" name="session_id" value="<?= $sid ?>">
            <input type="hidden" name="reviewee_id" value="<?= $pid ?>">

            <label class="form-label d-block mb-1">Your rating for <?= e($first) ?></label>
            <div class="star-input mb-1" data-star-picker style="color:var(--star)">
              <?php for ($n = 1; $n <= 5; $n++): ?>
                <button type="button" aria-label="<?= $n ?> out of 5"
                        style="background:none;border:0;padding:0;color:inherit;line-height:1">
                  <i class="bi bi-star"></i></button>
              <?php endfor; ?>
              <input type="hidden" name="rating" value="0">
            </div>
            <div class="form-text mb-2"><code>CHECK (rating BETWEEN 1 AND 5)</code>
              rejects anything else.</div>

            <label class="form-label" for="c<?= $sid ?>">Comment</label>
            <textarea class="form-control" id="c<?= $sid ?>" name="comment" rows="2" maxlength="200"
                      placeholder="What went well? What could have been better?"></textarea>
            <div class="form-text">Up to 200 characters &mdash; the column is
              <code>VARCHAR(200)</code>.</div>

            <button class="btn btn-sm btn-brand mt-2" type="submit">
              <i class="bi bi-star me-1"></i>Publish review</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php page_close(); ?>
