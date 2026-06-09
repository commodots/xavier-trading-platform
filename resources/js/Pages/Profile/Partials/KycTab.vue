<template>
  <div class="bg-[#1C1F2E] p-6 rounded-2xl border border-gray-800 space-y-6 text-white shadow-xl">
    
    <div class="flex items-center justify-between pb-2 border-b border-gray-800">
      <div>
        <h2 class="text-lg font-bold text-white tracking-wide">Identity Verification</h2>
        <p class="text-xs text-gray-400 mt-0.5">Manage your account verification status and trading tiers</p>
      </div>
      <span 
        class="text-[10px] uppercase px-2.5 py-1 rounded-full font-extrabold tracking-wider border"
        :class="tierClass"
      >
        {{ kyc?.tier ? `Tier ${kyc.tier}` : 'Unverified' }}
      </span>
    </div>

    <div class="p-4 rounded-xl bg-gradient-to-r from-blue-500/10 to-[#00D4FF]/5 border border-blue-500/20 flex justify-between items-center">
      <div class="space-y-1">
        <span class="text-xs text-blue-300 font-medium uppercase tracking-wider block">Daily Withdrawal Limit</span>
        <span class="text-2xl font-black text-white tracking-tight">
          {{ formatCurrency(kyc?.daily_limit || 0) }}
        </span>
      </div>
      <div class="text-right">
        <span class="text-[11px] px-2 py-1 bg-[#151a27] rounded-md border border-gray-800 text-gray-400 font-mono uppercase">
          Verification Level: {{ kyc?.level || 'None' }}
        </span>
      </div>
    </div>

    <div class="space-y-3 bg-[#151a27] p-4 rounded-xl border border-gray-800/60">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold text-gray-300">Verification Steps</span>
        <span class="text-xs font-bold text-[#00D4FF]">
          {{ Math.round((kyc?.tier || 0) / 3 * 100) }}% Complete
        </span>
      </div>

      <div class="w-full h-2 bg-gray-800 rounded-full overflow-hidden flex gap-1">
        <div 
          v-for="step in 3" 
          :key="step" 
          class="h-full flex-1 transition-all duration-500 rounded-full"
          :class="[
            (kyc?.tier || 0) >= step ? 'bg-gradient-to-r from-[#0047AB] to-[#00D4FF]' : 'bg-gray-700'
          ]"
        ></div>
      </div>

      <div class="grid grid-cols-3 pt-1 text-center">
        <div class="space-y-1">
          <p class="text-[10px] font-bold uppercase tracking-wider" :class="(kyc?.tier || 0) >= 1 ? 'text-[#00D4FF]' : 'text-gray-500'">1. Email</p>
          <p class="text-[10px] font-mono text-gray-400" v-if="(kyc?.tier || 0) >= 1">Verified ✓</p>
        </div>
        <div class="space-y-1 border-x border-gray-800">
          <p class="text-[10px] font-bold uppercase tracking-wider" :class="(kyc?.tier || 0) >= 2 ? 'text-[#00D4FF]' : 'text-gray-500'">2. Identity</p>
          <div v-if="(kyc?.tier || 0) >= 2" class="text-[10px] text-gray-400 space-y-0.5 font-mono">
            <p v-if="kyc?.bvn_last4">BVN: ****{{ kyc.bvn_last4 }}</p>
            <p v-if="kyc?.nin_last4">NIN: ****{{ kyc.nin_last4 }}</p>
          </div>
        </div>
        <div class="space-y-1">
          <p class="text-[10px] font-bold uppercase tracking-wider" :class="(kyc?.tier || 0) >= 3 ? 'text-[#00D4FF]' : 'text-gray-500'">3. Biometrics</p>
          <p class="text-[10px] font-mono text-gray-400" v-if="(kyc?.tier || 0) >= 3">Facial Match ✓</p>
        </div>
      </div>
    </div>

    <div>
      <div v-if="kyc?.status === 'rejected'" class="p-4 border border-red-900/40 rounded-xl bg-red-500/5 space-y-2 animate-fadeIn">
        <div class="flex items-center gap-2 text-red-400">
          <span class="text-sm">❌</span>
          <h4 class="text-xs font-bold tracking-wider uppercase">Verification Rejected</h4>
        </div>
        <p class="text-xs text-gray-400 leading-relaxed pl-6">
          Reason: <span class="text-gray-200">{{ kyc.rejection_reason || 'The documents provided were invalid or unreadable.' }}</span>
        </p>
        <div class="pt-2 pl-6">
          <button @click="$emit('open-verification')" class="px-4 py-2 text-xs bg-red-500 text-white font-bold rounded-lg hover:bg-red-600 transition">
            Restart Verification Process
          </button>
        </div>
      </div>

      <div v-else-if="kyc?.tier >= 3" class="p-4 text-center border border-green-900/30 rounded-xl bg-green-500/5 flex items-center justify-center gap-3 animate-fadeIn">
        <div class="text-left">
          <h4 class="text-xs font-bold text-green-400 uppercase tracking-wider">Account Fully Verified</h4>
          <p class="text-[11px] text-gray-400">All features active. Verified on {{ kyc.verified_at ? new Date(kyc.verified_at).toLocaleDateString() : 'N/A' }}</p>
        </div>
      </div>

      <div v-else class="pt-2 animate-fadeIn">
        <button 
          @click="$emit('open-verification')" 
          class="w-full py-3 bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white text-sm font-bold rounded-xl shadow-lg hover:opacity-95 transition tracking-wide"
        >
          {{ !kyc?.tier ? 'Begin Identity Verification' : 'Complete Verification to Tier 3' }}
        </button>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, defineProps, defineEmits } from "vue";

const props = defineProps({
  kyc: { type: Object, default: () => null }
});

defineEmits(['open-verification']);

const tierClass = computed(() => {
  if (!props.kyc?.tier) return 'border-gray-700 bg-gray-800 text-gray-400';
  if (props.kyc.tier >= 3) return 'border-green-500/30 bg-green-500/10 text-green-400';
  if (props.kyc.tier === 2) return 'border-purple-500/30 bg-purple-500/10 text-purple-400';
  return 'border-blue-500/30 bg-blue-500/10 text-blue-400';
});

const formatCurrency = (value) => {
  if (value === null || value === undefined) return '₦0.00';
  const currency = props.kyc?.currency || 'NGN';
  const locale = currency === 'USD' ? 'en-US' : 'en-NG';
  try {
    return new Intl.NumberFormat(locale, {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 0
    }).format(value);
  } catch (e) {
    return `${currency} ${Number(value).toLocaleString()}`;
  }
};
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn { animation: fadeIn 0.25s ease-out forwards; }
</style>