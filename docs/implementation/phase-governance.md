# Phase Governance – Umsetzungshinweise

## Enthalten

- Vorstandssitzungen und Beschlüsse
- Aufgaben inklusive Erinnerung
- Dokumentkategorien, Versionen, Freigabe und Mitgliederdownload
- Mahnstufen 1 bis 4 und PDF-Textausgabe
- Rollenbezogene Dashboard-KPIs

## Wichtige Routen

- `/admin/governance`
- `/admin/documents`
- `app:board:task-reminders`
- `app:membership:queue-dunning`

## Deployment

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear --env=prod
```

Das Verzeichnis `var/club-documents` wird automatisch erzeugt, muss privat bleiben und darf nicht unter `public/` veröffentlicht werden. Es gehört mit derselben Sicherungsstrategie wie die Datenbank gesichert.

Empfohlener täglicher Lauf:

```bash
php bin/console app:board:task-reminders
php bin/console app:membership:queue-dunning --minimum-days 14 --repeat-days 30
```

## Sicherheitsabnahme

- die tatsächlichen Rolle-Konto-Zuordnungen kontrollieren
- fremde Club-IDs in URLs manipulieren und 403 erwarten
- nicht freigegebene Dokumentversion als Mitglied testen
- Mahn-PDF und Download-Header prüfen
- vor Lastschriftdatei-Export eine Vier-Augen- und Verschlüsselungsprüfung durchführen

## Bekannte Restpunkte

Die bestehende Rollenvererbung ist für eine strikte Trennung der Ehrenämter noch zu breit. Produktionsstart mit mehreren Finanzberechtigten sollte daher erst nach Umsetzung einer expliziten Rollenmatrix erfolgen.
