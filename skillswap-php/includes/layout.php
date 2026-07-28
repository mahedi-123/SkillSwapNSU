<?php
/* =============================================================
   SkillSwap NSU  —  layout.php
   -------------------------------------------------------------
   The page shell: <head>, the top bar, the left rail and the
   footer. In the static build this markup was written by
   mountShell() and profileRail() in ui.js; here it is rendered
   on the server before the page is sent.

   Every asset is served from this folder, so the site works with
   the network cable unplugged — which matters when it is being
   demonstrated from a pen drive on somebody else's laptop.
   ============================================================= */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/queries.php';

function page_head(string $title, bool $wide = false): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> &mdash; SkillSwap NSU</title>
<link href="static/vendor/fonts.css" rel="stylesheet">
<link href="static/vendor/bootstrap.min.css" rel="stylesheet">
<link href="static/vendor/icons/bootstrap-icons.min.css" rel="stylesheet">
<link href="static/css/style.css" rel="stylesheet">
</head>
<body>
<?php
}

/* The blue-collar version of the static build's demo banner: this
   one says the opposite, because here the writes are real. */
function live_note(): void
{
    $u = me();
    ?>
<div class="demo-note">
  <div class="container d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <span><i class="bi bi-database-check me-1"></i>Live build &mdash; signed in as
      <strong><?= e($u['name']) ?></strong>. Every button below runs real SQL against
      the <code>skillexchange</code> database on this machine.</span>
    <span class="d-flex gap-3 align-items-center">
      <a href="logout.php" class="text-decoration-underline">Sign out</a>
    </span>
  </div>
</div>
<?php
}

function top_bar(string $active): void
{
    $u    = me();
    $pend = pending_received((int) $u['user_id']);

    $nav = [
        ['dashboard', 'dashboard.php', 'house-door',       'Home'],
        ['search',    'search.php',    'search',           'Find'],
        ['requests',  'requests.php',  'arrow-left-right', 'Requests'],
        ['sessions',  'sessions.php',  'calendar-event',   'Sessions'],
        ['reviews',   'reviews.php',   'star',             'Reviews'],
    ];
    ?>
<nav class="topbar">
  <div class="container d-flex align-items-center gap-3 py-1">
    <a class="brand-mark" href="dashboard.php">
      <span class="brand-glyph"><i class="bi bi-arrow-left-right"></i></span>
      SkillSwap <span class="brand-tag">NSU</span>
    </a>

    <form class="nav-search flex-grow-1 d-none d-md-block" role="search" action="search.php" method="get">
      <div class="input-group input-group-sm">
        <span class="input-group-text bg-transparent border-0 pe-1"
              style="background:var(--brand-050)!important"><i class="bi bi-search text-muted-2"></i></span>
        <input class="form-control border-0" name="q" type="search"
               value="<?= e($_GET['q'] ?? '') ?>"
               placeholder="Search students, skills or departments" aria-label="Search">
      </div>
    </form>

    <div class="d-flex align-items-center ms-auto">
      <?php foreach ($nav as [$key, $href, $icon, $label]): ?>
        <a class="topnav-link position-relative <?= $key === $active ? 'active' : '' ?>"
           href="<?= $href ?>"><i class="bi bi-<?= $icon ?>"></i><?php
          if ($key === 'requests' && $pend): ?><span class="badge-dot pulse"><?= $pend ?></span><?php
          endif; ?><span><?= $label ?></span></a>
      <?php endforeach; ?>

      <div class="dropdown ms-2">
        <a class="topnav-link" href="#" data-bs-toggle="dropdown" aria-expanded="false">
          <?= avatar($u, 24) ?><span>Me <i class="bi bi-caret-down-fill" style="font-size:.55rem"></i></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:230px">
          <li class="px-3 py-2 d-flex gap-2 align-items-center">
            <?= avatar($u, 40) ?>
            <div class="lh-sm">
              <div class="fw-semibold"><?= e($u['name']) ?></div>
              <div class="small text-muted-2"><?= e($u['department']) ?></div>
            </div>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="profile.php?id=<?= (int) $u['user_id'] ?>">
                <i class="bi bi-person me-2"></i>View profile</a></li>
          <li><a class="dropdown-item" href="edit-profile.php">
                <i class="bi bi-pencil-square me-2"></i>Edit profile &amp; skills</a></li>
          <li><a class="dropdown-item" href="admin.php">
                <i class="bi bi-shield-lock me-2"></i>Admin console</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Sign out</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<?php
}

function profile_rail(string $active): void
{
    $u     = me();
    $id    = (int) $u['user_id'];
    $r     = rating_of($id);
    $teach = count(skills_of($id, 'Teach'));
    $learn = count(skills_of($id, 'Learn'));
    $counts = request_status_counts($id);
    $exch  = array_sum($counts);
    $pend  = pending_received($id);
    $upc   = upcoming_count($id);

    $items = [
        ['dashboard', 'dashboard.php', 'house-door',       'Home',           ''],
        ['search',    'search.php',    'search',           'Find a partner', ''],
        ['requests',  'requests.php',  'arrow-left-right', 'Requests',       $pend ?: ''],
        ['sessions',  'sessions.php',  'calendar-event',   'Sessions',       $upc ?: ''],
        ['reviews',   'reviews.php',   'star',             'Reviews',        $r['count'] ?: ''],
        ['profile',   'profile.php?id=' . $id, 'person',   'My profile',     ''],
        ['edit',      'edit-profile.php', 'pencil-square', 'Edit profile',   ''],
        ['admin',     'admin.php',     'shield-lock',      'Admin console',  ''],
    ];
    ?>
<div class="panel overflow-hidden">
  <div class="profile-card-cover"></div>
  <div class="profile-card-body">
    <?= avatar($u, 88) ?>
    <h3 class="mt-2 mb-0" style="font-size:1.02rem"><?= e($u['name']) ?></h3>
    <div class="small text-muted-2"><?= e($u['department']) ?> &middot; NSU</div>
    <div class="mt-2 d-flex justify-content-center align-items-center gap-1">
      <?= stars($r['avg']) ?>
      <span class="small text-muted-2"><?= $r['count'] ? e($r['avg']) . ' (' . $r['count'] . ')' : '' ?></span>
    </div>
    <div class="d-flex justify-content-center gap-3 mt-3 pt-3 border-top">
      <div><div class="fw-bold" style="color:var(--brand-700)"><?= $teach ?></div>
           <div class="small text-muted-2" style="font-size:11.5px">Teaching</div></div>
      <div><div class="fw-bold" style="color:var(--brand-700)"><?= $learn ?></div>
           <div class="small text-muted-2" style="font-size:11.5px">Learning</div></div>
      <div><div class="fw-bold" style="color:var(--brand-700)"><?= $exch ?></div>
           <div class="small text-muted-2" style="font-size:11.5px">Exchanges</div></div>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-body tight">
    <nav class="side-nav d-grid gap-1">
      <?php foreach ($items as [$k, $href, $icon, $label, $count]): ?>
        <a class="nav-link <?= $k === $active ? 'active' : '' ?>" href="<?= $href ?>">
          <i class="bi bi-<?= $icon ?>"></i><span><?= $label ?></span>
          <?php if ($count): ?><span class="count<?= $k === 'requests' ? ' pulse' : '' ?>"><?= $count ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
<?php
}

/* Every write action leaves a message here naming the statement
   it ran, so the data layer explains itself during the demo. */
function flash_toasts(): void
{
    $flashes = take_flashes();
    if (!$flashes) {
        return;
    }
    ?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080">
  <?php foreach ($flashes as $f): ?>
  <div class="toast align-items-center text-bg-dark border-0 show" role="status">
    <div class="d-flex">
      <div class="toast-body">
        <i class="bi bi-<?= $f['kind'] === 'bad' ? 'exclamation-triangle' : 'database-check' ?> me-1"></i>
        <?= e($f['text']) ?>
        <?php if (!empty($f['sql'])): ?>
          <div class="mt-1 small" style="font-family:var(--data);opacity:.75;word-break:break-word">
            <?= e($f['sql']) ?>
          </div>
        <?php endif; ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto"
              data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php
}

function page_foot(bool $withScripts = true): void
{
    ?>
<footer class="site-footer py-3 mt-4">
  <div class="container small d-flex flex-wrap justify-content-between gap-2">
    <span>SkillSwap NSU &middot; CSE311L Database Systems Lab &middot; PHP + MySQL build</span>
    <span>&copy; <?= date('Y') ?></span>
  </div>
</footer>
<?php flash_toasts(); ?>
<?php if ($withScripts): ?>
<script src="static/vendor/bootstrap.bundle.min.js"></script>
<script src="static/js/app.js"></script>
<?php endif; ?>
</body>
</html>
<?php
}

/* Convenience: the whole signed-in chrome in one call. */
function page_open(string $title, string $active, string $railActive = ''): void
{
    require_login();
    page_head($title);
    live_note();
    top_bar($active);
    echo '<main class="container py-4"><div class="row g-4">',
         '<div class="col-lg-3"><div class="rail-sticky">';
    profile_rail($railActive ?: $active);
    echo '</div></div><div class="col-lg-9">';
}

function page_close(): void
{
    echo '</div></div></main>';
    page_foot();
}
