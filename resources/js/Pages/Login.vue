<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900 text-white">
    <div class="w-full max-w-sm p-8 bg-slate-800 rounded-lg">
	<img 
  src="/images/xavier-logo.png" 
  alt="Xavier Logo"
  class="h-16 mx-auto mb-6"
/>
      <h2 class="text-2xl font-semibold mb-4 text-center">Login</h2>

      <form @submit.prevent="loginUser">
        <input v-model="email" placeholder="Email" type="email" class="w-full p-2 rounded bg-slate-700" required />
        <input v-model="password" placeholder="Password" type="password" class="w-full p-2 rounded mt-3 bg-slate-700" required />

        <button class="w-full mt-4 py-2 bg-gradient-to-r from-blue-700 to-cyan-400 rounded">Login</button>
      </form>

      <p class="text-sm text-slate-400 mt-3 text-center">
        Don’t have an account?
        <router-link to="/register" class="text-cyan-300">Register</router-link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import api from "@/api";
import { useRouter } from "vue-router";

const router = useRouter();
const email = ref("");
const password = ref("");

async function loginUser() {
  try {
    const { data } = await api.post("/login", { email: email.value, password: password.value });
    if (data.success && data.data?.token) {
      localStorage.setItem("xavier_token", data.data.token);
      router.push("/dashboard");
    } else {
      alert(data.message || "Invalid credentials");
    }
  } catch (e) {
    alert("Login failed");
  }
}
</script>
