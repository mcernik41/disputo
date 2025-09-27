<?php

declare(strict_types=1);

namespace App\Services\Seed;

use Nette\Database\Explorer;

/**
 * Seeder pro tabulku area – tematická klasifikace politické diskuze.
 * Hierarchie: Téma -> Podtéma -> Konkrétní okruhy.
 */
class AreaSeeder
{
    public function __construct(private Explorer $db) {}

    public function seed(): void
    {
        if ($this->db->table('area')->count('*') > 0) {
            return; // již naplněno
        }

        $nowUser = -1; // ID uživatele Systém

        $insert = fn(string $name, ?int $parentId = null) => $this->db->table('area')->insert([
            'area_name' => $name,
            'area_parentalArea_id' => $parentId,
            'area_user_creating' => $nowUser,
            'area_user_approval' => $nowUser,
        ])->getPrimary();

        $themes = [
            'Ekonomika' => [
                'Daně' => ['DPH', 'Daň z příjmu', 'Daňové úlevy'],
                'Práce' => ['Nezaměstnanost', 'Mzdy', 'Pracovní právo'],
                'Podnikání' => ['Živnostníci', 'Regulace', 'Investice']
            ],
            'Sociální politika' => [
                'Důchody' => ['Reforma důchodů', 'Valorizace'],
                'Rodina' => ['Mateřská', 'Přídavky na děti'],
                'Sociální dávky' => ['Hmotná nouze', 'Invalidita']
            ],
            'Zdravotnictví' => [
                'Financování' => ['Pojištění', 'Spoluúčast'],
                'Personál' => ['Lékaři', 'Sestry'],
                'Dostupnost' => ['Regionální dostupnost', 'Čekací lhůty']
            ],
            'Školství' => [
                'Kurikum' => ['Reforma RVP', 'Digitalizace výuky'],
                'Financování' => ['Platy učitelů', 'Infrastruktura'],
                'Vysoké školy' => ['Akreditace', 'Stipendia']
            ],
            'Životní prostředí' => [
                'Energie' => ['Obnovitelné zdroje', 'Jaderná energie'],
                'Odpady' => ['Recyklace', 'Plastový odpad'],
                'Klimatická změna' => ['Emise', 'Adaptace']
            ],
            'Bezpečnost' => [
                'Armáda' => ['Modernizace', 'Rozpočet'],
                'Policie' => ['Kriminalita', 'Prevence'],
                'Kyberbezpečnost' => ['Útoky', 'Ochrana dat']
            ],
            'Regionální rozvoj' => [
                'Infrastruktura' => ['Silnice', 'Železnice', 'Veřejná doprava', 'Digitální infrastruktura'],
                'Městské plánování' => ['Územní plán', 'Brownfieldy', 'Smart city'],
                'Venkov' => ['Zemědělství', 'LEADER programy', 'Venkovské služby'],
                'Cestovní ruch' => ['Regionální značky', 'Turistické atrakce', 'Ubytovací kapacity']
            ]
        ];

        $ids = [];
        foreach ($themes as $theme => $sub) {
            $themeId = $insert($theme, null); $ids[] = $themeId;
            foreach ($sub as $subTheme => $leafs) {
                $subId = $insert($subTheme, $themeId); $ids[] = $subId;
                foreach ($leafs as $leaf) {
                    $ids[] = $insert($leaf, $subId);
                }
            }
        }
    }
}
