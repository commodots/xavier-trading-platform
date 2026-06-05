<template>
  <div class="bg-[#0f172a] p-6 rounded-lg border border-gray-700 space-y-4 text-white">
    <!-- Header with Status Badges -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-white">KYC Information</h2>
      <div class="flex gap-2">
        <span v-if="kyc?.daily_limit" class="text-[10px] bg-gray-800 text-gray-300 px-2 py-1 rounded border border-gray-600 font-bold">
          LIMIT: {{ formatCurrency(kyc.daily_limit) }}
        </span>
        <span v-if="kyc?.status === 'verified' || kyc?.status === 'approved'" 
          class="text-[10px] uppercase px-2 py-1 rounded border border-green-400 bg-green-500/20 text-green-400 font-bold">
          Tier: {{ kyc.tier ?? 1 }} ({{ kyc.level }})
        </span>
      </div>
    </div>

    <!-- Success Notification -->
    <transition name="slideDown">
      <div v-if="showSuccessNotification" class="p-4 border border-green-700 rounded-lg bg-green-900/20 animate-slideDown">
        <p class="text-sm text-green-300 font-semibold">✓ Verification Complete!</p>
        <p class="text-xs text-green-400 mt-1">Your identity documents have been submitted successfully.</p>
      </div>
    </transition>

    <!-- Form or Display Section -->
    <div v-if="!kyc || kyc.status === 'rejected' || showUpgradeForm" class="animate-fadeIn">
      <!-- Upgrade Form Header -->
      <div v-if="showUpgradeForm" class="flex items-center justify-between p-3 mb-4 border border-blue-800 rounded bg-blue-900/20">
        <p class="text-xs text-blue-300">Upgrade to higher tier by providing additional verification documents.</p>
        <button @click="showUpgradeForm = false" class="text-xs text-gray-400 hover:text-white">Cancel</button>
      </div>

      <!-- Rejection Notice -->
      <div v-if="kyc?.status === 'rejected'" class="p-4 mb-4 text-red-400 border border-red-700 rounded bg-red-900/20">
        <p class="text-xs font-bold tracking-widest uppercase">✗ Verification Rejected</p>
        <p class="mt-1 text-sm text-gray-300">{{ kyc.rejection_reason || 'The documents provided were invalid.' }}</p>
        <p class="mt-2 text-xs text-gray-400">Please review the requirements and resubmit your documents.</p>
      </div>

      <!-- Initial KYC Prompt -->
      <div v-if="!kyc" class="p-4 mb-4 border rounded-lg bg-blue-500/10 border-blue-500/30">
        <p class="text-xs leading-tight text-blue-300">
          Complete your identity verification to unlock all platform features including deposits, withdrawals, and trading with higher limits.
        </p>
      </div>

      <div class="p-6 text-center border border-dashed border-gray-700 rounded-xl bg-gray-900/40 space-y-4">
        <p class="text-sm text-gray-400">
          Click below to start your secure identity verification scanner via Dojah
        </p>
        <button 
          @click="launchDojahVerification" 
          :disabled="!isSdkReady || loading"
          class="w-full sm:w-auto px-6 py-3 bg-[#00D4FF] text-[#0B132B] font-bold rounded-lg disabled:opacity-40 hover:opacity-90 transition flex items-center justify-center gap-2 mx-auto"
        >
          <span v-if="loading" class="w-4 h-4 border-2 rounded-full border-[#0B132B]/30 border-t-[#0B132B] animate-spin"></span>
          {{ isSdkReady ? 'Launch Identity Verification' : 'Loading...' }}
        </button>
      </div>
    </div>

    <!-- Verification Pending State -->
    <div v-else-if="kyc.status === 'pending'" class="p-6 text-center border border-yellow-700/50 rounded-xl bg-yellow-900/10">
      <div class="mb-3 text-3xl animate-pulse">⏳</div>
      <h3 class="text-xs font-bold tracking-widest text-yellow-400 uppercase">Identity Verification Pending</h3>
      <p class="mt-2 text-sm text-gray-400">
        We are validating your identity details against official records. This usually takes a few minutes.
      </p>
      <div class="mt-4 flex items-center justify-center gap-2 text-[11px] text-gray-500">
        <span class="inline-block w-2 h-2 rounded-full bg-yellow-500 animate-ping"></span>
        Awaiting verification webhook confirmation
      </div>
    </div>

    <div v-else-if="kyc.status === 'approved' || kyc.status === 'verified'" class="space-y-4">
      <div class="p-6 text-center border border-green-700/50 rounded-xl bg-green-900/10">
        <div class="mb-2 text-3xl">✅</div>
        <h3 class="text-xs font-bold tracking-widest text-green-400 uppercase">Identity Verified</h3>
        <p class="mt-1 text-sm text-gray-400">Tier: <span class="text-white capitalize">{{ kyc.level }}</span></p>
        <p v-if="kyc.verified_at" class="mt-1 text-xs text-gray-500">
          Verified on {{ new Date(kyc.verified_at).toLocaleDateString() }}
        </p>
        
        <button v-if="kyc.tier < 2" 
          @click="showUpgradeForm = true"
          class="px-4 py-2 mt-4 text-xs font-bold text-white transition bg-blue-600 rounded hover:bg-blue-700">
          Upgrade Tier
        </button>
      </div>
      
      <!-- KYC Details Grid -->
      <div class="grid gap-3 pt-6 border-t border-gray-800">
        <p class="flex justify-between text-sm">
          <span class="text-gray-500">Daily Withdrawal Limit:</span>
          <span class="font-bold text-green-400">{{ formatCurrency(kyc.daily_limit) }}</span>
        </p>
        <p class="flex justify-between text-sm">
          <span class="text-gray-500">BVN Status:</span>
          <span class="text-green-400 font-medium">Linked and Validated ✓</span>
        </p>
        <p class="flex justify-between text-sm">
          <span class="text-gray-500">NIN Status:</span>
          <span class="text-green-400 font-medium">Linked and Validated ✓</span>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, defineProps, defineEmits } from "vue";
import api from "@/api";

const props = defineProps({
  kyc: { type: Object, default: () => null }
});

const emit = defineEmits(['refresh']);
const showUpgradeForm = ref(false);
const showSuccessNotification = ref(false);
const isSdkReady = ref(false);
const loading = ref(false);

onMounted(() => {
  // Mount the interactive Dojah widget script cleanly
  if (window.Connect) {
    isSdkReady.value = true;
  } else {
    const script = document.createElement("script");
    script.src = "https://widget.dojah.io/widget.js";
    script.type = "text/javascript";
    script.onload = () => { isSdkReady.value = true; };
    document.body.appendChild(script);
  }
});

const launchDojahVerification = () => {
  if (!window.Connect) return;

  const options = {
    app_id: import.meta.env.VITE_DOJAH_APP_ID,
    p_key: import.meta.env.VITE_DOJAH_PUBLIC_KEY,
    type: "custom", 
    debug: import.meta.env.DEV,
    config: {
      pages: [
        { page: "bvn", label: "Verify BVN" },
        { page: "nin", label: "Verify NIN" },
        { page: "liveness", label: "Liveness Check" }
      ]
    },
    onSuccess: async function (response) {
      loading.value = true;
      const refId = response.referenceId || response.data?.referenceId || response.reference;
      
      try {
        
        await api.post('/kyc/dojah-submit', { reference_id: refId });
        
        showSuccessNotification.value = true;
        showUpgradeForm.value = false;
        emit('refresh');
        
        setTimeout(() => {
          showSuccessNotification.value = false;
        }, 5000);
      } catch (err) {
        console.error("Dojah token transmission exception:", err);
        alert(err.response?.data?.message || "Failed to process verification callback link.");
      } finally {
        loading.value = false;
      }
    },
    onError: function (err) {
      console.error("Dojah Verification SDK error context:", err);
      alert("Verification session aborted or failed to configure hardware sync windows.");
    }
  };

  const connect = new window.Connect(options);
  connect.setup();
  connect.open();
};

const formatCurrency = (value) => {
  if (value === null || value === undefined) return '0.00';
  const currency = props.kyc?.currency || 'NGN';
  const locale = currency === 'USD' ? 'en-US' : 'en-NG';
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency,
    }).format(value);
  } catch (e) {
    return `${currency} ${Number(value).toFixed(2)}`;
  }
};
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn { animation: fadeIn 0.3s ease-in-out; }
.animate-slideDown { animation: slideDown 0.3s ease-in-out; }
</style>