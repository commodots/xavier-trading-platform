<template>
  <div class="w-full max-w-xl bg-[#1C1F2E]/90 backdrop-blur-md rounded-2xl p-4 sm:p-8 border border-gray-800 relative">
    <button @click="$emit('close')" class="absolute text-gray-400 transition-colors top-4 right-4 hover:text-white">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>

    <div class="mb-8">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-xl font-bold">Identity Verification Progress</h2>
        <span class="text-sm font-semibold text-[#00D4FF]">{{ progressPercentage }}% Complete</span>
      </div>
      <div class="w-full h-2 overflow-hidden bg-gray-700 rounded-full">
        <div class="bg-gradient-to-r from-[#0047AB] to-[#00D4FF] h-full transition-all duration-500"
          :style="{ width: progressPercentage + '%' }"></div>
      </div>
    </div>

    <div v-if="currentStep === 1" class="space-y-4">
      <h3 class="text-lg font-medium">Step 1: Email Verification</h3>
      <p class="text-xs text-gray-400">Confirm your email address to get started!</p>

      <div v-if="internalUser.email_verified_at"
        class="p-4 text-center border rounded-lg bg-green-500/10 border-green-500/30">
        <p class="text-sm font-bold text-green-400">✓ Email Verified: {{ internalUser.email }}</p>
      </div>
      <div v-else class="p-4 text-center border rounded-lg bg-red-500/10 border-red-500/30">
        <p class="text-sm text-red-400">Email verification pending.</p>
      </div>

      <button @click="currentStep = 2" :disabled="!internalUser.email_verified_at"
        class="w-full bg-[#00D4FF] text-[#0B132B] py-2.5 rounded-lg font-bold disabled:opacity-40 transition">
        Continue to BVN
      </button>
    </div>

    <div v-if="currentStep === 2" class="space-y-4">
      <h3 class="text-lg font-medium">Step 2: Bank Verification Number (BVN)</h3>

      <div v-if="verifiedSteps.bvn" class="space-y-4">
        <div class="p-4 text-center border rounded-lg bg-green-500/10 border-green-500/30">
          <p class="text-sm font-bold text-green-400">✓ BVN Verified</p>
        </div>
        <button @click="currentStep = 3"
          class="w-full bg-[#00D4FF] text-[#0B132B] py-2.5 rounded-lg font-bold transition">Continue to NIN</button>
      </div>

      <div v-else class="space-y-4">
        <p class="text-xs text-gray-400">Provide your 11-digit BVN to confirm your identity.</p>
        <div>
          <input v-model="bvn" type="text" maxlength="11" placeholder="Enter 11-digit BVN"
            class="w-full px-4 py-3 bg-[#151a27] border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none tracking-widest text-center" />
        </div>
        <button @click="submitBvn" :disabled="loading || bvn.length !== 11"
          class="w-full bg-[#00D4FF] text-[#0B132B] py-2.5 rounded-lg font-bold disabled:opacity-40 transition flex items-center justify-center gap-2">
          <span v-if="loading"
            class="w-4 h-4 border-2 rounded-full border-[#0B132B]/30 border-t-[#0B132B] animate-spin"></span>
          Verify BVN
        </button>
      </div>
    </div>

    <div v-if="currentStep === 3" class="space-y-4">
      <h3 class="text-lg font-medium">Step 3: National Identification Number (NIN)</h3>

      <div v-if="verifiedSteps.nin" class="space-y-4">
        <div class="p-4 text-center border rounded-lg bg-green-500/10 border-green-500/30">
          <p class="text-sm font-bold text-green-400">✓ NIN Verified</p>
        </div>
        <button @click="currentStep = 4"
          class="w-full bg-[#00D4FF] text-[#0B132B] py-2.5 rounded-lg font-bold transition">
          Continue to Biometric Face Verification
        </button>
      </div>

      <div v-else class="space-y-4">
        <p class="text-xs text-gray-400">Provide your 11-digit NIN.</p>
        <div>
          <input v-model="nin" type="text" maxlength="11" placeholder="Enter 11-digit NIN"
            class="w-full px-4 py-3 bg-[#151a27] border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none tracking-widest text-center" />
        </div>
        <button @click="submitNin" :disabled="loading || nin.length !== 11"
          class="w-full bg-[#00D4FF] text-[#0B132B] py-2.5 rounded-lg font-bold disabled:opacity-40 transition flex items-center justify-center gap-2">
          <span v-if="loading"
            class="w-4 h-4 border-2 rounded-full border-[#0B132B]/30 border-t-[#0B132B] animate-spin"></span>
          Verify NIN
        </button>
      </div>
    </div>

    <div v-if="currentStep === 4" class="py-4 space-y-4 text-center">
      <div
        class="w-16 h-16 bg-[#00D4FF]/10 text-[#00D4FF] rounded-full flex items-center justify-center mx-auto mb-2 border border-[#00D4FF]/20">
        <span class="text-2xl">📸</span>
      </div>
      <h3 class="text-lg font-medium">Step 4: Biometric Face Verification</h3>
      <p class="px-4 text-sm text-gray-300">Uses Dojah to securely capture your biometric details.</p>

      <div v-if="selfieToken || verifiedSteps.selfie" class="max-w-xs p-3 mx-auto border bg-green-500/10 border-green-500/30 rounded-xl">
        <p class="text-xs font-medium text-green-400">✓ Biometric Details Captured</p>
      </div>

      <button type="button" @click="launchDojahLiveness" :disabled="!isSdkReady"
        class="w-full bg-[#00D4FF] text-[#0B132B] py-2.5 rounded-lg font-bold hover:opacity-90 transition">
        {{ (selfieToken || verifiedSteps.selfie) ? 'Relaunch Device Scanner' : 'Launch Camera Scanner' }}
      </button>

      <div class="flex justify-end pt-6 border-t border-gray-800">
        <button @click="handleSelfieContinue" :disabled="loading"
          class="px-6 py-2 bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white rounded-lg font-bold disabled:opacity-40 transition">
          Continue to Review
        </button>
      </div>
    </div>

    <div v-if="currentStep === 5" class="space-y-4">
      
      <div v-if="finalKycStatus === 'pending'" class="py-4 space-y-4 text-center animate-fade-in">
        <div class="w-16 h-16 bg-blue-500/10 text-[#00D4FF] rounded-full flex items-center justify-center mx-auto border border-blue-500/20">
          <span class="text-2xl animate-pulse">⏳</span>
        </div>
        <h2 class="text-xl font-bold">Verification In Review</h2>
        <p class="px-4 text-xs text-gray-400">
          Our systems are matching your biometric details against official records. Most accounts are validated in under 10 minutes.
        </p>
        <button @click="$emit('close')" class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2.5 rounded-lg font-bold transition">
          Return to Dashboard
        </button>
      </div>

      <div v-else-if="finalKycStatus === 'approved' || finalKycStatus === 'verified'" class="py-4 space-y-4 text-center">
        <div class="flex items-center justify-center w-16 h-16 mx-auto text-green-400 border rounded-full bg-green-500/10 border-green-500/20">
          <span class="text-2xl">🏆</span>
        </div>
        <h2 class="text-xl font-bold text-green-400">Account Verified</h2>
        <p class="px-4 text-xs text-gray-400">
          Identity profiles fully approved! Your trading stocks, wallets, and withdrawals are now available.
        </p>
        <button @click="closeAndRefresh" class="w-full bg-green-500 text-[#0B132B] py-2.5 rounded-lg font-bold transition">
          Enter Dashboard
        </button>
      </div>

      <div v-else-if="finalKycStatus === 'rejected'" class="py-4 space-y-4 text-center">
        <div class="flex items-center justify-center w-16 h-16 mx-auto text-red-400 border rounded-full bg-red-500/10 border-red-500/20">
          <span class="text-2xl">❌</span>
        </div>
        <h2 class="text-xl font-bold text-red-400">Verification Failed</h2>
        <p class="px-4 text-xs text-gray-400">
          The biometric details could not be validated against your database records.
        </p>
        <button @click="finalKycStatus = null; currentStep = 2" class="w-full bg-red-500 text-white py-2.5 rounded-lg font-bold transition">
          Restart Verification Flow
        </button>
      </div>

      <div v-else class="space-y-4">
        <h3 class="text-lg font-medium">Step 5: Review & Submission</h3>
        <div class="bg-[#151a27] p-4 rounded-xl border border-gray-800 space-y-2">
          <p class="text-xs tracking-widest text-gray-400 uppercase">Verification Summary</p>
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">Email:</span>
            <span class="text-green-400">Verified ✓</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">BVN Status:</span>
            <span :class="verifiedSteps.bvn ? 'text-green-400' : 'text-white'">
              {{ verifiedSteps.bvn ? 'Verified ✓' : 'Captured' }}
            </span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">NIN Status:</span>
            <span :class="verifiedSteps.nin ? 'text-green-400' : 'text-white'">
              {{ verifiedSteps.nin ? 'Verified ✓' : 'Captured' }}
            </span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-500">Biometrics:</span>
            <span :class="verifiedSteps.selfie ? 'text-green-400' : 'text-white'">
              {{ verifiedSteps.selfie ? 'Captured ✓' : 'Pending' }}
            </span>
          </div>
        </div>
        
        <button @click="finalize" :disabled="loading"
          class="w-full bg-[#00D4FF] text-[#0B132B] py-3 rounded-lg font-bold hover:opacity-90 transition flex items-center justify-center gap-2">
          <span v-if="loading"
            class="w-4 h-4 border-2 rounded-full border-[#0B132B]/30 border-t-[#0B132B] animate-spin"></span>
          Finish
        </button>
        <button @click="currentStep = 4" class="w-full py-2 text-xs text-gray-500 transition hover:text-white">
          Back to Biometrics
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, defineEmits } from 'vue';
import api from '@/api';

const emit = defineEmits(['close', 'verified']);

const internalUser = ref(JSON.parse(localStorage.getItem('user') || '{}'));
const verifiedSteps = ref({ email: false, bvn: false, nin: false, selfie: false });

const currentStep = ref(1);
const loading = ref(false);
const isSdkReady = ref(false);

const bvn = ref('');
const nin = ref('');
const selfieToken = ref(null);

const finalKycStatus = ref(null); // tracking state: null, 'pending', 'approved', 'rejected'

const progressPercentage = computed(() => {
  return Math.min((currentStep.value - 1) * 25, 100);
});

onMounted(async () => {
  try {
    const statusRes = await api.get('/kyc/status');
    if (statusRes.data?.steps) {
      verifiedSteps.value = statusRes.data.steps;
    }

    const profileRes = await api.get('/profile/me');
    const freshUser = profileRes.data?.data || profileRes.data;
    if (freshUser) {
      internalUser.value = freshUser;
      localStorage.setItem("user", JSON.stringify(freshUser));
    }
  } catch (err) {
    console.error("Error setting up sync context layer:", err);
  }

  if (window.Connect) {
    isSdkReady.value = true;
  } else {
    const script = document.createElement("script");
    script.src = "https://widget.dojah.io/widget.js";
    script.type = "text/javascript";
    script.onload = () => { isSdkReady.value = true; };
    document.body.appendChild(script);
  }

  if (!internalUser.value.email_verified_at) {
    currentStep.value = 1;
  } else if (!verifiedSteps.value.bvn) {
    currentStep.value = 2;
  } else if (!verifiedSteps.value.nin) {
    currentStep.value = 3;
  } else if (!verifiedSteps.value.selfie) {
    currentStep.value = 4;
  } else {
    currentStep.value = 5;
  }
});

const submitBvn = async () => {
  loading.value = true;
  try {
    await api.post('/kyc/bvn', { bvn: bvn.value });
    verifiedSteps.value.bvn = true;
    currentStep.value = 3;
  } catch (err) {
    alert(err.response?.data?.message || "BVN validation failed.");
  } finally {
    loading.value = false;
  }
};

const submitNin = async () => {
  loading.value = true;
  try {
    await api.post('/kyc/nin', { nin: nin.value });
    verifiedSteps.value.nin = true;
    currentStep.value = 4;
  } catch (err) {
    alert(err.response?.data?.message || "NIN validation failed.");
  } finally {
    loading.value = false;
  }
};

const launchDojahLiveness = () => {
  if (!window.Connect) return;

  // Break Dojah's state trap by cleaning up prior window references
  if (window.Connect && typeof window.Connect.close === 'function') {
    try {
      window.Connect.close();
    } catch (e) {
      console.log("No active widget instance to clear.");
    }
  }

  // Force delete global state handles if Dojah's script locked up the window thread
  if (window.dojahConnectInstance) {
    window.dojahConnectInstance = null;
  }

  const isDevMode = import.meta.env.DEV;

  const options = {
    app_id: import.meta.env.VITE_DOJAH_APP_ID,
    p_key: import.meta.env.VITE_DOJAH_PUBLIC_KEY,
    type: isDevMode ? "verification" : "custom", 
    debug: isDevMode,
    config: { 
      ambience: "dark",
      pages: isDevMode 
        ? [ { page: "selfie", config: { skip_image: false } } ]
        : [ { page: "liveness-check", config: { skip_image: true } } ]
    },
    onSuccess: (response) => {
      console.log("Dojah execution payload dump:", response);
      const refId = response.referenceId || response.data?.referenceId || response.reference || response.data?.reference;
      
      if (refId) {
        selfieToken.value = refId;
      } else {
        selfieToken.value = "captured_success_fallback";
      }
    },
    onError: (err) => {
      console.error("SDK failure details:", err);
      
      if (isDevMode) {
        console.warn("Dev Environment Bypass: Injecting dummy token.");
        selfieToken.value = "sandbox_token_" + Math.random().toString(36).substr(2, 9);
        verifiedSteps.value.selfie = true;
        currentStep.value = 5;
        return;
      }

      alert("Camera init failed. Please ensure device has camera hardware permissions.");
    }
  };
  
  const connect = new window.Connect(options);
  connect.setup();
  connect.open();
};

const handleSelfieContinue = () => {
  if (verifiedSteps.value.selfie) {
    currentStep.value = 5;
  } else if (selfieToken.value) {
    submitSelfie();
  } else {
    alert("Please complete the Biometric Camera Scanner before continuing. Ensure you have good lighting.");
  }
};

const submitSelfie = async () => {
  loading.value = true;
  try {
    const response = await api.post('/kyc/selfie', {
      profile_image: selfieToken.value
    });
    verifiedSteps.value.selfie = true;
    currentStep.value = 5;
  } catch (err) {
    console.error("Selfie upload error", err.response?.data || err);
    alert(err.response?.data?.message || "Selfie submission validation error.");
  } finally {
    loading.value = false;
  }
};

const finalize = async () => {
  loading.value = true;
  try {
    
    const response = await api.get('/kyc/status');
    const status = response.data?.kyc_status; // 'pending', 'verified', 'approved', 'rejected'

    if (status === 'verified' || status === 'approved') {
      finalKycStatus.value = 'approved';
      emit('verified');
    } else if (status === 'rejected') {
      finalKycStatus.value = 'rejected';
    } else {
      
      finalKycStatus.value = 'pending';
    }
  } catch (err) {
    console.error("Identity finalization error:", err);
    // If the check fails, we fallback to showing the pending review design gracefully
    finalKycStatus.value = 'pending';
  } finally {
    loading.value = false;
  }
};

// Helper utility to refresh dashboard state context when the modal is dismissed
const closeAndRefresh = () => {
  emit('verified');
  emit('close');
  // Optional window location reload if you want to wipe parent component states cleanly
  window.location.reload();
};
</script>