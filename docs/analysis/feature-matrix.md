# DICOMA – aktueller Soll-Ist-Abgleich

Stand: 28.10.2026. Maßstab ist die zum Auftrag beigefügte Master Specification.
Frühere CSV-Inventare dokumentieren den damaligen Stand; sie sind keine aktuelle
Abnahme. „Teilweise“ bedeutet ausdrücklich nicht, dass alle genannten
Teilfunktionen fehlen, sondern dass die gesamte Spezifikationsphase noch nicht
nachweislich abgenommen ist. Existierende Tests sind noch keine erfolgreichen
Testergebnisse des aktuellen Laufs.

Dies ist ein aktueller Abgleich auf Modul-/Prozessgruppenebene. Die vollständige
Abnahme jeder einzelnen Anforderung der Master Specification bleibt ausstehend;
insbesondere werden unbekannte Abläufe nicht als „vollständig“ eingestuft.

## In dieser Konsolidierungsphase umgesetzt

| Modul | Anforderung | Status | Bestand / betroffene Dateien | Defizit | Umsetzung | Abhängigkeiten | Priorität | Test |
|---|---|---|---|---|---|---|---|---|
| Frontend | Funktionsfähige öffentliche Seiten/Formulare | Behoben | `templates/base.html.twig`, `SiteTheme` | Leere Basis lieferte leere Seiten | Konfigurierte Layoutvererbung wiederhergestellt | Twig/Theme | Kritisch | Bestehender Themewechseltest, Registrierung und Website-HTTP-Tests |
| Portale | Gemeinsamer persönlicher und administrativer Einstieg | Implementiert | `PortalController`, `PortalNavigation`, `templates/portal/` | Kein gemeinsamer Einstieg | Geschütztes `/portal`, getrennte Linkgruppen, private/no-store | Vorhandene Rollen/Fachprüfungen | Hoch | Jeder angebotene Einstiegslink wird als jeweiliges Konto geöffnet |
| Navigation | Gäste und unabhängige Funktionsrollen | Behoben | `PortalNavigation`, Frontendmenü | Gastbereich fehlte; Verwaltung hinter Mitgliedsrolle versteckt | Kontobezogene Ausbildung; Funktionsportale mit benötigtem Vereinskontext | Person/Member/CompetitionAccess/PublicWebsite | Hoch | Rollenmatrix einschließlich ehemaliger Mitglieder, Mehrfachrollen und fehlendem Verein |
| Navigation | Gemeinsame öffentliche Links | Implementiert | Header, Footer, `getPublicLinks()` | Doppelte manuelle Listen | Eine Quelle für beide Navigationsbereiche | Öffentlicher Website-Verein | Mittel | Öffentliche Links und ausgeschlossene inaktive/unbekannte Vereine |
| Login | Konsistenter Einstieg und Zielpfaderhalt | Behoben | Authenticator, SecurityController, Security-Konfiguration | Drei unterschiedliche Ziele | `/portal` als Fallback, gespeichertes Ziel weiterhin vorrangig | Session/Security | Hoch | Echter Login mit geschütztem Ziel; erneuter Loginseitenaufruf; CSRF-Logout |
| Backend | Verwaltungsansichten unabhängig von Adminrolle | Behoben | `competition/layout.html.twig` | Berechtigte Mannschaftsbetreuung erhielt Frontendrahmen | EasyAdmin-Rahmen mit eingeschränkter Sportnavigation | Vorhandene Objektberechtigung | Hoch | Trainer/Mannschaftsführung ohne Adminrechte, fremde/abgelaufene Zuordnung verweigert |
| Ausbildung | Persönliche Ansicht bleibt Self-Service | Behoben | `TrainingPortalController`, `training/index.html.twig` | Eigene Frontendseite enthielt verwaltete fremde Kurse | Verwaltungsabfragen/-liste nur im Backend | Bestehender Ausbildungsprozess | Hoch | Dasselbe Personalkonto sieht unter `/training` keine Verwaltung und unter `/admin/training` nur berechtigte Kurse |
| Kalender | Frontend-/Backendgrenze und Kompatibilität | Implementiert | `Frontend/ClubCalendarController`, Kalenderlayout | Mitglieder gelangten über Backendpfad in Sportnavigation | Neuer persönlicher Pfad; bestehender Adminpfad/Kurzaufruf erhalten; gleiche Projektion | ClubCalendar/Zugriffsservices | Hoch | Admin in beiden Ansichten, gewöhnliches Mitglied ohne Sportverwaltungslinks, bestehende Kalenderprojektion |
| Thekendienst | Unabhängige Leitungsrolle | Behoben | Security-Konfiguration, bestehender BarDutyAccess | Fallback verlangte Mitgliedsrolle trotz Funktionsberechtigung | Basisroute authentifiziert; Fachservice prüft Verein/Funktion weiterhin | BarDutyAccess | Hoch | Ehemaliges Mitglied mit Leitungsrolle; fremder und gewöhnlicher Direktaufruf verweigert |
| Fachportale | Einheitlicher Rückweg | Implementiert | Gemeinsamer Portalwechsel, Fachlayouts/EasyAdmin-Benutzermenü | Fehlende/abweichende Rückwege | Portalübersicht und öffentliche Website statt Rechteausweitung | Bestehende Layouts | Mittel | Portal-/Fachrollen und Sportbackend ohne öffentlichen Header |
| Öffentlich | Vereinsinformationen und Kontakt | Implementiert | PublicWebsiteController, `website/club.html.twig` | Fehlende öffentliche Informationsseiten | Bestehende Clubfelder, ausschließlich aktiver Website-Verein, keine internen Notizen, sichere Links | PublicWebsite/PUBLIC_SITE_CLUB_ID | Mittel | Clubfremde/private Inhalte, HTML, unsicheres URL-Schema, inaktiver/unbekannter Verein |
| UI/UX | Mobile und übersetzte Navigation | Implementiert | `portal-navigation.css`, vier Kataloge, Header | Doppelte IDs, leere Dropdowns, Überlauf bei 1024 px | Eindeutige IDs, Buttons/ARIA, Fokus, Umbruch und begrenztes Logo | Bootstrap 4 / Themevariablen | Hoch | Browserbreiten 320/390/768/1024/1440 px, Tastaturbedienung und Sprach-/Katalogtests |

## Ausgangsbefund vor dieser Konsolidierung und weiterhin offene Fachabnahme

| Modul / Spezifikation | Status | Geprüfter Bestand | Defizit / Umsetzung | Abhängigkeiten | Priorität | Abnahmetest |
|---|---|---|---|---|---|---|
| Technik / Phasen 1–2 | Teilweise | Symfony 7, Doctrine ORM 2, EasyAdmin 4, Twig, Composer, CI, Migrationen | Alte Analysen aktualisieren; aktuelle Regression und Betriebsfreigabe erforderlich | Alle Module | Hoch | Container-/Twig-/YAML-Lint, isolierte Regression; separate MariaDB-Migrationsprüfung |
| Personen / 5.1 | Implementiert, Regression erforderlich | `Person`, `User`, `Member`, `PersonModelTest`, Foundation-HTTP-Tests | Identität beim Gastbeitritt und Löschen bewahren; keine zweite Person anlegen | Mitglieder/Ausbildung | Hoch | Drei PersonModel-Tests und Gastbeitritt im Ausbildungsprozess |
| Vereinsgrenzen / 5.2 | Teilweise | Clubbeziehungen, Voter, Repositoryfilter, `CompetitionAccess`, `BarDutyAccess` | Kein pauschaler Vollständigkeitsnachweis für sämtliche Fachcontroller | Alle Fachmodule | Kritisch | Rollen-/Fremdvereins- und Direktaufruftests |
| Mitglieder / Phase 3 | Teilweise | `MembershipController`, `MembershipService`, `MembershipFinanceService`, `MembershipAutomation`, private Dokumentablage | Vorhandene Anträge, Beiträge, SEPA, Self-Service und Erinnerungen aktuell regressieren; vollständige fachliche Abnahme offen | Person/Verein/Dokumente | Hoch | `Phase3MembershipHttpTest`, Unit-/Membership-Tests |
| Ausbildung / Phase 4 | Teilweise | `TrainingPortalController`, `FrontendCourseRegistrationService`, Fortschritt, Anwesenheit, Abschluss/Archiv, Kurszahlungen | Gast-Self-Service im Menü fehlt; Verwaltungs-/Self-Service-Einstiege konsolidieren; Regression | Person/Kalender/Qualifikationen | Hoch | `Phase4TrainingHttpTest`, Gast ohne Mitgliedschaft, späterer Beitritt, Bescheinigung |
| Mannschaften/Turniere / Phase 5 | Teilweise, nicht fehlend | `TeamTraining`, `Tournament*`, `CompetitionController`, `TournamentService` | Nichtadmin-Verwaltung verwendet Frontendlayout; einheitliche Backendnavigation erforderlich | Verein/Person/Kalender | Hoch | `Phase5CompetitionHttpTest`, betreuende Rolle ohne Adminrechte |
| Ausrüstung/Flaschen / Phase 6 | Teilweise | Inventar, Reservierungen, Verleihworkflow, Wartung, private Dokumente, Flaschenprüfungen/Füllung | Bestehende Ketten und Konkurrenzfälle aktuell regressieren; Portalrückwege ergänzen | Verein/Mitglied/Kasse | Hoch | `Phase6EquipmentHttpTest`, separate MariaDB-Konkurrenztests |
| Gerätewartkasse / Phase 7 | Teilweise | Kassenbuch, Abschluss, Auszahlung, Korrekturen, Belege und Gebührenservices | Nicht aus vorhandenen CRUDs als vollständig ableiten; Finanz-/Konkurrenzabnahme bleibt erforderlich | Verleih/Füllung/Prüfung | Kritisch | Zahlungsduplikate, Abschluss/Korrektur und Fremdverein |
| Thekendienst / Phase 8 | Teilweise, nicht fehlend | `BarDutyController`, Schichten/Tausch/Vertretung, Konten, Erinnerungen, separate Thekenkasse | Gemeinsamen Portalwechsel ergänzen; Bestandsregression | Mitglied/Verein/Kalender | Mittel | `Phase8BarDutyHttpTest`, Tausch/Anwesenheit/Pflichtstunden/Kassenabschluss |
| Veranstaltungen / Phase 9 | Teilweise | `WebsitePublication(kind=event)`, öffentliche Liste/Detail, redaktionelle Freigabe | Ankündigungen sind **keine** Anmeldung: Teilnehmer, Kapazität, Warteliste, Fristen, Gebühren/Zahlungen, Storno und Erinnerungen fehlen | Person/Verein/Kalender/Finanzen | Hoch | Öffentliche/interne Sichtbarkeit; letzter Platz; Warteliste; Storno; eigene vs. fremde Teilnehmer |
| Clubreisen / Phase 10 | Fehlend | Kein eigener Reise-/Buchungsworkflow im aktuellen Modell | Stammdaten, Leitung, Leistungen/Bedingungen, Anmeldung, Voraussetzungen, Unterkunft/Transport, Anzahlungen/Restzahlungen/Erstattungen vollständig integrieren | Person/Qualifikation/Ausrüstung/Finanzen/Kalender | Hoch | Voraussetzungen, Kapazität, Fremdzugriff, Storno/Erstattung |
| Öffentliche Website / Phase 11 | Defekt im Ausgangsstand | Home, Artikel, Veranstaltungsankündigungen, Kurskatalog, Mitgliedsantrag | Leere `base.html.twig` beheben; öffentliche Vereinsinfos/Kontakt/Mannschafts-/Reiseansichten noch nicht vollständig | SiteTheme/Website-Verein | Kritisch | Tatsächlich gerenderte Seiten, Formulare und Stylesheets |
| Mitglieder-/Gastportal / Phase 11 | Teilweise | `/my`, `/training`, Profil, Mitgliedschaft, Ausrüstung, Dienste | Gemeinsamer Einstieg, sichtbarer Gastbereich, keine toten Verwaltungslinks; Veranstaltungen/Reisen fehlen | Fachmodule/Rollen | Hoch | Gast, aktives/inaktives Mitglied, mehrere unabhängige Rollen |
| Fach-/Vorstandsportale / Phase 11 | Teilweise | EasyAdmin, Ausbildungs-, Gerätewart-, Sport-, Thekendienst- und Redaktionsportale | Einheitlicher Portalwechsel; vollständige Vorstandsaufgaben/Beschlüsse/Dokumente/Berichte nicht nachgewiesen | Rollen/Vereinszuordnung | Hoch | Berechtigte Einstiege erreichbar, unberechtigte Direktaufrufe verweigert |
| Kalender / Phase 12 | Teilweise | `ClubCalendar` projiziert Kurse, Mannschaftstraining, Turniere und Thekendienste ohne Terminkopien | Mitglieder-Leseansicht von Sportverwaltung lösen; Reise-/Veranstaltungs-/Gerätewartprojektionen fehlen; eigene Kurstermine für Lernende prüfen | Fachtermine/Berechtigungen | Hoch | Eindeutige Quellschlüssel, nur erlaubte Termine, keine Sportverwaltungslinks für gewöhnliche Mitglieder |
| Benachrichtigungen / Phase 12 | Teilweise | Mitgliedschafts-, TÜV- und Thekendienstnachrichten/-automation | Kein modulübergreifender Ereignis-/Inbox-Vertrag; neue Module integrieren, Versand idempotent gestalten | Fachereignisse/Versand | Mittel | Ereignis genau einmal; Empfänger-/Vereinsgrenzen; Versandfehler sichtbar |
| Dokumente / Phasen 12/17 | Teilweise | Private Mitglieds-/TÜV-/Geräte-/Turnierdokumente, Ausbildungsarchiv | Kein einheitlicher berechtigungsgesteuerter Dokumenteneinstieg; Aufbewahrung/Export/Löschkonzept fachlich vervollständigen | Eigentümer/Verein | Hoch | Fremdzugriff verweigert; Download nicht öffentlich; Aufbewahrung nachvollziehbar |
| Globale Suche / Phase 12 | Fehlend | Fachliche Suchfelder, kein globaler Suchprozess | Suchindex/-abfragen nur über erlaubte Quellen; keine Metadatenlecks | Alle Fachberechtigungen | Mittel | Gesperrte Personen/Dokumente auch nicht in Treffern/Zählern |
| Berichte / Phase 12 | Teilweise | Fachliche Dashboards und CSV-/XLSX-Exporte | Kein vollständiger rollenabhängiger Vereinsberichtskatalog | Fachmodule/Finanzen | Mittel | Export und Dashboard mit identischen Vereinsgrenzen |
| UI/UX / Phase 16 | Teilweise/defekt | Airspace, gemeinsame CSS-Tokens, EasyAdmin, mehrere Fachlayouts | Leere Basis, doppelte Menü-IDs, leere Dropdowns, fehlende Gast-/Presse-/Kursadmin-Einstiege und widersprüchliche Login-Ziele korrigieren | Portalnavigation | Hoch | HTML-Struktur, mobile Navigation, Übersetzungen, fokussierbare Links, Logout |
| Datenschutz/Betrieb / Phasen 17–19 | Teilweise | InstallationGuard, CSRF, private Ablagen, Auditdaten, Backup-/Migrationsgates | Keine vollständige Rechts-/Betriebsfreigabe; reale Wiederherstellung und neue Geschäftsprozesse separat abnehmen | Gesamtsystem | Kritisch | Separate Testdatenbank, Migrations-/Restore-Tests; keine Änderungen produktiver Daten ohne Freigabe |

## Weiteres Vorgehen

Die umgesetzte Teilphase ist in [phase11-portals.md](../phase11-portals.md)
beschrieben. Die genauen Prüfergebnisse stehen im
[Prüfbericht](../testing/test-report.md). Danach folgen die fachlich fehlenden Prozesse gemäß
[implementation-roadmap.md](implementation-roadmap.md). Die Auswahl „gesamte
Master Specification“ ist ein phasenweiser Gesamtauftrag, keine Behauptung,
sämtliche Anforderungen seien bereits umgesetzt.
