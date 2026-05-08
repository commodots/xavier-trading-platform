<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '@/api';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const status = ref(null);
const processing = ref(false);

onMounted(async () => {
    const verificationUrl = route.query.verify_url;

    if (verificationUrl) {
        processing.value = true;
        try {
            // Try with authenticated token if present, else raw get (no token required for signed route)
            await api.get(decodeURIComponent(verificationUrl));

            router.push('/welcome?verified=1');
            return;
        } catch (error) {
            console.error('Verification failed', error);
            status.value = 'error';
        } finally {
            processing.value = false;
        }
    }

    const token = localStorage.getItem('xavier_token');
    if (!token) {
        router.push('/login');
        return;
    }

    // If already verified (local state may not include email_verified_at), we can show resend.
});

const submit = async () => {
    processing.value = true;
    status.value = null;

    try {
        const res = await api.post('/email/verification-notification');

        // Check for the status key returned by the controller
        if (res.data.status === 'verification-link-sent') {
            status.value = 'verification-link-sent';
            startTimer();
        }

        const userRes = await api.get('/profile/me');
            localStorage.setItem('user', JSON.stringify(userRes.data.data || userRes.data));

    } catch (error) {
        // Handle API errors if necessary
        console.error("Resend verification failed:", error);
        status.value = 'error';
    } finally {
        processing.value = false;
    }
};

const verificationLinkSent = computed(
    () => status.value === 'verification-link-sent',
);

const verifyLater = () => {
    router.push('/welcome');
};

const logout = async () => {
    try {
        await api.post('/logout');
    } catch (error) {
        console.warn('Logout request failed, clearing local state anyway.', error);
    } finally {
        localStorage.removeItem('xavier_token');
        localStorage.removeItem('user');
        router.push('/login');
    }
};
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0B132B] to-[#1C2541] text-white">
    <div class="w-full max-w-md bg-[#1C1F2E]/80 backdrop-blur-md rounded-2xl shadow-xl p-8">

      <!-- Logo + Title -->
      <div class="mb-10 text-center">
        <img src="/images/xavier-logo.png" class="h-20 mx-auto mb-4 drop-shadow-lg" />
        <h1 class="text-3xl font-bold">Verify Your Email</h1>
        <p class="mt-1 text-gray-400">Check your email to continue</p>
      </div>

      <div class="mb-4 text-sm text-gray-300">
        Thanks for signing up! To get started, please verify your email address.
      </div>

      <div class="mb-4 text-sm font-medium text-green-400" v-if="verificationLinkSent">
        A new verification link has been sent to the email address you provided during registration.
      </div>

      <div v-if="status === 'error'" class="p-3 mb-4 text-sm text-red-400 border rounded-lg bg-red-500/10 border-red-500/50">
        Verification failed. Please try again.
      </div>

      <form @submit.prevent="submit">
        <div class="flex flex-col gap-3 mt-6">
          <button type="submit" :disabled="processing"
            class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2 rounded-lg font-semibold hover:opacity-90 disabled:opacity-70 flex items-center justify-center gap-2 transition-all">
            <span v-if="processing" class="w-4 h-4 border-2 rounded-full border-white/30 border-t-white animate-spin"></span>
            {{ processing ? 'Sending...' : 'Send Verification Email' }}
          </button>

          <button @click="verifyLater" type="button" :disabled="processing"
            class="w-full bg-transparent border border-gray-600 text-gray-300 py-2 rounded-lg font-semibold hover:bg-gray-700 transition-all">
            Verify Later
          </button>

          <button type="button" @click="logout" :disabled="processing"
            class="text-sm text-gray-400 underline rounded-md hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-[#00D4FF] focus:ring-offset-2">
            Log Out
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
