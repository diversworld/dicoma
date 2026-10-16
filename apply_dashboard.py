#!/usr/bin/env python3
# -*- coding: utf-8 -*-
from pathlib import Path
import shutil
p=Path('src/Controller/Admin/DashboardController.php')
if not p.exists():
    raise SystemExit('DashboardController.php nicht gefunden; im Projektverzeichnis ausführen.')
s=p.read_text(encoding='utf-8')
marker="MenuItem::subMenu('Ausrüstung & Prüfungen', 'fa fa-toolbox')->setSubItems(["
if marker not in s:
    raise SystemExit('Erwarteter EasyAdmin-Menüabschnitt fehlt. Keine Änderungen durchgeführt.')
if "EquipmentItemCrudController::class" in s:
    print('Inventarmenü bereits vorhanden. Keine Änderung.')
else:
    shutil.copy2(p,p.with_suffix('.php.before-equipment78a.bak'))
    addition="""MenuItem::subMenu('Ausrüstung & Prüfungen', 'fa fa-toolbox')->setSubItems([
                MenuItem::linkTo(EquipmentItemCrudController::class, 'Ausrüstungsinventar', 'fa fa-boxes-stacked'),
                MenuItem::linkTo(EquipmentCategoryCrudController::class, 'Ausrüstungskategorien', 'fa fa-list'),
                MenuItem::linkTo(EquipmentAttributeCrudController::class, 'Kategorie-Merkmale', 'fa fa-sliders'),"""
    p.write_text(s.replace(marker,addition,1),encoding='utf-8')
	print('EasyAdmin menu updated. Backup:', p.with_suffix('.php.before-equipment78a.bak'))