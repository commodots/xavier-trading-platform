<!-- resources/js/Pages/Admin/UserRoles.vue -->
<template>
  <MainLayout>
    <div>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Assign / Edit Roles</h1>
        <div>
          <button @click="goBack" class="px-3 py-2 bg-[#1C2541] rounded">Back</button>
        </div>
      </div>

      <div class="bg-[#0F1724] p-6 rounded-xl border border-[#1f3348]">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <input v-model="filter" placeholder="Search users..." class="bg-[#081025] border border-gray-700 rounded px-3 py-2" />
          <select v-model="selectedRole" class="bg-[#081025] border border-gray-700 rounded px-3 py-2">
            <option value="">-- Select role to assign --</option>
            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
          </select>
          <div class="flex gap-2">
            <button @click="assignRoleToSelected" class="px-4 py-2 rounded bg-green-600/90">Assign to Selected</button>
            <button @click="refresh" class="px-4 py-2 rounded bg-[#1C2541]">Refresh</button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 border-b border-gray-700">
              <tr>
                <th class="py-2"><input type="checkbox" v-model="selectAll" @change="toggleSelectAll" /></th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th class="text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in filteredUsers" :key="u.id" class="border-b border-gray-800 hover:bg-[#152033]">
                <td class="py-2"><input type="checkbox" v-model="selected" :value="u.id" /></td>
                <td>{{ u.name }}</td>
                <td>{{ u.email }}</td>
                <td>
                  <select v-model="u.role" class="bg-[#081025] border border-gray-700 rounded px-2 py-1 text-sm">
                    <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                  </select>
                </td>
                <td class="text-right">
                  <button @click="saveRole(u)" class="text-[#00D4FF] hover:underline">Save</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const roles = ref(['admin','user','accounts','compliance']);

const users = ref([]);
const filter = ref('');
const selectedRole = ref('');
const selected = ref([]);
const selectAll = ref(false);

const loadUsers = async () => {
  try {
    const res = await axios.get('/api/admin/users');
    users.value = res.data.data ?? res.data ?? [];
  } catch (e) {
    // fallback demo users
    users.value = [
      { id:1, name:'John Admin', email:'admin@xavier.com', role:'admin' },
      { id:2, name:'Alice User', email:'alice@example.com', role:'user' },
      { id:3, name:'Oliver Accounts', email:'accounts@xavier.com', role:'accounts' },
    ];
  }
};

onMounted(loadUsers);

const filteredUsers = computed(() => {
  const q = filter.value.toLowerCase();
  return users.value.filter(u => !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q));
});

const toggleSelectAll = () => {
  if (selectAll.value) selected.value = filteredUsers.value.map(u => u.id);
  else selected.value = [];
};

const saveRole = async (user) => {
  try {
    await axios.post(`/api/admin/users/${user.id}/role`, { role: user.role });
    alert('Role saved');
  } catch (e) {
    alert('Failed to save role');
  }
};

const assignRoleToSelected = async () => {
  if (!selectedRole.value) {
    return alert('Choose a role first');
  }
  if (!selected.value.length) {
    return alert('Select at least one user');
  }

  try {
    await axios.post('/api/admin/users/bulk-role', { user_ids: selected.value, role: selectedRole.value });
    // optimistic update locally
    users.value.forEach(u => { if (selected.value.includes(u.id)) u.role = selectedRole.value; });
    selected.value = [];
    selectAll.value = false;
    alert('Roles updated');
  } catch (e) {
    alert('Failed to assign roles');
  }
};

const refresh = loadUsers;
const goBack = () => router.push('/admin/users');
</script>

<style scoped>
</style>
