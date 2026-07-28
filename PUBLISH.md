# Publishing to GitHub and GitHub Pages

Everything needed is already in this folder. Nothing has to be built or
installed first — the site is plain HTML, CSS and JavaScript.

---

## 1. Create the repository

On GitHub, create a new repository.

- Owner: the team account
- Name: `skillswap-nsu` (or whatever the team prefers)
- Visibility: **Public** — the assignment requires the link to be accessible
- Do **not** add a README, .gitignore or licence; this folder already has them

---

## 2. Push this folder

From inside the folder that contains `index.html`:

```bash
git init
git add .
git commit -m "SkillSwap NSU — database and web UI"
git branch -M main
git remote add origin https://github.com/<owner>/<repo>.git
git push -u origin main
```

Check that these appear in the repository afterwards:

```
index.html          preview.html        README.md
database/           static/             tests/
.nojekyll
```

`.nojekyll` matters. Without it GitHub Pages runs the files through Jekyll,
which can drop folders it does not recognise. It is an empty file — leave it
alone.

---

## 3. Turn on GitHub Pages

In the repository: **Settings → Pages**

- Source: **Deploy from a branch**
- Branch: **main**, folder: **/ (root)**
- Save

The URL appears at the top of that page within a minute or two:

```
https://<owner>.github.io/<repo>/
```

The first build takes two to three minutes. The Actions tab shows a green tick
when it has finished.

---

## 4. Check the live site before submitting

Open the Pages URL and confirm:

- [ ] The home page loads with its dark ground and the ember accent
- [ ] The marquee under the hero is moving
- [ ] The dice in the ask box fills in a skill
- [ ] `Open the demo` reaches the dashboard
- [ ] Sign in works with `mahedi.shakib@northsouth.edu` / `password123`
- [ ] Accept on a request changes its status
- [ ] `preview.html` opens at `https://<owner>.github.io/<repo>/preview.html`

If the page loads but has no styling, the CSS path is wrong — confirm
`static/css/style.css` is in the repository and that the branch and folder in
Settings → Pages are `main` and `/ (root)`.

---

## 5. What to submit

Two links:

| | |
| ---- | ---- |
| Repository | `https://github.com/<owner>/<repo>` |
| Live site | `https://<owner>.github.io/<repo>/` |

---

## Notes for whoever runs this

**The database is not part of the deployment.** GitHub Pages serves static files
only; it cannot run MySQL or Python. The SQL under `database/` is for the
separate database submission and is imported into XAMPP locally — see the
README.

**The interface is live, not a mock.** Buttons change the data and redraw the
page. Those changes are held in the browser tab and reset when it closes,
because there is no server behind this deployment yet. The same markup becomes
Jinja templates when the Flask build is connected.

**Do not commit `node_modules/`.** It is already in `.gitignore`. The tests need
it locally (`npm install`) but it has no business in the repository.

**After changing any page, rebuild the preview** before pushing:

```bash
npm install
npm run build:preview
npm test
```
