<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AIFeedbackSeeder::class,
        ]);

        // 将来的にマスターデータのSeederを追加予定
        // $this->call([
        //     ConcentrationTypeSeeder::class,
        //     FragranceCategorySeeder::class,
        //     FragranceNoteSeeder::class,
        //     SceneSeeder::class,
        //     SeasonSeeder::class,
        // ]);
    }
}
