<template>
  <div class="p-4">
    <h3 class="text-lg font-semibold mb-4">Staff Access</h3>
    <div v-if="loading" class="text-sm text-gray-400">Loading...</div>
    <div v-else>
      <div v-for="role in roles" :key="role.role" class="mb-4 p-3 border rounded bg-[#071029]">
        <div class="flex items-center justify-between">
          <div class="text-white font-bold">{{ role.role }}</div>
          <button v-if="isAdmin" @click="save(role.role)" class="text-xs bg-blue-600 px-3 py-1 rounded">Save</button>
        </div>

        <div class="grid grid-cols-2 gap-2 mt-3 text-sm">
          <div v-for="cap in CAPABILITIES" :key="cap.key">
            <label class="flex items-center gap-2">
              <input :disabled="!isAdmin" type="checkbox" v-model="role.permissions[cap.key]" />
              {{ cap.label }}
            </label>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/lib/axios';

const roles = ref([]);
const loading = ref(true);
const isAdmin = ref(false);

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

    // ensure permissions object contains all capability keys (avoid duplicates and missing keys)
    const defaultPerms = {};
    CAPABILITIES.forEach(c => defaultPerms[c.key] = false);
    // map incoming roles into normalized objects
    const mapped = res.data.data.map(r => ({ role: r.role, permissions: Object.assign({}, defaultPerms, r.permissions || {}) }));
    // deduplicate by role name (keep first occurrence)
    const seen = new Set();
    roles.value = mapped.filter(m => {
      if (seen.has(m.role)) return false;
      seen.add(m.role);
      return true;
    });
    // debug: log counts
    // console.debug('staff-permissions fetched', { received: res.data.data.length, unique: roles.value.length });
    const current = me.data.data;
    isAdmin.value = current?.roles?.includes('admin') || current?.role === 'admin';
  } catch (e) {
    console.error('Failed to fetch staff permissions', e);
  } finally { loading.value = false; }
};

const save = async (role) => {
  const r = roles.value.find(x => x.role === role);
  try {
    await api.post('/admin/staff-permissions', { role: role, permissions: r.permissions });
    alert('Saved');
  } catch (e) {
    alert(e.response?.data?.message || 'Save failed');
  }
};

onMounted(fetch);
</script>
