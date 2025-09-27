<?php

declare(strict_types=1);

namespace App\Services\Seed;

use Nette\Database\Explorer;

/**
 * Seeder pro naplnění tabulky region hierarchií EU -> Stát -> (Kraj) -> (Okres) -> (Krajské město)
 * Vkládá pouze pokud je tabulka region prázdná.
 */
class RegionSeeder
{
    public function __construct(private Explorer $db) {}

    public function seed(): void
    {
        if ($this->db->table('region')->count('*') > 0) {
            return; // už je něco v tabulce
        }

        $nowUser = -1; // ID uživatele Systém

        $insert = fn(string $name, ?int $parentId = null) => $this->db->table('region')->insert([
            'region_name' => $name,
            'region_parentalRegion_id' => $parentId,
            'region_user_creating' => $nowUser,
            'region_user_approval' => $nowUser,
        ])->getPrimary();

        // Kořen uzel pro EU
        $euId = $insert('Evropská unie');

        // Seznam zemí EU (česky) – překlady budou v messages.*.neon podle klíče region.country.<slug>
        $countries = [
            'Belgie','Bulharsko','Česko','Dánsko','Estonsko','Finsko','Francie','Chorvatsko','Irsko','Itálie','Kypr','Litva','Lotyšsko','Lucembursko','Maďarsko','Malta','Německo','Nizozemsko','Polsko','Portugalsko','Rakousko','Rumunsko','Řecko','Slovensko','Slovinsko','Španělsko','Švédsko'];

        $countryIds = [];
        foreach ($countries as $c) {
            $countryIds[$c] = $insert($c, $euId);
        }

        // Česko – kraje
        $czId = $countryIds['Česko'] ?? null;
        if ($czId) {
            $kraje = [
                'Hlavní město Praha', 'Středočeský kraj', 'Jihočeský kraj', 'Plzeňský kraj', 'Karlovarský kraj',
                'Ústecký kraj', 'Liberecký kraj', 'Královéhradecký kraj', 'Pardubický kraj', 'Kraj Vysočina',
                'Jihomoravský kraj', 'Olomoucký kraj', 'Zlínský kraj', 'Moravskoslezský kraj'
            ];
            $krajIds = [];
            foreach ($kraje as $k) {
                $krajIds[$k] = $insert($k, $czId);
            }

            // Kompletní okresy ČR (prefix 'Okres ' kromě Prahy). Krajská města jsou vložena přímo jako podřazené uzly okresů.
            // Formát hodnoty kraje:
            //  - string => jen název okresu
            //  - array  => ['okres' => 'Název okresu', 'mesto' => 'Krajské město'] (pokud se má do okresu vložit krajské město)
            $okresyFull = [
                'Středočeský kraj' => [
                    'Benešov','Beroun','Kladno','Kolín','Kutná Hora','Mělník','Mladá Boleslav','Nymburk','Praha-východ','Praha-západ','Příbram','Rakovník'
                ],
                'Jihočeský kraj' => [
                    ['okres' => 'České Budějovice', 'mesto' => 'České Budějovice'],
                    'Český Krumlov','Jindřichův Hradec','Písek','Prachatice','Strakonice','Tábor'
                ],
                'Plzeňský kraj' => [
                    'Domažlice','Klatovy', ['okres' => 'Plzeň-město', 'mesto' => 'Plzeň'], 'Plzeň-jih','Plzeň-sever','Rokycany','Tachov'
                ],
                'Karlovarský kraj' => [
                    'Cheb', ['okres' => 'Karlovy Vary', 'mesto' => 'Karlovy Vary'], 'Sokolov'
                ],
                'Ústecký kraj' => [
                    'Děčín','Chomutov','Litoměřice','Louny','Most','Teplice', ['okres' => 'Ústí nad Labem', 'mesto' => 'Ústí nad Labem']
                ],
                'Liberecký kraj' => [
                    'Česká Lípa','Jablonec nad Nisou', ['okres' => 'Liberec', 'mesto' => 'Liberec'], 'Semily'
                ],
                'Královéhradecký kraj' => [
                    ['okres' => 'Hradec Králové', 'mesto' => 'Hradec Králové'], 'Jičín','Náchod','Rychnov nad Kněžnou','Trutnov'
                ],
                'Pardubický kraj' => [
                    'Chrudim', ['okres' => 'Pardubice', 'mesto' => 'Pardubice'], 'Svitavy','Ústí nad Orlicí'
                ],
                'Kraj Vysočina' => [
                    'Havlíčkův Brod', ['okres' => 'Jihlava', 'mesto' => 'Jihlava'], 'Pelhřimov','Třebíč','Žďár nad Sázavou'
                ],
                'Jihomoravský kraj' => [
                    'Blansko', ['okres' => 'Brno-město', 'mesto' => 'Brno'], 'Brno-venkov','Břeclav','Hodonín','Vyškov','Znojmo'
                ],
                'Olomoucký kraj' => [
                    'Jeseník', ['okres' => 'Olomouc', 'mesto' => 'Olomouc'], 'Prostějov','Přerov','Šumperk'
                ],
                'Zlínský kraj' => [
                    'Kroměříž','Uherské Hradiště','Vsetín', ['okres' => 'Zlín', 'mesto' => 'Zlín']
                ],
                'Moravskoslezský kraj' => [
                    'Bruntál','Frýdek-Místek','Karviná','Nový Jičín','Opava', ['okres' => 'Ostrava-město', 'mesto' => 'Ostrava']
                ],
            ];

            $okresIds = [];
            foreach ($okresyFull as $kraj => $list) {
                $parent = $krajIds[$kraj] ?? null;
                if (!$parent) continue;
                foreach ($list as $ok) {
                    $okresNameRaw = is_array($ok) ? ($ok['okres'] ?? null) : $ok;
                    if (!$okresNameRaw) continue;
                    $cityName = is_array($ok) ? ($ok['mesto'] ?? $ok['city'] ?? null) : null;
                    $name = str_starts_with($okresNameRaw, 'Praha') ? $okresNameRaw : 'Okres ' . $okresNameRaw;
                    $okresId = $insert($name, $parent);
                    $okresIds[$okresNameRaw] = $okresId;
                    if ($cityName) {
                        $insert($cityName, $okresId);
                    }
                }
            }
        }
    }
}
