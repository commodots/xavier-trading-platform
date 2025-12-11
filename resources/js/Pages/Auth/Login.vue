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
          <input v-model="email" type="email" class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg"
            required />
          <p class="mt-1 text-xs text-gray-500">Enter your registered email address</p>
        </div>

        <div class="mb-4">
          <label class="block mb-1 text-gray-300">Password</label>
          <input v-model="password" type="password"
            class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg" required />
          <p class="mt-1 text-xs text-gray-500">Enter your account password (minimum 8 characters)</p>
        </div>

        <button type="submit"
          class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2 rounded-lg font-semibold hover:opacity-90">
          Sign In
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

const submit = async () => {
  try {
    const res = await axios.post("/login", {
      email: email.value,
      password: password.value
    });

    // Save user data
    localStorage.setItem("xavier_token", res.data.token);
    localStorage.setItem("user", JSON.stringify(res.data.user));

    if (res.data.user.role === "admin") {
      router.push("/admin");
    } else {
      router.push("/dashboard");
    }
  } catch (err) {
    console.error(err);
    alert(err.response?.data?.message || "Login failed");
  }
};
</script>
