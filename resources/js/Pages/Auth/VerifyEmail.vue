<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0B132B] to-[#1C2541] text-white">
    <div class="w-full max-w-md bg-[#1C1F2E]/80 backdrop-blur-md rounded-2xl shadow-xl p-8">

      <div class="mb-10 text-center">
        <img src="/images/xavier-logo.png" class="h-20 mx-auto mb-4 drop-shadow-lg" />
        <h1 class="text-3xl font-bold">Verify Your Email</h1>
        <p class="mt-1 text-gray-400">Check your email to continue</p>
      </div>

      <div class="mb-4 text-sm text-gray-300">
        Thanks for signing up! To get started, please verify your email address.
      </div>

      <div 
        v-if="verificationLinkSent" 
        class="flex items-start gap-3 p-4 mb-5 text-sm border rounded-xl bg-green-500/10 border-green-500/30 animate-fadeIn"
      >
        <div class="space-y-1">
          <p class="font-bold text-green-400">Email sent successfully!</p>
          <p class="text-xs leading-relaxed text-gray-300">
            A fresh verification link is on its way to your inbox. Please check your spam folder if you don't see it within 2 minutes.
          </p>
        </div>
      </div>

      <div v-if="status === 'error'" class="p-3 mb-4 text-sm text-red-400 border rounded-lg bg-red-500/10 border-red-500/50">
        Verification failed. Please try again.
      </div>

      <form @submit.prevent="submit">
        <div class="flex flex-col gap-3 mt-6">
          
          <button type="submit" :disabled="processing || countdown > 0"
            class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2.5 rounded-lg font-semibold hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 transition-all">
            <span v-if="processing" class="w-4 h-4 border-2 rounded-full border-white/30 border-t-white animate-spin"></span>
            <span>{{ buttonText }}</span>
          </button>

          <button @click="verifyLater" type="button" :disabled="processing"
            class="w-full py-2 font-semibold text-gray-300 transition-all bg-transparent border border-gray-600 rounded-lg hover:bg-gray-700">
            Verify Later
          </button>

          <button type="button" @click="logout" :disabled="processing"
            class="mt-2 text-sm text-gray-400 underline rounded-md hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-[#00D4FF] focus:ring-offset-2">
            Log Out
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import api from '@/api';
import { useRoute, useRouter } from 'vue-router';

const router = useRouter();
const route = useRoute();
const status = ref(null);
const processing = ref(false);

// Cooldown state tracks remaining lock seconds
const countdown = ref(0);
let timerInstance = null;

onMounted(async () => {
    const verificationUrl = route.query.verify_url;

    if (verificationUrl) {
        processing.value = true;
        try {
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
});

const buttonText = computed(() => {
    if (processing.value) return 'Sending...';
    if (countdown.value > 0) return `Resend available in ${countdown.value}s`;
    return status.value === 'verification-link-sent' ? 'Resend Verification Email' : 'Send Verification Email';
});

const startTimer = () => {
    countdown.value = 60; 
    clearInterval(timerInstance);
    
    timerInstance = setInterval(() => {
        if (countdown.value > 0) {
            countdown.value--;
        } else {
            clearInterval(timerInstance);
        }
    }, 1000);
};

const submit = async () => {
    if (countdown.value > 0) return;
    
    processing.value = true;
    status.value = null;

    try {
        const res = await api.post('/email/verification-notification');

        if (res.data.status === 'verification-link-sent' || res.status === 200) {
            status.value = 'verification-link-sent';
            startTimer();
        }

        const userRes = await api.get('/profile/me');
        localStorage.setItem('user', JSON.stringify(userRes.data.data || userRes.data));

    } catch (error) {
        console.error("Resend verification failed:", error);
        status.value = 'error';
    } finally {
        processing.value = false;
    }
};

const verificationLinkSent = computed(() => status.value === 'verification-link-sent');

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

onUnmounted(() => {
    clearInterval(timerInstance);
});
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-6px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>