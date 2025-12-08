<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Kyc;

class KycSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(2, 21) as $id) {
            Kyc::create([
                'user_id' => $id,
                'bvn' => '22' . rand(100000000, 999999999),
                'id_type' => 'NIN',
                'id_number' => rand(10000000000, 99999999999),
                'status' => ['pending','verified','rejected'][rand(0,2)],
            ]);
        }
    }
}
