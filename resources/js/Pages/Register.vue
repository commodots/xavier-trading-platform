<script setup>
import { ref } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";


const router = useRouter();


const form = ref({
  first_name: "",
  last_name: "",
  email: "",
  phone: "",
  dob: "",
  id_type: "bvn",
  id_value: "",
  password: "",
});

const loading = ref(false);
const message = ref("");

const submit = async () => {
  loading.value = true;
  message.value = "";

  try {
    const response = await axios.post("http://127.0.0.1:8000/api/onboard", form.value, {
      headers: { "Content-Type": "application/json" },
      withCredentials: true,
    });

    if (response.data.success) {
      localStorage.setItem("user", JSON.stringify(response.data.data));
      message.value = "✅ Registration successful! Redirecting...";
      setTimeout(() => router.push("/welcome"), 1500);
    } else {
      message.value = "⚠️ " + (response.data.message || "Registration failed.");
    }
  } catch (error) {
    message.value = "❌ " + (error.response?.data?.message || "Server error.");
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="min-h-screen flex bg-gradient-to-br from-[#0B132B] to-[#1C2541] text-white">
    <!-- LEFT SECTION -->
    <div class="hidden md:flex w-[45%] flex-col justify-center pl-16 pr-6 space-y-5">
      <div class="flex items-center mb-3">
        <img 
          src="/images/xavier-logo.png"
          alt="Xavier Logo"
          class="h-[90px] w-auto object-contain"
        />
      </div>

      <h2 class="text-4xl font-bold leading-snug">
        Smart Trading,<br />Simplified for You
      </h2>

      <p class="max-w-md text-base leading-relaxed text-gray-400">
        Step into the future of finance with <strong>Xavier</strong> — a digital-first 
        trading platform offering secure wallets, intelligent analytics, and instant onboarding.
      </p>

      <ul class="mt-3 space-y-2 text-sm text-gray-400">
        <li>✔ Instant BVN/NIN verification</li>
        <li>✔ Auto-created wallet</li>
        <li>✔ Smart insights & dashboard</li>
      </ul>
    </div>

    <!-- RIGHT SECTION -->
    <div class="w-full md:w-[55%] flex items-center justify-center px-8 py-12">
      <div class="bg-[#1C1F2E]/90 backdrop-blur-md rounded-2xl shadow-2xl p-10 w-full max-w-lg">
        <h2 class="mb-6 text-2xl font-bold text-center">Let’s get you started</h2>

        <form @submit.prevent="submit">
          <div class="grid grid-cols-2 gap-4 mb-4">
            <input v-model="form.first_name" type="text" placeholder="First Name" required
              class="bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:border-[#00D4FF] focus:ring-0 outline-none" />
            <input v-model="form.last_name" type="text" placeholder="Last Name" required
              class="bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:border-[#00D4FF] focus:ring-0 outline-none" />
          </div>

          <div class="mb-4">
            <input v-model="form.phone" type="tel" placeholder="Phone Number" required
              class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:border-[#00D4FF] focus:ring-0 outline-none" />
          </div>

          <div class="mb-4">
            <input v-model="form.email" type="email" placeholder="Email Address" required
              class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:border-[#00D4FF] focus:ring-0 outline-none" />
          </div>

          <div class="grid grid-cols-2 gap-4 mb-4">
            <input v-model="form.dob" type="date" required
              class="bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:border-[#00D4FF] focus:ring-0 outline-none" />
            <input v-model="form.id_value" type="text" placeholder="BVN" required
              class="bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:border-[#00D4FF] focus:ring-0 outline-none" />
          </div>

          <div class="mb-6">
            <input v-model="form.password" type="password" placeholder="Password" required
              class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 focus:border-[#00D4FF] focus:ring-0 outline-none" />
          </div>

          <button type="submit"
            class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-2 rounded-lg font-semibold hover:opacity-90 transition">
            {{ loading ? 'Verifying...' : 'Verify BVN & Continue' }}
          </button>
        </form>

        <p class="mt-6 text-sm text-center text-gray-400">
          Already have an account?
          <router-link to="/login" class="text-[#00D4FF] font-semibold hover:underline">Sign in</router-link>
        </p>

        <p v-if="message" class="mt-4 text-sm text-center text-yellow-400">{{ message }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
body {
  font-family: 'Inter', sans-serif;
}
</style>
