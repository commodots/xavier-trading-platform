<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0B132B] to-[#1C2541] text-white">
    <div class="w-full max-w-md bg-[#1C1F2E]/80 backdrop-blur-md rounded-2xl shadow-xl p-8">

      <!-- Logo + Title -->
      <div class="mb-10 text-center">
        <img src="/images/xavier-logo.png" class="h-20 mx-auto mb-4 drop-shadow-lg" />
        <h1 class="text-3xl font-bold">Welcome Back</h1>
        <p class="mt-1 text-gray-400">Sign in to continue</p>
      </div>

      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block mb-1 text-gray-300">Email</label>
          <input v-model="email" type="email" class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg outline-none focus:border-[#00D4FF]"
            required />
          <p class="mt-1 text-xs text-gray-500">Enter your registered email address</p>
        </div>

        <div class="mb-4">
          <label class="block mb-1 text-gray-300">Password</label>
          <div class="relative">
            <input 
              v-model="password" 
              :type="showPassword ? 'text' : 'password'" 
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg outline-none focus:border-[#00D4FF] pr-12" 
              required 
            />
            <button 
              type="button" 
              @click="showPassword = !showPassword"
              class="absolute text-gray-500 transition-colors -translate-y-1/2 right-3 top-1/2 hover:text-gray-300"
            >
              <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </button>
          </div>
          <p class="mt-1 text-xs text-gray-500">Enter your account password</p>
        </div>

        <div v-if="errorMessage" class="p-3 mb-4 text-sm text-red-400 border rounded-lg bg-red-500/10 border-red-500/50 animate-fadeIn">
          {{ errorMessage }}
        </div>

        <button type="submit" :disabled="loading"
          class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2 rounded-lg font-semibold hover:opacity-90 disabled:opacity-70 flex items-center justify-center gap-2 transition-all">
          <span v-if="loading" class="w-4 h-4 border-2 rounded-full border-white/30 border-t-white animate-spin"></span>
          {{ loading ? 'Signing In...' : 'Sign In' }}
        </button>
      </form>

      <p class="mt-6 text-sm text-center text-gray-400">
        Don’t have an account?
        <a href="/register" class="text-[#00D4FF] hover:underline">Create one</a>
      </p>
      <p class="mt-6 text-sm text-center text-[#00D4FF] hover:underline">
        <a href="/forgot-password">Forgot password?</a>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const router = useRouter();

const email = ref("");
const password = ref("");
const showPassword = ref(false); 
const loading = ref(false);
const errorMessage = ref("");

const submit = async () => {
  loading.value = true;
  errorMessage.value = "";

  try {
    const res = await axios.post("/login", {
      email: email.value,
      password: password.value
    });

    localStorage.setItem("xavier_token", res.data.token);
    localStorage.setItem("user", JSON.stringify(res.data.user));

    if (res.data.user.role === "admin") {
      router.push("/admin");
    } else {
      router.push("/dashboard");
    }
  } catch (err) {
    console.error(err);
    errorMessage.value = err.response?.data?.message || "Invalid credentials. Please try again.";
  } finally {
    loading.value = false;
  }
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
  animation: fadeIn 0.3s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>