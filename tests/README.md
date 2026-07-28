# Tests

Seven suites that run the real pages headlessly and check what they actually
produce. They exist because a interface that looks right in one browser tab is
not the same as one that behaves.

## Running them

```bash
npm install        # pulls jsdom
npm test           # the six JavaScript suites
npm run test:contrast   # needs python3
```

Individual suites:

```bash
npm run test:pages      # every page loads and renders without a script error
npm run test:content    # the screens show the seeded rows, in the right counts
npm run test:clicks     # buttons change the data — accept, book, review, delete
npm run test:hero       # the dice, popular chips, marquee and category menu
npm run test:motion     # the seven landing-page animations
npm run test:preview    # preview.html carries all eleven screens
npm run test:contrast   # every colour pair against WCAG
```

## What each one covers

| Suite | Checks |
| ----- | ------ |
| `pages` | All eleven pages execute cleanly, no container renders empty |
| `content` | Row counts match the seed: 49 other students in search, 50 in admin, 11 departments, 11 categories |
| `clicks` | Accept, decline, cancel, send, book, reschedule, complete, review, delete — and that a duplicate skill or a past date is refused |
| `hero` | The dice only ever lands on a real catalogue skill and never repeats twice running; category counts add to 50 |
| `motion` | Tilt stays inside 3.5°, the spotlight never starts on a touch device, every review quote is a real row, reduced motion switches all of it off |
| `preview` | `preview.html` holds all eleven screens and its controls still work |
| `contrast` | Fifteen text-on-background pairs against WCAG; the floor is 4.5:1 |

## Rebuilding the preview

`preview.html` is generated, not written by hand. After changing any page:

```bash
npm run build:preview
```

It renders each real page, captures the resulting markup, and inlines the
project's own stylesheet — so the preview cannot drift from the interface.
