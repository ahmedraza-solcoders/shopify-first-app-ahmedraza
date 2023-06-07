<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();

        \App\Models\Plan::create([
            'name' => 'Standard',
            'price' => '8.99',
        ]);
        \App\Models\Plan::create([
            'name' => 'Premium',
            'price' => '14.99',
        ]);
    }
}
