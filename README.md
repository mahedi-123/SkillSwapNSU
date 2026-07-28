# SkillSwap NSU

A student skill-exchange platform for North South University. Students list what
they can teach and what they want to learn; the platform finds two-way matches,
they book a session, and afterwards they rate each other. No money is involved
anywhere in the system.

**CSE311L — Database Systems Lab**

---

## Repository layout

```
SkillSwapNSU/
├── index.html              Landing page
├── login.html              Sign in
├── register.html           Create account
├── dashboard.html          Student home — matches, requests, next sessions
├── search.html             Find a partner (skill / category / department / level / name)
├── profile.html            Public profile  (profile.html?id=19)
├── edit-profile.html       Edit details, manage skills, change password
├── requests.html           Exchange requests — received / sent / all
├── sessions.html           Sessions — upcoming / completed / cancelled + booking
├── reviews.html            Reviews received and written, leave a review
├── admin.html              Admin console — students, skills, requests, moderation
├── preview.html            Generated: all eleven screens in one file
│
├── README.md               This file
├── PUBLISH.md              Step-by-step GitHub and Pages instructions
├── package.json            Test and build scripts
├── .nojekyll               Required by GitHub Pages — do not delete
│
├── database/
│   ├── schema.sql              Tables, keys, constraints, indexes, views
│   ├── seed.sql                Sample data for every table
│   ├── queries.sql             Every SQL feature the lab requires
│   └── skillexchange_full.sql  schema + seed in one file (easiest import)
│
├── static/
│   ├── css/style.css       The whole theme
│   └── js/
│       ├── data.js         Export of seed.sql, used only by the static build
│       └── ui.js           Shared navigation shell, lookups, render helpers
│
├── tools/
│   └── build-preview.js    Regenerates preview.html from the real pages
│
├── tests/                  Seven suites — see tests/README.md
└── uploads/                Profile pictures at runtime (Flask build)
```

---

---

## Theme

A dark ground carrying a red cast, with a single ember accent.

| Role | Value |
| ---- | ----- |
| Page | `#140A09`, lit by three fixed radial glows (`#4A1B18`, `#3A1413`, `#2A100E`) |
| Cards | `#20120F`, raised `#2E1A16` |
| Text | `#FBF3F1` headings, `#DCC8C4` body, `#9A807C` mono labels |
| Accent | `#FF5A4D` (`#FF7A66` on hover, `#1A0705` for text sitting on it) |
| Hairlines | `#33201C`, `#4A2F29` |

The accent is spent deliberately: the primary button, the active nav marker, the
headline underline, marquee counts, and focus rings. Because the accent is red,
rejection is drawn in ash (`#C9A9A2`) rather than red — two reds would collapse
"this is the main action" and "this went wrong" into the same signal.

Every text-on-background pair is checked against WCAG by `test_contrast.py`;
the lowest is 4.98:1 against a 4.5 floor.

## Motion

| # | Piece | What it does | Where |
| - | ----- | ------------ | ----- |
| | Ask box | The question the product answers, with a dice that pulls a real skill from the catalogue | Home |
| | Marquee | Every skill that has a teacher, looping; track written twice so there is no seam, pauses on hover | Home |
| | Category menu | Eleven categories with live counts, closes on outside click and Escape | Home |
| | Counters | Headline numbers count up once as the band scrolls in | Home |
| | Reveal | Sections settle upward on entry, staggered across a row | Home |
| 8 | Hover peek | A student's full skill list and record open inside the result card | Search |
| 9 | Cursor spotlight | A soft ember wash follows the pointer, behind everything, never clickable | Home |
| 11 | Scroll progress | A hairline across the top fills as the page moves | Home |
| 12 | Self-advancing steps | The four request statuses walk themselves while on screen, hover takes control | Home |
| 13 | Review slider | Two rows of real `reviews` rows drifting opposite ways, seamless loop, pauses on hover | Home |
| 15 | Card tilt | Feature cards tilt toward the pointer, capped at 3.5 degrees each way | Home |
| 16 | Attention pulse | The request badge breathes only while something is genuinely waiting | Every page |

Working screens stay still on purpose: only the hover peek and the pulse run
outside the landing page, because a dashboard that moves while you read it is
harder to use, not nicer.

Everything above is switched off under `prefers-reduced-motion`, and the
pointer-driven pieces (9, 15) never start on a device without a pointer.

---

## Setting up the database (XAMPP)

1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open <http://localhost/phpmyadmin>.
3. Go to the **Import** tab.
4. Choose `database/skillexchange_full.sql` and press **Go**.

That one file drops any old copy of the database, recreates all six tables with
their constraints and indexes, creates the three views, and inserts the sample
data. When it finishes you should see:

| table              | rows |
| ------------------ | ---- |
| `users`            | 50   |
| `skills`           | 50   |
| `userskills`       | 261  |
| `exchangerequests` | 49   |
| `sessions`         | 34   |
| `reviews`          | 36   |

Prefer to run the steps separately? Import `database/schema.sql` first, then
`database/seed.sql`. Either way, `database/queries.sql` can be pasted into the
phpMyAdmin **SQL** tab afterwards — every write query in it is wrapped in a
transaction that rolls back, so the sample data is never disturbed.

### Command line alternative

```bash
cd C:\xampp\mysql\bin
mysql -u root < path\to\database\skillexchange_full.sql
```

---

## Viewing the web interface

**Online:** the GitHub Pages link in the repository description.

**One file, no setup:** open `preview.html`. Every screen is captured straight
out of the real pages and stitched into a single document with a tab strip, so
the whole interface can be reviewed by double-clicking one file. Rebuild it with
`node build_preview.js` after changing any page.

**Locally:** the pages are plain HTML, so opening `index.html` in a browser
works. To avoid any file-path differences, serve the folder instead:

```bash
python -m http.server 8000
# then open http://localhost:8000
```

### Signing in

All 50 seeded students share one password, so any of them can be used to look
around — sign in as different people and the dashboard, requests, sessions and
reviews all follow that account.

| Field    | Value                                     |
| -------- | ----------------------------------------- |
| Email    | any address from the `users` table         |
| Password | `password123`                             |

For example `mahedi.shakib@northsouth.edu`, `sadia.islam04@northsouth.edu` or
`asif.iqbal19@northsouth.edu`. The **Fill demo credentials** button on the
sign-in page enters a working pair for you. Account 1, Mahedi Hasan, is also the
account the admin console runs as.

Registering adds a real row to the students table, so the new account shows up in
search and in the admin list straight away. It still signs in with
`password123`: the static preview has no server, so it cannot hash and store a
chosen password the way Werkzeug does in the Flask build.

> **What works in this deployment.** GitHub Pages serves static files only; it
> cannot run Python. This build therefore reads the seeded rows from
> `static/js/data.js`, a direct export of `seed.sql`, so the screens show exactly
> the records that live in MySQL.
>
> The buttons are live: accepting a request, booking or completing a session,
> writing a review, editing skills and the admin deletions all change the data
> and redraw the page, and each one shows the SQL statement it stands for. Those
> changes are held in the browser tab only — **Reset demo data** in the top bar
> restores the seed, and so does closing the tab. In the Flask build the same
> actions run as parameterised SQL against MySQL and persist for real.

---

## Database design

Six tables, normalised to 3NF.

```
users ──< userskills >── skills
  │                        │
  │  sender / receiver     │  teach_skill / learn_skill
  └──────< exchangerequests >──────┘
                  │
                  └──< sessions ──< reviews >── users
```

### Data dictionary

**users** — one row per student

| Column            | Type         | Notes                                     |
| ----------------- | ------------ | ----------------------------------------- |
| `user_id`         | INT          | PK, AUTO_INCREMENT                        |
| `name`            | VARCHAR(100) | NOT NULL                                  |
| `email`           | VARCHAR(255) | NOT NULL, **UNIQUE** — this is the login   |
| `password`        | VARCHAR(255) | Werkzeug pbkdf2 hash, never plain text    |
| `department`      | VARCHAR(100) | NOT NULL, indexed for department search   |
| `bio`             | VARCHAR(255) | Optional                                  |
| `profile_picture` | VARCHAR(255) | Filename, defaults to `default.png`       |
| `created_at`      | TIMESTAMP    | Defaults to the current time              |

**skills** — the catalogue students choose from

| Column        | Type         | Notes                        |
| ------------- | ------------ | ---------------------------- |
| `skill_id`    | INT          | PK, AUTO_INCREMENT           |
| `skill_name`  | VARCHAR(100) | NOT NULL, **UNIQUE**         |
| `category`    | VARCHAR(50)  | NOT NULL, indexed            |
| `description` | VARCHAR(255) | One line explaining the skill |

**userskills** — bridge table resolving the many-to-many between students and skills

| Column          | Type | Notes                                                  |
| --------------- | ---- | ------------------------------------------------------ |
| `user_skill_id` | INT  | PK, AUTO_INCREMENT                                     |
| `user_id`       | INT  | FK → `users`, ON DELETE CASCADE                        |
| `skill_id`      | INT  | FK → `skills`, ON DELETE CASCADE                       |
| `skill_type`    | ENUM | `Teach` or `Learn`                                     |
| `proficiency`   | ENUM | `Beginner` / `Intermediate` / `Advanced` / `Expert`    |

UNIQUE `(user_id, skill_id, skill_type)` stops a student listing the same skill
twice under the same type.

**exchangerequests** — one proposed trade

| Column        | Type      | Notes                                                             |
| ------------- | --------- | ----------------------------------------------------------------- |
| `request_id`  | INT       | PK, AUTO_INCREMENT                                                |
| `sender_id`   | INT       | FK → `users`                                                      |
| `receiver_id` | INT       | FK → `users`                                                      |
| `teach_skill` | INT       | FK → `skills` — what the sender offers                            |
| `learn_skill` | INT       | FK → `skills` — what the sender wants                             |
| `status`      | ENUM      | `Pending` / `Accepted` / `Rejected` / `Cancelled` / `Completed`   |
| `created_at`  | TIMESTAMP | Defaults to the current time                                      |

CHECK `sender_id <> receiver_id` and CHECK `teach_skill <> learn_skill`.

**sessions** — a booked meeting belonging to one request

| Column         | Type         | Notes                                             |
| -------------- | ------------ | ------------------------------------------------- |
| `session_id`   | INT          | PK, AUTO_INCREMENT                                |
| `request_id`   | INT          | FK → `exchangerequests`, ON DELETE CASCADE        |
| `session_date` | DATE         | NOT NULL                                          |
| `session_time` | TIME         | NOT NULL                                          |
| `duration`     | INT          | Minutes, CHECK between 15 and 480, defaults to 60 |
| `mode`         | ENUM         | `Online` or `Offline`                             |
| `location`     | VARCHAR(255) | Used when the mode is Offline                     |
| `meeting_link` | VARCHAR(255) | Used when the mode is Online                      |
| `status`       | ENUM         | `Scheduled` / `Completed` / `Cancelled`           |
| `created_at`   | TIMESTAMP    | Defaults to the current time                      |

UNIQUE `(request_id, session_date, session_time)` prevents double booking the
same slot.

**reviews** — feedback after a completed session

| Column        | Type         | Notes                                      |
| ------------- | ------------ | ------------------------------------------ |
| `review_id`   | INT          | PK, AUTO_INCREMENT                         |
| `session_id`  | INT          | FK → `sessions`, ON DELETE CASCADE         |
| `reviewer_id` | INT          | FK → `users` — who wrote it                |
| `reviewee_id` | INT          | FK → `users` — who it is about             |
| `rating`      | INT          | CHECK between 1 and 5                      |
| `comment`     | VARCHAR(200) | Optional                                   |
| `created_at`  | TIMESTAMP    | Defaults to the current time               |

UNIQUE `(session_id, reviewer_id)` enforces one review per person per session,
so both partners may review each other but neither can review twice. CHECK
`reviewer_id <> reviewee_id` blocks self-reviews.

### Referential rules

- **ON DELETE CASCADE** — rows that are meaningless without their parent (a
  student's skills, requests, sessions and reviews) are removed automatically.
- **ON DELETE RESTRICT** — a skill cannot be deleted while an exchange request
  still points at it.
- **ON UPDATE RESTRICT** — every primary key is a surrogate AUTO_INCREMENT value
  that is never edited, so cascading updates are unnecessary. MariaDB also
  refuses CHECK constraints on columns that use ON UPDATE CASCADE, and the CHECK
  constraints are worth more here.

### Views

| View                 | Purpose                                                        |
| -------------------- | -------------------------------------------------------------- |
| `v_user_ratings`     | Review count and average rating per student                    |
| `v_request_details`  | Requests with IDs replaced by readable names and skill names   |
| `v_session_overview` | Sessions with both partner names and the skill being taught    |

### Normalisation

- **1NF** — every column holds a single atomic value. A student teaching three
  skills produces three rows in `userskills`, not a comma-separated list.
- **2NF** — no partial dependency exists, because every table has a single-column
  surrogate primary key. Skill details live in `skills` rather than being
  repeated on each `userskills` row.
- **3NF** — no transitive dependency. `skills.category` depends on `skill_id`
  only; a student's average rating is not stored on `users` but derived from
  `reviews` through the `v_user_ratings` view.

---

## SQL features demonstrated

All of these live in `database/queries.sql`, grouped and numbered.

| Feature                             | Where             |
| ----------------------------------- | ----------------- |
| SELECT, WHERE, LIKE, ORDER BY, LIMIT | A1 – A3           |
| INNER JOIN (up to five tables)      | B1 – B3           |
| LEFT JOIN                           | C1, C2            |
| RIGHT JOIN                          | C3                |
| GROUP BY                            | D1 – D6           |
| HAVING                              | D3, D4            |
| COUNT, SUM, AVG, MIN, MAX           | D2                |
| Scalar, IN, NOT EXISTS, correlated, derived-table and ALL subqueries | E1 – E6 |
| Parameterised search queries used by the UI | F1 – F3   |
| Views                               | G1 – G3           |
| Indexes, including EXPLAIN proof    | H1 – H3           |
| INSERT                              | I1                |
| UPDATE                              | I2, I3            |
| DELETE with cascade                 | I4                |
| Transactions (START TRANSACTION / COMMIT / ROLLBACK) | I1 – I5 |
| Reporting queries for the dashboard | J1 – J3           |

---

## The connected build — `skillswap-php/`

The folder `skillswap-php/` holds the same eleven screens wired to MySQL. It is
the version to run for the lab: copy the folder into `C:\xampp\htdocs\`, start
Apache and MySQL, import `database/skillexchange_full.sql` through phpMyAdmin,
and open `http://localhost/skillswap-php/`. Nothing else has to be installed —
Bootstrap, the icon font and the three typefaces are served from
`static/vendor/`, so it also runs with no internet connection. Full instructions
are in `skillswap-php/START-HERE.md`.

| Layer          | Technology                                  |
| -------------- | ------------------------------------------- |
| Frontend       | HTML5, CSS3, Bootstrap 5.3, vanilla JS      |
| Backend        | PHP 8 on Apache — both ship inside XAMPP    |
| Database       | MySQL / MariaDB on XAMPP                    |
| DB driver      | `mysqli` prepared statements, raw SQL only  |
| Authentication | PHP sessions, PBKDF2-SHA256 password hashes |

PHP rather than Flask because the instructor set XAMPP as the environment, and
XAMPP already contains Apache, PHP and MySQL. A Flask build would need Python and
two pip packages installed on every machine the project is demonstrated on; this
one needs XAMPP and nothing else.

No ORM is used anywhere. Every value in every statement goes through a `?`
placeholder and is bound by mysqli, which is both the point of a Database Systems
Lab project and the defence against SQL injection. Reads live in
`includes/queries.php`, one function per screen area; writes all arrive at
`actions.php` as POSTs, run one statement or one transaction, and redirect back.

Passwords are stored as PBKDF2-SHA256 hashes in the same Werkzeug format the
schema documents, so the fifty seeded rows are used exactly as they ship and new
registrations are written back in that format.

---

## Team

| Name | NSU ID | Responsibility |
| ---- | ------ | -------------- |
|      |        |                |
|      |        |                |
|      |        |                |
|      |        |                |
