<template>
  <div class="p-6 bg-[#1C1F2E] rounded-xl border border-[#2A314A]">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold">Fees & Charges Configuration</h2>
    </div>

    <div v-if="permissionError"
      class="mb-4 p-3 bg-red-500/10 border border-red-500/50 rounded text-red-500 text-sm flex justify-between items-center">
      <span>{{ permissionError }}</span>
      <button @click="permissionError = null" class="font-bold">&times;</button>
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

        <div class="space-y-4 text-white">
          <div>
            <label class="block mb-1 text-xs text-gray-400">Charge Type</label>
            <select v-model="form.charge_type" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded">
              <option value="flat">Flat Fee (₦)</option>
              <option value="percentage">Percentage (%)</option>
            </select>
          </div>
          <div>
            <label class="block mb-1 text-xs text-gray-400">Value</label>
            <input v-model="form.value" type="number" step="0.01"
              class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded" />
          </div>
          <div class="flex items-center gap-2">
            <input type="checkbox" v-model="form.active" id="active">
            <label for="active" class="text-xs text-gray-400">Active (Applied to transactions)</label>
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
          <button @click="showModal = false" class="px-4 py-2 text-sm text-gray-400">Cancel</button>
          <button @click="submitUpdate" :disabled="loading"
            class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg disabled:opacity-50">
            {{ loading ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showDeniedModal"
      class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 backdrop-blur-md">
      <div class="bg-[#1C1F2E] p-8 rounded-xl border border-orange-500/30 w-full max-w-sm text-center shadow-2xl">
        <div class="w-16 h-16 bg-orange-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
          <span class="text-3xl">⚠️</span>
        </div>
        <h3 class="text-xl font-bold text-white mb-2">Access Restricted</h3>
        <p class="text-gray-400 text-sm mb-6">
          {{ deniedMessage }}
        </p>
        <button @click="$router.push('/admin')"
          class="w-full py-3 bg-orange-600 hover:bg-orange-500 text-white rounded-lg transition-colors font-semibold">
          Understood
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/api';
import { useRouter } from 'vue-router';
const router = useRouter();

const charges = ref([]);
const showModal = ref(false);
const showDeniedModal = ref(false);
const loading = ref(false);
const permissionError = ref(null);
const deniedMessage = ref(""); 

const form = ref({ id: null, transaction_type: '', charge_type: 'flat', value: 0, active: true });

const fetchCharges = async () => {
  try {
    const res = await api.get('/admin/transaction-charges');
    charges.value = res.data;
  } catch (e) {
    if (e.response && e.response.status === 403) {
      deniedMessage.value = e.response.data.message || "Access Restricted";
      showDeniedModal.value = true;
    } else {
      console.error("Fetch failed", e);
    }
  }
};

const openEdit = (c) => {
  form.value = { ...c };
  showModal.value = true;
};

const submitUpdate = async () => {
  loading.value = true;
  permissionError.value = null;

  try {
    await api.put(`/admin/transaction-charges/${form.value.id}`, form.value);
    showModal.value = false;
    fetchCharges();
  } catch (e) {
    if (e.response && e.response.status === 403) {
      // If the message contains 'user', we can give a hint to switch accounts
      const msg = e.response.data.message;

      if (msg.toLowerCase().includes("'user'")) {
        deniedMessage.value = "Your current session is identified as a 'User'. Please ensure you have correctly switched to your Staff Profile to edit charges.";
      } else {
        deniedMessage.value = msg;
      }

      showModal.value = false;
      showDeniedModal.value = true;
    } else {
      permissionError.value = e.response?.data?.message || "An error occurred while saving.";
    }
  } finally {
    loading.value = false;
  }
};

onMounted(fetchCharges);
</script>