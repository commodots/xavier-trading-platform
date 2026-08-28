<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">Departments</h1>
      <button type="button" @click="openCreate" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Add Department</button>
    </div>

    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
      <SkeletonLoader type="table" :count="6" class="opacity-40" />
    </div>

    <div v-else class="bg-[#0F1724] border border-[#1f3348] rounded-xl overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-[#1f3348]">
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Name</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Code</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Description</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Status</th>
            <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-400 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="department in departments" :key="department.id" class="border-b border-[#1f3348] hover:bg-[#16213A]">
            <td class="px-4 py-3 text-white">{{ department.name }}</td>
            <td class="px-4 py-3 text-gray-300 font-mono">{{ department.code }}</td>
            <td class="px-4 py-3 text-gray-400 max-w-xs truncate">{{ department.description || 'N/A' }}</td>
            <td class="px-4 py-3">
              <span :class="department.is_active ? 'bg-green-900/50 text-green-400' : 'bg-red-900/50 text-red-400'" class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ department.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2 flex-wrap">
                <button type="button" @click="openEdit(department)" class="text-xs text-blue-400 hover:text-blue-300">Edit</button>
                <button type="button" @click="toggleDepartment(department)" class="text-xs hover:opacity-80" :class="department.is_active ? 'text-red-400' : 'text-green-400'">
                  {{ department.is_active ? 'Deactivate' : 'Activate' }}
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="departments.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-gray-500">No departments found</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create / Edit modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 px-4">
      <form @submit.prevent="submitModal" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-bold text-white">{{ editingId ? 'Edit Department' : 'New Department' }}</h2>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Name *</label>
          <input v-model="modalForm.name" type="text" required maxlength="255" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Code *</label>
          <input v-model="modalForm.code" type="text" required maxlength="50" placeholder="e.g. FIN" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none uppercase" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-400 mb-2">Description</label>
          <textarea v-model="modalForm.description" rows="3" maxlength="1000" class="w-full bg-[#16213A] border border-gray-700 rounded-lg px-3 py-2 text-white text-sm outline-none"></textarea>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-300">
          <input v-model="modalForm.is_active" type="checkbox" class="rounded bg-[#16213A] border-gray-700" />
          Active
        </label>

        <p v-if="errorMessage" class="text-sm text-red-400">{{ errorMessage }}</p>

        <div class="flex gap-3 pt-2">
          <button type="submit" :disabled="saving" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm disabled:opacity-50">{{ editingId ? 'Update' : 'Create' }}</button>
          <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const departments = ref([]);
const loading = ref(true);
const showModal = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const editingId = ref(null);

const blankForm = { name: '', code: '', description: '', is_active: true };
const modalForm = reactive({ ...blankForm });

const fetchDepartments = async () => {
  loading.value = true;
  try {
    const response = await api.get('/admin/departments/manage');
    departments.value = response.data ?? [];
  } catch (error) {
    console.error('Failed to load departments', error);
  } finally {
    loading.value = false;
  }
};

const openCreate = () => {
  Object.assign(modalForm, blankForm, { is_active: true });
  editingId.value = null;
  errorMessage.value = '';
  showModal.value = true;
};

const openEdit = (department) => {
  Object.assign(modalForm, {
    name: department.name,
    code: department.code,
    description: department.description || '',
    is_active: !!department.is_active,
  });
  editingId.value = department.id;
  errorMessage.value = '';
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
};

const submitModal = async () => {
  saving.value = true;
  errorMessage.value = '';
  try {
    if (editingId.value) {
      await api.put(`/admin/departments/manage/${editingId.value}`, modalForm);
    } else {
      await api.post('/admin/departments/manage', modalForm);
    }
    showModal.value = false;
    fetchDepartments();
  } catch (error) {
    console.error(error);
    const errors = error.response?.data?.errors;
    errorMessage.value = errors ? Object.values(errors).flat()[0] : (error.response?.data?.message || 'Failed to save department.');
  } finally {
    saving.value = false;
  }
};

const toggleDepartment = async (department) => {
  try {
    await api.post(`/admin/departments/manage/${department.id}/toggle`);
    fetchDepartments();
  } catch (error) {
    console.error(error);
    alert(error.response?.data?.message || 'Failed to update department.');
  }
};

onMounted(fetchDepartments);
</script>
