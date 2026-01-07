<template>
  <div class="p-4">
    <h3 class="text-lg font-semibold mb-4">Staff Access</h3>
    <div v-if="loading" class="text-sm text-gray-400">Loading...</div>
    <div v-else>
      <div v-for="role in roles" :key="role.role" class="mb-4 p-3 border rounded bg-[#071029] border-[#2A314A]">
        <div class="flex items-center justify-between">
          <div class="text-white font-bold">{{ role.role }}</div>
          <button 
            v-if="isAdmin" 
            @click="save(role.role)" 
            :disabled="saving"
            class="text-xs bg-blue-600 hover:bg-blue-500 disabled:opacity-50 px-3 py-1 rounded transition-colors"
          >
            {{ savingRole === role.role ? 'Saving...' : 'Save' }}
          </button>
        </div>

        <div class="grid grid-cols-2 gap-2 mt-3 text-sm">
          <div v-for="cap in CAPABILITIES" :key="cap.key">
            <label class="flex items-center gap-2 text-gray-300 cursor-pointer">
              <input :disabled="!isAdmin || saving" type="checkbox" v-model="role.permissions[cap.key]" />
              {{ cap.label }}
            </label>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showStatusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A] w-80 shadow-2xl text-center">
        <div :class="statusType === 'success' ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500'" 
             class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
          {{ statusType === 'success' ? '✓' : '✕' }}
        </div>
        <h3 class="text-white font-bold mb-2">{{ statusTitle }}</h3>
        <p class="text-gray-400 text-sm mb-6">{{ statusMessage }}</p>
        <button 
          @click="showStatusModal = false" 
          class="w-full py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors text-sm font-semibold"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/api';

const roles = ref([]);
const loading = ref(true);
const isAdmin = ref(false);

const saving = ref(false);
const savingRole = ref(null);
const showStatusModal = ref(false);
const statusType = ref('success');
const statusTitle = ref('');
const statusMessage = ref('');

const CAPABILITIES = [
  { key: 'manage_transaction_charges', label: 'Manage Transaction Charges' },
  { key: 'manage_services', label: 'Manage Services' },
  { key: 'manage_kyc_settings', label: 'Manage KYC Settings' },
  { key: 'manage_platform_earnings', label: 'View Platform Earnings' },
  { key: 'manage_system_settings', label: 'Manage System Settings' }
];

const fetch = async () => {
  loading.value = true;
  try {
    const [res, me] = await Promise.all([
      api.get('/admin/staff-permissions'),
      api.get('/user/profile/show')
    ]);

    const defaultPerms = {};
    CAPABILITIES.forEach(c => defaultPerms[c.key] = false);
    const mapped = res.data.data.map(r => ({ role: r.role, permissions: Object.assign({}, defaultPerms, r.permissions || {}) }));
    const seen = new Set();
    roles.value = mapped.filter(m => {
      if (seen.has(m.role)) return false;
      seen.add(m.role);
      return true;
    });
    const current = me.data.data;
    isAdmin.value = current?.roles?.includes('admin') || current?.role === 'admin';
  } catch (e) {
    console.error('Failed to fetch staff permissions', e);
  } finally { loading.value = false; }
};

const save = async (roleName) => {
  const r = roles.value.find(x => x.role === roleName);
  saving.value = true;
  savingRole.value = roleName;

  try {
    await api.post('/admin/staff-permissions', { role: roleName, permissions: r.permissions });
    
    statusType.value = 'success';
    statusTitle.value = 'Update Successful';
    statusMessage.value = `Permissions for ${roleName} have been updated.`;
    showStatusModal.value = true;
  } catch (e) {
    statusType.value = 'error';
    statusTitle.value = 'Update Failed';
    statusMessage.value = e.response?.data?.message || 'Could not update permissions.';
    showStatusModal.value = true;
  } finally {
    saving.value = false;
    savingRole.value = null;
  }
};

onMounted(fetch);
</script>