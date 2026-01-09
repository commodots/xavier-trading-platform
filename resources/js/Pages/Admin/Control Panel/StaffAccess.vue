<template>
  <div class="p-4">
    <h3 class="text-lg font-semibold mb-4 text-white">Staff Access</h3>
    
    <div v-if="loading" class="text-sm text-gray-400">Loading...</div>
    
    <div v-else>
      <div class="mb-6">
        <label class="block text-xs text-gray-400 mb-2 uppercase tracking-wider font-semibold">Select Staff Role</label>
        <select 
          v-model="selectedRoleName" 
          class="w-full max-w-md bg-[#0B132B] border border-[#1F2A44] text-white rounded-lg px-4 py-2.5 focus:outline-none focus:border-[#00D4FF] transition-colors cursor-pointer capitalize"
        >
          <option :value="null" disabled>-- Choose a Staff Type --</option>
          <option v-for="role in roles" :key="role.role" :value="role.role">
            {{ role.role }}
          </option>
        </select>
      </div>

      <div v-if="selectedRole" class="mb-4 p-5 border rounded-xl bg-[#071029] border-[#2A314A] transition-all duration-300">
        <div class="flex items-center justify-between mb-6 border-b border-[#1F2A44] pb-4">
          <div>
            <div class="text-[10px] text-[#00D4FF] uppercase font-bold tracking-widest mb-1">Editing Permissions For</div>
            <div class="text-white font-bold text-xl capitalize">{{ selectedRole.role }}</div>
          </div>
          <button 
            v-if="isAdmin" 
            @click="save(selectedRole.role)" 
            :disabled="saving"
            class="text-xs bg-blue-600 hover:bg-blue-500 disabled:opacity-50 px-5 py-2 rounded-lg font-bold transition-colors shadow-lg"
          >
            {{ savingRole === selectedRole.role ? 'Saving...' : 'Save Permissions' }}
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3 text-sm">
          <div 
            v-for="cap in CAPABILITIES" 
            :key="cap.key" 
            class="p-3 rounded-lg hover:bg-white/5 transition-colors group"
          >
            <label class="flex items-center gap-3 text-gray-300 cursor-pointer">
              <input 
                :disabled="!isAdmin || saving" 
                type="checkbox" 
                v-model="selectedRole.permissions[cap.key]" 
                class="w-4 h-4 rounded border-gray-600 bg-[#0B132B] text-blue-600 focus:ring-offset-[#071029]"
              />
              <span class="group-hover:text-white transition-colors">{{ cap.label }}</span>
            </label>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-12 border border-dashed border-[#1F2A44] rounded-xl">
        <p class="text-gray-500 text-sm">Please select a staff role from the dropdown above to manage their access capabilities.</p>
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
import { ref, onMounted, computed } from 'vue';
import api from '@/api';

const roles = ref([]);
const loading = ref(true);
const isAdmin = ref(false);
const selectedRoleName = ref(null);

const saving = ref(false);
const savingRole = ref(null);
const showStatusModal = ref(false);
const statusType = ref('success');
const statusTitle = ref('');
const statusMessage = ref('');

const CAPABILITIES = [
  { key: 'manage_transaction_charges', label: 'Transaction Charges' },
  { key: 'manage_services', label: 'Services' },
  { key: 'manage_kyc_settings', label: 'KYC Review' },
  { key: 'manage_platform_earnings', label: 'View Platform Earnings' },
  { key: 'manage_system_settings', label: 'System Settings' }
];

// Computed property to get the currently selected role object
const selectedRole = computed(() => {
  return roles.value.find(r => r.role === selectedRoleName.value) || null;
});

const fetch = async () => {
  loading.value = true;
  try {
    const [res, me] = await Promise.all([
      api.get('/admin/staff-permissions'),
      api.get('/user/profile/show')
    ]);

    const defaultPerms = {};
    CAPABILITIES.forEach(c => defaultPerms[c.key] = false);
    
    const mapped = res.data.data.map(r => ({ 
      role: r.role, 
      permissions: Object.assign({}, defaultPerms, r.permissions || {}) 
    }));
    
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
  } finally { 
    loading.value = false; 
  }
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