<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
    <div v-if="!showFeedback"
      class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
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
        </button>
        <button @click="tradeStep = 1" class="w-full py-2 text-xs text-gray-500">← Back</button>
      </div>

      <div v-if="tradeStep === 3" class="space-y-4">
        <div class="flex items-center justify-between p-2 px-1 border border-gray-700 rounded-lg bg-gray-800/50">
          <span class="text-[11px] text-gray-400">Your Holdings:</span>
          <span class="text-xs font-bold text-white">
            {{ currentAssetHolding.toFixed(4) }} {{ selectedTicker?.symbol }}
          </span>
        </div>

        <div class="p-5 text-center border rounded-lg bg-blue-500/10 border-blue-500/30">
          <div class="text-[10px] text-blue-400 uppercase font-bold tracking-widest mb-1">
            You are {{ tradeAction === 'buy' ? 'Receiving' : 'Selling' }}
          </div>
          <div class="mb-1 text-3xl font-bold text-white">
            {{ Number(unitInput).toFixed(6) }} <span class="text-sm font-medium text-gray-400">{{ selectedTicker?.symbol
              }}</span>
          </div>
          <div class="text-sm font-medium text-gray-300">
            Total {{ tradeAction === 'buy' ? 'Cost' : 'Value' }}: ₦{{ (nairaInput || 0).toLocaleString() }}
          </div>
        </div>

        <div class="flex items-center justify-between px-1">
          <span class="text-xs text-gray-500">Wallet Balance:</span>
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
            <input v-model.number="nairaInput" type="number" @input="syncFromNaira"
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
              placeholder="0.000000" />
            <span class="absolute text-xs font-bold text-gray-500 uppercase -translate-y-1/2 right-4 top-1/2">{{
              selectedTicker?.symbol }}</span>
          </div>
        </div>

        <button @click="handleTrade"
          :disabled="isProcessing || nairaInput <= 0 || (tradeAction === 'buy' && nairaInput > userBalance) || (tradeAction === 'sell' && unitInput > currentAssetHolding)"
          class="w-full py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0047AB] to-[#00D4FF] transition-all disabled:opacity-50 disabled:grayscale">
          <span v-if="tradeAction === 'buy' && nairaInput > userBalance">Insufficient Wallet Balance</span>
          <span v-else-if="tradeAction === 'sell' && unitInput > currentAssetHolding">Insufficient Holdings</span>
          <span v-else>{{ isProcessing ? 'Processing Order...' : 'Confirm ' + tradeAction.toUpperCase() }}</span>
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
import { ref, computed, onMounted, watch } from 'vue';
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

// Watch for modal opening with a specific ticker
watch(() => props.show, (newVal) => {
  if (newVal && props.initialTicker) {
    // Auto-select NGX category and the ticker
    selectedCategory.value = props.assetCategories.find(c => c.id === 'NGX') || props.assetCategories[0];
    selectedTicker.value = props.initialTicker;
    tradeStep.value = 3;
    nairaInput.value = 0;
    unitInput.value = 0;
    if (selectedCategory.value.id === 'NGX') {
      fetchNgxPrices();
    }
  } else if (newVal) {
    tradeStep.value = 1;
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
    const response = await api.get('/portfolio');
    if (response.data) {
      userBalance.value = response.data.wallet_balance;
      allHoldings.value = response.data.holdings;
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
  if (selectedTicker.value.currency === 'NGN' || !selectedTicker.value.currency) {
    unitInput.value = nairaInput.value / price;
  } else {
    unitInput.value = (nairaInput.value / USD_RATE) / price;
  }
};

const syncFromUnits = () => {
  if (!selectedTicker.value || unitInput.value <= 0) {
    nairaInput.value = 0;
    return;
  }
  const price = selectedTicker.value.price;
  if (selectedTicker.value.currency === 'NGN' || !selectedTicker.value.currency) {
    nairaInput.value = unitInput.value * price;
  } else {
    nairaInput.value = (unitInput.value * price) * USD_RATE;
  }
};

const currentAssetHolding = computed(() => {
  if (!selectedTicker.value) return 0;
  const holding = allHoldings.value.find(h => h.symbol === selectedTicker.value.symbol);
  return holding ? holding.quantity : 0;
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
    emit('close');
    tradeStep.value = 1;
    nairaInput.value = 0;
    unitInput.value = 0;
  }
  showFeedback.value = false;
};

const handleTrade = async () => {
  isProcessing.value = true;
  try {
    const payload = {
      symbol: selectedTicker.value.symbol,
      side: tradeAction.value, // 'buy' or 'sell'
      type: 'market',
      quantity: unitInput.value,
      price: selectedTicker.value.price,
      amount: nairaInput.value,
      market: selectedCategory.value.id.toUpperCase(),
      company:selectedTicker.value.symbol,
      market_price:selectedTicker.value.price,
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
  } catch (e) {
    feedbackType.value = 'error';
    feedbackMessage.value = "Gateway Timeout: Dummy NGX service unreachable.";
    showFeedback.value = true;
  } finally {
    isProcessing.value = false;
  }
};

onMounted(() => {
  localTickers.value = JSON.parse(JSON.stringify(props.tickers));
  fetchBalance();
});
</script>