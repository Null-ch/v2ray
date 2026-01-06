<?php

namespace Database\Seeders;

use App\Models\Pricing;
use Illuminate\Database\Seeder;

class PricingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pricings = [
            [
                'title' => '7 дней',
                'duration' => 7 * 24 * 60 * 60 * 1000,
                'price' => 30.00,
            ],
            [
                'title' => '30 дней',
                'duration' => 30 * 24 * 60 * 60 * 1000,
                'price' => 70.00,
            ],
            [
                'title' => '3 месяца',
                'duration' => 90 * 24 * 60 * 60 * 1000,
                'price' => 170.00,
            ],
            [
                'title' => '6 месяцев',
                'duration' => 180 * 24 * 60 * 60 * 1000,
                'price' => 340.00,
            ],
        ];

        foreach ($pricings as $pricing) {
            Pricing::firstOrCreate(
                ['title' => $pricing['title']],
                $pricing
            );
        }
    }
}

