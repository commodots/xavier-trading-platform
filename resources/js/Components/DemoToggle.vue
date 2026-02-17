<template>
  <div class="flex items-center gap-3 bg-[#111827] px-3 py-1.5 rounded-full border border-[#1F2A44]">
    <span :class="!isDemo ? 'text-green-400 font-bold' : 'text-gray-500'" class="text-xs transition-colors">
      LIVE MODE
    </span>

    <button @click="toggleMode"
      class="relative inline-flex h-5 w-10 items-center rounded-full transition-colors focus:outline-none"
      :class="isDemo ? 'bg-yellow-500' : 'bg-green-500'" :disabled="loading">
      <span class="inline-block h-3 w-3 transform rounded-full bg-white transition-transform"
        :class="isDemo ? 'translate-x-6' : 'translate-x-1'" />
    </button>

    <span :class="isDemo ? 'text-yellow-400 font-bold' : 'text-gray-500'" class="text-xs transition-colors">
      DEMO MODE
    </span>

    <svg v-if="loading" class="animate-spin h-4 w-4 text-white ml-2" xmlns="http://www.w3.org/2000/svg" fill="none"
      viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
      </path>
    </svg>
  </div>

  <Teleport to="body">
    <div v-if="isDemo"
      class="fixed top-0 left-0 w-full bg-yellow-500/90 text-yellow-900 text-center py-1 text-xs font-bold z-[100] tracking-widest uppercase">
      You are currently in Demo Mode. All trades and balances are simulated. 
    </div>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue';
import api from '@/api';

const props = defineProps({
  initialMode: {
    type: String,
    default: 'live'
  }
});

const isDemo = ref(props.initialMode === 'demo');
const loading = ref(false);

const toggleMode = async () => {
  const targetMode = isDemo.value ? 'live' : 'demo';
  isDemo.value = targetMode === 'demo'; 
  loading.value = true;

  try {
    const res = await api.post('/switch-mode', { mode: targetMode });

    if (res.data.message) {
      if (isDemo.value) {
        try {
          await api.post('/demo/start', { amount: 100000 }); 
        } catch (e) {}
      }

      let storedUser = JSON.parse(localStorage.getItem("user") || "{}");
      storedUser.trading_mode = targetMode;
      localStorage.setItem("user", JSON.stringify(storedUser));

      // Broadcast a global event 
      window.dispatchEvent(new Event('trading-mode-changed'));
    }
  } catch (error) {
    // Revert the toggle visually if the API fails
    isDemo.value = !isDemo.value; 
    console.error("Failed to switch mode", error);
  } finally {
    loading.value = false;
  }
};
</script>