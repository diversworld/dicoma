#!/usr/bin/env python3
"""Safely add consistent EasyAdmin index columns to the three inventory CRUDs."""
from pathlib import Path
import re
import shutil
import sys

ROOT = Path(__file__).resolve().parent
SPECS = {
 'EquipmentItemCrudController.php': '''
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('inventoryNumber', 'Inventarnummer');
            yield TextField::new('name', 'Bezeichnung');
            yield AssociationField::new('category', 'Ausrüstungsart');
            yield AssociationField::new('manufacturer', 'Hersteller');
            yield TextField::new('displayModel', 'Modell');
            yield AssociationField::new('size', 'Größe');
            yield ChoiceField::new('conditionStatus', 'Zustand')->setChoices([
                'Einsatzbereit' => 'ready', 'Wartung' => 'maintenance',
                'Beschädigt' => 'damaged', 'Ausgemustert' => 'retired',
            ]);
            yield BooleanField::new('lendable', 'Verleihfreigabe');
            return;
        }
''',
 'RegulatorCrudController.php': '''
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('inventoryNumber', 'Inventarnummer');
            yield AssociationField::new('club', 'Verein');
            yield AssociationField::new('manufacturer', 'Hersteller');
            yield AssociationField::new('firstStageModel', 'Erste Stufe');
            yield AssociationField::new('primarySecondStageModel', 'Zweite Stufe');
            yield ChoiceField::new('conditionStatus', 'Zustand')->setChoices([
                'Einsatzbereit' => 'ready', 'Wartung' => 'maintenance',
                'Beschädigt' => 'damaged',
            ]);
            yield BooleanField::new('lendable', 'Verleihfreigabe');
            yield DateField::new('nextServiceAt', 'Nächste Revision')->setFormat('dd.MM.yyyy');
            return;
        }
''',
 'TankCrudController.php': '''
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('inventory', 'Inventarnummer');
            yield AssociationField::new('club', 'Verein');
            yield TextField::new('serialnumber', 'Seriennummer');
            yield ChoiceField::new('size', 'Volumen')->setChoices([
                '3 L' => 3, '5 L' => 5, '7 L' => 7, '8 L' => 8,
                '10 L' => 10, '12 L' => 12, '15 L' => 15,
                '18 L' => 18, '20 L' => 20,
            ]);
            yield DateField::new('nextCheckDate', 'Nächste Prüfung')->setFormat('dd.MM.yyyy');
            yield BooleanField::new('oxigenClean', 'O₂-rein');
            return;
        }
'''
}

project = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path.cwd()
folder = project / 'src/Controller/Admin'
planned = []
for name, snippet in SPECS.items():
    path = folder / name
    if not path.is_file():
        sys.exit('ERROR: missing ' + str(path))
    source = path.read_text(encoding='utf-8')
    if 'DiCoMa STEP85 INDEX' in source:
        print('Already installed: ' + name)
        continue
    if 'use EasyCorp\\Bundle\\EasyAdminBundle\\Config\\Crud;' not in source:
        sys.exit('ERROR: missing Crud import: ' + name)
    for field in ('TextField','AssociationField','ChoiceField','BooleanField','DateField'):
        if field + '::new(' in snippet and field not in source:
            sys.exit('ERROR: missing field import: ' + field + ' in ' + name)
    pattern = r'(public\s+function\s+configureFields\s*\(\s*string\s+\$pageName\s*\)\s*:\s*iterable\s*\{)'
    matches = list(re.finditer(pattern, source))
    if len(matches) != 1:
        sys.exit('ERROR: unexpected configureFields signature in ' + name)
    match = matches[0]
    changed = source[:match.end()] + '\n        // DiCoMa STEP85 INDEX: dedicated, consistent overview columns\n' + snippet + source[match.end():]
    planned.append((path, changed))
for path, changed in planned:
    backup = path.with_suffix(path.suffix + '.before-step85.bak')
    if backup.exists():
        sys.exit('ERROR: backup already exists: ' + str(backup))
for path, changed in planned:
    shutil.copy2(path, path.with_suffix(path.suffix + '.before-step85.bak'))
    path.write_text(changed, encoding='utf-8')
    print('Updated: ' + str(path))
print('Finished. Run PHP syntax checks and test all three index pages.')
