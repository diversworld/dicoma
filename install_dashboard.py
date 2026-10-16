#!/usr/bin/env python3
from pathlib import Path
import sys
p = Path('src/Controller/Admin/DashboardController.php')
if not p.is_file():
    sys.exit('Run from the DiCoMa project root.')
s = p.read_text(encoding='utf-8')
if 'EquipmentBookingBasketTrait;' in s:
    print('Basket trait already installed.')
    sys.exit(0)
needle = 'class DashboardController'
idx = s.find(needle)
if idx < 0:
    sys.exit('DashboardController class not found. No changes made.')
brace = s.find('{', idx)
if brace < 0:
    sys.exit('DashboardController body not found. No changes made.')
backup = p.with_name(p.name + '.before-step79b.bak')
if not backup.exists():
    backup.write_text(s, encoding='utf-8')
s = s[:brace+1] + '\n    use EquipmentBookingBasketTrait;\n' + s[brace+1:]
p.write_text(s, encoding='utf-8')
print('Dashboard trait installed. Backup:', backup)
