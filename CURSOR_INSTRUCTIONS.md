# NIRIX DASHBOARD — CURSOR INSTRUCTIONS
**Version:** 4.0
**Updated:** March 2026
**Project path:** `C:\Users\AbhinavSharma\AI Projects\Nirix Dashboard`
**Live URL:** https://5abhinavs-ops.github.io/Nirix-Dashboard/

---

## 1. PROJECT OVERVIEW

A single-page technical dashboard for Nirix / SG Group fleet management. Deployed as a static GitHub Pages site. The build pipeline compiles `fleet_board.html` + `index_base.html` + `gdrive_sync.js` into a single `index.html` output file.

**Login password:** `nirix2026`

---

## 2. FILE STRUCTURE

```
C:\Users\AbhinavSharma\AI Projects\Nirix Dashboard\
├── index.html              ← BUILD OUTPUT — never edit directly
├── index_base.html         ← base dashboard shell (all modules except fleet)
├── index_clean_base.html   ← clean backup of base (no fleet injected)
├── fleet_board.html        ← Fleet Availability Board source (edit this)
├── gdrive_sync.js          ← n8n/Google Drive sync JS (injected by build)
├── build.py                ← build pipeline (run to regenerate index.html)
├── upgrade_chart.py        ← one-time upgrade script (D removal + chart)
├── activate_fleet_sync.py  ← activates n8n fleet workflows via API
├── logos/                  ← logo image files (base64-embedded in base)
└── NIRIX_DASHBOARD_SPEC.md ← legacy v1→v2 spec (superseded by this file)
```

**Golden rule:** All fleet board changes go into `fleet_board.html`. After every change run `python build.py` then commit+push.

---

## 3. BUILD PIPELINE

```bash
cd "C:\Users\AbhinavSharma\AI Projects\Nirix Dashboard"
python build.py
git add index.html fleet_board.html
git commit -m "description"
git push origin main
```

### What build.py does (in order):
1. Reads `index_base.html` and strips any previously injected fleet code
2. Reads `fleet_board.html`, strips its `<html>/<head>/<body>` wrapper, separates CSS and JS from HTML body
3. Injects fleet CSS into `</style>` in base
4. Injects fleet JS (wrapped in IIFE) + `switchModule()` function into `</script>` in base
5. Injects fleet tab into the nav tab bar
6. Injects `#module-fleet` div inside the flex content wrapper
7. Fixes `#app` to use `height:100vh` not `min-height:100vh`
8. Reads `gdrive_sync.js` and injects it as a `<script>` block before `</body>`
9. Injects Cloud Sync UI panel into the fleet left nav
10. Hooks `saveAll()` to call `n8nSave()` after writing to D
11. Hooks `initFleetModule()` to call `n8nAutoLoad()` on first open
12. Runs 12 integrity checks before writing output

### Build checks (all must pass):
- `module-fleet div` present
- `tab-fleet` present
- `switchModule` function present
- `initFleetModule` present
- `FLEET CSS` marker present
- `FLEET JS` marker present
- `Revert row spN` present
- `Undo button` present
- `drag+localStorage` present
- Single `module-fleet` (no duplicates)
- Single `tab-fleet` (no duplicates)
- `app height:100vh` set

---

## 4. FLEET BOARD MODULE (`fleet_board.html`)

### 4.1 Architecture

`fleet_board.html` is a **standalone HTML file** that also works as a build source. It contains:
- `<style>` block with all fleet CSS (marker: `/* ── FLEET CSS`)
- `<script>` block with all fleet JS (marker: `/* ── FLEET JS`)
- HTML body with left nav + main grid + side panel + analytics overlay + tooltip

When embedded by `build.py`, the `.hdr` div (standalone header) is stripped. All JS runs inside an IIFE to avoid polluting global scope. Key functions are explicitly exposed to `window`.

### 4.2 Status Categories

| Code | Label | Colour | CSS Class |
|------|-------|--------|-----------|
| A | Available | `#92D050` | `.sA` |
| M | Machinery Issue | `#FF0000` | `.sM` |
| S | Structural Damage | `#FF8C00` | `.sS` |
| P | Propeller Damage | `#9B30FF` | `.sP` |
| W | Payment Delay | `#1E90FF` | `.sW` |
| N | No Data (default) | `#e8eaee` | `.sN` |

**D (Down) has been permanently removed.** Any `"s":"D"` data entries should be remapped to `"s":"M"`.

### 4.3 Key Data Structures

```javascript
// Main data object — all fleet data keyed by "FLEET_Month_Year"
const D = {
  "SCB_Mar_2026": {
    fleet: "SCB", month: "Mar", year: 2026, days: 31,
    boats: [
      { name: "Ekam", sr: 1, days: [{ d: 1, s: "A", r: "" }, ...] }
    ]
  },
  "SGS_Mar_2026": { ... }
};

// Cell edits made in current session (not yet saved to Drive)
const EDITS = {}; // key: "dsKey|sr|day|slot" → { s, r }

// Status lookup dicts
const SL  = {A:'Available', M:'Machinery Issue', S:'Structural Damage', P:'Propeller Damage', W:'Payment Delay', N:'No Data'};
const SBG = {A:'#92D050', M:'#FF0000', S:'#FF8C00', P:'#9B30FF', W:'#1E90FF', N:'#e8eaee'};
const SFG = {A:'#1a3a10', M:'#fff', S:'#fff', P:'#fff', W:'#fff', N:'#5a6278'};
const SC  = ['sA','sM','sS','sP','sW','sN']; // all status CSS classes
```

### 4.4 Cell Key Format
`"dsKey|sr|day|slot"` e.g. `"SCB_Mar_2026|1|15|2"`
- `dsKey` = fleet+month+year key into D
- `sr` = boat serial number (1-based)
- `day` = day of month
- `slot` = time slot (0=00-06, 1=06-12, 2=12-18, 3=18-24)

### 4.5 Critical Functions

| Function | Purpose |
|----------|---------|
| `render()` | Full re-render: rebuild dropdown, KPIs, table |
| `renderKPI()` | Update KPI strip at top |
| `renderTable()` | Rebuild the main grid table |
| `toggleEdit()` | Toggle edit mode on/off |
| `cellDown(e,el)` | Mousedown on cell — starts pending drag or opens picker |
| `handleDown(e,el)` | Mousedown on fill handle — immediate drag start |
| `onMaybeStartDrag(e)` | Threshold check — starts drag if moved >4px |
| `onPendingCancel(e)` | Plain click — opens picker panel |
| `startRectDrag(anchorEl)` | Begin rectangle drag session |
| `expandRect(curEl)` | Update rectangle as mouse moves |
| `endDrag()` | Commit all cells in rectangle |
| `openPicker(e,el)` | Open side panel for a cell |
| `pickStatus(s)` | Select a status in the picker + sync activePaintStatus |
| `applyEdit()` | Commit side panel selection |
| `commitCell(el,s,r)` | Apply status+reason to a cell + push to undo stack |
| `saveAll()` | Commit EDITS into D + trigger n8nSave() |
| `exportJSON()` | Download D as fleet_YYYY-MM-DD.json |
| `openAnalytics(fl)` | Open analytics overlay for SCB or SGS |
| `renderAnalytics()` | Render all analytics content |
| `renderBoatBars(md,isScb)` | Render per-boat vertical stacked SVG bar chart |

### 4.6 Edit Mode Flow

```
User clicks "Edit Mode" button
  → toggleEdit() fires
  → emode = true
  → #module-fleet gets class "emode" (enables crosshair cursor, fill handles)

User clicks a cell (no drag):
  cellDown() → pending state
  mouse released without moving 4px
  → onPendingCancel() → openPicker()

User drags:
  cellDown() → pending state
  mouse moves >4px
  → onMaybeStartDrag() → startRectDrag() → expandRect() on every mousemove
  → endDrag() on mouseup → commits all cells

User clicks fill handle (▪ corner icon):
  handleDown() → immediate startRectDrag() (no threshold)
```

**Known bug fix applied:** `pickStatus(s)` now syncs `activePaintStatus` immediately so dragging after selecting a colour in the picker uses the correct colour (fixes orange-stays-orange bug).

**Known bug fix applied:** `toggleEdit()` null-guards `edit-chip` element (stripped by build.py in embedded mode).

### 4.7 Analytics

Opens as a full-screen overlay (`#analytics-overlay`). Has:
- KPI strip (Overall %, Total Down-Days, Machinery Days, Months Tracked)
- Monthly trend bar chart (Chart.js)
- Per-month boat-level vertical stacked SVG bar chart (`renderBoatBars`)
- Combined downtime doughnut chart (Chart.js)
- Key Insights list

**Boat bar chart** uses inline SVG (no Chart.js dependency). Bars are sorted by availability %. Colours match status codes. % labels above each bar. Rotated boat name labels below.

**Duplicate legend bug fix:** The static HTML legend inside the analytics card was removed. Only the dynamically generated legend from `renderBoatBars` is shown.

---

## 5. FLEETS & BOATS

### Sea Cabbie (SCB) — 11 boats
Ekam, Dve, Treeni, Chatur, Panch, Sapta, Ashta, Naav, Km Golf, Vayu1, Vayu2

### SG Shipping (SGS) — 12 boats
Galaxy, Brave, Fortune, Justice, Patience, Loyalty, Generous, Integrity, Dahlia, Sunflower, Marigold, Jasmine

---

## 6. n8n / GOOGLE DRIVE SYNC

### 6.1 How It Works
- When the Fleet tab opens → `n8nAutoLoad()` fires after 800ms → loads `nirix_fleet_data.json` from Google Drive via n8n
- When user clicks ✓ Save → `saveAll()` commits to D then calls `n8nSave()` → POSTs D to n8n → n8n writes to Drive
- Left nav has a "↓ Load from Drive" button for manual reload
- Status messages appear in `#gdrive-status` element

### 6.2 n8n Workflows

| Workflow | ID | Path | Method |
|----------|-----|------|--------|
| Nirix - Fleet Board Save | `duUHXXMgWZeZuwj7` | `/webhook/nirix-fleet-save` | POST |
| Nirix - Fleet Board Load | `6Vy4PQM3SGdFesef` | `/webhook/nirix-fleet-load` | GET |

**n8n instance:** `https://abnv5.app.n8n.cloud/`
**Google Drive credential used:** `TgPw2fk0KcMDKJ4g` ("Google Drive account")
**Secret header:** `x-nirix-secret: nirix2026fleet`
**Saved file:** `nirix_fleet_data.json` in root of Google Drive

### 6.3 Activating Workflows
After initial setup, run:
```bash
python activate_fleet_sync.py
```
Or activate manually in n8n UI.

### 6.4 gdrive_sync.js Constants
```javascript
const N8N_BASE   = 'https://abnv5.app.n8n.cloud/webhook';
const N8N_SECRET = 'nirix2026fleet';
const SAVE_PATH  = N8N_BASE + '/nirix-fleet-save';
const LOAD_PATH  = N8N_BASE + '/nirix-fleet-load';
```

---

## 7. OTHER EXISTING n8n WORKFLOWS

| Workflow | ID | Purpose |
|----------|-----|---------|
| Nirix - Photo Upload Handler | `gIKrbccZZtBV9JRD` | Receives photo uploads, stores to Drive, writes URL to Sheets |
| Nirix - Daily Machinery Report Processor | `xB8IpFEBQOaT4Id7` | Processes daily engine reports |
| Nirix - Inspection Report Processor | `SAo1sZZPCbPvKOVv` | Processes inspection reports |
| Nirix - Boat Tech Specs API | `HGlHpcf7KXGQeXzc` | Serves boat technical specifications |
| Nirix - 7 Day Photo Cleanup | `0ezGubieu3RUaTHS` | Deletes photos older than 7 days |
| Nirix - Photo URL Backfill (Run Once) | `8SU1ViiyIrk91uMB` | One-time backfill |
| Claude MCP Gateway | `litav5aQkHzuKUhV` | Claude MCP integration |

---

## 8. GOOGLE CREDENTIALS IN n8n

| Credential | ID | Used In |
|------------|-----|---------|
| Google Drive account | `TgPw2fk0KcMDKJ4g` | All Drive operations |
| Google Sheets account 3 | `i4Ga0DlSJ61Ff88q` | Sheets read/write |

**Google Sheets data:**
- Sheet ID: `1qul0ee5Ioh526zXw-dXBakCMFZk3emJhM6lRdM6pEKA`
- Sheet name: `Sheet1`
- API key: `AIzaSyCMq8X5Mfwdnz1Soa_RF70A6voHaA7Xm28`

---

## 9. DASHBOARD MODULES (index_base.html)

| Module | Tab ID | Container ID | Notes |
|--------|--------|-------------|-------|
| Daily Reports | `tab-daily` | `module-daily` | Google Sheets daily engine reports |
| Engine Run-hrs Records | `tab-runhrs` | `module-runhrs` | Monthly running hours |
| Boat Tech. Specs | `tab-boatspecs` | `module-boatspecs` | Technical specifications per vessel |
| Certification | `tab-certs` | `module-certs` | Certificate compliance tracking |
| Fleet Availability | `tab-fleet` | `module-fleet` | **Injected by build.py from fleet_board.html** |

Switching is handled by `switchModule(mod)` in `index_base.html`. When `mod === 'fleet'`, it calls `window.initFleetModule()` on first open.

---

## 10. KNOWN BUGS FIXED (DO NOT RE-INTRODUCE)

1. **edit-chip null crash** — `toggleEdit()` must null-guard `document.getElementById('edit-chip')` because the `.hdr` div is stripped by build.py in embedded mode
2. **emode closure isolation** — `window.toggleEdit` must NOT be overridden in build.py; it must use the IIFE closure version only
3. **eval-based window expose** — use direct `window.fn = fn` assignments, never `eval(fn)` loop
4. **agent log injections** — never inject `__agentLogFleet()` debug calls into drag functions
5. **`use strict` in IIFE** — removed from IIFE wrapper; causes ReferenceErrors on window assignments
6. **drag colour sync** — `pickStatus(s)` must update `activePaintStatus` so drag uses correct colour after picker selection
7. **duplicate analytics legend** — static HTML legend in analytics card was removed; only `renderBoatBars` generates the legend
8. **D (Down) category** — fully removed. Never re-add. Data remapped to M.

---

## 11. UPGRADE SCRIPTS

### upgrade_chart.py
Run **once** on a fresh `fleet_board.html` to apply all pending changes:
1. Remove Down (D) category from all CSS, dicts, arrays, analytics, pie chart
2. Remap all `"s":"D"` data entries to `"s":"M"`
3. Add `edit-chip` null guard to `toggleEdit()`
4. Replace horizontal percentage bars with futuristic vertical stacked SVG bar chart
5. Remove duplicate static legend from analytics card HTML

```bash
git checkout fleet_board.html   # restore clean source first
python upgrade_chart.py
python build.py
```

---

## 12. DEPLOYMENT CHECKLIST

Before pushing any change:
- [ ] `python build.py` runs with all 12 checks green
- [ ] `fleet_board.html` does NOT contain placeholder text
- [ ] `index.html` is the build output, not manually edited
- [ ] No `eval(fn)` loops in build.py
- [ ] No `"s":"D"` entries in fleet data
- [ ] No `.sD` CSS class
- [ ] `toggleEdit` null-guards `edit-chip`
- [ ] `pickStatus` syncs `activePaintStatus`
- [ ] n8n workflows active (check `activate_fleet_sync.py`)

---

## 13. DESIGN TOKENS (FLEET BOARD)

| Purpose | Value |
|---------|-------|
| Page bg | `#0A1628` |
| Surface bg | `#0D1F3C` |
| Border | `#1E3A5F` |
| Text primary | `#FFFFFF` |
| Text secondary | `#8892b8` |
| Text muted | `#5a6278` |
| Teal accent | `#5bc4a8` (SCB) / `#a0b0ff` (SGS) |
| Available green | `#92D050` |
| Machinery red | `#FF0000` |
| Structural blue | `#4472C4` |
| Propeller pink | `#FF66FF` |
| Payment orange | `#ED7D31` |
| No-data grey | `#e8eaee` |
| Drag highlight | box-shadow inset white 2px |
| Edited cell | orange border indicator |

---

## 14. FREQUENTLY NEEDED CODE PATTERNS

### Adding a new status category
1. Add CSS: `.sX{background:#XXXXXX}` in fleet_board.html
2. Add to `SL`, `SBG`, `SFG` dicts
3. Add `'sX'` to `SC` array
4. Add to all `forEach(c=>el.classList.remove(c))` calls in endDrag and applyStatusToCell
5. Add picker row in `#sp-options` HTML
6. Add legend entry in left nav HTML
7. Add to analytics if needed

### Changing the n8n webhook secret
Update `N8N_SECRET` in `gdrive_sync.js` AND update both n8n workflow IF nodes' `rightValue` field.

### Adding a new month of data
Add a new key to the `D` object in `fleet_board.html`:
```javascript
"SCB_Apr_2026": {
  fleet: "SCB", month: "Apr", year: 2026, days: 30,
  boats: [
    { name: "Ekam", sr: 1, days: [] },
    ...
  ]
}
```
Also add to `MO` array and `ML` dict.

### Forcing a fresh load from Drive
In browser console on the live site:
```javascript
n8nLoad();
```

---

## 15. BRANCH STRATEGY

**⚠️ ABSOLUTE RULE: ALL changes go to `experiments` ONLY. NEVER commit to `main` directly. No exceptions.**

**GitHub Pages is permanently pointed at `experiments` branch. Do NOT change this.**

### Every deploy must follow this exact sequence:
```
cd "C:\Users\AbhinavSharma\AI Projects\Nirix Dashboard"
git checkout experiments
[make changes to fleet_board.html or other source files]
python build.py
git add index.html fleet_board.html
git commit -m "experiments: description"
git push origin experiments
git checkout main
```

### Only promote to main when user explicitly confirms changes are good:
```
git checkout main
git merge experiments
git push origin main
git checkout experiments
```

### NEVER do this:
- `git commit` while on `main`
- `git push origin main` for any new feature or fix
- Make changes to source files while on `main` branch

---

## 16. UI THEME MATCHING — REFERENCE DESIGN PIPELINE

**Trigger:** Whenever a UI screenshot, mockup, or reference design image is uploaded with any request to match, apply, replicate, copy, or restyle the UI.

**This pipeline is mandatory. Do not skip stages. Do not eyeball colors.**

### Project Architecture (Single-File HTML)
This project is a **single HTML file** (`fleet_board.html` → built into `index.html`) with all CSS embedded in a `<style>` block. There is no React, no framework, no theme system.
- Colors are defined as CSS custom properties in `:root {}` inside `<style>`
- Some colors are hardcoded directly in CSS rules outside `:root`
- JS logic is in `<script>` blocks — **NEVER touch these during styling**
- Class names must never change — only values inside existing CSS rules

### STAGE 1 — ANALYZE (Screenshot → Design Spec)
Extract every visual property from the reference screenshot into a JSONC spec:
```jsonc
{
  "colorTokens": {
    "background": "#______",
    "surfaceBase": "#______",
    "surfaceElevated": "#______",
    "primary": "#______",
    "primaryGlow": "rgba(...)",
    "borderSubtle": "rgba(...)",
    "textPrimary": "#______",
    "textSecondary": "#______",
    "danger": "#______",
    "success": "#______",
    "warning": "#______"
  },
  "typography": {
    "headingWeight": "___",
    "bodyWeight": "___",
    "fontFamily": "___"
  },
  "spacing": {
    "cardBorderRadius": ___,
    "buttonBorderRadius": ___,
    "cardPadding": ___
  },
  "effects": {
    "cardShadow": "___",
    "glassEffect": false,
    "gradients": []
  }
}
```
Save as `stage1_design_spec.jsonc` in project root.

### STAGE 2 — VALIDATE (Spec vs Screenshot)
Re-examine the screenshot independently. For each token, verify it matches what you actually see. Common errors:
- Background vs surface — are they actually different shades?
- Border opacity — barely visible (0.08) or clearly visible (0.25)?
- Primary accent — more vivid or more muted than extracted?

Produce a correction table and save final corrected spec as `stage2_validation.md`. **The corrected spec is the sole source of truth for Stage 3.**

### STAGE 3 — IMPLEMENT (Corrected Spec → Code)
**For this single-file HTML project:**
1. Read the current `:root {}` CSS variables in `fleet_board.html` first
2. Build an explicit OLD → NEW color mapping
3. Update in this order:
   - **Layer 1:** CSS custom properties in `:root {}` block
   - **Layer 2:** Any hardcoded hex values in CSS rules outside `:root` (grep `<style>` block)
4. Run `python build.py` after all changes
5. **NEVER modify any `<script>` content**
6. **NEVER change class names, layout, or component structure**
7. **NEVER change spacing or border-radius unless the spec explicitly differs and user asked**

### STAGE 4 — SKIP
Stage 4 (dynamic theme coverage) applies to React Native apps only. Skip entirely for this project.

### STAGE 5 — VERIFY
1. Grep `<style>` block for any remaining OLD color values
2. Run `python build.py` — all 12 checks must pass
3. Commit to `experiments-ui` branch only:
```
git add fleet_board.html index.html
git commit -m "experiments-ui: restyle to match [reference]"
git push origin experiments-ui
```

---

*End of Cursor Instructions — v4.3*
