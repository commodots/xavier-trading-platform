<template>
  <main id="main-content" tabindex="-1" class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0B132B] to-[#1C2541] text-white">
    <div class="w-full max-w-md bg-[#1C1F2E]/80 backdrop-blur-md rounded-2xl shadow-xl p-8">

      <!-- Logo + Title -->
      <div class="mb-10 text-center">
        <img src="/images/xavier-logo.png" alt="Xavier" class="h-20 mx-auto mb-4 drop-shadow-lg" />
        <h1 id="forgot-password-title" class="text-3xl font-bold">Forgot Password?</h1>
        <p class="mt-1 text-gray-400">
          No problem. Enter your email and we&rsquo;ll send you a link to reset your password.
        </p>
      </div>

      <!-- Success state -->
      <div v-if="status" role="status"
        class="flex items-start gap-3 p-4 mb-5 text-sm border rounded-xl bg-green-500/10 border-green-500/30 animate-fadeIn">
        <div class="flex flex-shrink-0 items-center justify-center w-10 h-10 text-green-400 bg-green-500/10 rounded-full border border-green-500/20">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
          </svg>
        </div>
        <div class="space-y-1">
          <p class="font-bold text-green-400">Reset link sent!</p>
          <p class="text-xs leading-relaxed text-gray-300">
            We&rsquo;ve emailed a password reset link to <span class="font-semibold text-gray-200">{{ sentTo }}</span>.
            Please check your inbox and spam folder if it doesn&rsquo;t arrive within a few minutes.
          </p>
        </div>
      </div>

      <!-- General error -->
      <div v-if="errorMessage" role="alert"
        class="p-3 mb-4 text-sm text-red-400 border rounded-lg bg-red-500/10 border-red-500/50 animate-fadeIn">
        {{ errorMessage }}
      </div>

      <form v-if="!status" aria-labelledby="forgot-password-title" @submit.prevent="submit" class="space-y-4">
        <div>
          <label for="forgot-email" class="block mb-1 text-gray-300">Email</label>
          <input id="forgot-email" v-model="form.email" type="email" autocomplete="email"
            class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg outline-none focus:border-[#00D4FF]"
            required autofocus />
          <p class="mt-1 text-xs text-gray-500">We&rsquo;ll send a password reset link to this email</p>
          <p v-if="emailError" class="mt-1 text-sm text-red-400">{{ emailError }}</p>
        </div>

        <button type="submit" :disabled="form.processing"
          class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2.5 rounded-lg font-bold hover:opacity-90 disabled:opacity-40 transition flex items-center justify-center gap-2">
          <span v-if="form.processing" class="w-4 h-4 border-2 rounded-full border-white/30 border-t-white animate-spin"></span>
          {{ form.processing ? 'Sending Link...' : 'Send Reset Link' }}
        </button>
      </form>

      <!-- Success actions -->
      <div v-else class="flex flex-col gap-3">
        <button type="button" @click="useDifferentEmail"
          class="w-full py-2.5 rounded-lg font-semibold text-white bg-transparent border border-gray-600 hover:bg-gray-700/40 transition">
          Send to a different email
        </button>

        <button type="button" @click="goToReset"
          class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2.5 rounded-lg font-bold hover:opacity-90 transition">
          I have a reset link
        </button>
      </div>

      <p class="mt-6 text-sm text-center text-gray-400">
        Remembered your password?
        <a href="/login" class="text-[#00D4FF] hover:underline">Back to sign in</a>
      </p>

    </div>
  </main>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from "axios";
import { useRouter } from 'vue-router';


const router = useRouter();
const status = ref(null);
const sentTo = ref('');
const errorMessage = ref('');

const form = ref({
    email: '',
    processing: false,
    errors: {}
});


const emailError = computed(() => {
    const error = form.value.errors?.email;

    if (!error) return '';

    return Array.isArray(error) ? error[0] : error;
});


const submit = async () => {
    form.value.processing = true;
    form.value.errors = {};
    status.value = null;
    errorMessage.value = '';

    try {
        const res = await axios.post('/forgot-password', {
            email: form.value.email
        });

        if (res.data.status) {
            status.value = "Password reset link sent!";
            sentTo.value = form.value.email;
            form.value.email = '';
        }

    } catch (error) {
        if (error.response?.status === 422) {
            form.value.errors = error.response.data.errors;
        } else {
            errorMessage.value = error.response?.data?.message || "An unexpected error occurred.";
        }
    } finally {
        form.value.processing = false;
    }
};

const useDifferentEmail = () => {
    status.value = null;
    sentTo.value = '';
    errorMessage.value = '';
    form.value.errors = {};
};

const goToReset = () => {
    router.push('/reset-password');
};
</script>

<style scoped>
.animate-spin {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.animate-fadeIn {
  animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-6px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
