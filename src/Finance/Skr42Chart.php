<?php

declare(strict_types=1);

namespace App\Finance;

final class Skr42Chart
{
    public const NAME = 'SKR42 2026 EÜR';
    public const SOURCE = 'SKR42_Sportverein_2026_EUER_Kontenplan.xlsx';

    /** @return list<array{code:string,name:string,type:string,sphere:string,usage:string}> */
    public static function accounts(): array
    {
        $assets = [
            ['01000', 'Entgeltlich erworbene Konzessionen, Schutzrechte und Lizenzen'],
            ['01350', 'EDV-Software'],
            ['02000', 'Grundstücke und Bauten'],
            ['02300', 'Bauten auf eigenen Grundstücken'],
            ['02410', 'Gebäude'],
            ['02430', 'Hallen'],
            ['02440', 'Gaststätte'],
            ['02800', 'Außenanlagen'],
            ['04000', 'Technische Anlagen und Maschinen'],
            ['04700', 'Betriebsvorrichtungen'],
            ['05000', 'Andere Anlagen, Betriebs- und Geschäftsausstattung'],
            ['05200', 'Pkw'],
            ['05700', 'Pflegemaschinen'],
            ['06200', 'Werkzeuge'],
            ['06300', 'Betriebsausstattung'],
            ['06310', 'Kleidung'],
            ['06320', 'Geräte'],
            ['06350', 'Geschäftsausstattung'],
            ['06500', 'Büroeinrichtung'],
            ['06700', 'Geringwertige Wirtschaftsgüter'],
            ['06750', 'Wirtschaftsgüter (Sammelposten)'],
            ['06900', 'Sonstige Betriebs- und Geschäftsausstattung'],
            ['07000', 'Geleistete Anzahlungen und Anlagen im Bau'],
            ['09400', 'Darlehen'],
            ['12000', 'Forderungen aus Lieferungen und Leistungen'],
            ['12150', 'Forderungen zum allgemeinen USt-Satz (EÜR)'],
            ['12160', 'Forderungen zum ermäßigten USt-Satz (EÜR)'],
            ['12170', 'Forderungen aus steuerfreien/nicht steuerbaren Leistungen (EÜR)'],
            ['13000', 'Sonstige Vermögensgegenstände'],
            ['13100', 'Forderungen gegen Vorstandsmitglieder und Geschäftsführer'],
            ['13500', 'Kautionen'],
            ['13600', 'Darlehen'],
            ['13700', 'Durchlaufende Posten'],
            ['13720', 'Geldtransit'],
            ['14000', 'Abziehbare Vorsteuer'],
            ['14010', 'Abziehbare Vorsteuer 7 %'],
            ['14060', 'Abziehbare Vorsteuer 19 %'],
            ['14300', 'Aufzuteilende Vorsteuer'],
            ['14310', 'Aufzuteilende Vorsteuer 7 %'],
            ['14360', 'Aufzuteilende Vorsteuer 19 %'],
            ['14610', 'Forderungen aus Umsatzsteuer-Vorauszahlungen'],
        ];
        $rows = array_map(static fn (array $account): array => [
            'code' => $account[0],
            'name' => $account[1],
            'type' => 'asset',
            'sphere' => 'je nach Verwendung',
            'usage' => 'Bestand / Steuer',
        ], $assets);
        foreach ([
            ['40000', 'Echte Mitgliedsbeiträge', 'income', '1', 'Mitglieder'],
            ['40100', 'Aufnahmegebühren', 'income', '1', 'Mitglieder'],
            ['40450', 'Geldzuwendungen gegen Zuwendungsbestätigung', 'income', '1', 'Spenden'],
            ['40550', 'Sachzuwendungen gegen Zuwendungsbestätigung', 'income', '1', 'Spenden'],
            ['60020', 'Ehrenamtspauschale', 'expense', '1 / 3', 'Ehrenamt'],
            ['60040', 'Übungsleiterpauschale', 'expense', '1 / 3', 'Trainer'],
        ] as [$code, $name, $type, $sphere, $usage]) {
            $rows[] = compact('code', 'name', 'type', 'sphere', 'usage');
        }

        foreach ([
            ['19000', 'Girokonto', 'asset', '—', 'Liquidität'],
            ['19100', 'Barkasse', 'asset', '—', 'Liquidität'],
            ['19110', 'Kompressorkasse', 'asset', '—', 'Liquidität'],
            ['19120', 'Thekenkasse', 'asset', '—', 'Liquidität'],
            ['19200', 'Tagesgeld', 'asset', '2', 'Liquidität'],
            ['09500', 'Vereinsvermögen und Rücklagen', 'equity', '—', 'Vermögen'],
            ['16000', 'Verbindlichkeiten Lieferanten', 'liability', '—', 'Verbindlichkeiten'],
            ['40010', 'Mitgliedsbeiträge Jugend', 'income', '1', 'Mitglieder'],
            ['40020', 'Mitgliedsbeiträge Erwachsene', 'income', '1', 'Mitglieder'],
            ['40030', 'Abteilungsbeiträge', 'income', '1', 'Mitglieder'],
            ['40460', 'Spenden ohne Zuwendungsbestätigung', 'income', '1', 'Spenden'],
            ['40800', 'Öffentliche Sportförderung', 'income', '1 / 3', 'Zuschüsse'],
            ['40810', 'Zuschüsse Landessportbund', 'income', '1 / 3', 'Zuschüsse'],
            ['40820', 'Jugendfördermittel', 'income', '1 / 3', 'Zuschüsse'],
            ['42000', 'Kursgebühren', 'income', '3 / 4', 'Sportbetrieb'],
            ['42010', 'Turnier-Startgelder', 'income', '3 / 4', 'Sportbetrieb'],
            ['42020', 'Eintritt Sportveranstaltungen', 'income', '3 / 4', 'Sportbetrieb'],
            ['42030', 'Einnahmen Vereinsfest', 'income', '3 / 4', 'Veranstaltungen'],
            ['43000', 'Getränkeverkauf', 'income', '4', 'Gastronomie'],
            ['43010', 'Speisenverkauf', 'income', '4', 'Gastronomie'],
            ['44000', 'Werbung / Sponsoring gegen Leistung', 'income', '4', 'Sponsoring'],
            ['44010', 'Sponsoring ohne Gegenleistung', 'income', '1', 'Sponsoring'],
            ['45000', 'Zinsen', 'income', '2', 'Vermögensverwaltung'],
            ['45100', 'Vermietung Vereinsheim', 'income', '2 / 4', 'Vermögensverwaltung'],
            ['61000', 'Trainerhonorare', 'expense', '1 / 3 / 4', 'Personal'],
            ['61010', 'Schiedsrichter', 'expense', '3 / 4', 'Sportbetrieb'],
            ['61200', 'Fahrtkosten', 'expense', '1 / 3 / 4', 'Reisekosten'],
            ['61210', 'Reisekosten / Übernachtung', 'expense', '1 / 3 / 4', 'Reisekosten'],
            ['62000', 'Löhne und Gehälter', 'expense', '1 / 3 / 4', 'Personal'],
            ['62010', 'Arbeitgeber-Sozialabgaben', 'expense', '1 / 3 / 4', 'Personal'],
            ['61020', 'Trainerfortbildung', 'expense', '1 / 3', 'Personal'],
            ['63000', 'Sportgeräte Verbrauch', 'expense', '1 / 3', 'Sportbetrieb'],
            ['63010', 'Bälle / Trainingsmaterial', 'expense', '1 / 3', 'Sportbetrieb'],
            ['63020', 'Trikots / Sportbekleidung', 'expense', '1 / 3', 'Sportbetrieb'],
            ['64000', 'Sporthallenmiete', 'expense', '1 / 3', 'Sportanlagen'],
            ['64010', 'Sportplatzpflege', 'expense', '1 / 3', 'Sportanlagen'],
            ['64020', 'Reparaturen Sportanlagen', 'expense', '1 / 3', 'Sportanlagen'],
            ['64030', 'Energie Sportanlagen', 'expense', '1 / 3', 'Sportanlagen'],
            ['65000', 'Turnierkosten', 'expense', '3 / 4', 'Sportbetrieb'],
            ['65010', 'Pokale und Medaillen', 'expense', '1 / 3', 'Sportbetrieb'],
            ['66000', 'Verbandsbeiträge', 'expense', '1', 'Verwaltung'],
            ['66010', 'Versicherungen', 'expense', '1 / 3 / 4', 'Verwaltung'],
            ['66020', 'Büromaterial', 'expense', '1', 'Verwaltung'],
            ['66030', 'Porto / Telefon / Internet', 'expense', '1', 'Verwaltung'],
            ['66040', 'Software / IT', 'expense', '1 / 3 / 4', 'Verwaltung'],
            ['66050', 'Bankgebühren', 'expense', '1 / 2 / 3 / 4', 'Verwaltung'],
            ['66060', 'Buchhaltung / Steuerberatung', 'expense', '1 / 3 / 4', 'Verwaltung'],
            ['66070', 'Öffentlichkeitsarbeit', 'expense', '1 / 3 / 4', 'Verwaltung'],
            ['65020', 'Veranstaltungskosten', 'expense', '3 / 4', 'Veranstaltungen'],
            ['63030', 'Wareneinkauf Gastronomie', 'expense', '4', 'Gastronomie'],
            ['17000', 'Umsatzsteuer', 'liability', '3 / 4', 'Steuern'],
        ] as [$code, $name, $type, $sphere, $usage]) {
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'sphere' => $sphere,
                'usage' => 'Internes Vereins-Unterkonto · '.$usage,
            ];
        }
        return $rows;
    }
}
