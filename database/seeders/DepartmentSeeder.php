<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Technology', 'code' => 'TECH', 'description' => 'Technology and Engineering'],
            ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Marketing and Communications'],
            ['name' => 'Finance', 'code' => 'FIN', 'description' => 'Finance and Accounting'],
            ['name' => 'Operations', 'code' => 'OPS', 'description' => 'Operations and Administration'],
            ['name' => 'Human Resources', 'code' => 'HR', 'description' => 'Human Resources'],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }
    }
}