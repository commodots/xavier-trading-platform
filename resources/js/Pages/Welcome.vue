<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();
const user = ref(null);

onMounted(() => {
  // Retrieve user info from localStorage
  const stored = localStorage.getItem("user");
  if (stored) user.value = JSON.parse(stored);
  else router.push("/login");
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

      <h1 class="mb-2 text-3xl font-bold">Welcome to Xavier</h1>
      <p class="mb-6 text-base text-gray-400">
        Smart Trading Simplified — Your account is ready, and your wallet has been created.
      </p>

      <div v-if="user" class="bg-[#0B132B]/70 rounded-xl py-4 mb-6 border border-[#00D4FF]/20">
        <p class="text-lg font-semibold text-[#00D4FF]">{{ user.first_name }} {{ user.last_name }}</p>
        <p class="text-sm text-gray-400">{{ user.email }}</p>
        <p class="text-sm text-gray-400">DOB: {{ new Date(user.dob).toLocaleDateString() }}</p>
      </div>

      <div class="mb-8 space-y-2">
        <p class="text-sm text-gray-300">✅ BVN verified successfully</p>
        <p class="text-sm text-gray-300">💳 Wallet initialized with NGN balance</p>
        <p class="text-sm text-gray-300">🚀 You're all set to explore your dashboard</p>
      </div>

      <button
        @click="goToDashboard"
        class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-2 rounded-lg font-semibold hover:opacity-90 transition"
      >
        Continue to Dashboard
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
