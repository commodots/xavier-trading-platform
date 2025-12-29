<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-900 text-white">
    <div class="w-full max-w-sm p-8 bg-slate-800 rounded-lg">
      <img src="/images/xavier-logo.png" alt="Xavier Logo" class="h-16 mx-auto mb-6" />
      <h2 class="text-2xl font-semibold mb-4 text-center">
        {{ step === 1 ? 'Login' : '2FA Verification' }}
      </h2>

      <div v-if="error" class="bg-red-900/50 text-red-300 p-3 mb-4 rounded text-sm">
        {{ error }}
      </div>

      <form @submit.prevent="submitForm">

        <div v-if="step === 1">
          <input v-model="email" placeholder="Email" type="email"
            class="w-full p-2 rounded bg-slate-700 focus:ring-cyan-400 focus:ring-2" required />
          <input v-model="password" placeholder="Password" type="password"
            class="w-full p-2 rounded mt-3 bg-slate-700 focus:ring-cyan-400 focus:ring-2" required />
        </div>

        <div v-else-if="step === 2">
          <p class="text-sm text-slate-300 mb-3">
            Please enter the 6-digit code from your authenticator app for **{{ email }}**.
          </p>
          <input v-model="twoFactorCode" placeholder="6-Digit Code" type="text" inputmode="numeric" maxlength="6"
            class="w-full p-3 text-2xl tracking-widest text-center rounded bg-slate-700 focus:ring-cyan-400 focus:ring-2"
            required autofocus />
        </div>

        <button type="submit" :disabled="processing"
          class="w-full mt-4 py-2 font-semibold bg-gradient-to-r from-blue-700 to-cyan-400 rounded hover:from-blue-600 hover:to-cyan-300 disabled:opacity-50">
          {{ buttonText }}
        </button>
      </form>

      <p class="text-sm text-slate-400 mt-3 text-center">
        Don’t have an account?
        <router-link to="/register" class="text-cyan-300 hover:underline">Register</router-link>
      </p>

      <p v-if="step === 2" class="text-xs text-slate-500 mt-2 text-center">
        <button @click="resetForm" class="hover:text-cyan-400">Cancel / Go Back</button>
      </p>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import api from "@/api";
import { useRouter } from "vue-router";

const router = useRouter();


const email = ref("");
const password = ref("");

// 2FA State
const twoFactorCode = ref("");
const userId = ref(null);
const step = ref(1);

// UI State
const processing = ref(false);
const error = ref(null);

// Computed property for dynamic button text
const buttonText = computed(() => {
  if (processing.value) return 'Loading...';
  if (step.value === 1) return 'Sign In';
  if (step.value === 2) return 'Verify & Complete Login';
  return 'Submit';
});

// Resets the form to the initial state
const resetForm = () => {
  step.value = 1;
  password.value = '';
  twoFactorCode.value = '';
  userId.value = null;
  error.value = null;
};

// Main function called by the form submit
const submitForm = () => {
  if (step.value === 1) {
    loginUser();
  } else if (step.value === 2) {
    verify2FA();
  }
};

/**
 * 1. Handles the initial email/password submission to /api/login
 */
async function loginUser() {
  processing.value = true;
  error.value = null;

  try {
    const response = await api.post("/login", {
      email: email.value,
      password: password.value
    });

    const data = response.data;

    if (data.success) {
      if (data.requires_2fa) {
        // 🛑 STEP 2 REQUIRED: Switch the view to the 2FA challenge
        userId.value = data.user_id; // Capture the user ID (optional, but good)
        step.value = 2;
        password.value = ''; // Clear password for security
        error.value = null;

      } else if (data.token) {
        // 🛑 LOGIN SUCCESS: No 2FA required
        handleSuccessfulLogin(data.token, data.user);
      }
    } else {
      // General API error (e.g., success: false and a message)
      error.value = data.message || "Login failed due to an unknown issue.";
    }
  } catch (e) {
    if (e.response && e.response.status === 401) {
      // Invalid credentials from the API
      error.value = e.response.data.message || "Invalid email or password.";
    } else {
      // Network or other server error
      error.value = "An error occurred while connecting to the server.";
      console.error(e);
    }
  } finally {
    processing.value = false;
  }
}

/**
 * 2. Handles the 2FA code submission to /api/2fa/verify
 */
async function verify2FA() {
  processing.value = true;
  error.value = null;

  // Basic client-side validation
  if (twoFactorCode.value.length !== 6) {
    error.value = "The 2FA code must be 6 digits.";
    processing.value = false;
    return;
  }

  try {
    const response = await api.post("/2fa/verify", {
      email: email.value, // We use the email from Step 1 to identify the user
      token: twoFactorCode.value // 'token' is the 6-digit TOTP code
    });

    const data = response.data;

    if (data.success && data.token) {
      // 🛑 FINAL SUCCESS: Token received after 2FA verification
      handleSuccessfulLogin(data.token, data.user);
    } else {
      error.value = data.message || "Verification failed.";
    }
  } catch (e) {
    if (e.response && e.response.status === 422) {
      // Invalid 2FA token from the API
      error.value = e.response.data.message || "Invalid 2FA code.";
    } else {
      error.value = "An error occurred during verification.";
      console.error(e);
    }
  } finally {
    processing.value = false;
    twoFactorCode.value = ''; // Clear code regardless of result
  }
}

/**
 * Finalizes the login process
 */
async function handleSuccessfulLogin(token, user) {
  localStorage.clear(); 

  localStorage.setItem("xavier_token", token);
  localStorage.setItem("user", JSON.stringify(user));

  
  if (user.role === 'admin' || user.role === 'compliance') {
    window.location.href = "/admin/dashboard";
  } else {
    window.location.href = "/dashboard";
  }
}
</script>