<?php
/* =============================================================
   profile.php — one student, read from every table at once.

   The page is addressed as profile.php?id=19 and falls back to the
   signed-in student when the id is missing. Almost everything here
   is a JOIN away from the users row: skills through the userskills
   bridge, reviews through sessions and requests, and the header
   totals through two aggregates over the same joined chain.
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';
require_login();

$me  = me_id();
$pid = (int) q('id', $me);
$p   = user_by_id($pid);

/* An id nobody owns is a normal thing to type into the address bar,
   so it gets a panel of its own rather than a fatal error. */
if (!$p) {
    page_open('Student not found', 'profile');
    ?>
    <section class="panel">
      <div class="panel-head"><h2><i class="bi bi-person-x me-2" style="color:var(--brand)"></i>No such student</h2></div>
      <div class="panel-body">
        <?= empty_state('person-x',
              'SELECT * FROM users WHERE user_id = ' . $pid . ' returned no row. '
            . 'The student may have been removed from the console.',
              '<a class="btn btn-sm btn-brand" href="search.php">Find a partner</a>') ?>
      </div>
    </section>
    <?php
    page_close();
    exit;
}

$isMe  = $pid === $me;
$teach = skills_of($pid, 'Teach');
$learn = skills_of($pid, 'Learn');
$rate  = rating_of($pid);
$revs  = reviews_received($pid);
$hist  = requests_of($pid, 'all');

/* What my own two lists contain, so a skill that lines up with mine
   can be ticked without another query per chip. */
$myTeachIds = array_map('intval', array_column(skills_of($me, 'Teach'), 'skill_id'));
$myLearnIds = array_map('intval', array_column(skills_of($me, 'Learn'), 'skill_id'));

/* Header totals — two aggregates, both filtered on the same student. */
$done = (int) val(
    'SELECT COUNT(*) FROM exchangerequests
      WHERE (sender_id = ? OR receiver_id = ?) AND status = \'Completed\'',
    [$pid, $pid]);

$minutes = (int) val(
    'SELECT     COALESCE(SUM(se.duration), 0)
     FROM       sessions se
     INNER JOIN exchangerequests er ON er.request_id = se.request_id
     WHERE      se.status = \'Completed\'
       AND      (er.sender_id = ? OR er.receiver_id = ?)',
    [$pid, $pid]);
$hours = (int) round($minutes / 60);

/* How the ratings are spread, one GROUP BY instead of five counts. */
$spread = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach (rows('SELECT   rating, COUNT(*) AS n
               FROM     reviews
               WHERE    reviewee_id = ?
               GROUP BY rating', [$pid]) as $r) {
    $spread[(int) $r['rating']] = (int) $r['n'];
}

/* Only asked for when the profile belongs to somebody else. */
$open      = $isMe ? null : existing_request($me, $pid);
$myTeach   = $isMe ? []   : skills_of($me, 'Teach');
$wantMine  = $isMe ? []   : array_values(array_filter($learn,
                fn($s) => in_array((int) $s['skill_id'], $myTeachIds, true)));
$wantTheirs = $isMe ? []  : array_values(array_filter($teach,
                fn($s) => in_array((int) $s['skill_id'], $myLearnIds, true)));

/* Five other students from the same department, best rated first. */
$peers = rows(
    'SELECT     u.user_id, u.name, vr.total_reviews, vr.avg_rating,
                (SELECT COUNT(*) FROM userskills us
                  WHERE us.user_id = u.user_id AND us.skill_type = \'Teach\') AS teaches
     FROM       users u
     LEFT JOIN  v_user_ratings vr ON vr.user_id = u.user_id
     WHERE      u.department = ? AND u.user_id <> ? AND u.user_id <> ?
     ORDER BY   (vr.avg_rating IS NULL), vr.avg_rating DESC, u.name
     LIMIT      ?',
    [$p['department'], $pid, $me, 5]);

page_open($p['name'], 'profile');

/* A chip, plus a tick when the skill lines up with my own profile. */
function matched_chip(array $skill, string $kind, array $mine): string
{
    $tick = in_array((int) $skill['skill_id'], $mine, true)
          ? ' <i class="bi bi-check-circle-fill" style="color:var(--ok);font-size:10px"'
          . ' title="Matches your profile"></i>' : '';
    return str_replace('</a>', $tick . '</a>', skill_chip($skill, $kind));
}
?>

<div class="panel overflow-hidden mb-3">
  <div class="profile-card-cover" style="height:96px"></div>
  <div class="px-3 px-sm-4 pb-3">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
      <div class="d-flex align-items-end gap-3" style="margin-top:-44px">
        <?= avatar($p, 88) ?>
        <div class="pb-1">
          <h1 style="font-size:1.5rem;margin-bottom:0"><?= e($p['name']) ?></h1>
          <div class="text-muted-2"><?= e($p['department']) ?> &middot; North South University</div>
        </div>
      </div>
      <div class="d-flex gap-2 pb-1">
        <?php if ($isMe): ?>
          <a class="btn btn-sm btn-brand" href="edit-profile.php">
            <i class="bi bi-pencil-square me-1"></i>Edit profile</a>
        <?php else: ?>
          <a class="btn btn-sm btn-brand" href="#proposal">
            <i class="bi bi-send me-1"></i>Propose an exchange</a>
          <a class="btn btn-sm btn-quiet" href="search.php">Back to search</a>
        <?php endif; ?>
      </div>
    </div>

    <p class="mt-3 mb-3"><?= e($p['bio'] ?: 'This student has not written a bio yet.') ?></p>

    <div class="d-flex flex-wrap gap-4 pt-3 border-top" style="border-color:var(--line)!important">
      <div><div class="small-label">Rating</div>
        <div class="d-flex align-items-center gap-1"><?= stars($rate['avg']) ?>
          <span class="small text-muted-2"><?= $rate['count']
            ? e($rate['avg']) . ' (' . $rate['count'] . ')' : 'None yet' ?></span>
        </div></div>
      <div><div class="small-label">Completed exchanges</div>
        <div class="fw-semibold"><?= $done ?></div></div>
      <div><div class="small-label">Teaching time</div>
        <div class="fw-semibold"><?= $hours ?> hours</div></div>
      <div><div class="small-label">Member since</div>
        <div class="fw-semibold"><?= e(fmt_date($p['created_at'])) ?></div></div>
    </div>
  </div>
</div>

<?php if (!$isMe): ?>
<section class="panel" id="proposal">
  <div class="panel-head"><h2><i class="bi bi-send me-2" style="color:var(--brand)"></i>Propose an exchange</h2></div>
  <div class="panel-body">
    <?php if ($open): ?>
      <div class="alert alert-light border small mb-0">
        You already have a request with <?= e(explode(' ', $p['name'])[0]) ?> &mdash;
        currently <?= pill($open['status']) ?>.
        <a href="requests.php">Open requests</a>
      </div>

    <?php elseif (!$myTeach || !$teach): ?>
      <p class="small text-muted-2 mb-0">
        <?= $myTeach
            ? e($p['name']) . ' has not listed anything to teach yet, so there is nothing to ask for.'
            : 'You have nothing on your teach list yet. '
              . 'Add one skill on the edit page and you can propose a trade.' ?>
        <?php if (!$myTeach): ?><a href="edit-profile.php">Edit your skills</a><?php endif; ?>
      </p>

    <?php else: ?>
      <?php if ($wantMine && $wantTheirs): ?>
        <div class="mb-3"><?= swap_card(
              'You teach', $wantMine[0]['skill_name'], 'On their learn list',
              'You learn', $wantTheirs[0]['skill_name'], $wantTheirs[0]['proficiency'] . ' level') ?></div>
      <?php else: ?>
        <p class="small text-muted-2">There is no automatic two-way match here, but
          you can still propose any pair of skills.</p>
      <?php endif; ?>

      <form method="post" action="actions.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="request.create">
        <input type="hidden" name="back" value="<?= e(current_url()) ?>">
        <input type="hidden" name="receiver_id" value="<?= $pid ?>">

        <div class="row g-3 align-items-end">
          <div class="col-sm-5">
            <label class="form-label" for="giveSel">I will teach</label>
            <select class="form-select form-select-sm" id="giveSel" name="teach_skill">
              <?php foreach ($myTeach as $s): ?>
                <option value="<?= (int) $s['skill_id'] ?>"
                  <?= isset($wantMine[0]) && (int) $wantMine[0]['skill_id'] === (int) $s['skill_id']
                      ? 'selected' : '' ?>><?= e($s['skill_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-5">
            <label class="form-label" for="takeSel">I want to learn</label>
            <select class="form-select form-select-sm" id="takeSel" name="learn_skill">
              <?php foreach ($teach as $s): ?>
                <option value="<?= (int) $s['skill_id'] ?>"
                  <?= isset($wantTheirs[0]) && (int) $wantTheirs[0]['skill_id'] === (int) $s['skill_id']
                      ? 'selected' : '' ?>><?= e($s['skill_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-2 d-grid">
            <button class="btn btn-brand btn-sm" type="submit">Send request</button>
          </div>
        </div>
        <p class="small text-muted-2 mt-2 mb-0">They will see it under Requests and can
          accept or decline. CHECK (teach_skill &lt;&gt; learn_skill) refuses a pair of
          the same skill.</p>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="panel">
  <div class="panel-head"><h2><i class="bi bi-mortarboard me-2" style="color:var(--brand)"></i>Skills</h2></div>
  <div class="panel-body">
    <div class="small-label mb-2">Can teach (<?= count($teach) ?>)</div>
    <?php if ($teach): ?>
      <div class="d-flex flex-wrap gap-1">
        <?php foreach ($teach as $s) { echo matched_chip($s, 'Teach', $myLearnIds); } ?>
      </div>
    <?php else: ?>
      <p class="small text-muted-2 mb-0">Nothing listed yet.</p>
    <?php endif; ?>

    <hr style="border-color:var(--line)">

    <div class="small-label mb-2">Wants to learn (<?= count($learn) ?>)</div>
    <?php if ($learn): ?>
      <div class="d-flex flex-wrap gap-1">
        <?php foreach ($learn as $s) { echo matched_chip($s, 'Learn', $myTeachIds); } ?>
      </div>
    <?php else: ?>
      <p class="small text-muted-2 mb-0">Nothing listed yet.</p>
    <?php endif; ?>

    <?php if (!$isMe): ?>
      <p class="small text-muted-2 mt-3 mb-0">
        <i class="bi bi-check-circle-fill me-1" style="color:var(--ok)"></i>
        marks a skill that lines up with your own profile.</p>
    <?php endif; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-head">
    <h2><i class="bi bi-chat-quote me-2" style="color:var(--brand)"></i>Reviews</h2>
    <span class="small text-muted-2"><?= $rate['count']
      ? e($rate['avg']) . ' out of 5 from ' . $rate['count'] . ' review' . ($rate['count'] === 1 ? '' : 's')
      : 'No reviews yet' ?></span>
  </div>
  <div class="panel-body">
    <?php if (!$revs): ?>
      <?= empty_state('chat-quote', $isMe
            ? 'Finish a session and your partner can leave the first review.'
            : 'This student has not been reviewed yet.') ?>
    <?php else: ?>
      <div class="row g-3 mb-3 pb-3 border-bottom" style="border-color:var(--line)!important">
        <div class="col-sm-4 text-center">
          <div class="stat-num" style="font-size:2.4rem"><?= e($rate['avg']) ?></div>
          <?= stars($rate['avg']) ?>
          <div class="small text-muted-2 mt-1"><?= $rate['count'] ?>
            review<?= $rate['count'] === 1 ? '' : 's' ?></div>
        </div>
        <div class="col-sm-8">
          <?php foreach ([5, 4, 3, 2, 1] as $n): ?>
            <div class="d-flex align-items-center gap-2 mb-1">
              <span class="small text-muted-2" style="width:12px"><?= $n ?></span>
              <i class="bi bi-star-fill" style="color:var(--star);font-size:10px"></i>
              <div class="progress flex-grow-1" style="height:6px">
                <div class="progress-bar" style="width:<?=
                  count($revs) ? round(100 * $spread[$n] / count($revs), 1) : 0
                ?>%;background:var(--brand)"></div>
              </div>
              <span class="small text-muted-2" style="width:18px"><?= $spread[$n] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php foreach ($revs as $r): ?>
        <div class="d-flex gap-2 mb-3">
          <?= avatar(['user_id' => (int) $r['reviewer_id'], 'name' => $r['reviewer_name']], 40) ?>
          <div class="min-w-0">
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <a href="profile.php?id=<?= (int) $r['reviewer_id'] ?>"
                 class="fw-semibold text-reset"><?= e($r['reviewer_name']) ?></a>
              <?= stars($r['rating']) ?>
              <span class="small text-muted-2"><?= e(fmt_date($r['created_at'])) ?></span>
            </div>
            <div class="small text-muted-2">After a <?= e($r['skill_taught']) ?> session</div>
            <p class="mb-0 mt-1"><?= e($r['comment'] ?: 'No comment left.') ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<section class="panel">
  <div class="panel-head"><h2><i class="bi bi-clock-history me-2" style="color:var(--brand)"></i>Exchange history</h2></div>
  <?php if (!$hist): ?>
    <div class="panel-body"><?= empty_state('inbox', 'No exchange yet.') ?></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-clean">
        <thead><tr><th>Partner</th><th>Taught</th><th>Learned</th><th>Status</th><th>Opened</th></tr></thead>
        <tbody>
          <?php foreach ($hist as $r):
            $sent  = $r['dir'] === 'sent';
            $ptnId = (int) ($sent ? $r['receiver_id'] : $r['sender_id']);
            $ptn   = $sent ? $r['receiver_name'] : $r['sender_name'];
            $gave  = $sent ? $r['teach_name'] : $r['learn_name'];
            $got   = $sent ? $r['learn_name'] : $r['teach_name'];
          ?>
          <tr>
            <td><a href="profile.php?id=<?= $ptnId ?>" class="text-reset fw-semibold"><?= e($ptn) ?></a>
                <div class="small text-muted-2"><?= $sent ? 'Request sent' : 'Request received' ?></div></td>
            <td><?= e($gave) ?></td>
            <td><?= e($got) ?></td>
            <td><?= pill($r['status']) ?></td>
            <td class="text-muted-2"><?= e(fmt_date($r['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel-head"><h2><i class="bi bi-people me-2" style="color:var(--brand)"></i>Also in <?= e($p['department']) ?></h2></div>
  <div class="panel-body tight py-2">
    <?php if (!$peers): ?>
      <p class="small text-muted-2 mb-0 py-2">Nobody else from this department yet.</p>
    <?php else: ?>
      <?php foreach ($peers as $u): ?>
        <a class="d-flex gap-2 align-items-center py-2 text-reset text-decoration-none"
           href="profile.php?id=<?= (int) $u['user_id'] ?>">
          <?= avatar($u, 32) ?>
          <div class="lh-sm min-w-0 flex-grow-1">
            <div class="fw-semibold text-truncate" style="font-size:13px"><?= e($u['name']) ?></div>
            <div class="small text-muted-2" style="font-size:11.5px">
              <?= (int) $u['teaches'] ?> skills to teach</div>
          </div>
          <?php if ((int) $u['total_reviews']): ?>
            <span class="small"><span class="rating-num"><?= e($u['avg_rating']) ?></span>
              <i class="bi bi-star-fill" style="color:var(--star);font-size:10px"></i></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php page_close(); ?>
