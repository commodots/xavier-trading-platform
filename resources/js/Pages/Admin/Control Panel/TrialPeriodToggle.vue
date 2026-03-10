<template>
  <div class="p-8 bg-[#1C1F2E] rounded-2xl shadow-xl border border-[#2A314A] text-white">
    <div class="flex items-center gap-3 mb-6">
      <div class="p-2 rounded-lg bg-blue-600/20">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </div>
      <h2 class="text-xl font-bold">Trial Period Configuration</h2>
    </div>

    <div v-if="showSuccessModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="w-full max-w-sm p-8 text-center duration-300 transform bg-white shadow-2xl rounded-2xl animate-in zoom-in">
        <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-green-600 bg-green-100 rounded-full">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h3 class="mb-2 text-2xl font-bold text-gray-900">Updated!</h3>
        <p class="mb-6 text-gray-600">The trial duration has been successfully set to <strong>{{ form.trial_days }} days</strong>.</p>
        <button @click="showSuccessModal = false" class="w-full py-3 font-bold text-white transition bg-gray-900 rounded-xl hover:bg-gray-800">
          Continue
        </button>
      </div>
    </div>

    <form @submit.prevent="saveSettings" class="space-y-6">
      <div class="p-6 bg-[#252A3D] rounded-xl border border-[#343B54]">
        <label class="block mb-3 text-sm font-bold tracking-wider text-gray-300 uppercase">
          Trial Period Duration
        </label>
        <div class="flex items-center gap-4">
          <div class="relative">
            <input 
              v-model="form.trial_days" 
              type="number" 
              min="0"
              class="w-32 px-4 py-3 bg-[#1C1F2E] border border-[#343B54] rounded-xl text-white font-bold text-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
              placeholder="3"
            />
          </div>
          <span class="text-lg font-medium text-gray-400">days after registration.</span>
        </div>
        <p class="mt-4 text-sm leading-relaxed text-gray-400">
          This setting defines how long new users can access VIP content before a subscription is required. 
          <span class="font-semibold text-blue-400">Changes apply to new trials only.</span>
        </p>
      </div>
      
      <div class="flex justify-end">
        <button 
          :disabled="loading"
          type="submit" 
          class="flex items-center gap-2 px-8 py-3 font-bold text-white transition-all bg-blue-600 rounded-xl hover:bg-blue-700 hover:shadow-lg hover:shadow-blue-600/20 disabled:opacity-50 active:scale-95"
        >
          <span v-if="loading" class="w-4 h-4 border-2 rounded-full border-white/30 border-t-white animate-spin"></span>
          {{ loading ? 'Updating System...' : 'Save Settings' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/api';

const loading = ref(false);
const showSuccessModal = ref(false);
const form = ref({
    trial_days: 3,
    trading_fee: 0,
    withdrawal_fee: 0,
    base_currency: 'NGN'
});

const fetchSettings = async () => {
    try {
        const response = await api.get('/admin/settings');
        if (response.data.success) {
            form.value = response.data.data;
        }
    } catch (error) {
        console.error("Failed to load settings", error);
    }
};

const saveSettings = async () => {
    loading.value = true;
    try {
        const response = await api.post('/admin/settings/update', form.value);
        if (response.data.success) {
            
            form.value = { ...form.value, ...response.data.data };
            showSuccessModal.value = true;
        }
    } catch (error) {
        console.error("Save failed", error);
        alert('Could not save settings. Please check connection.');
    } finally {
        loading.value = false;
    }
};

onMounted(fetchSettings);
</script>