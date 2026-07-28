<?php
/* =============================================================
   edit-profile.php — the three things a student owns about
   himself: his users row, his userskills rows and his password.

   Nothing here is written by this page. Each panel is a form that
   posts one action to actions.php, which runs a single UPDATE,
   INSERT or DELETE and redirects back. The reads are the interesting
   part: the skill table is the userskills bridge joined to the
   catalogue, so every row carries its own user_skill_id and that
   one key is all a level change or a removal needs.
   ============================================================= */

require_once __DIR__ . '/includes/layout.php';
require_login();

$me    = me_id();
$u     = me();
$depts = all_departments();
$teach = skills_of($me, 'Teach');
$learn = skills_of($me, 'Learn');
$mine  = array_merge($teach, $learn);
$back  = current_url();

/* The catalogue, grouped by category so the add form can use
   <optgroup> the way the static build did. */
$catalogue = [];
foreach (all_skills() as $s) {
    $catalogue[$s['category']][] = $s;
}

const LEVELS = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];

page_open('Edit profile', 'edit', 'edit');
?>

<div class="mb-3">
  <h1 style="font-size:1.4rem;margin-bottom:2px">Edit profile</h1>
  <p class="text-muted-2 small mb-0">Your skills decide who the platform
    matches you with, so keep both lists current.</p>
</div>

<!-- ------------------ details ------------------ -->
<section class="panel">
  <div class="panel-head"><h2><i class="bi bi-person-vcard me-2" style="color:var(--brand)"></i>Your details</h2></div>
  <div class="panel-body">
    <form method="post" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile.update">
      <input type="hidden" name="back" value="<?= e($back) ?>">

      <div class="d-flex flex-wrap align-items-center gap-3 mb-4 pb-3 border-bottom"
           style="border-color:var(--line)!important">
        <?= avatar($u, 88) ?>
        <div>
          <div class="small-label">Profile picture</div>
          <div class="form-text mt-1">The seeded rows all carry
            <code>users.profile_picture = 'default.png'</code>, so this build draws the
            initials instead of serving a file. The column is still there for uploads.</div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-sm-6">
          <label class="form-label" for="name">Full name</label>
          <input class="form-control" id="name" name="name" required
                 value="<?= e($u['name']) ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="dept">Department</label>
          <select class="form-select" id="dept" name="department">
            <?php foreach ($depts as $d): ?>
              <option <?= $d === $u['department'] ? 'selected' : '' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="email">Email address</label>
          <input class="form-control" id="email" readonly value="<?= e($u['email']) ?>">
          <div class="form-text">Your email is your login and cannot be changed here.</div>
        </div>
        <div class="col-12">
          <label class="form-label" for="bio">Bio</label>
          <textarea class="form-control" id="bio" name="bio" rows="3"
                    maxlength="255"><?= e($u['bio']) ?></textarea>
          <div class="form-text">Up to 255 characters, the width of the column.</div>
        </div>
        <div class="col-12">
          <button class="btn btn-brand btn-sm" type="submit">Save changes</button>
          <a class="btn btn-quiet btn-sm" href="profile.php?id=<?= $me ?>">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</section>

<!-- ------------------ skills ------------------ -->
<section class="panel">
  <div class="panel-head">
    <h2><i class="bi bi-list-check me-2" style="color:var(--brand)"></i>Your skills</h2>
    <span class="small text-muted-2"><?= count($teach) ?> teaching,
      <?= count($learn) ?> learning</span>
  </div>

  <div class="panel-body">
    <form method="post" action="actions.php"
          class="row g-3 align-items-end mb-3 pb-3 border-bottom"
          style="border-color:var(--line)!important">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="skill.add">
      <input type="hidden" name="back" value="<?= e($back) ?>">

      <div class="col-sm-5">
        <label class="form-label" for="newSkill">Add a skill</label>
        <select class="form-select form-select-sm" id="newSkill" name="skill_id">
          <?php foreach ($catalogue as $cat => $list): ?>
            <optgroup label="<?= e($cat) ?>">
              <?php foreach ($list as $s): ?>
                <option value="<?= (int) $s['skill_id'] ?>"><?= e($s['skill_name']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3">
        <label class="form-label" for="newType">I want to</label>
        <select class="form-select form-select-sm" id="newType" name="skill_type">
          <option value="Teach">Teach it</option>
          <option value="Learn">Learn it</option>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label" for="newLevel">Level</label>
        <select class="form-select form-select-sm" id="newLevel" name="proficiency">
          <?php foreach (LEVELS as $l): ?>
            <option <?= $l === 'Advanced' ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2 d-grid">
        <button class="btn btn-brand btn-sm" type="submit">Add</button>
      </div>
      <div class="col-12"><div class="form-text mb-0">UNIQUE (user_id, skill_id, skill_type)
        keeps the same skill from appearing twice on one list.</div></div>
    </form>
  </div>

  <?php if (!$mine): ?>
    <div class="panel-body pt-0">
      <?= empty_state('list-check', 'No skill listed yet. Add your first one above.') ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-clean">
        <thead><tr><th>Skill</th><th>Category</th><th>Type</th><th>Level</th>
                   <th class="text-end">Action</th></tr></thead>
        <tbody>
          <?php foreach ($mine as $r):
            $usid = (int) $r['user_skill_id'];
            $isT  = $r['skill_type'] === 'Teach';
          ?>
          <tr>
            <td class="fw-semibold"><?= e($r['skill_name']) ?></td>
            <td class="text-muted-2"><?= e($r['category']) ?></td>
            <td><span class="pill <?= $isT ? 'pill-online' : 'pill-pending' ?>">
                  <i class="bi bi-<?= $isT ? 'megaphone' : 'book' ?>"></i>
                  <?= e($r['skill_type']) ?></span></td>
            <td>
              <form method="post" action="actions.php" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="skill.level">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <input type="hidden" name="user_skill_id" value="<?= $usid ?>">
                <select class="form-select form-select-sm w-auto d-inline-block"
                        name="proficiency" onchange="this.form.submit()">
                  <?php foreach (LEVELS as $l): ?>
                    <option <?= $l === $r['proficiency'] ? 'selected' : '' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td class="text-end">
              <?= action_button('skill.remove', ['user_skill_id' => $usid],
                    'Remove', 'trash', 'btn-quiet',
                    'Remove ' . $r['skill_name'] . ' from your ' . strtolower($r['skill_type']) . ' list?') ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- ------------------ password ------------------ -->
<section class="panel">
  <div class="panel-head"><h2><i class="bi bi-key me-2" style="color:var(--brand)"></i>Change password</h2></div>
  <div class="panel-body">
    <form method="post" action="actions.php" class="row g-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password.change">
      <input type="hidden" name="back" value="<?= e($back) ?>">

      <div class="col-sm-4">
        <label class="form-label" for="oldPw">Current password</label>
        <input type="password" class="form-control" id="oldPw" name="current" required>
      </div>
      <div class="col-sm-4">
        <label class="form-label" for="newPw">New password</label>
        <input type="password" class="form-control" id="newPw" name="fresh" required>
      </div>
      <div class="col-sm-4">
        <label class="form-label" for="newPw2">Confirm new password</label>
        <input type="password" class="form-control" id="newPw2" name="again" required>
      </div>
      <div class="col-12">
        <button class="btn btn-brand btn-sm" type="submit">Update password</button>
        <span class="small text-muted-2 ms-2">Checked against, and stored as, a
          Werkzeug pbkdf2 hash &mdash; the column never holds the password itself.</span>
      </div>
    </form>
  </div>
</section>

<?php page_close(); ?>
