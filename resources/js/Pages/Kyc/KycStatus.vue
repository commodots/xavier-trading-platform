<template>
  <main class="flex min-h-screen items-start justify-center bg-[#0B132B] p-3 text-white sm:items-center sm:p-4">
    <section class="w-full max-w-md rounded-2xl border border-gray-800 bg-[#1C1F2E] p-5 text-center shadow-2xl sm:p-8" aria-labelledby="kyc-status-title">
      <div v-if="loading" class="space-y-4" role="status" aria-live="polite">
        <span class="sr-only">Loading your verification status</span>
        <SkeletonLoader type="block" class="mx-auto h-16 w-16 rounded-full" />
        <SkeletonLoader type="block" class="mx-auto h-6 max-w-48" />
        <SkeletonLoader type="block" class="mx-auto h-4 max-w-72" />
      </div>

      <div v-else-if="error" class="space-y-4">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-amber-500/20 bg-amber-500/10 text-amber-300" aria-hidden="true">
          <span class="text-2xl">!</span>
        </div>
        <h1 id="kyc-status-title" class="text-xl font-bold sm:text-2xl">Status unavailable</h1>
        <p class="text-sm text-gray-300">{{ error }}</p>
        <button
          type="button"
          class="min-h-11 w-full rounded-lg bg-cyan-500 px-4 py-2.5 font-semibold text-[#0B132B] transition hover:bg-cyan-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300"
          @click="loadStatus"
        >
          Try again
        </button>
      </div>

      <div v-else-if="status === 'pending'" class="space-y-4">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-blue-500/20 bg-blue-500/10 text-[#00D4FF]" aria-hidden="true">
          <span class="text-2xl">⏳</span>
        </div>
        <h1 id="kyc-status-title" class="text-xl font-bold sm:text-2xl">Verification in review</h1>
        <p class="text-sm text-gray-300">
          Your identity verification is being reviewed. We’ll update your account when the review is complete.
        </p>
        <div class="rounded-lg border border-gray-800 bg-[#151a27] p-3 text-left text-sm text-blue-200">
          You can safely leave this page and check your status again later.
        </div>
        <RouterLink
          to="/dashboard"
          class="flex min-h-11 w-full items-center justify-center rounded-lg border border-gray-700 px-4 py-2.5 font-semibold transition hover:bg-white/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300"
        >
          Return to dashboard
        </RouterLink>
      </div>

      <div v-else-if="status === 'approved' || status === 'verified'" class="space-y-4">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-green-500/20 bg-green-500/10 text-green-400" aria-hidden="true">
          <span class="text-2xl">✓</span>
        </div>
        <h1 id="kyc-status-title" class="text-xl font-bold text-green-400 sm:text-2xl">Account verified</h1>
        <p class="text-sm text-gray-300">
          Your identity has been approved. Available account features are shown in your dashboard.
        </p>
        <RouterLink
          to="/dashboard"
          class="flex min-h-11 w-full items-center justify-center rounded-lg bg-green-500 px-4 py-2.5 font-bold text-[#0B132B] transition hover:bg-green-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-300"
        >
          Enter dashboard
        </RouterLink>
      </div>

      <div v-else-if="status === 'rejected'" class="space-y-4">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-red-500/20 bg-red-500/10 text-red-400" aria-hidden="true">
          <span class="text-2xl">!</span>
        </div>
        <h1 id="kyc-status-title" class="text-xl font-bold text-red-400 sm:text-2xl">Verification needs attention</h1>
        <p class="text-sm text-gray-300">
          Your verification was not approved. Review your details and try the verification process again.
        </p>
        <RouterLink
          to="/kyc/verify"
          class="flex min-h-11 w-full items-center justify-center rounded-lg bg-red-500 px-4 py-2.5 font-bold text-white transition hover:bg-red-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-300"
        >
          Try verification again
        </RouterLink>
      </div>

      <div v-else class="space-y-4">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-gray-700 bg-gray-800 text-gray-300" aria-hidden="true">
          <span class="text-2xl">i</span>
        </div>
        <h1 id="kyc-status-title" class="text-xl font-bold sm:text-2xl">Verification not started</h1>
        <p class="text-sm text-gray-300">
          Complete identity verification to unlock the account features that require it.
        </p>
        <RouterLink
          to="/kyc/verify"
          class="flex min-h-11 w-full items-center justify-center rounded-lg bg-cyan-500 px-4 py-2.5 font-bold text-[#0B132B] transition hover:bg-cyan-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-300"
        >
          Start verification
        </RouterLink>
      </div>
    </section>
  </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import api from '@/api';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';

const status = ref(null);
const loading = ref(true);
const error = ref('');

async function loadStatus() {
  loading.value = true;
  error.value = '';

  try {
    const response = await api.get('/kyc/status');
    status.value = response.data?.kyc_status ?? response.data?.status ?? null;
  } catch (requestError) {
    console.error('Unable to load KYC status:', requestError);
    error.value = 'We couldn’t retrieve your verification status. Check your connection and try again.';
  } finally {
    loading.value = false;
  }
}

onMounted(loadStatus);
</script>
