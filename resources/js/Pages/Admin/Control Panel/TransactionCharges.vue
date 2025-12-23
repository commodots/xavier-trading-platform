<template>
  <div class="p-6 bg-[#1C1F2E] rounded-xl border border-[#2A314A]">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold">Fees & Charges Configuration</h2>
      <p class="text-sm text-right text-gray-400">Settings used for platform earnings calculation</p>
    </div>

    <table class="w-full text-sm text-left">
      <thead class="bg-[#151a27] text-gray-400 uppercase tracking-wider">
        <tr>
          <th class="px-4 py-3">Transaction Type</th>
          <th class="px-4 py-3">Flat Fee (₦)</th>
          <th class="px-4 py-3">Percentage (%)</th>
          <th class="px-4 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="charge in charges" :key="charge.id" class="border-t border-[#2A314A] hover:bg-[#252a3d] transition-colors">
          <td class="px-4 py-3 font-medium capitalize">{{ charge.transaction_type }}</td>
          <td class="px-4 py-3">₦{{ charge.flat_fee }}</td>
          <td class="px-4 py-3">{{ charge.percentage_fee }}%</td>
          <td class="px-4 py-3 text-right">
            <button @click="editCharge(charge)" class="text-xs font-medium text-blue-400 underline hover:text-blue-300">
              Edit Fees
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A] w-96 shadow-2xl">
        <h3 class="mb-4 text-lg font-bold text-white">Edit {{ selectedType }} Fees</h3>
        
        <div class="space-y-4">
          <div>
            <label class="block mb-1 text-xs text-gray-400">Flat Fee (NGN)</label>
            <input v-model="form.flat_fee" type="number" step="0.01" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-white focus:border-blue-500 outline-none" />
          </div>
          <div>
            <label class="block mb-1 text-xs text-gray-400">Percentage Fee (%)</label>
            <input v-model="form.percentage_fee" type="number" step="0.01" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-white focus:border-blue-500 outline-none" />
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
          <button @click="showModal = false" class="px-4 py-2 text-sm text-gray-400 transition-colors hover:text-white">Cancel</button>
          <button @click="updateCharge" class="px-4 py-2 text-sm text-white transition-colors bg-blue-600 rounded-lg hover:bg-blue-700">Save Changes</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const charges = ref([]);
const showModal = ref(false);
const selectedType = ref('');
const currentId = ref(null);
const form = ref({ flat_fee: 0, percentage_fee: 0 });

const fetchCharges = async () => {
  try {
    const res = await axios.get('/admin/transaction-charges');
    charges.value = res.data;
  } catch (error) {
    console.error("Error fetching charges:", error);
  }
};

const editCharge = (charge) => {
  selectedType.value = charge.transaction_type;
  currentId.value = charge.id;
  form.value = { 
    flat_fee: charge.flat_fee, 
    percentage_fee: charge.percentage_fee 
  };
  showModal.value = true;
};

const updateCharge = async () => {
  try {
    await axios.put(`/admin/transaction-charges/${currentId.value}`, form.value);
    showModal.value = false;
    fetchCharges();
  } catch (error) {
    console.error("Update failed:", error);
  }
};

onMounted(fetchCharges);
</script>