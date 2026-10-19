<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Prize;
use App\Services\CardGenerator;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::create([
            'name' => 'Bingo Beneficente da Igreja São José',
            'organization_name' => 'Paróquia São José',
            'event_date' => now()->nextWeekday()->setTime(19, 0),
            'card_price' => 10.00,
            'theme_color' => 'amber',
            'number_min' => 1,
            'number_max' => 75,
            'use_letters' => true,
            'card_format' => '75',
            'status' => Game::STATUS_SETUP,
        ]);

        $rules = [
            ['Cesta de alimentos', Prize::RULE_ONE_LINE, 'R$ 150,00'],
            ['Liquidificador', Prize::RULE_TWO_LINES, 'R$ 200,00'],
            ['Kit cozinha', Prize::RULE_FOUR_CORNERS, 'R$ 250,00'],
            ['Prêmio principal', Prize::RULE_FULL_CARD, 'R$ 500,00'],
        ];

        foreach ($rules as $i => [$name, $rule, $value]) {
            $game->prizes()->create([
                'name' => $name,
                'rule' => $rule,
                'estimated_value' => $value,
                'sort_order' => $i + 1,
            ]);
        }

        $generator = app(CardGenerator::class);
        for ($i = 0; $i < 12; $i++) {
            $generator->createCard($game);
        }
    }
}
