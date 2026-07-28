# SkillSwap NSU — running this on XAMPP

CSE311L Database Systems Lab. Everything needed is in this folder. Nothing has to
be downloaded, installed or built first, and no internet connection is required —
Bootstrap, the icon font and the three typefaces are all served from
`static/vendor/`.

You need XAMPP, and nothing else.

---

## Setting it up on a machine that has XAMPP

**1. Copy this folder into htdocs.**

Copy the whole `skillswap` folder from the pen drive to:

```
C:\xampp\htdocs\skillswap
```

so that `C:\xampp\htdocs\skillswap\index.php` exists.

**2. Start Apache and MySQL.**

Open the XAMPP Control Panel and press *Start* next to **Apache** and next to
**MySQL**. Both must show a green running state.

**3. Import the database.**

Open <http://localhost/phpmyadmin>, go to the **Import** tab, choose
`database/skillexchange_full.sql` from this folder, and press **Go**.

That one file drops any previous copy, recreates all six tables with their keys,
constraints and indexes, creates the three views, and inserts the sample data.
When it finishes the `skillexchange` database should hold:

| table              | rows |
| ------------------ | ---- |
| `users`            | 50   |
| `skills`           | 50   |
| `userskills`       | 261  |
| `exchangerequests` | 49   |
| `sessions`         | 34   |
| `reviews`          | 36   |

**4. Open the site.**

<http://localhost/skillswap/>

Sign in with any student's email address and the password `password123` — for
example `mahedi.shakib@northsouth.edu`. The *Fill demo credentials* button on the
sign-in page enters a working pair for you. Account 1, Mahedi Hasan, is also the
account the admin console runs as.

---

## If something does not work

**A page about MySQL not being reachable.** That page appears instead of the site
whenever the database connection fails, and it lists the three usual causes. In
order of likelihood: MySQL is not started in the XAMPP Control Panel, the SQL file
has not been imported yet, or this machine's MySQL has a root password. Only the
last one needs an edit — put the password in `includes/config.php` under
`DB_PASS`.

**Apache will not start** because port 80 is taken, usually by Skype or IIS. The
XAMPP Control Panel's *Netstat* button names the culprit. Either stop that program
or change Apache's port, in which case the address becomes
`http://localhost:8080/skillswap/`.

**A blank white page** means PHP hit an error and is configured not to show it.
Uncomment the last line of `includes/config.php` and reload to see the message.

---

## What the buttons actually do

Every button on the site runs one parameterised SQL statement against the
`skillexchange` database on this machine, and every change persists — sign out,
close the browser, come back, and it is still there. After each action a message
appears in the corner naming the statement that ran, so the data layer explains
itself while the site is being demonstrated.

Worth showing, in this order:

Accepting a request on the **Requests** screen runs an `UPDATE` and immediately
unlocks session booking, because a session can only belong to an accepted request.
Booking the same date and time twice is refused by the database, not by PHP —
`UNIQUE (request_id, session_date, session_time)` rejects the second one. Marking
the last open session of an exchange as completed runs two statements inside one
transaction, so the session and its request either both finish or neither does.
Reviewing that session twice is refused by `UNIQUE (session_id, reviewer_id)`, and
reviewing yourself is refused by `CHECK (reviewer_id <> reviewee_id)`.

The clearest one is in the **admin console**. Deleting a student runs a single
`DELETE FROM users`, and their skills, requests, sessions and reviews all
disappear with them — that is `ON DELETE CASCADE` in the schema doing the work,
with no extra SQL from the application. Trying to delete a skill an exchange
request still points at fails instead, because that foreign key is
`ON DELETE RESTRICT`. Both are worth demonstrating and then re-importing the SQL
to get the sample data back.

Registration writes a real row. The password is hashed with PBKDF2 before the
`INSERT`, and the plain text is never stored — the `users.password` column holds
hashes only, which you can confirm in phpMyAdmin.

---

## How the folder is laid out

```
skillswap/
├── index.php            Landing page
├── login.php            Sign in          logout.php   Sign out
├── register.php         Create account
├── dashboard.php        Student home — matches, requests, next sessions
├── search.php           Find a partner (skill / category / department / level / name)
├── profile.php          Public profile  (profile.php?id=19)
├── edit-profile.php     Edit details, manage skills, change password
├── requests.php         Exchange requests — received / sent / all
├── sessions.php         Sessions — upcoming / completed / cancelled + booking
├── reviews.php          Reviews received and written, leave a review
├── admin.php            Admin console — students, skills, requests, moderation
├── actions.php          Every write action arrives here as a POST
│
├── includes/
│   ├── config.php       Database credentials — the only file you may need to edit
│   ├── db.php           The mysqli connection and four query helpers
│   ├── auth.php         Sign in, sessions, PBKDF2 hashing, CSRF tokens
│   ├── queries.php      Every SELECT the site runs, as parameterised SQL
│   ├── helpers.php      Escaping, dates, avatars, pills, stars, swap cards
│   └── layout.php       The page shell: head, top bar, left rail, footer
│
├── database/
│   ├── schema.sql              Tables, keys, constraints, indexes, views
│   ├── seed.sql                Sample data for every table
│   ├── queries.sql             Every SQL feature the lab requires, A1 to J3
│   └── skillexchange_full.sql  schema + seed in one file — import this one
│
├── static/
│   ├── css/style.css    The whole theme
│   ├── js/app.js        Presentation only — no data work happens in the browser
│   └── vendor/          Bootstrap, icons and fonts, so the site runs offline
│
└── uploads/             Profile pictures, unused in this build
```

---

## How the code is arranged

Reads live in `includes/queries.php`, one function per screen area, each returning
plain arrays. Writes live in `actions.php`: a page posts an action name and its
fields, one statement runs, a message is recorded and the reader is redirected
back to the page they came from. Because it is POST, redirect, GET, refreshing a
page after an action never repeats it.

Every value in every statement goes through a `?` placeholder and is bound by
mysqli. Nothing is concatenated into SQL anywhere in this project, which is both
the point of a Database Systems Lab and the defence against SQL injection. There
is no ORM and no query builder — the SQL in `includes/queries.php` is the same SQL
you can paste into phpMyAdmin.

Passwords are stored as PBKDF2-SHA256 hashes in the Werkzeug format the schema
documents, so the seeded rows are used exactly as they ship and new registrations
are written back in the same format.

---

## The two builds

There is a second, static version of this same interface published on GitHub Pages
at <https://mahedi-123.github.io/SkillSwapNSU/>. GitHub Pages serves files and runs
no code, so that version reads a JavaScript export of `seed.sql` and keeps its
changes in the browser tab. It exists so the interface has a public link.

This folder is the real one: the same eleven screens, backed by MySQL, where the
writes actually happen.
