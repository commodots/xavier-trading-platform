<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $userModel = new User();
        $usersToUpdate = User::whereNull('first_name')
            ->orWhereNull('last_name')
            ->get();

            foreach ($usersToUpdate as $user) {
            // Split the combined 'name' field by the first space
            $parts = explode(' ', $user->name, 2);
            
            $firstName = trim($parts[0] ?? $user->name);
            $lastName = trim($parts[1] ?? '');

            // Use update method to skip unnecessary attribute setting logic
            $user->update([
                'first_name' => $firstName,
                'last_name' => $lastName ?: null, 
            ]);
    }
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
