<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
    <div v-if="!showFeedback"
      class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
      <button @click="$emit('close')" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>

      <h2 class="mb-4 text-xl font-semibold">
        <span v-if="isDemo" class="mr-2 font-bold text-yellow-500">DEMO</span>
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
        </button>
        <button @click="tradeStep = 1" class="w-full py-2 text-xs text-gray-500">← Back</button>
      </div>

      <div v-if="tradeStep === 3" class="space-y-4 max-h-[450px] overflow-y-auto pr-2">
        <div class="flex items-center justify-between p-2 px-1 border border-gray-700 rounded-lg bg-gray-800/50">
          <span class="text-[11px] text-gray-400">Your Holdings:</span>
          <span class="text-xs font-bold text-white">
            {{ currentAssetHolding }} {{ selectedTicker?.symbol }}
            <span v-if="selectedHolding" class="text-[10px] text-gray-400">
              (Cleared: {{ selectedHolding.cleared_quantity || 0 }}, Uncleared: {{ selectedHolding.uncleared_quantity || 0 }})
            </span>
          </span>
        </div>

        <div class="flex items-center justify-between p-2 px-1 border border-gray-700 rounded-lg bg-gray-800/50">
          <span class="text-[11px] text-gray-400">Current Price:</span>
          <div class="text-right">
            <span class="text-xs font-bold text-white">
              {{ (selectedTicker?.currency !== 'USD') ? '₦' : '$' }}{{ selectedTicker?.price?.toLocaleString() }}
            </span>
            <div v-if="selectedTicker?.currency === 'USD'" class="text-xs text-gray-400">
              ≈ ₦{{ (selectedTicker?.price * USD_RATE)?.toLocaleString() }} (at ₦{{ USD_RATE }}/$)
            </div>
          </div>
        </div>

        <div class="p-5 text-center border rounded-lg bg-blue-500/10 border-blue-500/30">
          <div class="text-[10px] text-blue-400 uppercase font-bold tracking-widest mb-1">
            You are {{ tradeAction === 'buy' ? 'Receiving' : 'Selling' }}
          </div>
          <div class="mb-1 text-3xl font-bold text-white">
            {{ Number(unitInput).toFixed(selectedCategory?.id === 'CRYPTO' ? 6 : 0) }} <span class="text-sm font-medium text-gray-400">{{ selectedTicker?.symbol
              }}</span>
          </div>
          <div class="text-sm font-medium text-gray-300">
            Total {{ tradeAction === 'buy' ? 'Cost' : 'Value' }}: ₦{{ (unitInput * selectedTicker?.price * (selectedTicker?.currency === 'USD' ? USD_RATE : 1)).toLocaleString() }}
          </div>
        </div>

        <div class="flex items-center justify-between px-1">
          <span class="text-xs text-gray-500">{{ isDemo ? 'Virtual Wallet Balance:' : 'Cleared Wallet Balance:' }}</span>
          <span class="text-xs font-bold"
            :class="nairaInput > userBalance && tradeAction === 'buy' ? 'text-red-400' : 'text-gray-300'">
            ₦{{ userBalance.toLocaleString() }}
          </span>
        </div>

        <div class="flex border border-[#2A314A] rounded-lg overflow-hidden p-1 bg-[#0F1724]">
          <button @click="tradeAction = 'buy'"
            :class="tradeAction === 'buy' ? 'bg-blue-600 text-white' : 'text-gray-400'"
            class="flex-1 py-2 text-xs font-bold transition-all rounded-md">BUY</button>
          <button @click="tradeAction = 'sell'"
            :class="tradeAction === 'sell' ? 'bg-red-600 text-white' : 'text-gray-400'"
            class="flex-1 py-2 text-xs font-bold transition-all rounded-md">SELL</button>
        </div>

        <div>
          <label class="text-xs text-gray-400">Amount in Naira (₦)</label>
          <div class="relative">
            <input v-model="formattedNairaInput" @input="syncFromNaira"
              class="w-full px-4 py-3 mt-1 bg-[#0F1724] border border-gray-600 rounded-lg text-white outline-none pr-12"
              placeholder="0.00" />
            <span class="absolute text-xs font-bold text-gray-500 -translate-y-1/2 right-4 top-1/2">NGN</span>
          </div>
        </div>

        <div>
          <label class="text-xs text-gray-400">Amount in Units ({{ selectedTicker?.symbol }})</label>
          <div class="relative">
            <input v-model.number="unitInput" type="number" @input="syncFromUnits"
              class="w-full px-4 py-3 mt-1 bg-[#0F1724] border border-gray-600 rounded-lg text-white outline-none pr-12"
              placeholder="0" />
            <span class="absolute text-xs font-bold text-gray-500 uppercase -translate-y-1/2 right-4 top-1/2">{{
              selectedTicker?.symbol }}</span>
          </div>
        </div>

        <button @click="handleTrade"
          :disabled="isProcessing || nairaInput <= 0 || (tradeAction === 'buy' && nairaInput > userBalance) || (tradeAction === 'sell' && unitInput > currentAssetHolding)"
          :class="isDemo ? 'from-yellow-600 to-orange-500' : 'from-[#0047AB] to-[#00D4FF]'"
          class="w-full py-4 font-bold text-white transition-all rounded-xl bg-gradient-to-r disabled:opacity-50 disabled:grayscale">
          <span v-if="tradeAction === 'buy' && nairaInput > userBalance">Insufficient Wallet Balance</span>
          <span v-else-if="tradeAction === 'sell' && unitInput > currentAssetHolding">Insufficient Holdings</span>
          <span v-else>{{ isProcessing ? 'Processing Order...' : (isDemo ? 'Confirm DEMO ' : 'Confirm ') + tradeAction.toUpperCase() }}</span>
        </button>

        <button @click="tradeStep = 2" class="w-full text-xs text-gray-500 transition hover:text-white">← Choose
          different ticker</button>
      </div>
    </div>

    <div v-else class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-sm text-center border border-[#2A314A]">
      <div class="mb-4 text-5xl">
        {{ feedbackType === 'success' ? '✅' : '❌' }}
      </div>
      <h3 class="mb-2 text-xl font-bold text-white">
        {{ feedbackType === 'success' ? 'Order Successful' : 'Order Failed' }}
      </h3>
      <p class="mb-6 text-sm text-gray-400">{{ feedbackMessage }}</p>
      <button @click="closeFeedback"
        class="w-full py-3 font-bold text-white transition bg-gray-700 rounded-lg hover:bg-gray-600">
        Close
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import api from '@/api';

const props = defineProps({
  show: Boolean,
  tickers: Object,
  assetCategories: Array,
  initialTicker: Object
});

const emit = defineEmits(['close', 'trade-success']);

const tradeStep = ref(1);
const tradeAction = ref('buy');
const nairaInput = ref(0);
const unitInput = ref(0);
const isProcessing = ref(false);
const selectedCategory = ref(null);
const selectedTicker = ref(null);
const USD_RATE = 1500;
const localTickers = ref({});

const showFeedback = ref(false);
const feedbackMessage = ref('');
const feedbackType = ref('success');

const userBalance = ref(0);
const allHoldings = ref([]);
const isDemo = ref(false);

// function to reset the modal when closed
const resetModalState = () => {
  tradeStep.value = 1;
  tradeAction.value = 'buy';
  nairaInput.value = 0;
  unitInput.value = 0;
  selectedCategory.value = null;
  selectedTicker.value = null;
  showFeedback.value = false;
};

// Handle explicit close via the 'X' button
const handleClose = () => {
  resetModalState();
  emit('close');
};

watch(() => props.show, (newVal) => {
  if (newVal) {
    fetchBalance(); // Re-fetch balance every time modal opens to ensure accuracy
    
    if (props.initialTicker) {
      // Auto-select NGX category and the ticker
      selectedCategory.value = props.assetCategories.find(c => c.id === 'NGX') || props.assetCategories[0];
      selectedTicker.value = props.initialTicker;
      tradeStep.value = 3;
      nairaInput.value = 0;
      unitInput.value = 0;
      
      if (selectedCategory.value.id === 'NGX') {
        fetchNgxPrices(); 
      }
    } else {
      tradeStep.value = 1;
    }
  } else {
   
    resetModalState();
  }
});

const fetchNgxPrices = async () => {
 
  if (!localTickers.value.NGX) return;
  for (let ticker of localTickers.value.NGX) {
    try {
      const res = await api.get(`/dummy/ngx/market/${ticker.symbol}`);
      ticker.price = res.data.bid;
    } catch (e) {
      console.warn(`Failed to fetch price for ${ticker.symbol}`, e);
    }
  }
};

const fetchBalance = async () => {
  try {
    const userStr = localStorage.getItem("user");
    const userObj = userStr ? JSON.parse(userStr) : null;
    isDemo.value = userObj?.trading_mode === 'demo';

    if (isDemo.value) {
      // DEMO BALANCE FETCH
      const res = await api.get('/demo/portfolio');
      
      const demoData = res.data.data || res.data || {};
      userBalance.value = demoData.wallet_balance || demoData.balance || 0;
      
      const demoHoldings = demoData.holdings || [];
      
      if (Array.isArray(demoHoldings)) {
         allHoldings.value = demoHoldings.map(h => ({
          symbol: h.symbol,
          quantity: h.quantity,
          cleared_quantity: h.quantity,
          uncleared_quantity: 0
        }));
      } else {
         allHoldings.value = Object.entries(demoHoldings).map(([sym, details]) => ({
          symbol: sym,
          quantity: details.quantity,
          cleared_quantity: details.quantity,
          uncleared_quantity: 0
        }));
      }
      
    } else {
      // LIVE BALANCE FETCH
      const [portfolioRes, walletRes] = await Promise.all([
        api.get('/portfolio'),
        api.get('/wallet/balances')
      ]);

      if (portfolioRes.data) {
        allHoldings.value = portfolioRes.data.holdings;
      }
      if (walletRes.data.data) {
        userBalance.value = walletRes.data.data.cleared_balance_ngn || 0;
      }
    }
  } catch (error) {
    console.error("Failed to fetch trade data", error);
  }
};

const syncFromNaira = () => {
  if (!selectedTicker.value || nairaInput.value <= 0) {
    unitInput.value = 0;
    return;
  }
  const price = selectedTicker.value.price;
  const isCrypto = selectedCategory.value?.id === 'CRYPTO';
  if (selectedTicker.value.currency === 'NGN' || !selectedTicker.value.currency) {
    unitInput.value = isCrypto ? nairaInput.value / price : Math.floor(nairaInput.value / price);
  } else {
    unitInput.value = isCrypto ? (nairaInput.value / USD_RATE) / price : Math.floor((nairaInput.value / USD_RATE) / price);
  }
};

const syncFromUnits = () => {
  if (!selectedTicker.value || unitInput.value <= 0) {
    nairaInput.value = 0;
    return;
  }
  const price = selectedTicker.value.price;
  const isCrypto = selectedCategory.value?.id === 'CRYPTO';
  if (selectedTicker.value.currency === 'NGN' || !selectedTicker.value.currency) {
    nairaInput.value = isCrypto ? unitInput.value * price : Math.ceil(unitInput.value * price);
  } else {
    nairaInput.value = isCrypto ? (unitInput.value * price) * USD_RATE : Math.ceil((unitInput.value * price) * USD_RATE);
  }
};

const formattedNairaInput = computed({
  get() {
    return nairaInput.value.toLocaleString();
  },
  set(value) {
    nairaInput.value = Number(value.replace(/,/g, ''));
    syncFromNaira();
  }
});

const selectedHolding = computed(() => {
  if (!selectedTicker.value) return null;
  return allHoldings.value.find(h => h.symbol === selectedTicker.value.symbol);
});

const currentAssetHolding = computed(() => {
  if (!selectedTicker.value) return 0;
  const holding = selectedHolding.value;
  const isCrypto = selectedCategory.value?.id === 'CRYPTO';

  const availableQuantity = tradeAction.value === 'sell'
    ? (holding?.cleared_quantity || holding?.quantity || 0)
    : (holding?.quantity || 0);

  return holding ? (isCrypto ? availableQuantity : Math.floor(availableQuantity)) : 0;
});

const filteredTickers = computed(() => {
  return selectedCategory.value ? localTickers.value[selectedCategory.value.id] : [];
});

const selectCategory = (cat) => {
  selectedCategory.value = cat;
  tradeStep.value = 2;
  if (cat.id === 'NGX') {
    fetchNgxPrices();
  }
};

const selectTicker = (t) => {
  selectedTicker.value = t;
  tradeStep.value = 3;
  nairaInput.value = 0;
  unitInput.value = 0;
};

const closeFeedback = () => {
  if (feedbackType.value === 'success') {
    emit('trade-success');
    handleClose(); 
  } else {
    showFeedback.value = false;
  }
};

const handleTrade = async () => {
  isProcessing.value = true;
  try {
    if (isDemo.value) {
      // --- DEMO MODE SUBMISSION ---
      let mType = 'local';
      if (selectedCategory.value.id === 'GLOBAL') mType = 'international';
      if (selectedCategory.value.id === 'CRYPTO') mType = 'crypto';
      if (selectedCategory.value.id === 'FIXED_INCOME') mType = 'fixed_income';

      const payload = {
        symbol: selectedTicker.value.symbol,
        market_type: mType,
        type: tradeAction.value, // 'buy' or 'sell'
        quantity: unitInput.value,
        amount: nairaInput.value,
        price: selectedTicker.value.price
      };

      const res = await api.post('/demo/trade', payload);

      if (res.status === 200 || res.status === 201) {
        feedbackType.value = 'success';
        feedbackMessage.value = `Demo Order ${tradeAction.value.toUpperCase()} for ${selectedTicker.value.symbol} executed successfully.`;
        showFeedback.value = true;
      }
    } else {
      // --- LIVE MODE SUBMISSION ---
      const payload = {
        symbol: selectedTicker.value.symbol,
        side: tradeAction.value, 
        type: 'market',
        quantity: unitInput.value,
        price: selectedTicker.value.price,
        amount: nairaInput.value,
        market: selectedCategory.value.id.toUpperCase(),
        company: selectedTicker.value.symbol,
        market_price: selectedTicker.value.price,
      };

      const res = await api.post('/orders', payload);

      if (res.status === 200 || res.status === 201) {
        // Trigger dummy settlement status check after successful order
        await api.post('/dummy/cscs/settle', {
          trade_id: 'T' + res.data.data.id ,
          amount: nairaInput.value,
          cycle: 'T' + Date.now(),
        });
        feedbackType.value = 'success';
        feedbackMessage.value = `Order ${tradeAction.value.toUpperCase()} for ${selectedTicker.value.symbol} accepted by NGX Gateway.`;
        showFeedback.value = true;
      }
    }
  } catch (e) {
    feedbackType.value = 'error';
    feedbackMessage.value = e.response?.data?.error || e.response?.data?.message || "Gateway Timeout: Service unreachable.";
    showFeedback.value = true;
  } finally {
    isProcessing.value = false;
  }
};

onMounted(() => {
  localTickers.value = JSON.parse(JSON.stringify(props.tickers));
  
  // Listen for the instant toggle switch
  window.addEventListener('trading-mode-changed', fetchBalance);
});

onUnmounted(() => {
  // Clean up the listener so it doesn't cause memory leaks when the modal is destroyed
  window.removeEventListener('trading-mode-changed', fetchBalance);
});
</script>