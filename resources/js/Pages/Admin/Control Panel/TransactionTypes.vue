<template>
  <div class="p-6 bg-[#1C1F2E] rounded-xl border border-[#2A314A]">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold">Transaction Types</h2>
      <button @click="showModal = true" class="px-4 py-2 text-sm transition-colors bg-blue-600 rounded-lg hover:bg-blue-700">
        Add New Type
      </button>
    </div>

    <table class="w-full text-sm text-left">
      <thead class="bg-[#151a27] text-gray-400 uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3">Name</th>
          <th class="px-4 py-3">Category</th>
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="type in transactionTypes" :key="type.id" class="border-t border-[#2A314A] hover:bg-[#252a3d] transition-colors">
          <td class="px-4 py-3 capitalize">{{ type.name }}</td>
          <td class="px-4 py-3 text-gray-400 capitalize">{{ type.category }}</td>
          <td class="px-4 py-3">
            <span :class="type.active ? 'text-green-400 bg-green-400/10' : 'text-red-400 bg-red-400/10'" class="px-2 py-0.5 rounded text-xs font-medium">
              {{ type.active ? 'Active' : 'Inactive' }}
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="flex justify-end">
              <label :for="'toggle-' + type.id" class="relative inline-flex items-center cursor-pointer">
                <input 
                  type="checkbox" 
                  :id="'toggle-' + type.id"
                  :checked="type.active" 
                  @change="toggleActive(type)"
                  class="sr-only peer"
                >
                <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A] w-96 shadow-2xl">
        <h3 class="mb-4 text-lg font-bold">Add Transaction Type</h3>
        <div class="space-y-4">
          <div>
            <label class="block mb-1 text-xs text-gray-400">Type Name</label>
            <input v-model="form.name" type="text" placeholder="e.g., buy_stock" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-white focus:border-blue-500 outline-none" />
          </div>
          <div>
            <label class="block mb-1 text-xs text-gray-400">Category</label>
            <select v-model="form.category" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-white focus:border-blue-500 outline-none">
              <option value="funding">Funding</option>
              <option value="trading">Trading</option>
            </select>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="showModal = false" class="px-4 py-2 text-sm text-gray-400 transition-colors hover:text-white">Cancel</button>
          <button @click="saveType" class="px-4 py-2 text-sm text-white transition-colors bg-blue-600 rounded-lg hover:bg-blue-700">Save Type</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/api';

const transactionTypes = ref([]);
const showModal = ref(false);
const form = ref({ name: '', category: 'funding', active: true });


const fetchTypes = async () => {
  try {
    const res = await api.get('/admin/transaction-types');
    transactionTypes.value = res.data;
  } catch (error) {
    console.error("Error fetching types:", error);
  }
};

const saveType = async () => {
  try {
    await api.post('/admin/transaction-types', form.value);
    showModal.value = false;
    form.value = { name: '', category: 'funding', active: true };
    fetchTypes();
  } catch (error) {
    alert(error.response?.data?.message || "Check fields and try again");
  }
};


const toggleActive = async (type) => {
  try {
    await api.put(`admin/transaction-types/${type.id}`, { 
      active: !type.active 
    });
    fetchTypes();
  } catch (error) {
    console.error("Update failed:", error);
  }
};

onMounted(fetchTypes);
</script>