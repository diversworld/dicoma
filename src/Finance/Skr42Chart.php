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
        return $rows;
    }
}
