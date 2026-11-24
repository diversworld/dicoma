# WCAG-2.2-AA-Abnahme

Automatisierte Tests prüfen ausschließlich Regressionen, nicht die vollständige
Konformität. Vor einer Produktivfreigabe ist die folgende manuelle Prüfung auf
aktuellen Desktop- und Mobilbrowsern durchzuführen und mit Ticket/Protokoll im
Bereich **Audit und Datenschutz** zu dokumentieren.

## Wahrnehmbarkeit

- Text-/Nicht-Text-Kontrast einschließlich Fokusindikator prüfen.
- Inhalte bei 200 % Zoom und 320-CSS-Pixel-Breite ohne Verlust bedienen.
- Informationen und Status nicht ausschließlich über Farbe vermitteln.
- Aussagekräftige Alt-Texte; dekorative Bilder leer beschriften.
- Titel, Hauptlandmarke, Überschriftenhierarchie und Sprachangabe prüfen.
- Audio-/Video-Inhalte nicht ohne Textalternative bereitstellen.

## Bedienbarkeit

- Alle Workflows ausschließlich per Tastatur abbilden.
- Fokusreihenfolge, Sichtbarkeit und Fokusfallen prüfen.
- Skip-Link und Rückkehr aus Modalfenstern testen.
- Menüs, Tabellenfilter, Uploads, Formulare und EasyAdmin-Aktionen auf Touch
  und Tastatur testen.
- Keine zeitkritischen Abläufe ohne Verlängerung; Sessionablauf klar behandeln.
- Schaltflächen/Links mindestens in der vorgesehenen Zielgröße und mit eindeutiger
  Beschriftung bereitstellen.

## Verständlichkeit

- Formularfehler identifizieren, beschreiben und korrigierbar machen.
- Pflichtfelder, Hilfetexte und Eingabeformate programmatisch zuordnen.
- Fehlermeldungen nicht nur visuell und nicht nur über Farbe ausgeben.
- Gleiche Navigation und Bedienbegriffe über Rollen hinweg verwenden.
- Fehlbedienungen bei Finanz-, SEPA-, Austritts- und Löschvorgängen bestätigen.

## Robustheit

- Semantik und Namen mit Browser-Accessibility-Tree prüfen.
- HTML, ARIA und dynamische Meldungen mit Screenreader testen.
- Ja/Nein- und Statusmeldungen bei eingeschaltetem hoher Kontrast prüfen.
- Dialoge, Dropdowns, Tabellen und Upload-Fortschritt mit aktueller
  Screenreader-/Browser-Kombination testen.

## Mindestumfang

Mindestens prüfen:

1. öffentliche Startseite, Login und Kontakt,
2. Mitgliederportal, Profil, Dokumente und Beitragsstatus,
3. Vorstandsdashboard, Aufgaben und Sitzungen,
4. Kassenwartdashboard, Forderungen, Zahlung, SEPA und Buchhaltung,
5. Dokumentupload, Freigabe und Download,
6. Aufnahme, Austritt und Datenschutzbereinigung.

Automatisiert: [tests/Integration/AccessibilitySmokeTest.php](../../tests/Integration/AccessibilitySmokeTest.php)

Das Produktivsystem gilt erst nach dokumentierter manueller Abnahme als
WCAG-freigegeben.
