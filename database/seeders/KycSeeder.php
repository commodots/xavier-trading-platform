<?php

namespace Database\Seeders;

use App\Models\Kyc;
use Illuminate\Database\Seeder;

class KycSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(2, 21) as $id) {
            Kyc::updateOrCreate(
                ['user_id' => $id],
                [
                    'bvn' => '22'.str_pad((string) (100000000 + $id), 9, '0', STR_PAD_LEFT),
                    'id_type' => 'NIN',
                    'id_value' => (string) (10000000000 + $id),
                    'status' => ['pending', 'verified', 'rejected'][$id % 3],
                ]
            );
        }
    }
}
