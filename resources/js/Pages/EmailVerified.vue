<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import PrimaryButton from '@/Components/PrimaryButton.vue';

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

      <h1 class="mb-2 text-3xl font-bold">
        Email Successfully Verified
      </h1>

      <p class="mb-6 text-base text-gray-400">
        Your email has been verified. You can now access all features of your account.
      </p>

      <PrimaryButton @click="goToDashboard" class="w-full">
        Go to Dashboard
      </PrimaryButton>
    </div>
  </div>
</template>