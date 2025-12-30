<template>
  <MainLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-semibold">📊 Portfolio</h1>
      <p class="text-sm text-gray-400">Your asset allocation & performance overview.</p>

      <!-- Total Equity -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="text-sm text-gray-400">Total Portfolio Value</div>
        <div class="mt-2 text-4xl font-bold">₦{{ totalEquity.toLocaleString() }}</div>
      </div>

      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 relative">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">Allocation Breakdown</h2>
          <button @click="showTradeModal = true"
            class="bg-gradient-to-r from-[#0047AB] to-[#00D4FF] px-6 py-2 rounded-lg text-white font-bold hover:opacity-90 transition shadow-lg flex items-center gap-2">
            <span>⇄</span> Trade Assets
          </button>
        </div>
        <apexchart height="300" type="pie" :options="chartOptions" :series="chartSeries" />
      </div>

      <!-- Holdings -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="mb-4 text-lg font-semibold">Your Holdings</h2>
        <table class="w-full text-sm">
          <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
            <tr>
              <th class="py-2 text-left">Asset</th>
              <th class="text-left">Qty</th>
              <th class="text-left">Avg Cost</th>
              <th class="text-left">Market Price</th>
              <th class="text-left">P/L</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in holdings" :key="h.symbol" class="border-b border-[#1f3348] hover:bg-[#16213A]">
              <td class="py-3 font-semibold">{{ h.symbol }}</td>
              <td>{{ h.quantity }}</td>
              <td>
              ₦{{ h.currency === 'USD' ? (h.avg_price * 1500).toLocaleString() : h.avg_price.toLocaleString() }}
              </td>
              <td>
                {{ h.currency === 'USD' ? '$' : '₦' }}{{ Number(h.market_price).toLocaleString() }}
              </td>
              <td
                :class="(h.total_value_ngn - (h.avg_price_ngn * h.quantity)) >= 0 ? 'text-green-400' : 'text-red-400'">
                ₦{{ (
                  (h.currency === 'USD' ? (h.total_value * 1500) : h.total_value) -
                  (h.currency === 'USD' ? (h.avg_price * 1500 * h.quantity) : (h.avg_price * h.quantity))
                ).toLocaleString() }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="showTradeModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
          <button @click="closeTradeModal" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>

          <h2 class="mb-4 text-xl font-semibold">
            {{ tradeStep === 1 ? 'Select Market' : tradeStep === 2 ? 'Select Ticker' : 'Trade ' + selectedTicker.symbol
            }}
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
                <div class="font-mono text-sm">{{ ticker.currency === 'NGN' ? '₦' : '$' }}{{
                  ticker.price.toLocaleString() }}</div>
              </div>
            </button>
            <button @click="tradeStep = 1" class="w-full py-2 text-xs text-gray-500">← Back</button>
          </div>

          <div v-if="tradeStep === 3" class="space-y-4">
            <div class="p-4 bg-blue-500/10 border border-blue-500/30 rounded-lg text-center">
              <div class="text-[10px] text-blue-400 uppercase font-bold tracking-widest">Market Price ({{
                selectedTicker.symbol }})</div>
              <div class="text-3xl font-bold text-white">
                {{ selectedTicker.currency === 'NGN' ? '₦' : '$' }}{{ selectedTicker.price.toLocaleString() }}
              </div>
            </div>

            <div class="flex border border-[#2A314A] rounded-lg overflow-hidden p-1 bg-[#0F1724]">
              <button @click="tradeAction = 'buy'"
                :class="tradeAction === 'buy' ? 'bg-blue-600 text-white' : 'text-gray-400'"
                class="flex-1 py-2 text-xs font-bold rounded-md transition-all">BUY</button>
              <button @click="tradeAction = 'sell'"
                :class="tradeAction === 'sell' ? 'bg-red-600 text-white' : 'text-gray-400'"
                class="flex-1 py-2 text-xs font-bold rounded-md transition-all">SELL</button>
            </div>

            <div>
              <label class="text-xs text-gray-400">Enter Quantity</label>
              <input v-model.number="tradeAmount" type="number" step="0.0001"
                class="w-full px-4 py-3 mt-1 bg-[#0F1724] border border-gray-600 rounded-lg text-white focus:ring-2 focus:ring-blue-500 outline-none"
                placeholder="0.00" />
            </div>

            <div class="bg-[#0F1724] p-3 rounded-lg border border-[#1f3348] text-sm space-y-1">
              <div class="flex justify-between">
                <span class="text-gray-400">Total:</span>
                <span class="font-bold text-white">{{ selectedTicker.currency === 'NGN' ? '₦' : '$' }}{{ (tradeAmount *
                  selectedTicker.price).toLocaleString() }}</span>
              </div>
            </div>

            <button @click="executeTrade" :disabled="isProcessing || tradeAmount <= 0"
              class="w-full py-4 rounded-xl font-bold text-white bg-gradient-to-r from-[#0047AB] to-[#00D4FF] hover:shadow-[0_0_20px_rgba(0,71,171,0.4)] transition-all disabled:opacity-50">
              {{ isProcessing ? 'Processing Order...' : 'Confirm ' + tradeAction.toUpperCase() }}
            </button>

            <button @click="tradeStep = 2" class="w-full text-xs text-gray-500 hover:text-white transition">← Choose
              different ticker</button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from "@/layouts/MainLayout.vue";
import { ref, onMounted, computed } from "vue";
import VueApexCharts from "vue3-apexcharts";
import api from "@/api";

const apexchart = VueApexCharts;

// --- State ---
const holdings = ref([]);
const totalEquity = ref(0);
const chartSeries = ref([]);
const chartOptions = ref({
  labels: ["Wallet", "NGX", "Global Stocks (USD)", "Crypto"],
  legend: { position: "bottom", labels: { colors: "#fff" } },
  theme: { mode: "dark" }
});

// --- Trade Modal Logic ---
const showTradeModal = ref(false);
const tradeStep = ref(1);
const tradeAction = ref('buy');
const tradeAmount = ref(0);
const isProcessing = ref(false);
const selectedCategory = ref(null);
const selectedTicker = ref(null);

const assetCategories = [
  { id: 'local', name: 'Local Stocks (NGX)', description: 'Nigerian Stock Exchange' },
  { id: 'foreign', name: 'Global Stocks (USD)', description: 'US Markets (Tesla, Apple, etc.)' },
  { id: 'crypto', name: 'Cryptocurrency', description: 'Bitcoin & Digital Assets' }
];

const tickers = {
  local: [
    { symbol: 'MTNN', name: 'MTN Nigeria', price: 245.50, currency: 'NGN' },
    { symbol: 'DANGCEM', name: 'Dangote Cement', price: 320.00, currency: 'NGN' },
    { symbol: 'ZENITH', name: 'Zenith Bank', price: 35.20, currency: 'NGN' }
  ],
  foreign: [
    { symbol: 'TSLA', name: 'Tesla Inc.', price: 175.40, currency: 'USD' },
    { symbol: 'AAPL', name: 'Apple Inc.', price: 189.10, currency: 'USD' },
    { symbol: 'NVDA', name: 'Nvidia Corp.', price: 820.50, currency: 'USD' }
  ],
  crypto: [
    { symbol: 'BTC', name: 'Bitcoin', price: 64250.00, currency: 'USD' },
    { symbol: 'ETH', name: 'Ethereum', price: 3450.00, currency: 'USD' },
    { symbol: 'SOL', name: 'Solana', price: 145.00, currency: 'USD' }
  ]
};

const filteredTickers = computed(() => {
  return selectedCategory.value ? tickers[selectedCategory.value.id] : [];
});

const selectCategory = (cat) => {
  selectedCategory.value = cat;
  tradeStep.value = 2;
};

const selectTicker = (t) => {
  selectedTicker.value = t;
  tradeStep.value = 3;
};

const closeTradeModal = () => {
  showTradeModal.value = false;
  tradeStep.value = 1;
  tradeAmount.value = 0;
  selectedTicker.value = null;
};

const executeTrade = async () => {
  isProcessing.value = true;
  try {
    setTimeout(() => {
      alert(`Success: ${tradeAction.value.toUpperCase()} ${tradeAmount.value} ${selectedTicker.value.symbol}`);
      closeTradeModal();
    }, 1200);
  } finally {
    isProcessing.value = false;
  }
};

onMounted(async () => {
  try {
    const res = await api.get("/portfolio");
    const data = res.data;

    const wallet = Number(data.wallet_balance || 0);
    const ngx = Number(data.ngx_value || 0);
    const crypto = Number(data.crypto_value || 0);
    const globalNgn = Number(data.global_stocks_value_ngn || 0);
    const total = parseFloat(data.total_equity || 0);

    totalEquity.value = total > 0 ? total : (wallet + ngx + crypto + globalNgn);
    holdings.value = data.holdings || [];

    chartSeries.value = [wallet, ngx, globalNgn, crypto];

  } catch (err) {
    console.error("Portfolio fetch error:", err);
  }
});
</script>