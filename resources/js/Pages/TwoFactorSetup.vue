<template>
  <GuestLayout>
    
      <h2 class="mb-6 text-3xl font-bold text-gray-600">Two-Factor Authentication Setup</h2>

      <div v-if="message"
        :class="message.type === 'success' ? 'text-green-500' : ' text-red-500'"
        class="mb-4 text-sm">
        {{ message.text }}
      </div>

      <div v-if="!is2faEnabled" class="pb-4 mb-6 border-b border-slate-700">
        <p class="text-lg text-gray-600">Status: <span class="font-semibold text-red-500">Disabled</span></p>
        <button @click="startSetup" :disabled="processing || qrImage"
          class="px-6 py-2 mt-3 bg-blue-700 rounded hover:bg-blue-600 disabled:opacity-50">
          {{ qrImage ? 'Loading QR...' : 'Start 2FA Setup' }}
        </button>
      </div>
      <div v-else class="pb-4 mb-6 border-b border-slate-700">
        <p class="text-lg">Status: <span class="font-semibold text-green-400">Enabled!</span></p>
        <p class="mt-2 text-sm text-slate-400">Your account is protected with 2FA.</p>
      </div>


      <div v-if="qrImage && !is2faEnabled">
        <h3 class="mb-4 text-xl font-semibold text-gray-500">Step 1: Scan the QR Code</h3>
        <p class="mb-4 text-sm text-gray-500">
          Scan the image below with your Google Authenticator or Microsoft Authenticator app.
        </p>
        <div class="inline-block p-4 mb-4 bg-gray-300 rounded ">
          <div v-html="qrImage"></div>
        </div>
        <p class=" text-slate-400">Can't scan? Click here</p>

        <h3 class="mt-6 mb-4 text-xl font-semibold">Step 2: Confirm the Code</h3>
        <form @submit.prevent="confirmSetup">
          <input v-model="confirmationCode" placeholder="Enter 6-digit Code" type="text" inputmode="numeric"
            maxlength="6"
            class="w-full p-3 text-lg text-center text-white rounded bg-slate-700 focus:ring-cyan-400 focus:ring-2"
            required autofocus />
          <button type="submit" :disabled="processing"
            class="w-full py-3 mt-4 font-semibold rounded bg-gradient-to-r from-green-600 to-emerald-400 hover:from-green-500 hover:to-emerald-300 disabled:opacity-50">
            {{ processing ? 'Verifying...' : 'Verify & Activate 2FA' }}
          </button>
        </form>
      </div>
  </GuestLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/api";
import GuestLayout from "@/Layouts/GuestLayout.vue";


// Data States
const is2faEnabled = ref(false);

// Setup State
const qrImage = ref(null);
const secretKey = ref(null);
const confirmationCode = ref("");

// UI State
const processing = ref(false);
const message = ref(null);

onMounted(() => {
  checkCurrent2FAStatus();
});

async function checkCurrent2FAStatus() {
  processing.value = true;
  try {
   
    const response = await api.get("/user/profile/show");

    const userData = response.data.data || response.data;
    
      is2faEnabled.value = !!userData.google2fa_enabled;

  } catch (e) {
    console.error("Failed to fetch user profile or check 2FA status.", e);
  } finally {
    processing.value = false;
  }
}



async function startSetup() {
  processing.value = true;
  message.value = null;

  try {
    
    const { data } = await api.get("/2fa/setup");

    if (data.success) {
      qrImage.value = data.qr;
      secretKey.value = data.secret;
      message.value = { text: "QR code generated. Please scan it now.", type: 'success' };
    } else {
      message.value = { text: data.message || "Failed to start 2FA setup.", type: 'error' };
    }
  } catch (e) {
  
    message.value = { text: "Error during 2FA setup: Are you logged in?", type: 'error' };
    console.error(e);
  } finally {
    processing.value = false;
  }
}

async function confirmSetup() {
  processing.value = true;
  message.value = null;


  try {
    
    const { data } = await api.post("/2fa/confirm", {
      code: confirmationCode.value
    });

    if (data.success) {
      is2faEnabled.value = true;
      qrImage.value = null;
      
      message.value = { text: "2FA is now active!", type: 'success' };
      
      setTimeout(() => { message.value = null; }, 5000);
    }
  } catch (e) {
    message.value = { 
      text: e.response?.data?.message || "Verification failed. Check the code.", 
      type: 'error' 
    };
  } finally {
    processing.value = false;
    confirmationCode.value = "";
  }
}
</script>