<template>
    <div class="space-y-6">
      <h1 class="text-xl font-bold text-white">KYC & Withdrawal Limits</h1>

      <div class="bg-[#111827] rounded-xl p-6 border border-[#1F2A44] max-w-3xl">
        <h2 class="text-gray-400 mb-4 text-sm uppercase tracking-wider">Global Tier Rules</h2>

        <div v-for="s in settings" :key="s.tier" class="mb-6 p-4 bg-[#1C2541] rounded-lg border border-[#1F2A44]">
          <div class="flex justify-between items-center mb-3">
            <div>
              <div class="font-bold text-[#00D4FF]">Tier {{ s.tier }}</div>
              <input v-model="s.tier_name" class="mt-2 bg-[#0B132B] text-white px-2 py-1 rounded border border-[#1F2A44]" />
            </div>
            <div class="text-xs text-gray-400">ID: {{ s.id ?? '—' }}</div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-gray-400 mb-1">Daily Withdrawal Limit (NGN)</label>
              <input 
                type="number" 
                v-model.number="s.daily_limit" 
                class="w-full bg-[#0B132B] border border-[#1F2A44] rounded px-3 py-2 text-white focus:outline-none focus:border-[#00D4FF]"
              />
            </div>

            <div>
              <label class="block text-xs text-gray-400 mb-1">Unlimited</label>
              <div class="flex items-center space-x-3">
                <input type="checkbox" :checked="isUnlimited(s)" @change="toggleUnlimited(s)" class="w-4 h-4" />
                <span class="text-sm text-gray-300">Treat as no limit</span>
              </div>
            </div>
          </div>
          <div class="mt-4">
            <label class="block text-xs text-gray-400 mb-2">Required Documents</label>
            <div class="grid grid-cols-3 gap-2">
              <label v-for="doc in docTypes" :key="doc" class="inline-flex items-center space-x-2 text-sm text-gray-300">
                <input type="checkbox" :value="doc" :checked="(s.required_documents||[]).includes(doc)" @change="toggleDoc(s, doc)" class="w-4 h-4" />
                <span>{{ docLabel(doc) }}</span>
              </label>
            </div>
          </div>
        </div>

        <button 
          :disabled="saving"
          @click="saveSettings" 
          class="w-full py-3 bg-[#00D4FF] text-black font-bold rounded-lg hover:bg-[#00b8e6] transition disabled:opacity-60"
        >
          {{ saving ? 'Saving...' : 'Save Global Limits' }}
        </button>
      </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/api";

const settings = ref([]);
const saving = ref(false);
const docTypes = ['bvn','nin','tin','intl_passport','national_id','drivers_license','proof_of_address'];

onMounted(async () => {
  let res = await api.get("/admin/kyc-settings");
  settings.value = res.data.data.map(s => ({
    ...s, 
    required_documents: s.required_documents || []
  }));
});

const saveSettings = async () => {
  try {
    saving.value = true;
    await api.post("/admin/kyc-settings", { settings: settings.value });
    alert("Limits updated successfully!");
  } catch (e) {
    alert(e.response?.data?.message || 'Save failed');
  } finally {
    saving.value = false;
  }
};

function isUnlimited(s) {
  return Number(s.daily_limit) >= 999999999;
}

function toggleUnlimited(s) {
  if (isUnlimited(s)) {
    s.daily_limit = 0;
  } else {
    s.daily_limit = 999999999;
  }
}

function docLabel(key) {
  const map = {
    bvn: 'BVN',
    nin: 'NIN',
    tin: 'TIN',
    intl_passport: 'International Passport',
    national_id: 'National ID',
    drivers_license: 'Driver\'s License',
    proof_of_address: 'Proof of Address'
  };
  return map[key] || key;
}

function toggleDoc(s, doc) {
  s.required_documents = s.required_documents || [];
  const idx = s.required_documents.indexOf(doc);
  if (idx === -1) s.required_documents.push(doc);
  else s.required_documents.splice(idx, 1);
}
</script>