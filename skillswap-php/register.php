<?php
/* =============================================================
   register.php — one account, three INSERTs, one transaction.
   The row goes into users with a PBKDF2 hash in the password
   column, and the two skill choices go into userskills against
   the id AUTO_INCREMENT just handed back. Either all three land
   or none of them do.
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';

/* Already signed in? There is nothing to register. */
if (me()) {
    header('Location: dashboard.php');
    exit;
}

$departments = all_departments();
$skills      = all_skills();
$levels      = ['Intermediate', 'Advanced', 'Expert'];

/* The catalogue, grouped so each select can carry optgroups. */
$byCategory = [];
foreach ($skills as $s) {
    $byCategory[$s['category']][] = $s;
}
$skillIds = array_map('intval', array_column($skills, 'skill_id'));

/* What the reader typed, kept so a failed POST redisplays it. */
$in = [
    'name'       => trim((string) ($_POST['name'] ?? '')),
    'email'      => strtolower(trim((string) ($_POST['email'] ?? ''))),
    'department' => (string) ($_POST['department'] ?? ''),
    'bio'        => trim((string) ($_POST['bio'] ?? '')),
    'teach'      => (string) ($_POST['teach_skill'] ?? ''),
    'level'      => (string) ($_POST['teach_level'] ?? 'Intermediate'),
    'learn'      => (string) ($_POST['learn_skill'] ?? ''),
    'agree'      => isset($_POST['agree']),
];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password2'] ?? '');

    /* The token first: a form that cannot prove where it came from
       never reaches the validation, let alone the database. */
    if (!csrf_ok()) {
        $err['form'] = 'This form went stale. Please fill it in again.';
    } else {

        if (mb_strlen($in['name']) < 3) {
            $err['name'] = 'Enter your full name.';
        }
        if (!in_array($in['department'], $departments, true)) {
            $err['department'] = 'Choose your department.';
        }

        if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            $err['email'] = 'Enter a valid email address.';
        } elseif (!str_ends_with($in['email'], '@northsouth.edu')) {
            $err['email'] = 'Use your North South University address, ending in @northsouth.edu.';
        } elseif (val('SELECT user_id FROM users WHERE email = ?', [$in['email']])) {
            /* users.email carries a UNIQUE key; this SELECT only
               lets us say so politely before MySQL says it rudely. */
            $err['email'] = 'That email is already registered. Try signing in instead.';
        }

        if (strlen($password) < 8) {
            $err['password'] = 'Use at least 8 characters.';
        }
        if ($confirm !== $password) {
            $err['password2'] = 'The two passwords do not match.';
        }

        if (!in_array((int) $in['teach'], $skillIds, true)) {
            $err['teach_skill'] = 'Pick one skill you can teach.';
        }
        if (!in_array($in['level'], $levels, true)) {
            $in['level'] = 'Intermediate';
        }

        if (!in_array((int) $in['learn'], $skillIds, true)) {
            $err['learn_skill'] = 'Pick one skill you want to learn.';
        } elseif ((int) $in['learn'] === (int) $in['teach']) {
            $err['learn_skill'] = 'Choose a different skill from the one you teach.';
        }

        if (!$in['agree']) {
            $err['agree'] = 'Please confirm this before continuing.';
        }
        if (mb_strlen($in['bio']) > 255) {
            $err['bio'] = 'Keep the bio under 255 characters.';
        }
    }

    /* Three statements, one unit of work. If the second INSERT
       fails on the UNIQUE key, the first must not survive it. */
    if (!$err) {
        try {
            tx_begin();

            run('INSERT INTO users (name, email, password, department, bio, profile_picture)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$in['name'], $in['email'], password_make($password), $in['department'],
                 $in['bio'] !== '' ? $in['bio'] : 'New to SkillSwap NSU.', 'default.png']);

            $userId = last_id();

            run('INSERT INTO userskills (user_id, skill_id, skill_type, proficiency)
                 VALUES (?, ?, ?, ?)',
                [$userId, (int) $in['teach'], 'Teach', $in['level']]);

            run('INSERT INTO userskills (user_id, skill_id, skill_type, proficiency)
                 VALUES (?, ?, ?, ?)',
                [$userId, (int) $in['learn'], 'Learn', 'Beginner']);

            tx_commit();

            flash('Account created for ' . $in['name'] . '.',
                  'INSERT INTO users (name, email, password, department, bio) VALUES (?, ?, ?, ?, ?), '
                . 'then two INSERTs into userskills, all inside one transaction.');

            header('Location: login.php?registered=1&email=' . urlencode($in['email']));
            exit;

        } catch (Throwable $ex) {
            tx_undo();
            $err['form'] = 'The account could not be created, so nothing was written: '
                         . $ex->getMessage();
        }
    }
}

page_head('Create account');
?>
<body class="bg-white">

<div class="container-fluid">
  <div class="row min-vh-100">

    <aside class="col-lg-4 auth-side d-none d-lg-flex flex-column p-5">
      <a class="brand-mark" style="color:var(--fg-0)" href="index.php">
        <span class="brand-glyph" style="background:var(--mint);color:var(--mint-ink)">
          <i class="bi bi-arrow-left-right"></i></span>
        SkillSwap <span class="brand-tag"
          style="background:transparent;color:var(--fg-2);border-color:var(--line-strong)">NSU</span>
      </a>

      <div class="my-auto" style="max-width:24rem">
        <h1 style="font-size:1.95rem;line-height:1.15">One profile, two lists.</h1>
        <p class="mt-3 mb-4" style="opacity:.9">Tell us what you can teach and
          what you want to learn. Matching does the rest.</p>
        <div class="d-grid gap-3">
          <div class="bullet"><i class="bi bi-mortarboard"></i>
            <span>Open to students of all <?= count($departments) ?> NSU departments.</span></div>
          <div class="bullet"><i class="bi bi-cash-stack"></i>
            <span>No fees, no payments, no wallets. Skills only.</span></div>
          <div class="bullet"><i class="bi bi-shield-check"></i>
            <span>Your password is hashed before it reaches the database.</span></div>
        </div>
      </div>

      <p class="small mb-0" style="opacity:.75">CSE311L Database Systems Lab</p>
    </aside>

    <main class="col-lg-8 auth-wrap">
      <div class="auth-card" style="max-width:560px">
        <a class="brand-mark d-lg-none mb-4" href="index.php">
          <span class="brand-glyph"><i class="bi bi-arrow-left-right"></i></span>
          SkillSwap <span class="brand-tag">NSU</span>
        </a>

        <h1 style="font-size:1.7rem">Create your account</h1>
        <p class="text-muted-2">Takes about a minute. You can add more skills later.</p>

        <?php if ($err): ?>
          <div class="alert alert-danger py-2 small">
            <?php if (isset($err['form'])): ?>
              <?= e($err['form']) ?>
            <?php else: ?>
              <strong>Nothing was written.</strong> Fix the following and send it again:
              <ul class="mb-0 mt-1 ps-3">
                <?php foreach ($err as $message): ?>
                  <li><?= e($message) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="post" action="register.php" novalidate>
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label" for="name">Full name</label>
              <input class="form-control" id="name" name="name" value="<?= e($in['name']) ?>"
                     placeholder="e.g. Nusrat Jahan" required>
              <div class="invalid-feedback d-block small"><?= e($err['name'] ?? '') ?></div>
            </div>

            <div class="col-sm-6">
              <label class="form-label" for="department">Department</label>
              <select class="form-select" id="department" name="department" required>
                <option value="">Choose your department</option>
                <?php foreach ($departments as $d): ?>
                  <option<?= $d === $in['department'] ? ' selected' : '' ?>><?= e($d) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback d-block small"><?= e($err['department'] ?? '') ?></div>
            </div>

            <div class="col-12">
              <label class="form-label" for="email">NSU email address</label>
              <input type="email" class="form-control" id="email" name="email"
                     value="<?= e($in['email']) ?>" autocomplete="username"
                     placeholder="name.surname@northsouth.edu" required>
              <div class="form-text">Must be unique &mdash; the
                <code>users.email</code> column has a UNIQUE constraint.</div>
              <div class="invalid-feedback d-block small"><?= e($err['email'] ?? '') ?></div>
            </div>

            <div class="col-sm-6">
              <label class="form-label" for="password">Password</label>
              <div class="input-group">
                <input type="password" class="form-control" id="password" name="password"
                       autocomplete="new-password" placeholder="At least 8 characters" required>
                <button class="btn btn-quiet" type="button" data-toggle-password
                        aria-label="Show password"><i class="bi bi-eye"></i></button>
              </div>
              <div class="invalid-feedback d-block small"><?= e($err['password'] ?? '') ?></div>
            </div>

            <div class="col-sm-6">
              <label class="form-label" for="password2">Confirm password</label>
              <input type="password" class="form-control" id="password2" name="password2"
                     autocomplete="new-password" required>
              <div class="invalid-feedback d-block small"><?= e($err['password2'] ?? '') ?></div>
            </div>

            <div class="col-12">
              <label class="form-label" for="bio">Short bio
                <span class="text-muted-2 fw-normal">(optional)</span></label>
              <textarea class="form-control" id="bio" name="bio" rows="2" maxlength="255"
                        placeholder="One or two lines about how you like to teach or learn."><?= e($in['bio']) ?></textarea>
              <div class="form-text">Up to 255 characters, the width of the column.</div>
              <div class="invalid-feedback d-block small"><?= e($err['bio'] ?? '') ?></div>
            </div>

            <div class="col-sm-6">
              <label class="form-label" for="teach_skill">A skill you can teach</label>
              <select class="form-select" id="teach_skill" name="teach_skill" required>
                <option value="">Choose a skill</option>
                <?php foreach ($byCategory as $category => $group): ?>
                  <optgroup label="<?= e($category) ?>">
                    <?php foreach ($group as $s): ?>
                      <option value="<?= (int) $s['skill_id'] ?>"
                        <?= (string) $s['skill_id'] === $in['teach'] ? ' selected' : '' ?>>
                        <?= e($s['skill_name']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback d-block small"><?= e($err['teach_skill'] ?? '') ?></div>
            </div>

            <div class="col-sm-6">
              <label class="form-label" for="teach_level">Your level in it</label>
              <select class="form-select" id="teach_level" name="teach_level">
                <?php foreach ($levels as $lvl): ?>
                  <option<?= $lvl === $in['level'] ? ' selected' : '' ?>><?= e($lvl) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Stored in <code>userskills.proficiency</code>.</div>
            </div>

            <div class="col-12">
              <label class="form-label" for="learn_skill">A skill you want to learn</label>
              <select class="form-select" id="learn_skill" name="learn_skill" required>
                <option value="">Choose a skill</option>
                <?php foreach ($byCategory as $category => $group): ?>
                  <optgroup label="<?= e($category) ?>">
                    <?php foreach ($group as $s): ?>
                      <option value="<?= (int) $s['skill_id'] ?>"
                        <?= (string) $s['skill_id'] === $in['learn'] ? ' selected' : '' ?>>
                        <?= e($s['skill_name']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback d-block small"><?= e($err['learn_skill'] ?? '') ?></div>
            </div>

            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="agree" name="agree"
                       value="1"<?= $in['agree'] ? ' checked' : '' ?> required>
                <label class="form-check-label small" for="agree">
                  I understand SkillSwap NSU is for skill exchange only and no
                  money is involved.</label>
                <div class="invalid-feedback d-block small"><?= e($err['agree'] ?? '') ?></div>
              </div>
            </div>

            <div class="col-12 d-grid">
              <button class="btn btn-brand py-2" type="submit">Create account</button>
            </div>
          </div>
        </form>

        <p class="text-center small mt-4 mb-0">Already registered?
          <a href="login.php">Sign in</a></p>

        <p class="text-center small text-muted-2 mt-4 mb-0">
          The password you choose is turned into a PBKDF2 hash before the INSERT
          runs, so the <code>users.password</code> column never holds plain text.</p>
      </div>
    </main>
  </div>
</div>

<script src="static/vendor/bootstrap.bundle.min.js"></script>
<script src="static/js/app.js"></script>
</body>
</html>
