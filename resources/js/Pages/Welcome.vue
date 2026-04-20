<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from '@/api';

const router = useRouter();
const route = useRoute();
const user = ref(null);
const verificationStatus = ref(null);
const verificationError = ref(null);

const verified = computed(() => route.query.verified === '1' || verificationStatus.value === 'success');

onMounted(async () => {
  const verifyUrl = route.query.verify_url;

  if (verifyUrl) {
    try {
      const decoded = decodeURIComponent(verifyUrl);
      const res = await api.get(decoded);
      if (res.data?.success) {
        verificationStatus.value = 'success';
      } else {
        verificationStatus.value = 'error';
        verificationError.value = res.data?.message || 'Verification failed. Please try again.';
      }
    } catch (error) {
      verificationStatus.value = 'error';
      verificationError.value = error.response?.data?.message || 'Verification failed. Please try again.';
    }
  }

  const stored = localStorage.getItem("user");

  if (stored) {
    user.value = JSON.parse(stored);
  }

  // If verified and a session exists, refresh the profile to update email_verified_at state
  if (verified.value && localStorage.getItem('xavier_token')) {
    try {
      const res = await api.get('/profile/me');
      const updatedUser = res.data?.data || res.data;
      if (updatedUser) {
        user.value = updatedUser;
        localStorage.setItem("user", JSON.stringify(updatedUser));
      }
    } catch (error) {
      console.error("Failed to sync user verification status:", error);
    }
  }

  if (!user.value && !verifyUrl) {
    router.push("/login");
  }
});

const goToDashboard = () => {
  router.push("/dashboard");
};
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-[#0B132B] to-[#1C2541] text-white px-6">
    <!-- Card -->
    <div class="bg-[#1C1F2E]/90 backdrop-blur-lg rounded-3xl shadow-2xl p-10 max-w-lg w-full text-center border border-[#00D4FF]/20">
      <img
        src="/images/xavier-logo.png"
        alt="Xavier Logo"
        class="mx-auto h-[90px] w-auto mb-6 object-contain"
      />

      <h1 v-if="user" class="mb-2 text-3xl font-bold">
        Welcome, {{ user.name }} to Xavier
      </h1>
      <h1 v-else class="mb-2 text-3xl font-bold">Welcome to Xavier</h1>
      
      <p v-if="verified" class="mb-2 font-semibold text-green-300">
        ✅ You have successfully verified your email.
      </p>
      <p v-if="verificationStatus === 'error'" class="mb-2 font-semibold text-red-300">
        ⚠️ {{ verificationError }}
      </p>

      <p class="mb-6 text-base text-gray-400">
        Smart Trading Simplified — Your account is ready, and your wallet has been created.
      </p>

      <div v-if="user" class="bg-[#0B132B]/70 rounded-xl py-4 mb-6 border border-[#00D4FF]/20">
        <p class="text-lg font-semibold text-[#00D4FF]">{{ user.first_name }} {{ user.last_name }}</p>
        <p class="text-sm text-gray-400">{{ user.email }}</p>
        <p class="text-sm text-gray-400">DOB: {{ new Date(user.dob).toLocaleDateString() }}</p>
      </div>


      <button
        @click="goToDashboard"
        class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-2 rounded-lg font-semibold hover:opacity-90 transition"
      >
        Get Started!
      </button>
    </div>

    <!-- Footer -->
    <p class="mt-10 text-xs text-gray-500">
      © {{ new Date().getFullYear() }} Xavier Management Ltd — All Rights Reserved
    </p>
  </div>
</template>

<style scoped>
body {
  font-family: 'Inter', sans-serif;
}
</style>
