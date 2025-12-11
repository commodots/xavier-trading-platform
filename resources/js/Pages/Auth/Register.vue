<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0B132B] to-[#1C2541] text-white">
    <div class="w-full max-w-md bg-[#1C1F2E]/80 backdrop-blur-md rounded-2xl shadow-xl p-8">

      <!-- Logo + Title -->
      <div class="mb-10 text-center">
        <img src="/images/xavier-logo.png" class="h-20 mx-auto mb-4 drop-shadow-lg" />
        <h1 class="text-3xl font-bold">Create Account</h1>
        <p class="mt-1 text-gray-400">Start your investment journey</p>
      </div>

      <form @submit.prevent="submit">

        <div class="mb-4">
          <label class="block mb-1 text-gray-300">Full Name</label>
          <input v-model="name" type="text" class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg"
            required />
        </div>

        <p v-if="localErrors.name" class="mt-1 mb-4 text-sm text-red-400">
          {{ localErrors.name[0] }}
        </p>

        <div class="mb-4">
          <label class="block mb-1 text-gray-300">Email</label>
          <input v-model="email" type="email" class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg"
            required />
          <p class="mt-1 text-xs text-gray-500">Must be a valid email address</p>
        </div>

        <p v-if="localErrors.email" class="mt-1 mb-4 text-sm text-red-400">
          {{ localErrors.email[0] }}
        </p>

        <div class="mb-4">
          <label class="block mb-1 text-gray-300">Password</label>
          <input v-model="password" type="password"
            class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg" required />
          <p class="mt-1 text-xs text-gray-500">At least 8 characters</p>
          <p v-if="localErrors.password" class="mt-1 text-sm text-red-400">
            {{ localErrors.password[0] || localErrors.password }}
          </p>
        </div>

        <div class="mb-4">
          <label class="block mb-1 text-gray-300">Confirm Password</label>
          <input v-model="password_confirmation" type="password"
            class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg" required />
          <p class="mt-1 text-xs text-gray-500">Must match your password</p>
          <p v-if="localErrors.password_confirmation" class="mt-1 text-sm text-red-400">
            {{ localErrors.password_confirmation[0] || localErrors.password_confirmation }}
          </p>
        </div>

        <button type="submit"
          class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2 rounded-lg font-semibold hover:opacity-90">
          Create Account
        </button>

      </form>

      <p class="mt-6 text-sm text-center text-gray-400">
        Already have an account?
        <a href="/login" class="text-[#00D4FF] hover:underline">Sign In</a>
      </p>

    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const router = useRouter();

const name = ref("");
const email = ref("");
const password = ref("");
const password_confirmation = ref("");

const localErrors = ref({});
const MIN_PASSWORD_LENGTH = 8;

const submit = async () => {
  localErrors.value = {};
  let passedClientValidation = true;

  // Check Password Length
  if (password.value.length < MIN_PASSWORD_LENGTH) {
    localErrors.value.password = [`Password must be at least ${MIN_PASSWORD_LENGTH} characters.`];
    passedClientValidation = false;
  }

  // Check Password Confirmation Match
  if (password.value !== password_confirmation.value) {
    localErrors.value.password_confirmation = ["Password does not match."];
    passedClientValidation = false;
  }

  if (!passedClientValidation) {
    return; 
  }

  try {
    const res = await axios.post("/register", {
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: password_confirmation.value
    });

    // Save user token + data
    localStorage.setItem("xavier_token", res.data.token);
    localStorage.setItem("user", JSON.stringify(res.data.user));

    router.push("/verify-email");
  } catch (err) {
    console.error(err);
    alert(err.response?.data?.message || "Registration failed");
  }
};
</script>
