#!/usr/bin/env python3
"""
Regenerate index_clean_base.html from the current merged index.html.

Per CURSOR_INSTRUCTIONS.md:
- Strips all fleet injections (CSS/JS/HTML, tabs, switchModule with fleet).
- Injects switchModule for daily/runhrs/boatspecs/certs only so the file still runs in a browser.

Run after pulling a known-good index.html, or to repair a contaminated base:
  python export_clean_base.py
Then:
  copy index_clean_base.html index_base.html
  python build.py
"""
import os
import sys

from build import strip_fleet_from_html, inject_switch_module_no_fleet, BASE

INDEX = os.path.join(BASE, 'index.html')
OUT_CLEAN = os.path.join(BASE, 'index_clean_base.html')


def main():
    if not os.path.exists(INDEX):
        print('ERROR: index.html not found')
        sys.exit(1)
    with open(INDEX, 'r', encoding='utf-8') as f:
        raw = f.read()
    clean = strip_fleet_from_html(raw)
    clean = inject_switch_module_no_fleet(clean)

    forbidden = (
        'id="module-fleet"',
        'id="tab-fleet"',
        '<div id="module-fleet"',
        'initFleetModule',
        '/* ── FLEET CSS START ── */',
    )
    leaks = [s for s in forbidden if s in clean]
    if leaks:
        print('WARNING: clean base still contains:', leaks)
        print('You may need to fix strip_fleet_from_html() or edit index.html manually.')

    with open(OUT_CLEAN, 'w', encoding='utf-8') as f:
        f.write(clean)
    print(f'Wrote {OUT_CLEAN} ({len(clean):,} bytes)')
    print('Next: copy index_clean_base.html index_base.html  then  python build.py')


if __name__ == '__main__':
    main()
