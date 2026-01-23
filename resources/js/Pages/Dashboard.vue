<script setup>
import { ref, onMounted, computed } from "vue";
import api from "@/api";
import VueApexCharts from "vue3-apexcharts";
import MainLayout from "@/Layouts/MainLayout.vue";
import TradeModal from "@/Components/TradeModal.vue";


const apexchart = VueApexCharts;

// state
const loading = ref(false);
const data = ref(null);
const transactions = ref([]);
const error = ref(null);
const showTradeModal = ref(false);


// Asset Data
const assetCategories = [
  { id: 'NGX', name: 'Local Stocks (NGX)', description: 'Nigerian Stock Exchange' },
  { id: 'GLOBAL', name: 'Global Stocks (USD)', description: 'US Markets (Tesla, Apple, etc.)' },
  { id: 'CRYPTO', name: 'Cryptocurrency (USD)', description: 'Bitcoin & Digital Assets' },
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

// fallback demo data
const fallback = {
  wallet_balance: 1240000,
  ngx_value: 845000,
  global_stocks_value_usd: 3720,
  crypto_value_usd: 520000,
  fixed_income_value:200000,
  total_equity: 2600000,
  series_performance: [
    { name: "Total Equity", data: [2400000, 2420000, 2435000, 2440000, 2480000, 2520000, 2600000] },
    { name: "Wallet", data: [1200000, 1210000, 1215000, 1220000, 1240000, 1250000, 1240000] }
  ],
  performance_categories: ["7d-6", "7d-5", "7d-4", "7d-3", "7d-2", "7d-1", "Today"],
  portfolio_distribution: [
    { label: "Wallet", value: 1240000 },
    { label: "NGX", value: 845000 },
    { label: "Global Stocks (USD)", value: 3720 },
    { label: "Crypto (USD)", value: 520000 },
    { label: "Fixed Income", value: 20000 }
  ],
  holdings: [
    { symbol: "ZENITH", name: "Zenith Bank", quantity: 100, avg_price: 45.2, market_price: 50.5 },
    { symbol: "MTNN", name: "MTN Nigeria", quantity: 50, avg_price: 120, market_price: 135 },
    { symbol: "AAPL", name: "Apple Inc", quantity: 2, avg_price: 145, market_price: 175 },
    { symbol: "BTC", name: "Bitcoin", quantity: 0.021, avg_price: 18000000, market_price: 24761904 },
     { symbol: 'CP_MTN_I', name: 'MTN Commercial Paper', quantity:50, avg_price: 1000.00, market_price:950.00},
    { symbol: 'ABB2026S0', name: 'FGN Bond Jan 2026', quantity:40, avg_price: 1000.00, market_price:950.00}
  ],
  transactions: [
    { date: "2025-10-20", type: "Deposit", asset: "NGN Wallet", currency: "NGN",amount: 500000, status: "Completed", ref: "DEP-00123" },
    { date: "2025-10-21", type: "Buy", asset: "ZENITH", currency: "NGN", amount: 4520, status: "Completed", ref: "TRD-00124" },
    { date: "2025-10-22", type: "Sell", asset: "AAPL", currency: "USD", amount: 350, status: "Completed", ref: "TRD-00125" }
  ]
};

// --- Logic ---
const walletBalance = computed(() => (data.value?.wallet_balance ?? 0));
const ngxValue = computed(() => (data.value?.ngx_value ?? 0));
const fixedIncomeValue = computed(() => (data.value?.fixed_income_value ?? 0));
const globalValueNGN = computed(() => (data.value?.global_stocks_value_ngn ?? 0));
const globalValueUSD = computed(() => (data.value?.global_stocks_value_usd ?? 0));
const cryptoValueNGN = computed(() => (data.value?.crypto_value_ngn ?? 0));
const cryptoValueUSD = computed(() => (data.value?.crypto_value_usd ?? 0));

const totalEquity = computed(() => data.value?.total_equity ?? 0);


const perfSeries = ref([]);
const perfOptions = ref({
  chart: { id: "performance", toolbar: { show: false }, animations: { enabled: true } },
  xaxis: { categories: fallback.performance_categories },
  stroke: { curve: "smooth", width: 2 },
  markers: { size: 3 },
  theme: { mode: "dark" },
  colors: ["#00D4FF", "#0047AB"],
  legend: { position: "top" },
  yaxis: { labels: { formatter: (val) => "₦" + Number(val).toLocaleString() } }
});

const donutOptions = ref({
  chart: { type: "donut", toolbar: { show: false } },
  labels: ["Wallet", "NGX", "Global Stocks (USD)", "Crypto (USD)", "Fixed Income"],
  theme: { mode: "dark" },
  legend: { position: "bottom" },
  colors: ["#00D4FF", "#0047AB", "#00A3FF", "#8CFF66","#4d5c72"],
});

const donutSeries = ref([0, 0, 0, 0]);

const formatDate = (dateStr) => {
  if (!dateStr) return "";
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-NG', { year: 'numeric', month: 'short', day: 'numeric' });
};

/**
 * Generates a fake 7-day trend based on current value 
 * so the chart always shows a line.
 */
function generateTrend(currentValue) {
  const points = [];
  let lastVal = currentValue;
  // Create 7 points ending at the current value
  for (let i = 0; i < 7; i++) {
    points.unshift(lastVal);
    // Simulate a slight random change (between -1% and +1%) for previous days
    const variance = 1 + (Math.random() * 0.02 - 0.01);
    lastVal = lastVal / variance;
  }
  return points;
}

// Fetch dashboard data
async function fetchDashboard() {
  loading.value = true;
  error.value = null;
  try {
    const token = localStorage.getItem("xavier_token");
    const [portfolioResp, transResp] = await Promise.all([
      api.get("/portfolio", { headers: { Authorization: `Bearer ${token}` } }),
      api.get("/transactions", { headers: { Authorization: `Bearer ${token}` } })
    ]);

    data.value = portfolioResp.data;
    transactions.value = Array.isArray(transResp.data) ? transResp.data : transResp.data.transactions;

    
    if (data.value.portfolio_distribution) {
      donutSeries.value = data.value.portfolio_distribution.map(p => Number(p.value));
      donutOptions.value.labels = data.value.portfolio_distribution.map(p => p.label);
    } else {
      donutSeries.value = [
        Number(data.value.wallet_balance),
        Number(data.value.ngx_value),
        Number(data.value.fixed_income_value),
        Number(data.value.global_stocks_value_usd),
        Number(data.value.crypto_value_usd)
      ];
    }

    if (data.value.series_performance && data.value.series_performance.length > 0) {
      perfSeries.value = data.value.series_performance.map(s => ({
        name: s.name,
        data: s.data.map(val => Number(val))
      }));
    } else {
      // GENERATE LINES: Use current values to create a trend line backwards
      perfSeries.value = [
        { 
          name: "Total Equity", 
          data: generateTrend(Number(data.value.total_equity)) 
        },
        { 
          name: "Wallet", 
          data: generateTrend(Number(data.value.wallet_balance)) 
        }
      ];
    }

  } catch (e) {
    data.value = fallback;
    error.value = "Live dashboard unavailable — showing demo data.";
    perfSeries.value = fallback.series_performance;
    donutSeries.value = fallback.portfolio_distribution.map(p => p.value);
  } finally {
    loading.value = false;
  }
}

onMounted(fetchDashboard);
</script>

<template>
  <MainLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">Dashboard</h1>
          <p class="text-sm text-gray-400">Overview of your portfolio</p>
        </div>
        <div class="flex items-center gap-4">
          <button @click="showTradeModal = true"
            class="bg-gradient-to-r from-[#0047AB] to-[#00D4FF] px-6 py-2 rounded-lg text-white font-bold hover:opacity-90 transition shadow-lg">
            ⇄ Trade
          </button>
          <div @click="$router.push({ name: 'wallet' })"
            class="text-right hidden sm:block col-span-1 md:col-span-2 bg-[#111827]/60 p-4 rounded-xl border border-[#1f3348] cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95">
            <div class="text-xs text-gray-400">Total Wallet Balance</div>
            <div class="text-lg font-semibold">₦{{ walletBalance.toLocaleString() }}</div>
          </div>
          <TradeModal :show="showTradeModal" :tickers="tickers" :assetCategories="assetCategories"
            @close="showTradeModal = false"
            @trade-success="fetchDashboard"
             />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-4 lg:grid-cols-5">
        <div @click="$router.push({ name: 'portfolio' })"
          class="col-span-1 md:col-span-2 bg-[#111827]/60 p-4 rounded-xl border border-[#1f3348] cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95">
          <div class="text-xs text-gray-400">Total Portfolio Value</div>
          <div class="text-2xl font-bold">₦{{ totalEquity.toLocaleString() }}</div>
        </div>
        <div @click="$router.push({ name: 'ngx' })"
          class="bg-[#111827]/60 p-4 rounded-xl border border-[#1f3348] cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95">
          <div class="text-xs text-gray-400">NGX</div>
          <div class="text-xl font-semibold">₦{{ ngxValue.toLocaleString() }}</div>
        </div>
        <div @click="$router.push({ name: 'global-stocks' })"
          class="bg-[#111827]/60 p-4 rounded-xl border border-[#1f3348] cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95">
          <div class="text-xs text-gray-400">US Stocks</div>
          <div class="text-xl font-semibold">${{ globalValueUSD.toLocaleString() }}</div>
        </div>
        <div @click="$router.push({ name: 'crypto' })"
          class="bg-[#111827]/60 p-4 rounded-xl border border-[#1f3348] cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95">
          <div class="text-xs text-gray-400">Crypto</div>
          <div class="text-xl font-semibold">₦{{ cryptoValueNGN.toLocaleString() }}</div>
        </div>
        <div @click="$router.push({ name: 'fixed-income' })"
          class="bg-[#111827]/60 p-4 rounded-xl border border-[#1f3348] cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95">
          <div class="text-xs text-gray-400">Fixed Income</div>
          <div class="text-xl font-semibold">₦{{ fixedIncomeValue.toLocaleString() }}</div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 bg-[#0F1724] p-4 rounded-xl border border-[#1f3348]">
          <div class="mb-3 font-semibold">Performance</div>
          <apexchart type="line" height="260" :options="perfOptions" :series="perfSeries" />
        </div>
        <div class="bg-[#0F1724] p-4 rounded-xl border border-[#1f3348]">
          <div class="mb-2 font-semibold">Distribution</div>
          <apexchart type="donut" height="260" :options="donutOptions" :series="donutSeries" />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="bg-[#0F1724] p-4 rounded-xl border border-[#1f3348]">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Holdings</div>
            <div class="text-xs text-gray-400">Sort by value</div>
          </div>

          <table class="w-full text-sm">
            <thead class="text-left text-gray-400 text-xs border-b border-[#1f2a44]">
              <tr>
                <th class="py-2">Asset</th>
                <th>Qty</th>
                <th>Avg Cost</th>
                <th>Market Price</th>
                <th>Unrealised</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="h in (data?.holdings || fallback.holdings)" :key="h.symbol" class="border-b border-[#1f2a44]">
                <td class="py-3">
                  <div class="font-medium">{{ h.symbol }}</div>
                  <div class="text-xs text-gray-400">{{ h.name }}</div>
                </td>
                <td>{{ h.quantity }}</td>
                <td>₦{{ Number(h.avg_price_ngn || h.avg_price).toLocaleString() }}</td>
                <td>{{ h.currency === 'USD' ? '$' : '₦' }}{{ Number(h.market_price).toLocaleString() }}</td>
                <td
                  :class="(h.total_value_ngn - (h.avg_price_ngn * h.quantity)) >= 0 ? 'text-green-400' : 'text-red-400'">
                  {{ (h.total_value_ngn - (h.avg_price_ngn * h.quantity)) >= 0 ? '+' : '' }}
                  ₦{{ (h.total_value_ngn - (h.avg_price_ngn * h.quantity)).toLocaleString() }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="bg-[#0F1724] p-4 rounded-xl border border-[#1f3348]">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Recent Transactions</div>
            <div class="text-xs text-gray-400">All activity</div>
          </div>

          <ul class="space-y-2 text-sm text-gray-300">
            <li v-for="t in (transactions.length > 0 ? transactions : fallback.transactions)" :key="t.id || t.ref"
              class="flex items-center justify-between p-2 rounded hover:bg-[#122033]">
              <div>
                <div class="font-medium">{{ t.type }} — {{ t.currency }}</div>
                <div class="text-xs text-gray-400">
                  {{ formatDate(t.created_at) }} • ref: {{ t.reference || t.id }}
                </div>
              </div>
              <div class="text-right">
                <div class="font-medium" :class="t.type === 'deposit' ? 'text-green-400' : 'text-white'">
                  {{ t.type === 'withdrawal' ? '-' : '' }}{{ t.currency === 'USD' ? '$' : '₦' }}{{
                    Number(t.amount).toLocaleString() }}
                </div>
                <div class="text-xs text-gray-400">{{ t.status }}</div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script>
import { defineComponent } from "vue";
export default defineComponent({
  components: { apexchart: VueApexCharts },
});
</script>