#!/usr/bin/env python3
from pathlib import Path
import sys, shutil
root=Path(sys.argv[1] if len(sys.argv)>1 else '.').resolve()
p=root/'src/Controller/Admin/EquipmentItemCrudController.php'
s=p.read_text(encoding='utf-8')
old="->addJsFile('js/equipment-inventory-options.js?v=84')"
if old not in s:
    print('ABBRUCH: erwartete JS-Einbindung nicht gefunden. Controller unveraendert.');sys.exit(1)
s=s.replace(old,"->addJsFile('js/equipment-inventory-options.js?v=86fix2')")
s=s.replace("\n            ->addJsFile('js/dicoma-manufacturer-options.js?v=81');",';')
if "dicoma-manufacturer-options.js" in s:
    print('ABBRUCH: abweichende Hersteller-Skripteinbindung.');sys.exit(1)
shutil.copy2(p,p.with_suffix('.php.before86fix'))
p.write_text(s,encoding='utf-8')
print('OK: EquipmentItemCrudController.php aktualisiert; Backup .php.before86fix')
