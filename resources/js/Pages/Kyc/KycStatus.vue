<template>
  <div class="min-h-screen flex items-center justify-center bg-[#0B132B] text-white p-4">
    <div class="w-full max-w-md bg-[#1C1F2E] rounded-2xl p-8 border border-gray-800 text-center shadow-2xl">
      
      <div v-if="status === 'pending'" class="space-y-4">
        <div class="w-16 h-16 bg-blue-500/10 text-[#00D4FF] rounded-full flex items-center justify-center mx-auto border border-blue-500/20">
          <span class="text-2xl animate-pulse">⏳</span>
        </div>
        <h2 class="text-2xl font-bold">Verification In Review</h2>
        <p class="text-sm text-gray-400">
          Our systems are matching your biometric data against the official records.
        </p>
        <div class="bg-[#151a27] p-3 rounded-lg border border-gray-800 text-xs text-blue-300">
          ⓘ Most accounts are validated in under 10 minutes.
        </div>
      </div>

      <div v-else-if="status === 'approved'" class="space-y-4">
        <div class="w-16 h-16 bg-green-500/10 text-green-400 rounded-full flex items-center justify-center mx-auto border border-green-500/20">
          <span class="text-2xl">🏆</span>
        </div>
        <h2 class="text-2xl font-bold text-green-400">Account Verified</h2>
        <p class="text-sm text-gray-400">
          Identity profiles fully approved. Your trading stocks, wallets, and withdrawals are now available.
        </p>
        <Link href="/dashboard" class="block w-full bg-green-500 text-[#0B132B] py-2.5 rounded-lg font-bold transition">
          Enter Dashboard
        </Link>
      </div>

      <div v-else-if="status === 'rejected'" class="space-y-4">
        <div class="w-16 h-16 bg-red-500/10 text-red-400 rounded-full flex items-center justify-center mx-auto border border-red-500/20">
          <span class="text-2xl">❌</span>
        </div>
        <h2 class="text-2xl font-bold text-red-400">Verification Failed</h2>
        <p class="text-sm text-gray-400">
          The biometric data could not be validated against your identification records.
        </p>
        <Link href="/kyc/verify" class="block w-full bg-red-500 text-white py-2.5 rounded-lg font-bold transition">
          Restart Verification
        </Link>
      </div>

    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  status: {
    type: String,
    default: 'pending' // pending, approved, rejected
  }
});
</script>