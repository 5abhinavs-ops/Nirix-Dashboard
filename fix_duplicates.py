with open('index.html', 'r', encoding='utf-8') as f:
    lines = f.readlines()

print(f"Total lines: {len(lines)}")
print("\n--- Scanning for ALL potential duplicate declarations ---")

# Check every variable that could be declared twice
checks = [
    'BOAT_SPECS', 'BS_SG', 'BS_SC', 'bsSelected', 'bsOpenSG', 'bsOpenSC',
    'activeModule', 'activeView', 'activeRunhrs', 'allRows', 'specRows',
    'selectedBoat', 'currentMonth', 'currentYear', 'fleetCompany',
    'DASHBOARD_PASS', 'SEA_CABBIE', 'SG_SHIPPING'
]

for varname in checks:
    found = []
    for i, line in enumerate(lines):
        s = line.strip()
        # Look for declaration patterns
        if (f'const {varname}' in s or f'var {varname}' in s or f'let {varname}' in s):
            if s.startswith(('const ', 'var ', 'let ')) or f'    const {varname}' in line or f'    var {varname}' in line or f'    let {varname}' in line:
                found.append(i+1)
    if len(found) > 1:
        print(f"DUPLICATE: {varname} at lines {found}")
    elif len(found) == 1:
        print(f"OK: {varname} at line {found[0]}")
    else:
        print(f"MISSING: {varname} - not found!")
