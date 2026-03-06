<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
    <div v-if="!showFeedback"
      class="bg-[#1C1F2E] p-6 rounded-2xl shadow-xl w-full max-w-md relative border transition-colors duration-300"
      :class="isDemo ? 'border-yellow-600 shadow-yellow-900/20' : 'border-[#2A314A]'">
      
      <button @click="handleClose" class="absolute text-gray-400 transition-colors top-4 right-4 hover:text-white">✖</button>

      <h2 class="flex items-center mb-4 text-xl font-semibold">
        <span v-if="isDemo" class="mr-2 px-2 py-0.5 bg-yellow-500 text-black text-[10px] font-black rounded uppercase">DEMO</span>
        {{ tradeStep === 1 ? 'Select Market' : tradeStep === 2 ? 'Select Ticker' : 'Trade ' + selectedTicker?.symbol }}
      </h2>

      <div v-if="tradeStep === 1" class="grid grid-cols-1 gap-2">
        <button v-for="cat in assetCategories" :key="cat.id" @click="selectCategory(cat)"
          class="w-full p-3 text-left transition-all border rounded-xl group"
          :class="isDemo ? 'border-yellow-600/30 bg-yellow-500/5 hover:bg-yellow-500/10' : 'border-[#1f3348] hover:border-blue-500 bg-[#0F1724]'">
          <div class="text-sm font-bold" :class="isDemo ? 'text-yellow-500' : 'text-white group-hover:text-blue-400'">{{ cat.name }}</div>
          <div class="text-[10px] text-gray-500">{{ cat.description }}</div>
        </button>
      </div>

      <div v-if="tradeStep === 2" class="space-y-2">
        <div class="max-h-[300px] overflow-y-auto pr-1 space-y-1.5 custom-scrollbar">
          <button v-for="ticker in filteredTickers" :key="ticker.symbol" @click="selectTicker(ticker)"
            class="flex items-center justify-between w-full p-3 transition border rounded-xl"
            :class="isDemo ? 'border-yellow-600/30 bg-yellow-500/5 hover:bg-yellow-500/10' : 'border-[#1f3348] hover:border-blue-500 bg-[#0F1724]'">
            <div>
              <div class="text-sm font-bold text-white">{{ ticker.symbol }}</div>
              <div class="text-[10px] text-gray-500">{{ ticker.name }}</div>
            </div>
          </button>
        </div>
        <button @click="tradeStep = 1" class="w-full py-2 text-[10px] text-gray-500 hover:text-white transition">← Back to Markets</button>
      </div>

      <div v-if="tradeStep === 3" class="space-y-3">
        <div class="grid grid-cols-2 gap-2">
          <div class="p-2 border border-gray-700 rounded-lg bg-gray-800/50">
            <div class="text-[9px] text-gray-500 uppercase font-bold">Holdings</div>
            <div class="text-xs font-bold text-white truncate">
              {{ currentAssetHolding }} {{ selectedTicker?.symbol }}
            </div>
          </div>
          <div class="p-2 border border-gray-700 rounded-lg bg-gray-800/50">
            <div class="text-[9px] text-gray-500 uppercase font-bold">Price</div>
            <div class="text-xs font-bold text-white">
              {{ (selectedTicker?.currency !== 'USD') ? '₦' : '$' }}{{ selectedTicker?.price?.toLocaleString() }}
            </div>
          </div>
        </div>

        <div class="py-3 text-center transition-colors border rounded-lg"
          :class="isDemo ? 'border-yellow-600/40 bg-yellow-500/10' : 'bg-blue-500/5 border-blue-500/20'">
          <div class="text-[9px] uppercase font-black tracking-widest mb-1"
            :class="isDemo ? 'text-yellow-500' : 'text-blue-400'">
            {{ tradeAction === 'buy' ? 'Receiving' : 'Selling' }}
          </div>
          <div class="text-xl font-bold text-white">
            {{ Number(unitInput).toFixed(selectedCategory?.id === 'CRYPTO' ? 6 : 2) }}
            <span class="text-xs font-normal text-gray-400">{{ selectedTicker?.symbol }}</span>
          </div>
          <div class="text-[10px] text-gray-400 mt-0.5">
            Est. Value: ₦{{ (unitInput * selectedTicker?.price * (selectedTicker?.currency === 'USD' ? USD_RATE : 1)).toLocaleString() }}
          </div>
        </div>

        <div class="flex items-center justify-between px-1">
          <span class="text-[10px] text-gray-500 uppercase font-bold">{{ isDemo ? 'Demo Wallet' : 'Wallet Balance' }}</span>
          <span class="text-xs font-bold" :class="nairaInput > userBalance && tradeAction === 'buy' ? 'text-red-400' : 'text-gray-300'">
            ₦{{ userBalance.toLocaleString() }}
          </span>
        </div>

        <div class="flex border border-[#2A314A] rounded-lg overflow-hidden p-1 bg-[#0F1724]">
          <button @click="tradeAction = 'buy'"
            :class="tradeAction === 'buy' ? 'bg-blue-600 text-white shadow-inner' : 'text-gray-500 hover:text-gray-300'"
            class="flex-1 py-1.5 text-[10px] font-black transition-all rounded-md uppercase">BUY</button>
          <button @click="tradeAction = 'sell'"
            :class="tradeAction === 'sell' ? 'bg-red-600 text-white shadow-inner' : 'text-gray-500 hover:text-gray-300'"
            class="flex-1 py-1.5 text-[10px] font-black transition-all rounded-md uppercase">SELL</button>
        </div>

        <div class="p-3 space-y-3 border border-gray-800 rounded-xl bg-black/20">
          <div class="flex items-center justify-between gap-2">
            <span class="text-[10px] text-gray-400 uppercase font-bold shrink-0">Order By:</span>
            <select v-model="inputMode"
              class="text-xs font-bold bg-transparent border-none outline-none cursor-pointer"
              :class="isDemo ? 'text-yellow-500' : 'text-blue-400'">
              <option value="amount">Amount (₦)</option>
              <option value="quantity">Quantity (Units)</option>
            </select>
          </div>

          <div class="relative">
            <input v-if="inputMode === 'amount'" v-model="formattedNairaInput" @input="syncFromNaira"
              class="w-full px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm outline-none focus:border-blue-500 transition-all"
              placeholder="0.00" />
            <input v-else v-model.number="unitInput" type="number" @input="syncFromUnits"
              class="w-full px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm outline-none focus:border-blue-500 transition-all"
              placeholder="0" />
            <span class="absolute text-[10px] font-bold text-gray-500 -translate-y-1/2 right-3 top-1/2 uppercase">
              {{ inputMode === 'amount' ? 'NGN' : selectedTicker?.symbol }}
            </span>
          </div>
        </div>

        <div class="pt-1">
          <button @click="handleTrade"
            :disabled="isProcessing || nairaInput <= 0 || (tradeAction === 'buy' && nairaInput > userBalance) || (tradeAction === 'sell' && unitInput > currentAssetHolding)"
            :class="isDemo ? 'from-yellow-600 to-orange-500 shadow-yellow-900/20' : 'from-[#0047AB] to-[#00D4FF] shadow-blue-900/20'"
            class="w-full py-3.5 font-black text-white transition-all rounded-xl bg-gradient-to-r text-sm shadow-lg disabled:opacity-40 disabled:grayscale uppercase tracking-wide">
            <span v-if="tradeAction === 'buy' && nairaInput > userBalance">Insufficient Balance</span>
            <span v-else-if="tradeAction === 'sell' && unitInput > currentAssetHolding">Insufficient Units</span>
            <span v-else>{{ isProcessing ? 'Processing...' : (isDemo ? 'Confirm Demo ' : 'Confirm ') + tradeAction }}</span>
          </button>
          <button @click="tradeStep = 2" class="w-full mt-2 text-[10px] text-gray-500 hover:text-white transition">← Change Asset</button>
        </div>
      </div>
    </div>

    <div v-else class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-sm text-center border"
      :class="isDemo ? 'border-yellow-600' : 'border-[#2A314A]'">
      <div class="mb-4 text-5xl">
        {{ feedbackType === 'success' ? '✅' : '❌' }}
      </div>
      <h3 class="mb-2 text-xl font-bold text-white">
        {{ feedbackType === 'success' ? 'Order Successful' : 'Order Failed' }}
      </h3>
      <p class="mb-6 text-sm text-gray-400">{{ feedbackMessage }}</p>
      <button @click="closeFeedback"
        class="w-full py-3 font-bold text-white transition bg-gray-700 rounded-lg shadow-lg hover:bg-gray-600">
        Close
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/api';

const router = useRouter();

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

const inputMode = ref('amount'); 

// function to reset the modal when closed
const resetModalState = () => {
  tradeStep.value = 1;
  tradeAction.value = 'buy';
  nairaInput.value = 0;
  unitInput.value = 0;
  inputMode.value = 'amount'; 
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

  const userStr = localStorage.getItem("user");
  const userObj = userStr ? JSON.parse(userStr) : null;
  if (userObj?.trading_mode === 'demo') {
    return;
  }

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
    const user = JSON.parse(localStorage.getItem("user") || "{}");
    isDemo.value = user.trading_mode === 'demo';

    const [portfolioRes, walletRes] = await Promise.all([
      api.get('/portfolio'),
      api.get('/wallet/balances')
    ]);

    const portData = portfolioRes.data.data || portfolioRes.data;
    const walData = walletRes.data.data || walletRes.data;

    const rawHoldings = portData.holdings || [];
    allHoldings.value = Array.isArray(rawHoldings)
      ? rawHoldings
      : Object.values(rawHoldings);

    if (isDemo.value) {
      userBalance.value = portData.wallet_balance || portData.balance || 0;
    } else {
      userBalance.value = walData.cleared_balance_ngn || walData.balance || 0;
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
  if (!holding) return 0;

  // For Demo mode, if cleared_quantity is missing, fall back to total quantity
  const availableToSell = isDemo.value 
    ? (holding.quantity || 0) 
    : (holding.cleared_quantity || 0);

  return selectedCategory.value?.id === 'CRYPTO' ? availableToSell : Math.floor(availableToSell);
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
    
  router.push('/orders');
  fetchBalance();
    
    
    emit('trade-success');
    handleClose();
};

const handleTrade = async () => {
  isProcessing.value = true;
  try {
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
      await fetchBalance();
      // Trigger dummy settlement status check after successful order
      if (!isDemo.value && selectedCategory.value.id === 'NGX') {
        await api.post('/dummy/cscs/settle', {
          trade_id: 'T' + (res.data.data?.id || Date.now()),
          amount: nairaInput.value,
          cycle: 'T' + Date.now(),
        }).catch(e => console.warn("Settlement dummy failed, but trade was successful"));
      }
      feedbackType.value = 'success';
      feedbackMessage.value = isDemo.value
        ? `Demo ${tradeAction.value.toUpperCase()} executed instantly.`
        : `Order ${tradeAction.value.toUpperCase()} submitted to exchange.`;
      showFeedback.value = true;
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