<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center max-w-3xl">
      <h1 class="text-xl font-bold text-white">KYC & Withdrawal Limits</h1>
      <button 
        @click="addNewTier" 
        class="bg-green-600 hover:bg-green-500 text-white text-xs font-bold px-4 py-2 rounded-lg transition"
      >
        + Add New Tier
      </button>
    </div>

    <div class="bg-[#111827] rounded-xl p-6 border border-[#1F2A44] max-w-3xl">
      <h2 class="text-gray-400 mb-4 text-sm uppercase tracking-wider">Global Tier Rules</h2>

      <div v-for="s in settings" :key="s.tier" class="mb-6 p-4 bg-[#1C2541] rounded-lg border border-[#1F2A44]">
        <div class="flex justify-between items-center mb-3">
          <div>
            <div class="font-bold text-[#00D4FF]">Tier {{ s.tier }}</div>
            <input v-model="s.tier_name" class="mt-2 bg-[#0B132B] text-white px-2 py-1 rounded border border-[#1F2A44]" />
          </div>
          <div class="text-right">
            <div class="text-xs text-gray-400 mb-2">ID: {{ s.id ?? 'New' }}</div>
            <div class="flex gap-2">
              <button 
                @click="confirmDelete(s)"
                class="text-xs bg-red-600/20 text-red-500 hover:bg-red-600 hover:text-white border border-red-500/50 px-3 py-1.5 rounded transition"
              >
                Delete
              </button>
              
              <button 
                :disabled="saving && savingTier === s.tier"
                @click="saveTier(s)" 
                class="text-xs bg-[#00D4FF] text-black font-bold px-4 py-1.5 rounded hover:bg-[#00b8e6] transition disabled:opacity-60"
              >
                {{ saving && savingTier === s.tier ? 'Saving...' : 'Save Tier' }}
              </button>
            </div>
          </div>
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
            <label v-for="doc in docTypes" :key="doc" class="inline-flex items-center space-x-2 text-sm text-gray-300 cursor-pointer">
              <input type="checkbox" :value="doc" :checked="(s.required_documents||[]).includes(doc)" @change="toggleDoc(s, doc)" class="w-4 h-4" />
              <span>{{ docLabel(doc) }}</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <div v-if="modal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A] w-full max-w-sm shadow-2xl text-center">
        <div :class="modal.iconClass" class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
          {{ modal.icon }}
        </div>
        <h3 class="text-white font-bold mb-2 text-lg">{{ modal.title }}</h3>
        <p class="text-gray-400 text-sm mb-6">{{ modal.message }}</p>
        
        <div class="flex gap-3">
          <button 
            v-if="modal.type === 'confirm'"
            @click="modal.show = false"
            class="flex-1 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg transition text-sm font-semibold"
          >
            Cancel
          </button>
          <button 
            @click="modalAction" 
            :class="modal.btnClass"
            class="flex-1 py-2 text-white rounded-lg transition text-sm font-semibold"
          >
            {{ modal.btnText }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from "vue";
import api from "@/api";

const settings = ref([]);
const saving = ref(false);
const savingTier = ref(null);
const docTypes = ['bvn','nin','tin','intl_passport','national_id','drivers_license','proof_of_address'];

// Modal State
const modal = reactive({
  show: false,
  type: 'status', 
  title: '',
  message: '',
  icon: '',
  iconClass: '',
  btnText: 'Close',
  btnClass: 'bg-blue-600 hover:bg-blue-500',
  tierToDelete: null
});

onMounted(() => fetchSettings());

const fetchSettings = async () => {
  try {
    let res = await api.get("/admin/kyc-settings");
    settings.value = res.data.data.map(s => ({
      ...s, 
      required_documents: s.required_documents || []
    }));
  } catch (e) {
    console.error("Failed to load settings");
  }
};

const showModal = (title, message, type = 'success') => {
  modal.type = 'status';
  modal.title = title;
  modal.message = message;
  modal.btnText = 'Close';
  modal.show = true;
  
  if (type === 'success') {
    modal.icon = '✓';
    modal.iconClass = 'bg-green-500/10 text-green-500';
    modal.btnClass = 'bg-gray-700 hover:bg-gray-600';
  } else {
    modal.icon = '✕';
    modal.iconClass = 'bg-red-500/10 text-red-500';
    modal.btnClass = 'bg-red-600 hover:bg-red-500';
  }
};

const confirmDelete = (s) => {
  if (!s.id) {
    settings.value = settings.value.filter(item => item.tier !== s.tier);
    return;
  }
  modal.type = 'confirm';
  modal.tierToDelete = s;
  modal.title = 'Confirm Delete';
  modal.message = `Are you sure you want to delete Tier ${s.tier}? This cannot be undone.`;
  modal.icon = '!';
  modal.iconClass = 'bg-orange-500/10 text-orange-500';
  modal.btnText = 'Delete Tier';
  modal.btnClass = 'bg-red-600 hover:bg-red-500';
  modal.show = true;
};

const modalAction = () => {
  if (modal.type === 'confirm' && modal.tierToDelete) {
    deleteTier(modal.tierToDelete);
  } else {
    modal.show = false;
  }
};

const addNewTier = () => {
  const maxTier = settings.value.length > 0 
    ? Math.max(...settings.value.map(o => o.tier)) 
    : 0;
    
  settings.value.push({
    tier: maxTier + 1,
    tier_name: `Tier ${maxTier + 1}`,
    daily_limit: 0,
    required_documents: [],
    id: null
  });
};

const saveTier = async (tierData) => {
  try {
    saving.value = true;
    savingTier.value = tierData.tier;
    await api.post("/admin/kyc-settings", { settings: [tierData] });
    showModal('Saved!', `Tier ${tierData.tier} updated successfully.`);
    fetchSettings();
  } catch (e) {
    showModal('Save Failed', e.response?.data?.message || 'Could not save tier.', 'error');
  } finally {
    saving.value = false;
    savingTier.value = null;
  }
};

const deleteTier = async (s) => {
  modal.show = false;
  try {
    await api.delete(`/admin/kyc-settings/${s.tier}`);
    showModal('Deleted', 'Tier deleted successfully.');
    fetchSettings();
  } catch (e) {
    showModal('Delete Failed', e.response?.data?.message || "Could not delete tier.", 'error');
  }
};

function isUnlimited(s) {
  return Number(s.daily_limit) >= 999999999;
}

function toggleUnlimited(s) {
  s.daily_limit = isUnlimited(s) ? 0 : 999999999;
}

function docLabel(key) {
  const map = {
    bvn: 'BVN', nin: 'NIN', tin: 'TIN', 
    intl_passport: 'Passport', national_id: 'National ID', 
    drivers_license: 'License', proof_of_address: 'Address'
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