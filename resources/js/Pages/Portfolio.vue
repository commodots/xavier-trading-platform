<template>
  <MainLayout>
    <div class="space-y-6">
      <h1 class="text-2xl font-semibold">
        <span v-if="isDemo" class="mr-2 font-bold text-yellow-500">DEMO</span>
        📊 Portfolio
      </h1>
      <p class="text-sm text-gray-400">Your asset allocation & performance overview.</p>

      <div :class="loading ? 'blur-sm animate-pulse opacity-50 pointer-events-none transition-all duration-300' : 'transition-all duration-300'">
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
          <div class="text-sm text-gray-400">
            {{ isDemo ? 'Virtual Portfolio Value' : 'Total Portfolio Value' }}
          </div>
          <div class="mt-2 text-4xl font-bold transition-colors" :class="isDemo ? 'text-yellow-400' : 'text-white'">
            ₦{{ totalEquity.toLocaleString() }}
          </div>
        </div>
      </div>

      <div :class="loading ? 'blur-sm animate-pulse opacity-50 pointer-events-none transition-all duration-300' : 'transition-all duration-300'">
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 relative">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">Allocation Breakdown</h2>
            <button @click="showTradeModal = true" :class="[
              'px-6 py-2 rounded-lg text-white font-bold hover:opacity-90 transition shadow-lg flex items-center gap-2',
              isDemo ? 'bg-gradient-to-r from-yellow-600 to-orange-500' : 'bg-gradient-to-r from-[#0047AB] to-[#00D4FF]'
            ]">
              <span>⇄</span> {{ isDemo ? 'Demo Trade Assets' : 'Trade Assets' }}
            </button>
          </div>
          <TradeModal :show="showTradeModal" :tickers="tickers" :assetCategories="assetCategories"
            @close="showTradeModal = false" @trade-success="refreshPortfolio" />
          <apexchart height="300" type="pie" :options="chartOptions" :series="chartSeries" />
        </div>
      </div>

      <div :class="loading ? 'blur-sm animate-pulse opacity-50 pointer-events-none transition-all duration-300' : 'transition-all duration-300'">
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
          <h2 class="mb-4 text-lg font-semibold">Your Holdings</h2>
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="py-2 text-left">Asset</th>
                <th class="text-left">Total Qty</th>
                <th class="text-left">Cleared</th>
                <th class="text-left">Uncleared</th>
                <th class="text-left">Status</th>
                <th class="text-left">Avg Cost</th>
                <th class="text-left">Market Price</th>
                <th class="text-left">P/L</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="holdings.length === 0">
                <td colspan="8" class="py-6 text-center text-gray-500">
                  You currently hold no assets.
                </td>
              </tr>
              <tr v-else v-for="h in holdings" :key="h.symbol" class="border-b border-[#1f3348] hover:bg-[#16213A]">
                <td class="py-3 font-semibold">{{ h.symbol }}</td>
                <td>{{ formatQuantity(h.quantity, h.category || '') }}</td>
                <td>{{ formatQuantity(h.cleared_quantity || h.quantity || 0, h.category || '') }}</td>
                <td>{{ formatQuantity(h.uncleared_quantity || 0, h.category || '') }}</td>
                <td>
                  <span :class="(h.uncleared_quantity || 0) > 0 ? 'text-yellow-400' : 'text-green-400'">
                    {{ (h.uncleared_quantity || 0) > 0 ? '&#x1F7E1; Pending' : '&#X1F7E2; Settled' }}
                  </span>
                </td>
                <td>
                  ₦{{ h.currency === 'USD' ? ((h.avg_price || 0) * 1500).toLocaleString() : (h.avg_price || 0).toLocaleString() }}
                </td>
                <td>
                  {{ h.currency === 'USD' ? '$' : '₦' }}{{ Number(h.market_price || 0).toLocaleString() }}
                </td>
                <td :class="h.total_value_ngn >= ((h.avg_price_ngn || 0) * h.quantity) ? 'text-green-400' : 'text-red-400'">
                  ₦{{ ((h.total_value_ngn || 0) - ((h.avg_price_ngn || 0) * h.quantity)).toLocaleString() }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from "@/Layouts/MainLayout.vue";
import TradeModal from "@/Components/TradeModal.vue";
import { ref, onMounted, onUnmounted } from "vue";
import VueApexCharts from "vue3-apexcharts";
import api from "@/api";

const apexchart = VueApexCharts;

// --- State ---
const isDemo = ref(false);
const loading = ref(true);
const holdings = ref([]);
const totalEquity = ref(0);
const chartSeries = ref([]);
const chartOptions = ref({
  labels: ["Wallet", "NGX", "Global Stocks (USD)", "Crypto (USD)", "Fixed Income"],
  legend: { position: "bottom", labels: { colors: "#fff" } },
  theme: { mode: "dark" }
});

const showTradeModal = ref(false);

const assetCategories = [
  { id: 'NGX', name: 'Local Stocks (NGX)', description: 'Nigerian Stock Exchange' },
  { id: 'GLOBAL', name: 'Global Stocks (USD)', description: 'US Markets (Tesla, Apple, etc.)' },
  { id: 'CRYPTO', name: 'Cryptocurrency(USD)', description: 'Bitcoin & Digital Assets' },
  { id: 'FIXED_INCOME', name: 'Fixed Income', description: 'Fixed Income Market' }
];

const tickers = {
  NGX: [
    { symbol: 'MTNN', name: 'MTN Nigeria', price: 245.50, currency: 'NGN' },
    { symbol: 'DANGCEM', name: 'Dangote Cement', price: 320.00, currency: 'NGN' },
    { symbol: 'ZENITH', name: 'Zenith Bank', price: 35.20, currency: 'NGN' }
  ],
  GLOBAL: [
    { symbol: 'TSLA', name: 'Tesla Inc.', price: 175.40, currency: 'USD' },
    { symbol: 'AAPL', name: 'Apple Inc.', price: 189.10, currency: 'USD' },
    { symbol: 'NVDA', name: 'Nvidia Corp.', price: 820.50, currency: 'USD' }
  ],
  CRYPTO: [
    { symbol: 'BTC', name: 'Bitcoin', price: 64250.00, currency: 'USD' },
    { symbol: 'ETH', name: 'Ethereum', price: 3450.00, currency: 'USD' },
    { symbol: 'SOL', name: 'Solana', price: 145.00, currency: 'USD' }
  ],
  FIXED_INCOME: [
    { symbol: 'FGNSB_2027', name: 'FGN Savings Bond 2027', price: 1000.00, currency: 'NGN' },
    { symbol: 'CP_MTN_I', name: 'MTN Commercial Paper', price: 1000.00, currency: 'NGN' },
    { symbol: 'ABB2026S0', name: 'FGN Bond Jan 2026', price: 1000.00, currency: 'NGN' }
  ]
};


const handleModeSwitching = (e) => {
  isDemo.value = e.detail === 'demo';
  loading.value = true;
};

const refreshPortfolio = async () => {
  loading.value = true;
  try {
    const userStr = localStorage.getItem("user");
    const userObj = userStr ? JSON.parse(userStr) : null;
    isDemo.value = userObj?.trading_mode === 'demo';

    // Set the endpoint dynamically
    const endpoint = isDemo.value ? "/demo/portfolio" : "/portfolio";
    const res = await api.get(endpoint);
    
    // Extract data
    const data = isDemo.value ? res.data.data : res.data;

    const wallet = Number(data.wallet_balance || 0);
    const ngx = Number(data.ngx_value || 0);
    const crypto = Number(data.crypto_value_usd || 0);
    const globalUsd = Number(data.global_stocks_value_usd || 0);
    const fixedIncome = Number(data.fixed_income_value || 0);

    totalEquity.value = Number(data.total_equity || 0);
    holdings.value = data.holdings || [];
    chartSeries.value = [wallet, ngx, globalUsd, crypto, fixedIncome];
    
  } catch (err) {
    console.error("Portfolio fetch error:", err);
  } finally {
    loading.value = false;
  }
};

function formatQuantity(quantity, category) {
  const num = Number(quantity);
  
  if (category?.toLowerCase() === 'crypto') {
    return num.toFixed(8).replace(/\.?0+$/, ''); // Remove trailing zeros
  } else {
    return Math.floor(num).toString();
  }
}

onMounted(() => {
  refreshPortfolio();
 
  window.addEventListener('trading-mode-switching', handleModeSwitching);

  window.addEventListener('trading-mode-changed', refreshPortfolio);
});

onUnmounted(() => {
  window.removeEventListener('trading-mode-switching', handleModeSwitching);
  window.removeEventListener('trading-mode-changed', refreshPortfolio);
});
</script>