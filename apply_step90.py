#!/usr/bin/env python3
from pathlib import Path
import sys
root=Path(sys.argv[1] if len(sys.argv)>1 else '.')
p=root/'src/Controller/Admin/DashboardController.php'
s=p.read_text(encoding='utf-8')
old="MenuItem::linkTo(EquipmentReservationCrudController::class,'Ausrüstungsverleih','fa fa-hand-holding'),"
new="MenuItem::linkTo(EquipmentBookingCrudController::class,'Ausrüstungsverleih','fa fa-hand-holding')->setAction(\\EasyCorp\\Bundle\\EasyAdminBundle\\Config\\Crud::PAGE_INDEX),"
if old not in s:
    raise SystemExit('STOP: Menueintrag weicht vom Projektarchiv ab. DashboardController.php bitte manuell pruefen.')
s=s.replace(old,new)
old2="MenuItem::linkTo(EquipmentBookingCrudController::class,'Sammelreservierungen','fa fa-layer-group'),"
s=s.replace(old2,'')
backup=p.with_suffix('.php.before-step90.bak')
if backup.exists(): raise SystemExit('STOP: Backup existiert bereits; keine zweite automatische Aenderung.')
backup.write_text(p.read_text(encoding='utf-8'),encoding='utf-8')
p.write_text(s,encoding='utf-8')
print('OK: EasyAdmin-Menue angepasst; Backup:',backup)
