<template>
  <div class="flex items-center gap-3 bg-[#111827] px-3 py-1.5 rounded-full border border-[#1F2A44]">
    <span :class="!isDemo ? 'text-green-400 font-bold' : 'text-gray-500'" class="text-xs transition-colors">
      LIVE MODE
    </span>

    <button @click="toggleMode"
      class="relative inline-flex items-center w-10 h-5 transition-colors rounded-full focus:outline-none"
      :class="isDemo ? 'bg-yellow-500' : 'bg-green-500'" :disabled="loading">
      <span class="inline-block w-3 h-3 transition-transform transform bg-white rounded-full"
        :class="isDemo ? 'translate-x-6' : 'translate-x-1'" />
    </button>

    <span :class="isDemo ? 'text-yellow-400 font-bold' : 'text-gray-500'" class="text-xs transition-colors">
      DEMO MODE
    </span>

    <span v-if="loading" class="ml-2 text-xs italic text-gray-400 animate-pulse">
      Loading...
    </span>
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

  window.dispatchEvent(new CustomEvent('trading-mode-switching', { detail: targetMode }));

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