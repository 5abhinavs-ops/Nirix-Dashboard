# NIRIX TECHNICAL DASHBOARD — CURSOR INSTRUCTIONS
# Last updated: March 2026

---

## PROJECT OVERVIEW

Single-file HTML dashboard for SG Group office team.
Hosted on GitHub Pages. No framework. Pure HTML + JS + CSS.

**Live URL:** https://5abhinavs-ops.github.io/Nirix-Dashboard/
**Password:** nirix2026
**GitHub repo:** https://github.com/5abhinavs-ops/Nirix-Dashboard.git

---

## FILE STRUCTURE

```
C:\Users\AbhinavSharma\AI Projects\Nirix Dashboard\
├── index.html              ← BUILD OUTPUT — do not edit directly (then embed_logos.py)
├── index_base.html         ← INPUT TO build.py — copied from index_clean_base.html before each build
├── index_clean_base.html   ← FLEET-FREE dashboard — regenerate with export_clean_base.py
├── fleet_board.html        ← FLEET MODULE SOURCE — edit this for fleet changes
├── build.py                ← BUILD SCRIPT — merges base + fleet into index.html
├── export_clean_base.py    ← Strips fleet from index.html → writes index_clean_base.html
├── deploy.bat              ← DEPLOY SCRIPT — copies clean→base, build, embed_logos, git push
├── embed_logos.py          ← Embeds logo PNG files as base64 into index.html
├── fix_duplicates.py       ← Utility: scans for duplicate JS declarations in index.html
└── logos\
    ├── sg_group.png
    ├── nirix.png
    ├── sea_cabbie.png
    └── sg_shipping.png
```

---

## CRITICAL — THE BUILD SYSTEM

### How it works
`index.html` is NOT edited directly. It is the **output** of `build.py`.

`build.py` reads two source files:
- `index_base.html` — the dashboard without the fleet module
- `fleet_board.html` — the Fleet Availability module

It strips any leftover fleet code from `index_base.html`, injects fresh fleet CSS/JS/HTML from `fleet_board.html`, and writes the merged result to `index.html`.

### The contamination problem
`build.py` writes its output to `index.html` but reads its input from `index_base.html`.
If `index_base.html` is ever replaced with a previous build output (which already contains fleet code), running `build.py` again stacks another copy of the fleet code on top, creating duplicate functions that crash JavaScript silently — including login.

### RULE: Always copy clean base before building
`deploy.bat` does this automatically. For a manual build:

```bat
copy index_clean_base.html index_base.html
python build.py
```

### Regenerating `index_clean_base.html` from the live merged file
When `index.html` is correct but `index_clean_base.html` is missing or stale:

```bat
python export_clean_base.py
copy index_clean_base.html index_base.html
python build.py
```

`export_clean_base.py` strips all fleet markers/HTML/JS and inserts `switchModule` for **daily / runhrs / boatspecs / certs** only (no Fleet tab), so the clean base still runs in a browser without running `build.py`.

### How to verify index_clean_base.html is clean
It must NOT contain any of these strings:
- `/* ── FLEET CSS ── */`
- `/* ── FLEET JS ── */`
- `FLEET CSS START`
- `FLEET OVERRIDE`
- `initFleetModule`
- `module-fleet`

If any are present, it is contaminated. Restore from git:
```bat
git show 75f0a35:index.html > index_clean_base.html
```

### What to edit for each type of change
| What you want to change | Edit this file |
|---|---|
| Dashboard core (daily reports, run hours, specs, certs, login) | Edit `index_clean_base.html` or export from `index.html` then edit; use as `index_base.html` input to `build.py` |
| Fleet Availability module | `fleet_board.html` |
| Build / strip / inject logic | `build.py` |
| Regenerate fleet-free base from merged `index.html` | Run `export_clean_base.py` |

Never edit `index.html` directly — it gets overwritten every build.

---

## DEPLOY PROCESS

```bat
cd "C:\Users\AbhinavSharma\AI Projects\Nirix Dashboard"
copy index_clean_base.html index_base.html
python build.py
deploy.bat
```

`deploy.bat` does:
1. Runs `embed_logos.py` — embeds logo PNG files as base64 into `index.html`
2. `git add index.html`
3. `git commit -m "Deploy: update dashboard"`
4. `git push origin main`

GitHub Pages auto-deploys from main / root. Wait ~60 seconds, then hard refresh (Ctrl+Shift+R).

---

## IF LOGIN STOPS WORKING

Login silently fails when JavaScript crashes on page load. The most common cause is duplicate function definitions from a contaminated build.

**Diagnosis:**
1. Open the live URL in browser
2. Press F12 → Console tab
3. Enter the password and click Sign In
4. Look for red error messages — they show the exact line and function name

**Fix:**
```bat
python export_clean_base.py
copy index_clean_base.html index_base.html
python build.py
deploy.bat
```

If you need to revert to the last known working `index.html` without rebuilding:
```bat
git checkout 75f0a35 -- index.html
git add index.html
git commit -m "Revert: restore working index.html"
git push origin main
```

---

## GIT REFERENCE

```
Remote:  https://github.com/5abhinavs-ops/Nirix-Dashboard.git
Branch:  main
Known clean commit: 75f0a35
```

Useful commands:
```bat
git log --oneline -10        ← see recent commits
git show <hash>:index.html   ← view index.html at any commit
git diff HEAD index.html     ← see uncommitted changes
```

---

## DASHBOARD ARCHITECTURE

### Login
- Password: `nirix2026` (stored as `DASHBOARD_PASS` constant at top of script)
- `doLogin()` checks input, hides login screen, shows `#app`, calls `loadData()`

### Module Tabs
1. **Daily Reports** — Today's engine check cards + Monthly grid view
2. **Engine Run-hrs Records** — Fleet monthly overview + Run hours charts
3. **Boat Tech Specs** — Vessel specification sheets
4. **Fleet Availability** — Fleet scheduling board (injected by build.py)

### Data Source
- Google Sheets API (read-only, public)
- `SHEET_ID`: `1qul0ee5Ioh526zXw-dXBakCMFZk3emJhM6lRdM6pEKA`
- `Sheet1` = daily engine reports
- `EngineSpecs` = engine spec data

### Fleet
**SG Shipping (12 boats):** SG Galaxy, SG Brave, SG Fortune, SG Justice, SG Patience,
SG Loyalty, SG Generous, SG Integrity, SG Dhalia, SG Sunflower, SG Jasmine, SG Marigold

**Sea Cabbie (11 boats):** SG Ekam, SG Naav, SG Dve, KM Golf, SG Panch, SG Chatur,
SG Sapta, SG Ashta, SG Trinee, Vayu 1, Vayu 2

---

## KEY JS VARIABLES & FUNCTIONS

### Global State (in index_base.html)
```js
let activeModule = 'daily'        // 'daily' | 'runhrs' | 'boatspecs' | 'fleet'
let activeView = 'daily'          // 'daily' | 'monthly'
let activeRunhrs = 'fleet'        // 'fleet' | 'runhrs' | 'specs'
let allRows = []                   // data from Google Sheets Sheet1
let specRows = []                  // data from Google Sheets EngineSpecs
let selectedBoat = 'SG Ekam'
let bsSelected = 'SG Galaxy'
let bsOpenSG = true, bsOpenSC = true
```

### Critical Functions
- `doLogin()` — password check, shows app
- `loadData()` — fetches Google Sheets data
- `renderAll()` — re-renders current active module
- `switchModule(mod)` — switches between daily/runhrs/boatspecs/fleet
- `switchDailyView(view, el)` — daily/monthly toggle
- `switchRunhrsView(view, el)` — fleet/runhrs/specs toggle
- `renderDaily()` — today's report cards
- `renderMonthly()` — monthly grid
- `renderFleet()` — fleet run hours overview
- `renderRunhrs()` — monthly run hours detail
- `renderBoatSpecs()` — boat tech specs module
- `openModal(boat, dateStr)` — opens daily report modal
- `closeModal()` — closes modal
- `sgGroupLogoHTML()` — SG Group logo img tag (embedded by embed_logos.py)
- `nirixLogoHTML()` — Nirix logo img tag (embedded by embed_logos.py)

### Fleet Module Functions (in fleet_board.html, injected by build.py)
These are wrapped in an IIFE and exposed to window:
- `window.initFleetModule()` — called by switchModule when switching to fleet tab
- `window.render()` — renders the fleet availability table
- `window.toggleEdit()` — enters/exits edit mode
- `window.openPicker(e, el)` — opens status picker panel
- `window.closePicker()` — closes status picker panel
- `window.pickStatus(s)` — sets status in picker
- `window.applyEdit()` — applies status to selected cell
- `window.undoLast()` — undoes last cell change
- `window.saveAll()` — saves all edits to Google Sheets
- `window.exportJSON()` — exports data as JSON
- `window.openAnalytics(fl)` — opens analytics overlay
- `window.closeAnalytics()` — closes analytics overlay

---

## STYLING REFERENCE

### Colours
- Background: `#0A1628`
- Card/panel: `#0D1F3C`
- Header: `#0D2A3A`
- Border: `#1E3A5F`
- Text primary: `#FFFFFF`
- Text secondary: `#8AA8C0`
- Text muted: `#3A4A60`
- Green accent: `#1A9A6A`
- Teal accent: `#0D8A9A`
- Alert orange: `#D4920A`
- Critical red: `#C84040`

### Tab border colours
- Daily Reports: `#1E3A5F`
- Engine Run-hrs: `#0D8A9A`
- Boat Tech Specs: `#1A6A3A`
- Fleet Availability: `#7A5A1A`

---

## COMMON PITFALLS

### 1. Never edit index.html directly
It is overwritten by every `build.py` run. All edits go into `index_base.html` or `fleet_board.html`.

### 2. Always restore index_base.html before building
`copy index_clean_base.html index_base.html` must run before every `python build.py`.
If skipped, fleet code stacks up and breaks login.

### 3. No duplicate JS declarations
Do not declare `BOAT_SPECS`, `BS_SG`, `BS_SC`, `bsSelected`, `activeModule`, `allRows`,
`specRows`, or any other global variable twice.
Run `python fix_duplicates.py` to scan for duplicates in the current index.html.

### 4. Single script block only
All JS lives in a single `<script>` tag inside `<head>`. Do not add a second `<script>` tag.

### 5. embed_logos.py must run before every deploy
`deploy.bat` handles this automatically. Never skip it.

### 6. Fleet JS is wrapped in an IIFE
build.py wraps all fleet JS in `(function(){ ... })()` and exposes functions via
`window.functionName`. Do not add bare `function` declarations to fleet_board.html's
`<script>` block — they will be duplicated if the IIFE wrapper is also present.

---

## WHAT NOT TO TOUCH

- Do NOT edit `index.html` directly
- Do NOT run any old `patch_*.cjs` scripts — they are obsolete
- Do NOT run `embed_logos.cjs` — replaced by `embed_logos.py`
- Do NOT edit anything in "Nirix Daily reports app" folder
- Do NOT create a second git repo or push to a different remote
- Do NOT add `node_modules` or build output to git
