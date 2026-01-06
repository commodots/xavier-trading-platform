<template>
  <div class="bg-[#0f172a] p-6 rounded-lg border border-gray-700 space-y-4">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-semibold text-white">KYC Information</h2>
      <div class="flex gap-2">
        <span v-if="kyc?.daily_limit" class="text-[10px] bg-gray-800 text-gray-300 px-2 py-1 rounded border border-gray-600 font-bold">
          LIMIT: {{ formatCurrency(kyc.daily_limit) }}
        </span>
        <span v-if="kyc?.level" 
          :class="kyc.level === 'full' ? 'bg-green-500/20 text-green-400' : 'bg-blue-500/20 text-blue-400'"
          class="text-[10px] uppercase px-2 py-1 rounded border border-current font-bold">
          Tier: {{ kyc.level }}
        </span>
      </div>
    </div>

    <div v-if="!kyc?.status || showUpgradeForm" class="animate-fadeIn">
      <div v-if="showUpgradeForm" class="flex items-center justify-between p-3 mb-4 border border-blue-800 rounded bg-blue-900/20">
        <p class="text-xs text-blue-300">Upgrade to Full Verification by providing your NIN and ID Document.</p>
        <button @click="showUpgradeForm = false" class="text-xs text-gray-400 hover:text-white">Cancel</button>
      </div>
      <KycForm @success="handleRefresh" :initial="kyc" />
    </div>

    <div v-else-if="kyc.status === 'pending'" class="p-6 text-center border border-yellow-700/50 rounded-xl bg-yellow-900/10">
      <div class="mb-2 text-3xl">&#x231B;</div>
      <h3 class="font-bold text-yellow-400 uppercase text-xs tracking-widest">Verification Pending</h3>
      <p class="mt-1 text-sm text-gray-400">Our team is currently reviewing your documents.</p>
    </div>

    <div v-else-if="kyc.status === 'approved' || kyc.status === 'verified'" class="space-y-4">
      <div class="p-6 text-center border border-green-700/50 rounded-xl bg-green-900/10">
        <div class="mb-2 text-3xl">&#9989;</div>
        <h3 class="font-bold text-green-400 uppercase text-xs tracking-widest">Identity Verified</h3>
        <p class="mt-1 text-sm text-gray-400">Level: <span class="capitalize text-white">{{ kyc.level }}</span></p>
        
        <button v-if="kyc.level === 'basic'" 
          @click="showUpgradeForm = true"
          class="px-4 py-2 mt-4 text-xs font-bold transition bg-blue-600 rounded hover:bg-blue-700 text-white">
          Upgrade to Full Tier
        </button>
      </div>
      
      <div class="grid gap-3 pt-6 border-t border-gray-800">
        <p class="flex justify-between text-sm">
          <span class="text-gray-500">Daily Withdrawal Limit:</span>
          <span class="text-green-400 font-bold">{{ formatCurrency(kyc.daily_limit) }}</span>
        </p>
        <p class="flex justify-between text-sm">
          <span class="text-gray-500">ID Type:</span>
          <span class="text-white">{{ formatIdType(kyc.id_type) }}</span>
        </p>
        <p class="flex justify-between text-sm">
          <span class="text-gray-500">BVN:</span>
          <span class="text-white">****{{ String(kyc.bvn || '').slice(-4) }}</span>
        </p>
        <p v-if="kyc.nin" class="flex justify-between text-sm">
          <span class="text-gray-500">NIN:</span>
          <span class="text-white">****{{ String(kyc.nin).slice(-4) }}</span>
        </p>
      </div>
    </div>

    <div v-else-if="kyc.status === 'rejected'" class="space-y-4">
      <div class="p-4 text-red-400 border border-red-700 rounded bg-red-900/20">
        <p class="font-bold uppercase text-xs tracking-widest">&#10060; Verification Rejected</p>
        <p class="mt-1 text-sm text-gray-300">{{ kyc.rejection_reason || 'The documents provided were invalid.' }}</p>
      </div>
      <div class="pt-4">
        <h4 class="text-xs font-bold text-gray-500 mb-3 uppercase">Resubmit your documents</h4>
        <KycForm @success="handleRefresh" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue";
import KycForm from "@/Components/KycForm.vue"; 

const props = defineProps({
  kyc: { type: Object, default: () => null }
});

const emit = defineEmits(['refresh']);
const showUpgradeForm = ref(false);

const handleRefresh = () => {
  showUpgradeForm.value = false;
  emit('refresh'); 
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

const formatIdType = (type) => {
  if (!type) return 'Not Provided';
  const types = {
    'intl_passport': 'International Passport',
    'national_id': 'National ID Card',
    'drivers_license': 'Driver\'s License',
    'voters_card': 'Voter\'s Card',
    'nin': 'NIN Slip'
  };
  return types[type] || type.replace('_', ' ').toUpperCase();
};
</script>