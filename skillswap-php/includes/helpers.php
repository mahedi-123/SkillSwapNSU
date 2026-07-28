<?php
/* =============================================================
   SkillSwap NSU  —  helpers.php
   -------------------------------------------------------------
   The small render pieces the eleven screens share: escaping,
   date and time formatting, avatars, status pills, star rows,
   skill chips and the swap card. These are the PHP counterparts
   of the same functions in the static build's ui.js, so the two
   versions produce the same markup and use the same stylesheet.
   ============================================================= */

function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/** A GET parameter, trimmed, with a fallback when absent or blank. */
function q(string $name, $fallback = '')
{
    $v = $_GET[$name] ?? null;
    if ($v === null) {
        return $fallback;
    }
    $v = trim((string) $v);
    return $v === '' ? $fallback : $v;
}

/** '2026-07-25' -> '25 Jul 2026' */
function fmt_date(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $t = strtotime(substr($iso, 0, 10));
    return $t ? date('j M Y', $t) : '';
}

/** '15:30:00' -> '3:30 PM' */
function fmt_time(?string $t): string
{
    if (!$t) {
        return '';
    }
    $ts = strtotime('2000-01-01 ' . $t);
    return $ts ? date('g:i A', $ts) : '';
}

function initials(?string $name): string
{
    $parts = preg_split('/\s+/', trim((string) $name)) ?: ['?'];
    $first = mb_substr($parts[0] ?? '?', 0, 1);
    $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/* Deterministic avatar tint so each student keeps the same colour. */
const AV_TINTS = ['#2E1A16', '#38201B', '#291512', '#43271F',
                  '#22110F', '#4E2E25', '#331C18', '#472A22'];

function avatar(?array $user, int $size = 40): string
{
    if (!$user) {
        return '';
    }
    $tint = AV_TINTS[((int) $user['user_id']) % count(AV_TINTS)];
    return '<span class="avatar avatar-' . $size . '" style="background:' . $tint . '"'
         . ' title="' . e($user['name']) . '" aria-hidden="true">'
         . e(initials($user['name'])) . '</span>';
}

const PILL_ICON = [
    'Pending'   => 'hourglass-split', 'Accepted'  => 'check-circle',
    'Completed' => 'patch-check',     'Rejected'  => 'x-circle',
    'Cancelled' => 'slash-circle',    'Scheduled' => 'calendar-check',
];

function pill(?string $status): string
{
    $status = (string) $status;
    $icon   = PILL_ICON[$status] ?? 'circle';
    return '<span class="pill pill-' . e(strtolower($status)) . '">'
         . '<i class="bi bi-' . $icon . '"></i>' . e($status) . '</span>';
}

function mode_pill(?string $mode): string
{
    $icon = $mode === 'Online' ? 'camera-video' : 'geo-alt';
    return '<span class="pill pill-' . e(strtolower((string) $mode)) . '">'
         . '<i class="bi bi-' . $icon . '"></i>' . e($mode) . '</span>';
}

function stars($rating): string
{
    if ($rating === null || $rating === '') {
        return '<span class="text-muted-2 small">No rating yet</span>';
    }
    $rating = (float) $rating;
    $full   = (int) floor($rating);
    $half   = ($rating - $full) >= 0.5;

    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full)                    { $out .= '<i class="bi bi-star-fill"></i>'; }
        elseif ($i === $full + 1 && $half)  { $out .= '<i class="bi bi-star-half"></i>'; }
        else                                { $out .= '<i class="bi bi-star"></i>'; }
    }
    return '<span class="stars">' . $out . '</span>';
}

/** $skill needs skill_name, and optionally proficiency. */
function skill_chip(array $skill, string $kind): string
{
    $cls = $kind === 'Teach' ? 'chip-teach' : 'chip-learn';
    $lvl = !empty($skill['proficiency'])
         ? '<span class="lvl">' . e($skill['proficiency']) . '</span>' : '';
    return '<a class="chip ' . $cls . '" href="search.php?skill='
         . urlencode($skill['skill_name']) . '">' . e($skill['skill_name']) . ' ' . $lvl . '</a>';
}

/* The signature component — one skill trade, both directions. */
function swap_card(string $giveLabel, string $giveSkill, string $giveMeta,
                   string $takeLabel, string $takeSkill, string $takeMeta,
                   bool $even = false): string
{
    return '
  <div class="swap' . ($even ? ' even' : '') . '">
    <div class="swap-side give">
      <div class="small-label">' . e($giveLabel) . '</div>
      <div class="swap-skill">' . e($giveSkill) . '</div>
      <div class="swap-meta">' . e($giveMeta) . '</div>
    </div>
    <div class="swap-badge" aria-hidden="true"><i class="bi bi-arrow-left-right"></i></div>
    <div class="swap-side take">
      <div class="small-label">' . e($takeLabel) . '</div>
      <div class="swap-skill">' . e($takeSkill) . '</div>
      <div class="swap-meta">' . e($takeMeta) . '</div>
    </div>
  </div>';
}

function empty_state(string $icon, string $text, string $ctaHtml = ''): string
{
    return '<div class="empty"><i class="bi bi-' . e($icon) . '"></i><p>'
         . $text . '</p>' . $ctaHtml . '</div>';
}

/** A POST button that runs one write action. */
function action_button(string $action, array $fields, string $label,
                       string $icon = '', string $cls = 'btn-quiet',
                       string $confirm = ''): string
{
    $inputs = '';
    foreach ($fields as $k => $v) {
        $inputs .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    $onsub = $confirm
        ? ' onsubmit="return confirm(' . e(json_encode($confirm)) . ')"' : '';

    return '<form method="post" action="actions.php" class="d-inline"' . $onsub . '>'
         . csrf_field()
         . '<input type="hidden" name="action" value="' . e($action) . '">'
         . '<input type="hidden" name="back" value="' . e(current_url()) . '">'
         . $inputs
         . '<button class="btn btn-sm ' . e($cls) . '" type="submit">'
         . ($icon ? '<i class="bi bi-' . e($icon) . ' me-1"></i>' : '')
         . e($label) . '</button></form>';
}

/** Where a write action should send the reader back to. */
function current_url(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
    return basename(parse_url($uri, PHP_URL_PATH) ?: 'dashboard.php')
         . (($qs = parse_url($uri, PHP_URL_QUERY)) ? '?' . $qs : '');
}

/** Rebuild the current query string with some keys replaced. */
function url_with(array $changes, string $page = ''): string
{
    $params = $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null || $v === '') { unset($params[$k]); }
        else                          { $params[$k] = $v; }
    }
    $page = $page ?: basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    return $page . ($params ? '?' . http_build_query($params) : '');
}
