#!/usr/bin/env python3
"""Read the theme tokens straight out of style.css and check every
text-on-background pair the interface actually uses against WCAG."""
import re, sys

import os
HERE = os.path.dirname(os.path.abspath(__file__))
CSS = open(os.path.join(HERE, '..', 'static', 'css', 'style.css')).read()

tok = dict(re.findall(r'--([\w-]+):\s*(#[0-9A-Fa-f]{6});', CSS))
need = ['bg-0', 'bg-1', 'bg-2', 'bg-3', 'fg-0', 'fg-1', 'fg-2', 'fg-3',
        'ember', 'ember-dim', 'ember-ink', 'line', 'line-strong']
missing = [t for t in need if t not in tok]
if missing:
    print('tokens not found in style.css:', missing)
    sys.exit(1)


def lin(c):
    c /= 255
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def lum(h):
    h = h.lstrip('#')
    r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
    return .2126 * lin(r) + .7152 * lin(g) + .0722 * lin(b)


def ratio(a, b):
    la, lb = lum(a), lum(b)
    hi, lo = max(la, lb), min(la, lb)
    return (hi + .05) / (lo + .05)


T = tok
# label, foreground, background, minimum
CHECKS = [
    ('headings on the page',        T['fg-0'], T['bg-0'], 4.5),
    ('headings on a card',          T['fg-0'], T['bg-2'], 4.5),
    ('headings on a raised card',   T['fg-0'], T['bg-3'], 4.5),
    ('body text on a card',         T['fg-1'], T['bg-2'], 4.5),
    ('secondary text on a card',    T['fg-2'], T['bg-2'], 4.5),
    ('mono labels on a card',       T['fg-3'], T['bg-2'], 4.5),
    ('mono labels in a form well',  T['fg-3'], T['bg-1'], 4.5),
    ('links and accents on a card', T['ember'], T['bg-2'], 4.5),
    ('accents on the page',         T['ember'], T['bg-0'], 4.5),
    ('accent hover state',          T['ember-dim'], T['bg-2'], 4.5),
    ('label on the primary button', T['ember-ink'], T['ember'], 4.5),
    ('table header text',           T['fg-2'], T['bg-1'], 4.5),
    ('input text in its well',      T['fg-0'], T['bg-1'], 4.5),
    # non-text: borders only need to be perceivable
    ('rejected/ash text on a card', '#C9A9A2', T['bg-2'], 4.5),
    ('hairline against a card',     T['line-strong'], T['bg-2'], 1.4),
    ('hairline against the page',   T['line'], T['bg-0'], 1.15),
]

fails = 0
print(f"ground {T['bg-0']}   card {T['bg-2']}   accent {T['ember']}\n")
for label, fg, bg, floor in CHECKS:
    r = ratio(fg, bg)
    ok = r >= floor
    if not ok:
        fails += 1
    print(f"{'ok  ' if ok else 'FAIL'} {label:30s} {r:5.2f}:1   floor {floor}")

print()
print('every pair clears WCAG' if not fails else f'{fails} pair(s) below the floor')
sys.exit(1 if fails else 0)
