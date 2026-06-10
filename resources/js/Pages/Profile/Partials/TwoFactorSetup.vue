<template>
  <div class="py-4">
    <h2 class="mb-1 text-2xl font-bold text-white tracking-tight">Two-Factor Authentication</h2>
    <p class="mb-6 text-sm text-slate-400">Add an extra layer of security to your account using an authenticator app.</p>

    <div v-if="message"
      :class="message.type === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400'"
      class="mb-6 p-4 text-sm rounded-lg border flex items-center gap-2">
      <span class="w-2 h-2 rounded-full" :class="message.type === 'success' ? 'bg-emerald-400' : 'bg-rose-400'"></span>
      {{ message.text }}
    </div>

    <div class="mb-8 p-5 rounded-2xl border flex items-center justify-between transition-all"
      :class="is2faEnabled ? 'bg-emerald-500/5 border-emerald-500/20 shadow-lg shadow-emerald-500/5' : 'bg-slate-800/40 border-slate-700/60'">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mb-1">Protection Status</p>
        <p class="text-xl font-black" :class="is2faEnabled ? 'text-emerald-400' : 'text-rose-500'">
          {{ is2faEnabled ? 'Active & Secure' : 'Disabled / Vulnerable' }}
        </p>
      </div>
      
      <div class="flex gap-3">
        <button v-if="!is2faEnabled" @click="startSetup" :disabled="processing || qrImage"
          class="px-5 py-2.5 text-xs font-bold bg-blue-600 text-white rounded-xl hover:bg-blue-500 disabled:opacity-40 transition-all shadow-lg shadow-blue-600/20 uppercase tracking-widest">
          {{ qrImage ? 'Configuring...' : 'Enable Now' }}
        </button>
        
        <button v-if="is2faEnabled" @click="promptDisable" :disabled="processing"
          class="px-4 py-2 text-sm font-bold bg-rose-500/10 text-rose-500 border border-rose-500/20 rounded-lg hover:bg-rose-500 hover:text-white transition-all uppercase tracking-tight">
          Disable 2FA
        </button>
      </div>
    </div>

    
    <div v-if="qrImage && !is2faEnabled" class="space-y-6 pt-4 border-t border-slate-800">
      
      <div>
        <div class="flex items-center gap-2 mb-2">
          <span class="flex items-center justify-center w-5 h-5 rounded-full bg-slate-800 text-xs text-slate-300 font-mono">1</span>
          <h3 class="text-sm font-semibold text-slate-200">Scan the QR Code</h3>
        </div>
        <p class="mb-4 text-xs leading-relaxed text-slate-400">
          Open your authenticator app (e.g., Google Authenticator, Authy) and scan the QR code below.
        </p>
        
        <div class="flex flex-col items-center justify-center p-6 mb-3 bg-white rounded-xl shadow-xl max-w-[220px] mx-auto">
          <div v-html="qrImage" class="qr-code-svg-wrapper w-full flex justify-center"></div>
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

      <div class="pt-6 border-t border-slate-800/60">
        <div class="flex items-center gap-2 mb-2">
          <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-[10px] text-white font-bold">2</span>
          <h3 class="text-sm font-bold text-slate-200 uppercase tracking-wider">Verify Token</h3>
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
            class="w-full py-3 text-sm font-bold uppercase text-white bg-blue-600 hover:bg-blue-500 rounded-xl transition-all shadow-md shadow-blue-900/20 disabled:opacity-50">
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
    const { data } = await api.post("/security/2fa/setup");
    
    
    if (data.secret || data.success) {
      secretKey.value = data.secret;
      
      
      const qrUrl = data.qr_code_url || data.qr;
      
      // Generate a rendered image string using a clean image chart API line
      qrImage.value = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(qrUrl)}" alt="2FA QR Code" class="mx-auto" />`;
      
      message.value = { text: "Security credentials built. Please scan code.", type: 'success' };
    } else {
      message.value = { text: data.message || "Failed to initialize 2FA routine.", type: 'error' };
    }
  } catch (e) {
    message.value = { 
      text: e.response?.data?.message || "Session authentication error: Check network state.", 
      type: 'error' 
    };
    console.error(e);
  } finally {
    processing.value = false;
  }
}

async function confirmSetup() {
  processing.value = true;
  message.value = null;
  try {
   
    const { data } = await api.post("/security/2fa/verify", {
      token: confirmationCode.value
    });
    
    is2faEnabled.value = true;
    qrImage.value = null;
    showManualKey.value = false;
    message.value = { text: data.message || "Two-Factor Authentication linked successfully.", type: 'success' };
    setTimeout(() => { message.value = null; }, 5000);
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