<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'trial.duration',
                'value' => '7',
                'type' => 'int',
                'group' => null,
                'description' => null,
                'is_system' => true,
            ],
            [
                'key' => 'trial.tag',
                'value' => 'NL',
                'type' => 'string',
                'group' => null,
                'description' => null,
                'is_system' => true,
            ],
            [
                'key' => 'ref.bonus.duration',
                'value' => '2',
                'type' => 'int',
                'group' => null,
                'description' => null,
                'is_system' => true,
            ],
            [
                'key' => 'default.monthly.cost',
                'value' => '70',
                'type' => 'int',
                'group' => null,
                'description' => null,
                'is_system' => true,
            ],
            [
                'key' => 'payments.use_telegram_invoice',
                'value' => true,
                'type' => 'bool',
                'group' => null,
                'description' => null,
                'is_system' => true,
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}

