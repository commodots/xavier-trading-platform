<template>
  <div class="py-4">
    <h2 class="mb-2 text-2xl font-bold text-white tracking-tight">Enable Two-Factor Authentication</h2>
    <p class="mb-6 text-sm text-slate-400">Secure your account transactions and portfolio with an authenticator app token.</p>

    <div v-if="message"
      :class="message.type === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400'"
      class="mb-6 p-4 text-sm rounded-lg border flex items-center gap-2">
      <span class="w-2 h-2 rounded-full" :class="message.type === 'success' ? 'bg-emerald-400' : 'bg-rose-400'"></span>
      {{ message.text }}
    </div>

    <div class="mb-6 p-4 rounded-xl border flex items-center justify-between"
      :class="is2faEnabled ? 'bg-emerald-950/20 border-emerald-500/20' : 'bg-slate-800/40 border-slate-700/60'">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Protection Status</p>
        <p class="text-lg font-bold" :class="is2faEnabled ? 'text-emerald-400' : 'text-rose-400'">
          {{ is2faEnabled ? 'Active & Secure' : 'Disabled / Vulnerable' }}
        </p>
      </div>
      
      <button v-if="!is2faEnabled" @click="startSetup" :disabled="processing || qrImage"
        class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-500 disabled:opacity-40 transition-all shadow-sm">
        {{ qrImage ? 'Loading...' : 'Enable 2FA' }}
      </button>
    </div>

    
    <div v-if="qrImage && !is2faEnabled" class="space-y-6 pt-4 border-t border-slate-800">
      
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span class="flex items-center justify-center w-5 h-5 rounded-full bg-slate-800 text-xs text-slate-300 font-mono">1</span>
          <h3 class="text-sm font-semibold text-slate-200">Scan the QR Code</h3>
        </div>
        <p class="mb-4 text-xs leading-relaxed text-slate-400">
          Open your authenticator app (Google Authenticator or Microsoft Authenticator) and scan the QR code below.
        </p>
        
        <div class="flex flex-col items-center justify-center p-6 mb-3 bg-white rounded-xl shadow-inner max-w-[240px] mx-auto">
          <div v-html="qrImage" class="qr-code-svg-wrapper"></div>
        </div>
        
        <div class="text-center">
          <button @click="showManualKey = !showManualKey" type="button"
            class="text-xs font-medium text-blue-400 hover:text-blue-300 underline underline-offset-4 focus:outline-none">
            {{ showManualKey ? 'Hide manual code text' : "Can't scan the image? Copy code instead" }}
          </button>
          
          <div v-if="showManualKey && secretKey" class="mt-3 p-3 bg-slate-950 rounded-lg border border-slate-800">
            <p class="text-[10px] uppercase font-mono tracking-wider text-slate-400 mb-1 text-left">Your Unique Account Secret</p>
            <div class="flex items-center justify-between gap-2 ">
              <code class="text-sm font-mono text-white select-all break-all">{{ secretKey }}</code>
            </div>
          </div>
        </div>
      </div>

      <div class="pt-4 border-t border-slate-800/60">
        <div class="flex items-center gap-2 mb-2">
          <span class="flex items-center justify-center w-5 h-5 rounded-full bg-slate-800 text-xs text-slate-300 font-mono">2</span>
          <h3 class="text-sm font-semibold text-slate-200">Confirm Authenticator Token</h3>
        </div>
        
        <form @submit.prevent="confirmSetup" class="space-y-4">
          <div>
            <input v-model="confirmationCode" 
              placeholder="000 000" 
              type="text" 
              inputmode="numeric"
              maxlength="6"
              class="w-full p-3 text-2xl tracking-[0.5em] font-mono text-center text-white rounded-xl bg-slate-950 border border-slate-800 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all placeholder:tracking-normal placeholder:text-slate-700"
              required autofocus />
          </div>
          
          <button type="submit" :disabled="processing"
            class="w-full py-3 text-sm font-semibold text-white bg-blue-600 hover:emerald-300  rounded-xl transition-all shadow-md shadow-emerald-950/20 disabled:opacity-50">
            {{ processing ? 'Validating Token...' : 'Complete Activation' }}
          </button>
        </form>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/api";

// Data States
const is2faEnabled = ref(false);

// Setup State
const qrImage = ref(null);
const secretKey = ref(null);
const confirmationCode = ref("");
const showManualKey = ref(false);

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
      message.value = { text: "Security credentials built. Please scan code.", type: 'success' };
    } else {
      message.value = { text: data.message || "Failed to initialize 2FA routine.", type: 'error' };
    }
  } catch (e) {
    message.value = { text: "Session authentication error: Check network state.", type: 'error' };
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
      showManualKey.value = false;
      message.value = { text: "Two-Factor Authentication linked successfully.", type: 'success' };
      setTimeout(() => { message.value = null; }, 5000);
    }
  } catch (e) {
    message.value = { 
      text: e.response?.data?.message || "Invalid authenticator code verification.", 
      type: 'error' 
    };
  } finally {
    processing.value = false;
    confirmationCode.value = "";
  }
}
</script>

<style scoped>

.qr-code-svg-wrapper :deep(svg) {
  width: 100% !important;
  height: auto !important;
  max-width: 180px;
  display: block;
}
</style>