<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
      <button @click="$emit('close')" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>
      
      <h2 class="mb-4 text-xl font-semibold">
        {{ tradeStep === 1 ? 'Select Market' : tradeStep === 2 ? 'Select Ticker' : 'Trade ' + selectedTicker?.symbol }}
      </h2>

      <div v-if="tradeStep === 1" class="space-y-3">
        <button v-for="cat in assetCategories" :key="cat.id" @click="selectCategory(cat)"
          class="w-full text-left p-4 bg-[#0F1724] border border-[#1f3348] rounded-xl hover:border-blue-500 transition group">
          <div class="font-bold text-white group-hover:text-blue-400">{{ cat.name }}</div>
          <div class="text-xs text-gray-500">{{ cat.description }}</div>
        </button>
      </div>

      <div v-if="tradeStep === 2" class="space-y-2 max-h-[400px] overflow-y-auto pr-2">
        <button v-for="ticker in filteredTickers" :key="ticker.symbol" @click="selectTicker(ticker)"
          class="w-full flex justify-between items-center p-4 bg-[#0F1724] border border-[#1f3348] rounded-xl hover:border-blue-500 transition">
          <div>
            <div class="font-bold text-white">{{ ticker.symbol }}</div>
            <div class="text-xs text-gray-500">{{ ticker.name }}</div>
          </div>
          <div class="text-right">
            <div class="font-mono text-sm">
              {{ ticker.currency === 'NGN' ? '₦' : '$' }}{{ ticker.price.toLocaleString() }}
            </div>
          </div>
        </button>
        <button @click="tradeStep = 1" class="w-full py-2 text-xs text-gray-500">← Back</button>
      </div>

      <div v-if="tradeStep === 3" class="space-y-4">
  
  <div class="p-5 bg-blue-500/10 border border-blue-500/30 rounded-lg text-center">
    <div class="text-[10px] text-blue-400 uppercase font-bold tracking-widest mb-1">
      You are {{ tradeAction === 'buy' ? 'Receiving' : 'Selling' }}
    </div>
    
    <div class="text-3xl font-bold text-white mb-1">
      {{ calculatedQuantity.toFixed(6) }} <span class="text-sm font-medium text-gray-400">{{ selectedTicker.symbol }}</span>
    </div>

    <div class="text-sm text-gray-300 font-medium">
      Total {{ tradeAction === 'buy' ? 'Cost' : 'Value' }}: ₦{{ (nairaInput || 0).toLocaleString() }}
    </div>
    
    <div class="text-[10px] text-gray-400 mt-3 border-t border-blue-500/20 pt-2 italic">
      1 {{ selectedTicker.symbol }} = {{ selectedTicker.currency === 'NGN' ? '₦' : '$' }}{{ selectedTicker.price.toLocaleString() }}
    </div>
  </div>

  <div class="flex justify-between items-center px-1">
    <span class="text-xs text-gray-500">Wallet Balance:</span>
    <span class="text-xs font-bold" :class="nairaInput > userBalance && tradeAction === 'buy' ? 'text-red-400' : 'text-gray-300'">
      ₦{{ userBalance.toLocaleString() }}
    </span>
  </div>

  <div class="flex border border-[#2A314A] rounded-lg overflow-hidden p-1 bg-[#0F1724]">
    <button @click="tradeAction = 'buy'" :class="tradeAction === 'buy' ? 'bg-blue-600 text-white' : 'text-gray-400'"
      class="flex-1 py-2 text-xs font-bold rounded-md transition-all">BUY</button>
    <button @click="tradeAction = 'sell'" :class="tradeAction === 'sell' ? 'bg-red-600 text-white' : 'text-gray-400'"
      class="flex-1 py-2 text-xs font-bold rounded-md transition-all">SELL</button>
  </div>

  <div>
    <label class="text-xs text-gray-400">Enter Amount in Naira (₦)</label>
    <div class="relative">
      <input v-model.number="nairaInput" type="number" 
        class="w-full px-4 py-3 mt-1 bg-[#0F1724] border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500 outline-none pr-12"
        placeholder="0.00" />
      <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 text-xs font-bold">NGN</span>
    </div>
  </div>

  <button @click="handleTrade" :disabled="isProcessing || nairaInput <= 0 || (tradeAction === 'buy' && nairaInput > userBalance)"
    class="w-full py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0047AB] to-[#00D4FF] hover:shadow-[0_0_20px_rgba(0,71,171,0.4)] transition-all disabled:opacity-50">
    <span v-if="tradeAction === 'buy' && nairaInput > userBalance">Insufficient Wallet Balance</span>
    <span v-else>{{ isProcessing ? 'Processing Order...' : 'Confirm ' + tradeAction.toUpperCase() }}</span>
  </button>

  <button @click="tradeStep = 2" class="w-full text-xs text-gray-500 hover:text-white transition">← Choose different ticker</button>
</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/api';

const props = defineProps({
  show: Boolean,
  tickers: Object,
  assetCategories: Array
});

const emit = defineEmits(['close', 'trade-success']);

const tradeStep = ref(1);
const tradeAction = ref('buy');
const nairaInput = ref(0);
const isProcessing = ref(false);
const selectedCategory = ref(null);
const selectedTicker = ref(null);
const USD_RATE = 1500; 

const userBalance = ref(0); 

const fetchBalance = async () => {
  try {
    const response = await api.get('/wallet/balances'); 
    if (response.data.success) {
      
      userBalance.value = response.data.data.balance_ngn;
    }
  } catch (error) {
    console.error("Failed to fetch balance", error);
  }
};

const filteredTickers = computed(() => {
  return selectedCategory.value ? props.tickers[selectedCategory.value.id] : [];
});


const calculatedQuantity = computed(() => {
  if (!selectedTicker.value || nairaInput.value <= 0) return 0;
  if (selectedTicker.value.currency === 'NGN') {
    return nairaInput.value / selectedTicker.value.price;
  } else {
    const usdValue = nairaInput.value / USD_RATE;
    return usdValue / selectedTicker.value.price;
  }
});

const selectCategory = (cat) => {
  selectedCategory.value = cat;
  tradeStep.value = 2;
};

const selectTicker = (t) => {
  selectedTicker.value = t;
  tradeStep.value = 3;
};

const handleTrade = async () => {
  isProcessing.value = true;
  try {
    await new Promise(resolve => setTimeout(resolve, 1200));
    alert(`Success: ${tradeAction.value.toUpperCase()} executed for ₦${nairaInput.value.toLocaleString()}`);
    emit('close');
  } finally {
    isProcessing.value = false;
  }
};
onMounted(fetchBalance);
</script>