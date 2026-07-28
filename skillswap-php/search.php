<?php
/* =============================================================
   search.php — find somebody to swap a skill with.

   Every filter on this page is a WHERE clause. The five inputs
   arrive as GET parameters and go straight into search_students()
   as bound values, so the browser never sees a row it is not
   allowed to see and never has to sift the list itself. The order
   of the results, and the counts behind each card, come from a
   second statement over the ids the search returned.
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';
require_login();

$me = me_id();

$f = [
    'q'          => q('q'),
    'skill'      => q('skill'),
    'category'   => q('category'),
    'department' => q('department'),
    'level'      => q('level'),
];
$levels = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];
if ($f['level'] !== '' && !in_array($f['level'], $levels, true)) {
    $f['level'] = '';
}

$sorts = [
    'match'  => 'Best match',
    'rating' => 'Highest rated',
    'name'   => 'Name (A&ndash;Z)',
    'newest' => 'Newest members',
];
$sort = q('sort', 'match');
if (!isset($sorts[$sort])) {
    $sort = 'match';
}

$list  = search_students($f, $me);
$total = (int) val('SELECT COUNT(*) FROM users WHERE user_id <> ?', [$me]);

/* One statement per list, not one per student: the skills of every
   student the search returned, fetched with an IN list of the ids and
   sorted into a teach half and a learn half afterwards. */
$teachOf = $learnOf = $meta = [];
$order   = [];

if ($list) {
    $ids  = array_map('intval', array_column($list, 'user_id'));
    $marks = implode(',', array_fill(0, count($ids), '?'));

    foreach (rows(
        'SELECT     us.user_id, us.skill_type, us.proficiency,
                    s.skill_id, s.skill_name, s.category
         FROM       userskills us
         INNER JOIN skills s ON s.skill_id = us.skill_id
         WHERE      us.user_id IN (' . $marks . ')
         ORDER BY   s.skill_name', $ids) as $s) {
        if ($s['skill_type'] === 'Teach') {
            $teachOf[(int) $s['user_id']][] = $s;
        } else {
            $learnOf[(int) $s['user_id']][] = $s;
        }
    }

    /* The sort and the hover panel in one pass. The score column is
       the two-way overlap — how many skills of theirs are on my learn
       list plus how many of mine are on theirs — which is what "best
       match" means, so the ordering is done by MySQL and not here. */
    $orderBy = [
        'match'  => 'score DESC, (vr.avg_rating IS NULL), vr.avg_rating DESC, u.name',
        'rating' => '(vr.avg_rating IS NULL), vr.avg_rating DESC, vr.total_reviews DESC, u.name',
        'name'   => 'u.name',
        'newest' => 'u.created_at DESC, u.name',
    ][$sort];

    foreach (rows(
        'SELECT     u.user_id,
                    (SELECT COUNT(*) FROM exchangerequests er
                      WHERE er.sender_id = u.user_id
                         OR er.receiver_id = u.user_id)               AS exchanges,
                    (SELECT COUNT(*) FROM sessions se
                     INNER JOIN exchangerequests er2 ON er2.request_id = se.request_id
                      WHERE se.status = ?
                        AND (er2.sender_id = u.user_id
                          OR er2.receiver_id = u.user_id))            AS held,
                    (SELECT COUNT(*) FROM userskills a
                      WHERE a.user_id = u.user_id AND a.skill_type = \'Teach\'
                        AND a.skill_id IN (SELECT skill_id FROM userskills
                                            WHERE user_id = ? AND skill_type = \'Learn\'))
                  + (SELECT COUNT(*) FROM userskills b
                      WHERE b.user_id = u.user_id AND b.skill_type = \'Learn\'
                        AND b.skill_id IN (SELECT skill_id FROM userskills
                                            WHERE user_id = ? AND skill_type = \'Teach\')) AS score
         FROM       users u
         LEFT JOIN  v_user_ratings vr ON vr.user_id = u.user_id
         WHERE      u.user_id IN (' . $marks . ')
         ORDER BY   ' . $orderBy,
        array_merge(['Completed', $me, $me], $ids)) as $m) {
        $meta[(int) $m['user_id']] = $m;
        $order[] = (int) $m['user_id'];
    }
}

/* The two halves of a two-way match, each one statement: what they
   teach that I want, and what they want that I teach. */
$theyTeachIWant = $theyWantITeach = [];
foreach (rows(
    'SELECT     us.user_id, us.proficiency, s.skill_id, s.skill_name
     FROM       userskills us
     INNER JOIN skills s ON s.skill_id = us.skill_id
     WHERE      us.skill_type = ?
       AND      us.skill_id IN (SELECT skill_id FROM userskills
                                 WHERE user_id = ? AND skill_type = ?)
     ORDER BY   s.skill_name', ['Teach', $me, 'Learn']) as $s) {
    $theyTeachIWant[(int) $s['user_id']][] = $s;
}
foreach (rows(
    'SELECT     us.user_id, us.proficiency, s.skill_id, s.skill_name
     FROM       userskills us
     INNER JOIN skills s ON s.skill_id = us.skill_id
     WHERE      us.skill_type = ?
       AND      us.skill_id IN (SELECT skill_id FROM userskills
                                 WHERE user_id = ? AND skill_type = ?)
     ORDER BY   s.skill_name', ['Learn', $me, 'Teach']) as $s) {
    $theyWantITeach[(int) $s['user_id']][] = $s;
}

/* Every filter in force, as a chip that links to the same search
   without it. */
$chips = [];
if ($f['q'])          { $chips[] = ['Name contains "' . $f['q'] . '"', 'q']; }
if ($f['skill'])      { $chips[] = ['Skill: ' . $f['skill'],           'skill']; }
if ($f['category'])   { $chips[] = ['Category: ' . $f['category'],     'category']; }
if ($f['department']) { $chips[] = ['Department: ' . $f['department'], 'department']; }
if ($f['level'])      { $chips[] = ['Level: ' . $f['level'],           'level']; }

page_open('Find a partner', 'search');
?>

<div class="row g-4">

  <!-- ---------------------- filters ---------------------- -->
  <div class="col-lg-4">
    <div class="rail-sticky">
      <section class="panel">
        <div class="panel-head">
          <h2><i class="bi bi-funnel me-2" style="color:var(--brand)"></i>Filters</h2>
          <a class="small text-decoration-none" href="search.php">Clear</a>
        </div>
        <div class="panel-body">
          <form method="get" action="search.php">
            <input type="hidden" name="sort" value="<?= e($sort) ?>">

            <div class="mb-3">
              <label class="form-label" for="fName">Student name</label>
              <input class="form-control form-control-sm" id="fName" name="q"
                     value="<?= e($f['q']) ?>" placeholder="e.g. Rahman">
            </div>
            <div class="mb-3">
              <label class="form-label" for="fSkill">Skill</label>
              <input class="form-control form-control-sm" id="fSkill" name="skill" list="skillList"
                     value="<?= e($f['skill']) ?>" placeholder="e.g. Python">
              <datalist id="skillList">
                <?php foreach (all_skills() as $s): ?>
                  <option value="<?= e($s['skill_name']) ?>"></option>
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="mb-3">
              <label class="form-label" for="fCategory">Category</label>
              <select class="form-select form-select-sm" id="fCategory" name="category">
                <option value="">Any category</option>
                <?php foreach (all_categories() as $c): ?>
                  <option <?= $f['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="fDept">Department</label>
              <select class="form-select form-select-sm" id="fDept" name="department">
                <option value="">Any department</option>
                <?php foreach (all_departments() as $d): ?>
                  <option <?= $f['department'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="fLevel">Experience level</label>
              <select class="form-select form-select-sm" id="fLevel" name="level">
                <option value="">Any level</option>
                <?php foreach ($levels as $l): ?>
                  <option <?= $f['level'] === $l ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-brand btn-sm w-100" type="submit">Apply filters</button>
          </form>
        </div>
      </section>

      <section class="panel">
        <div class="panel-head"><h3><i class="bi bi-lightbulb me-2"></i>Tip</h3></div>
        <div class="panel-body">
          <p class="small text-muted-2 mb-0">A two-way match means they teach
            something on your learn list and want something on your teach list.
            Those requests get accepted far more often.</p>
        </div>
      </section>
    </div>
  </div>

  <!-- ---------------------- results ---------------------- -->
  <div class="col-lg-8">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
      <div>
        <h1 style="font-size:1.4rem;margin-bottom:2px">Find a partner</h1>
        <p class="text-muted-2 mb-0 small"><?= count($list) ?>
          student<?= count($list) === 1 ? '' : 's' ?> found out of <?= $total ?></p>
      </div>
      <form method="get" action="search.php" class="d-flex align-items-center gap-2">
        <?php foreach ($f as $k => $v): ?>
          <input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>">
        <?php endforeach; ?>
        <label class="form-label mb-0" for="sortBy">Sort by</label>
        <select class="form-select form-select-sm w-auto" id="sortBy" name="sort"
                onchange="this.form.submit()">
          <?php foreach ($sorts as $k => $label): ?>
            <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <div class="d-flex flex-wrap gap-1 mb-3">
      <?php foreach ($chips as [$label, $key]): ?>
        <a class="chip" href="<?= e(url_with([$key => null], 'search.php')) ?>"><?= e($label) ?>
          <i class="bi bi-x-lg" style="font-size:10px"></i></a>
      <?php endforeach; ?>
    </div>

    <div class="d-grid gap-3">
      <?php if (!$order): ?>
        <?= empty_state('person-x',
              'No student matches these filters. Try widening the skill or removing the department.',
              '<a class="btn btn-sm btn-brand" href="search.php">Clear filters</a>') ?>
      <?php else: ?>
        <?php foreach ($order as $uid):
          $u       = null;
          foreach ($list as $cand) { if ((int) $cand['user_id'] === $uid) { $u = $cand; break; } }
          $teach   = $teachOf[$uid] ?? [];
          $learn   = $learnOf[$uid] ?? [];
          $give    = $theyWantITeach[$uid][0] ?? null;   /* mine, on their learn list */
          $take    = $theyTeachIWant[$uid][0] ?? null;   /* theirs, on my learn list  */
          $matched = $give && $take;
          $shown   = array_slice($teach, 0, 4);
          $more    = count($teach) - count($shown);
        ?>
        <article class="panel p-3 hover-lift peekable">
          <div class="d-flex gap-3">
            <?= avatar($u, 48) ?>
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex flex-wrap justify-content-between gap-2">
                <div class="min-w-0">
                  <a href="profile.php?id=<?= $uid ?>"
                     class="fw-semibold text-reset" style="font-size:1rem"><?= e($u['name']) ?></a>
                  <div class="small text-muted-2"><?= e($u['department']) ?> &middot;
                    <?= (int) $meta[$uid]['exchanges'] ?> exchange<?= (int) $meta[$uid]['exchanges'] === 1 ? '' : 's' ?></div>
                </div>
                <div class="text-end">
                  <?= stars($u['avg_rating']) ?>
                  <div class="small text-muted-2" style="font-size:11.5px">
                    <?= $u['total_reviews']
                        ? e($u['avg_rating']) . ' from ' . (int) $u['total_reviews'] . ' review'
                          . ((int) $u['total_reviews'] === 1 ? '' : 's')
                        : 'No reviews yet' ?></div>
                </div>
              </div>

              <p class="small text-muted-2 mt-1 mb-2"><?= e($u['bio']) ?></p>

              <div class="small-label">Can teach</div>
              <div class="d-flex flex-wrap gap-1 mb-2">
                <?php foreach ($shown as $s) { echo skill_chip($s, 'Teach'); } ?>
                <?php if ($more > 0): ?><span class="chip">+<?= $more ?> more</span><?php endif; ?>
                <?php if (!$teach): ?><span class="small text-muted-2">Nothing listed yet</span><?php endif; ?>
              </div>

              <div class="d-flex align-items-center gap-2">
                <span class="small-label">Wants to learn</span>
                <span class="peek-hint">hover for everything</span>
              </div>
              <div class="d-flex flex-wrap gap-1">
                <?php foreach ($learn as $s) { echo skill_chip($s, 'Learn'); } ?>
                <?php if (!$learn): ?><span class="small text-muted-2">Nothing listed yet</span><?php endif; ?>
              </div>

              <!-- the rest of the picture, without leaving the list -->
              <div class="peek"><div class="peek-inner"><div class="peek-body">
                <?php if ($more > 0): ?>
                  <div class="small-label mb-1">All <?= count($teach) ?> skills they teach</div>
                  <div class="d-flex flex-wrap gap-1 mb-2">
                    <?php foreach ($teach as $s) { echo skill_chip($s, 'Teach'); } ?>
                  </div>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-4">
                  <div><div class="small-label">Exchanges</div>
                    <div class="fw-semibold" style="font-size:13px"><?= (int) $meta[$uid]['exchanges'] ?></div></div>
                  <div><div class="small-label">Sessions held</div>
                    <div class="fw-semibold" style="font-size:13px"><?= (int) $meta[$uid]['held'] ?></div></div>
                  <div><div class="small-label">Reviews</div>
                    <div class="fw-semibold" style="font-size:13px"><?= (int) $u['total_reviews'] ?></div></div>
                  <div><div class="small-label">Department</div>
                    <div class="fw-semibold" style="font-size:13px"><?= e($u['department']) ?></div></div>
                </div>
              </div></div></div>
            </div>
          </div>

          <?php if ($matched): ?>
          <div class="mt-3">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="pill pill-accepted"><i class="bi bi-check-circle"></i>Two-way match</span>
              <span class="small text-muted-2">Both of you get something out of this.</span>
            </div>
            <?= swap_card('You teach', $give['skill_name'], 'On their learn list',
                          'You learn', $take['skill_name'], $take['proficiency'] . ' level') ?>
          </div>
          <?php endif; ?>

          <div class="d-flex gap-2 mt-3">
            <?php if ($matched): ?>
              <?= action_button('request.create',
                    ['receiver_id' => $uid,
                     'teach_skill' => (int) $give['skill_id'],
                     'learn_skill' => (int) $take['skill_id']],
                    'Send request', 'send', 'btn-brand') ?>
            <?php else: ?>
              <a class="btn btn-sm btn-outline-brand" href="profile.php?id=<?= $uid ?>">
                <i class="bi bi-send me-1"></i>Choose skills</a>
            <?php endif; ?>
            <a class="btn btn-sm btn-quiet" href="profile.php?id=<?= $uid ?>">View profile</a>
          </div>
        </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php page_close(); ?>
