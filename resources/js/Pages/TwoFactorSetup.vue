<template>
  <GuestLayout>
    
      <h2 class="text-3xl font-bold mb-6  text-gray-600">Two-Factor Authentication Setup</h2>

      <div v-if="message"
        :class="message.type === 'success' ? 'bg-green-900/50 text-green-300' : ' text-red-500'"
        class="mb-4 text-sm">
        {{ message.text }}
      </div>

      <div v-if="!is2faEnabled" class="mb-6 border-b border-slate-700 pb-4">
        <p class="text-lg text-gray-600">Status: <span class="text-red-500 font-semibold">Disabled</span></p>
        <button @click="startSetup" :disabled="processing || qrImage"
          class="mt-3 py-2 px-6 bg-blue-700 rounded hover:bg-blue-600 disabled:opacity-50">
          {{ qrImage ? 'Loading QR...' : 'Start 2FA Setup' }}
        </button>
      </div>
      <div v-else class="mb-6 border-b border-slate-700 pb-4">
        <p class="text-lg">Status: <span class="text-green-400 font-semibold">Enabled!</span></p>
        <p class="text-sm text-slate-400 mt-2">Your account is protected with 2FA.</p>
      </div>


      <div v-if="qrImage && !is2faEnabled">
        <h3 class="text-xl font-semibold mb-4">Step 1: Scan the QR Code</h3>
        <p class="text-sm text-slate-300 mb-4">
          Scan the image below with your Google Authenticator or Microsoft Authenticator app.
        </p>
        <div class="bg-white p-4 rounded inline-block mx-auto mb-4">
          <div v-html="qrImage"></div>
        </div>
        <p class="text-xs text-slate-400">Secret: **{{ secretKey }}**</p>

        <h3 class="text-xl font-semibold mt-6 mb-4">Step 2: Confirm the Code</h3>
        <form @submit.prevent="confirmSetup">
          <input v-model="confirmationCode" placeholder="Enter 6-digit Code" type="text" inputmode="numeric"
            maxlength="6"
            class="w-full p-3 text-lg text-center rounded bg-slate-700 text-white focus:ring-cyan-400 focus:ring-2"
            required autofocus />
          <button type="submit" :disabled="processing"
            class="w-full mt-4 py-3 bg-gradient-to-r from-green-600 to-emerald-400 rounded font-semibold hover:from-green-500 hover:to-emerald-300 disabled:opacity-50">
            {{ processing ? 'Verifying...' : 'Verify & Activate 2FA' }}
          </button>
        </form>
      </div>
  </GuestLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import GuestLayout from "@/Layouts/GuestLayout.vue";

// 
const api = axios.create({
  baseURL: '/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Add the Sanctum Bearer token to requests
api.interceptors.request.use(config => {
  const token = localStorage.getItem('xavier_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
// ----------------------------------------------------

// Data States
const is2faEnabled = ref(false); // Assumed to be false until we check/set it

// Setup State
const qrImage = ref(null);
const secretKey = ref(null);
const confirmationCode = ref("");

// UI State
const processing = ref(false);
const message = ref(null);

onMounted(() => {
  // 💡 Best Practice: Check current 2FA status on load
  checkCurrent2FAStatus();
});

// ----------------------------------------------------
// 🛑 Helper to check initial status (Requires a /profile or /user endpoint)
// ----------------------------------------------------
async function checkCurrent2FAStatus() {
  processing.value = true;
  try {
    // You should have a protected endpoint that returns the user object
    const response = await api.get("/profile");

    // Assuming your profile endpoint returns the user data with the 2FA status
    if (response.data.data.is_2fa_enabled) {
      is2faEnabled.value = response.data.data.is_2fa_enabled;
    }
  } catch (e) {
    // If the token is invalid or expired, the API might return a 401 error here.
    console.error("Failed to fetch user profile or check 2FA status.", e);
  } finally {
    processing.value = false;
  }
}
// ----------------------------------------------------


/**
 * 1. Calls the API to generate the secret key and QR code image.
 * 💡 Maps to: GET /api/2fa/setup (Laravel STEP 2)
 */
async function startSetup() {
  processing.value = true;
  message.value = null;

  try {
    // 🛑 Use the custom 'api' instance
    const { data } = await api.get("/2fa/setup");

    if (data.success) {
      qrImage.value = data.qr;
      secretKey.value = data.secret;
      message.value = { text: "QR code generated. Please scan it now.", type: 'success' };
    } else {
      message.value = { text: data.message || "Failed to start 2FA setup.", type: 'error' };
    }
  } catch (e) {
    // 401 Unauthorized likely means the Sanctum token is missing or expired
    message.value = { text: "Error during 2FA setup: Are you logged in?", type: 'error' };
    console.error(e);
  } finally {
    processing.value = false;
  }
}

/**
 * 2. Calls the API to confirm the 6-digit code and activate 2FA.
 * 💡 Maps to: POST /api/2fa/confirm (Laravel STEP 3)
 */
async function confirmSetup() {
  processing.value = true;
  message.value = null;

  if (confirmationCode.value.length !== 6) {
    message.value = { text: "Code must be 6 digits.", type: 'error' };
    processing.value = false;
    return;
  }

  try {
    // 🛑 Use the custom 'api' instance
    const { data } = await api.post("/2fa/confirm", {
      code: confirmationCode.value
    });

    if (data.success) {
      message.value = { text: "2FA successfully activated! You will now need a code to log in.", type: 'success' };
      is2faEnabled.value = true; // Update local state

      // Clear setup data
      qrImage.value = null;
      secretKey.value = null;
    } else {
      message.value = { text: data.message || "Invalid code. Please try again.", type: 'error' };
    }
  } catch (e) {
    if (e.response && e.response.status === 422) {
      message.value = { text: "Invalid authentication code.", type: 'error' };
    } else {
      message.value = { text: "Error confirming 2FA.", type: 'error' };
    }
    console.error(e);
  } finally {
    processing.value = false;
    confirmationCode.value = "";
  }
}
</script>