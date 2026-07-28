<?php
/* =============================================================
   login.php — SELECT the row by email, verify the stored PBKDF2
   hash, put the user_id in the PHP session. Nothing else.
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';

/* Already signed in? Go straight through. */
if (me()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = trim((string) ($_POST['email'] ?? $_GET['email'] ?? ''));
$next  = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) ($_GET['next'] ?? 'dashboard.php'));
$next  = $next ?: 'dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Enter both your email address and your password.';
    } elseif (attempt_login($email, $password)) {
        header('Location: ' . (str_ends_with($next, '.php') ? $next : 'dashboard.php'));
        exit;
    } else {
        $error = 'No account matches that email and password. '
               . 'Every seeded account uses ' . DEMO_PASSWORD . '.';
    }
}

$demo = row('SELECT email FROM users ORDER BY user_id LIMIT 1');

page_head('Sign in');
?>
<body class="bg-white">

<div class="container-fluid">
  <div class="row min-vh-100">

    <aside class="col-lg-5 auth-side d-none d-lg-flex flex-column p-5">
      <a class="brand-mark" style="color:var(--fg-0)" href="index.php">
        <span class="brand-glyph" style="background:var(--mint);color:var(--mint-ink)">
          <i class="bi bi-arrow-left-right"></i></span>
        SkillSwap <span class="brand-tag"
          style="background:transparent;color:var(--fg-2);border-color:var(--line-strong)">NSU</span>
      </a>

      <div class="my-auto" style="max-width:26rem">
        <h1 style="font-size:2.1rem;line-height:1.15">Your classmates already
          know what you are trying to learn.</h1>
        <p class="mt-3 mb-4" style="opacity:.9">Sign in to see who can teach it,
          and what they want from you in return.</p>

        <div class="d-grid gap-3">
          <div class="bullet"><i class="bi bi-arrow-left-right"></i>
            <span>Two-way matches only &mdash; every exchange gives both students something.</span></div>
          <div class="bullet"><i class="bi bi-calendar-check"></i>
            <span>Book sessions online or at a spot on campus.</span></div>
          <div class="bullet"><i class="bi bi-star"></i>
            <span>Ratings come from finished sessions, so they mean something.</span></div>
        </div>
      </div>

      <p class="small mb-0" style="opacity:.75">CSE311L Database Systems Lab
        &middot; North South University</p>
    </aside>

    <main class="col-lg-7 auth-wrap">
      <div class="auth-card">
        <a class="brand-mark d-lg-none mb-4" href="index.php">
          <span class="brand-glyph"><i class="bi bi-arrow-left-right"></i></span>
          SkillSwap <span class="brand-tag">NSU</span>
        </a>

        <h1 style="font-size:1.7rem">Sign in</h1>
        <p class="text-muted-2">Use your North South University email address.</p>

        <div class="panel p-3 mb-3" style="background:var(--brand-050);border-color:var(--brand-100)">
          <div class="small-label mb-1">Demo account</div>
          <div class="small mb-2">The seeded database ships with 50 students who
            all share one password.</div>
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-outline-brand" id="fillDemo" type="button"
                    data-email="<?= e($demo['email'] ?? '') ?>" data-pw="<?= e(DEMO_PASSWORD) ?>">
              <i class="bi bi-person-check me-1"></i>Fill demo credentials</button>
          </div>
        </div>

        <?php if (isset($_GET['registered'])): ?>
          <div class="alert alert-success py-2 small">Account created. Sign in to continue.</div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php?next=<?= e($next) ?>">
          <div class="mb-3">
            <label class="form-label" for="email">Email address</label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted-2"></i></span>
              <input type="email" class="form-control" id="email" name="email"
                     value="<?= e($email) ?>" autocomplete="username"
                     placeholder="name.surname@northsouth.edu" required>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label" for="password">Password</label>
            <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-lock text-muted-2"></i></span>
              <input type="password" class="form-control" id="password" name="password"
                     autocomplete="current-password" placeholder="Your password" required>
              <button class="btn btn-quiet" type="button" id="togglePw"
                      aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="remember" checked>
              <label class="form-check-label small" for="remember">Keep me signed in</label>
            </div>
            <span class="small text-muted-2">Password recovery is out of scope for this lab.</span>
          </div>

          <button class="btn btn-brand w-100 py-2" type="submit">Sign in</button>
        </form>

        <p class="text-center small mt-4 mb-0">New to SkillSwap?
          <a href="register.php">Create an account</a></p>

        <p class="text-center small text-muted-2 mt-4 mb-0">
          Passwords are stored as PBKDF2 hashes, never as plain text.</p>
      </div>
    </main>
  </div>
</div>

<script src="static/vendor/bootstrap.bundle.min.js"></script>
<script src="static/js/app.js"></script>
</body>
</html>
