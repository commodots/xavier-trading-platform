<template>
  <div class="p-6 bg-[#1C1F2E] rounded-xl border border-[#2A314A]">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold">Fees & Charges Configuration</h2>
    </div>

    <table class="w-full text-sm text-left">
      <thead class="bg-[#151a27] text-gray-400 uppercase">
        <tr>
          <th class="px-4 py-3">Type</th> 
          <th class="px-4 py-3">Charge Mode</th>
          <th class="px-4 py-3">Value</th> 
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in charges" :key="c.id" class="border-t border-[#2A314A]">
          <td class="px-4 py-3 capitalize font-bold">{{ c.transaction_type }}</td>
          <td class="px-4 py-3 uppercase text-xs">{{ c.charge_type }}</td>
          <td class="px-4 py-3">
            {{ c.charge_type === 'flat' ? '₦' : '' }}{{ c.value }}{{ c.charge_type === 'percentage' ? '%' : '' }}
          </td>
          <td class="px-4 py-3">
            <span :class="c.active ? 'text-green-400' : 'text-red-400'">
              {{ c.active ? 'Active' : 'Disabled' }}
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <button @click="openEdit(c)" class="text-blue-400 underline">Edit</button> 
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A] w-96 shadow-2xl">
        <h3 class="mb-4 text-lg font-bold">Edit {{ form.transaction_type }} Fee</h3>
        
        <div class="space-y-4">
          <div>
            <label class="block mb-1 text-xs text-gray-400">Charge Type</label>
            <select v-model="form.charge_type" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-white">
              <option value="flat">Flat Fee (₦)</option>
              <option value="percentage">Percentage (%)</option> 
            </select>
          </div>
          <div>
            <label class="block mb-1 text-xs text-gray-400">Value</label> 
            <input v-model="form.value" type="number" step="0.01" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-white" />
          </div>
          <div class="flex items-center gap-2">
             <input type="checkbox" v-model="form.active" id="active">
             <label for="active" class="text-xs text-gray-400">Active (Applied to transactions)</label>
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
          <button @click="showModal = false" class="px-4 py-2 text-sm text-gray-400">Cancel</button>
          <button @click="submitUpdate" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg">Save Changes</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/api';

const charges = ref([]);
const showModal = ref(false);
const form = ref({ id: null, transaction_type: '', charge_type: 'flat', value: 0, active: true });

const fetchCharges = async () => {
    const res = await api.get('/admin/transaction-charges');
    charges.value = res.data;
};

const openEdit = (c) => {
    form.value = { ...c };
    showModal.value = true;
};

const submitUpdate = async () => {
    await api.put(`/admin/transaction-charges/${form.value.id}`, form.value);
    showModal.value = false;
    fetchCharges();
};

onMounted(fetchCharges);
</script>