<?php

namespace Database\Seeders;

use App\Models\LastUpdatedMessage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LastUpdatedIdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = LastUpdatedMessage::first();
        if (!$data) {
            LastUpdatedMessage::create([
                'last_updated_id' => 516966384
            ]);
        }
    }
}
