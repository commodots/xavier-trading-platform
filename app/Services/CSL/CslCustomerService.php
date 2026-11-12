<?php

namespace App\Services\CSL;

use App\Models\CslCustomerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CslCustomerService
{
    public function __construct(
        protected CslStockClient $client,
        protected CslAccountService $accountService,
    ) {}

    public function submitForUser(int $userId): void
    {
        $profile = CslCustomerProfile::query()
            ->where('user_id', $userId)
            ->firstOrFail();

        $user = User::query()->findOrFail($userId);

        if (! $user->kyc()->first()?->isVerified()) {
            return;
        }

        if ($profile->customer_id) {
            $this->accountService->syncForUser($userId, $profile->customer_id);

            return;
        }

        if (in_array($profile->status, ['pending_approval', 'rejected'], true)) {
            return;
        }

        $lastNameParts = preg_split('/\s+/', trim((string) $user->last_name), -1, PREG_SPLIT_NO_EMPTY);
        $lastName = array_pop($lastNameParts);

        if (blank($user->first_name) || blank($lastName)) {
            throw new RuntimeException('Xavier customer profile is missing the required CSL name fields.');
        }

        $profile->update([
            'status' => 'submitting',
            'last_error' => null,
        ]);

        $customer = array_filter([
            'last_name' => $lastName,
            'first_name' => $user->first_name,
            'other_names' => implode(' ', $lastNameParts),
            'birth_date' => $user->dob?->format('Y-m-d'),
            'mobile_phone_no' => $profile->mobile_phone_no,
            'gender_cd' => $profile->gender_cd,
            'primary_email_address' => $user->email,
            'address_street' => $profile->address_street,
            'address_city' => $profile->address_city,
            'address_country_cd' => $profile->address_country_cd,
            'bank_cd' => $profile->bank_cd,
            'bank_account_name' => $profile->bank_account_name,
            'bank_account_no' => $profile->bank_account_no,
            'bank_branch_name' => $profile->bank_branch_name,
            'bank_address_details' => $profile->bank_address_details,
            'bank_bvn_no' => $profile->bank_bvn_no,
            'external_reference1' => 'XAVIER-'.$profile->id,
        ], fn ($value): bool => $value !== null && $value !== '');

        $response = $this->client->createIndividualCustomer($customer);

        $result = $response['result'][0] ?? null;

        if (! is_array($result) || ! isset($result['code'])) {
            throw new RuntimeException('CSL customer creation response was missing its result code.');
        }

        $code = strtoupper((string) $result['code']);
        $customerId = data_get($result, 'reference.customer_id');

        if ($code === 'A' && filled($customerId)) {
            $profile->update([
                'customer_id' => (string) $customerId,
                'status' => 'approved',
                'last_error' => null,
                'submitted_at' => now(),
                'approved_at' => now(),
            ]);

            $this->accountService->syncForUser($userId, (string) $customerId);

            return;
        }

        if ($code === 'P' || ($code === 'A' && blank($customerId))) {
            $profile->update([
                'status' => 'pending_approval',
                'last_error' => null,
                'submitted_at' => now(),
            ]);

            Log::info('CSL individual customer application is pending approval.', [
                'user_id' => $userId,
            ]);

            return;
        }

        $profile->update([
            'status' => 'rejected',
            'last_error' => 'CSL rejected the individual customer application.',
            'submitted_at' => now(),
        ]);

        Log::warning('CSL rejected an individual customer application.', [
            'user_id' => $userId,
            'response_code' => $code,
        ]);
    }
}
