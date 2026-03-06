<template>
  <div class="flex items-center gap-3 bg-[#111827] px-3 py-1.5 rounded-full border border-[#1F2A44]">
    <span :class="!isDemo ? 'text-green-400 font-bold' : 'text-gray-500'" class="text-xs transition-colors">
      LIVE MODE
    </span>

    <button @click="toggleMode"
      class="relative inline-flex items-center w-10 h-5 transition-colors rounded-full focus:outline-none"
      :class="isDemo ? 'bg-yellow-500' : 'bg-green-500'" :disabled="loading">
      <span class="inline-block w-3 h-3 transition-transform transform bg-white rounded-full shadow-md"
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
import { ref, onMounted} from 'vue';
import api from '@/api';

const props = defineProps({
  initialMode: {
    type: String,
    default: 'live'
  }
});

const isDemo = ref(props.initialMode === 'demo');
const loading = ref(false);

onMounted(() => {
  const savedUser = JSON.parse(localStorage.getItem('user') || '{}');
  if (savedUser.trading_mode) {
    isDemo.value = savedUser.trading_mode === 'demo';
  }
});

const toggleMode = async () => {
  if (loading.value) return;
  
  const targetMode = isDemo.value ? 'live' : 'demo';
  loading.value = true;

  try {
    const res = await api.post('/switch-mode', { mode: targetMode });

    if (res.data.success) {
      // Update local visual state
      isDemo.value = targetMode === 'demo';

      // Update LocalStorage with the fresh user data from the backend
      // This ensures balances (Wallet vs DemoWallet) are synced
      localStorage.setItem("user", JSON.stringify(res.data.user));
      localStorage.setItem("trading_mode", targetMode);

      // Broadcast the change to the rest of the app
      window.dispatchEvent(new CustomEvent('trading-mode-changed', { 
        detail: { mode: targetMode, user: res.data.user } 
      }));
    }
  } catch (error) {
    console.error("Failed to switch mode", error);
  } finally {
    loading.value = false;
  }
};
</script>