<script setup>
import { ref, onMounted, computed } from "vue";
import axios from "axios";
import VueApexCharts from "vue3-apexcharts";
import MainLayout from "@/layouts/MainLayout.vue";

const apexchart = VueApexCharts;

// state
const loading = ref(true);
const data = ref(null);
const error = ref(null);

// fallback demo data
const fallback = {
  wallet_balance: 1240000,
  ngx_value: 845000,
  global_stocks_value_usd: 3720,
  crypto_value: 520000,
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
    { label: "Crypto", value: 520000 }
  ],
  holdings: [
    { symbol: "ZENITH", name: "Zenith Bank", qty: 100, avg_cost: 45.2, market_price: 50.5 },
    { symbol: "MTN", name: "MTN Nigeria", qty: 50, avg_cost: 120, market_price: 135 },
    { symbol: "AAPL", name: "Apple Inc", qty: 2, avg_cost: 145, market_price: 175 },
    { symbol: "BTC", name: "Bitcoin", qty: 0.021, avg_cost: 18000000, market_price: 24761904 }
  ],
  transactions: [
    { date: "2025-10-20", type: "Deposit", asset: "NGN Wallet", amount: 500000, status: "Completed", ref: "DEP-00123" },
    { date: "2025-10-21", type: "Buy", asset: "ZENITH", amount: 4520, status: "Completed", ref: "TRD-00124" },
    { date: "2025-10-22", type: "Sell", asset: "AAPL", amount: 350, status: "Completed", ref: "TRD-00125" }
  ]
};

// computed display values
const walletBalance = computed(() => (data.value?.wallet_balance ?? fallback.wallet_balance));
const ngxValue = computed(() => (data.value?.ngx_value ?? fallback.ngx_value));
const globalValueUSD = computed(() => (data.value?.global_stocks_value_usd ?? fallback.global_stocks_value_usd));
const cryptoValue = computed(() => (data.value?.crypto_value ?? fallback.crypto_value));

// chart options
const perfSeries = ref(fallback.series_performance);
const perfOptions = ref({
  chart: { id: "performance", toolbar: { show: false }, animations: { enabled: true } },
  xaxis: { categories: fallback.performance_categories },
  stroke: { curve: "smooth", width: 2 },
  markers: { size: 3 },
  theme: { mode: "dark" },
  colors: ["#00D4FF", "#0047AB"],
  legend: { position: "top" },
});

const donutOptions = ref({
  chart: { type: "donut", toolbar: { show: false } },
  labels: ["Wallet", "NGX", "Global Stocks", "Crypto"],
  theme: { mode: "dark" },
  legend: { position: "bottom" },
  colors: ["#00D4FF", "#0047AB", "#00A3FF", "#8CFF66"],
});

const donutSeries = computed(() => [
  Number(walletBalance.value),
  Number(ngxValue.value),
  Number(globalValueUSD.value),
  Number(cryptoValue.value)
]);

// Fetch dashboard data
async function fetchDashboard() {
  loading.value = true;
  try {
    const resp = await axios.get("/portfolio", { withCredentials: true });

    data.value = resp.data;
    if (data.value.series_performance) {
      perfSeries.value = data.value.series_performance;
      perfOptions.value.xaxis.categories = data.value.performance_categories || perfOptions.value.xaxis.categories;
    }
    if (data.value.portfolio_distribution) {
      donutSeries.value = data.value.portfolio_distribution.map(p => Number(p.value));
      donutOptions.value.labels = data.value.portfolio_distribution.map(p => p.label);
    }
  } catch (e) {
    data.value = fallback;
    error.value = "Live dashboard unavailable — showing demo data.";
  } finally {
    loading.value = false;
  }
}

onMounted(fetchDashboard);

</script>

<template>
  <MainLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">Dashboard</h1>
          <p class="text-sm text-gray-400">Overview of your portfolio</p>
        </div>
        <div class="text-right">
          <div class="text-xs text-gray-400">Wallet Balance</div>
          <div class="text-lg font-semibold">₦{{ walletBalance.toLocaleString() }}</div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4 lg:grid-cols-5">
        <div class="col-span-1 md:col-span-2 bg-[#111827]/60 p-4 rounded-xl border border-[#1f3348]">
          <div class="text-xs text-gray-400">Wallet Balance</div>
          <div class="text-2xl font-bold">₦{{ walletBalance.toLocaleString() }}</div>
          <div class="mt-2 text-sm text-gray-400">Available / Locked</div>
        </div>
        <div class="bg-[#111827]/60 p-4 rounded-xl border border-[#1f3348]">
          <div class="text-xs text-gray-400">NGX Portfolio</div>
          <div class="text-xl font-semibold">₦{{ ngxValue.toLocaleString() }}</div>
        </div>
        <div class="bg-[#111827]/60 p-4 rounded-xl border border-[#1f3348]">
          <div class="text-xs text-gray-400">Global Stocks</div>
          <div class="text-xl font-semibold">${{ globalValueUSD.toLocaleString() }}</div>
        </div>
        <div class="bg-[#111827]/60 p-4 rounded-xl border border-[#1f3348]">
          <div class="text-xs text-gray-400">Crypto</div>
          <div class="text-xl font-semibold">₦{{ cryptoValue.toLocaleString() }}</div>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 bg-[#0F1724] p-4 rounded-xl border border-[#1f3348]">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Performance</div>
            <div class="text-xs text-gray-400">1W • 1M • 3M • 1Y</div>
          </div>
          <apexchart type="line" height="260" :options="perfOptions" :series="perfSeries" />
        </div>

        <div class="bg-[#0F1724] p-4 rounded-xl border border-[#1f3348]">
          <div class="mb-2 font-semibold">Portfolio Distribution</div>
          <apexchart type="donut" height="260" :options="donutOptions" :series="donutSeries" />
        </div>
      </div>

      <!-- Holdings & Transactions -->
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
                <td>{{ h.qty }}</td>
                <td>{{ Number(h.avg_cost).toLocaleString() }}</td>
                <td>{{ Number(h.market_price).toLocaleString() }}</td>
                <td class="text-green-400">
                  +{{ ((h.market_price - h.avg_cost) * h.qty).toLocaleString() }}
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
            <li v-for="t in (data?.transactions || fallback.transactions)" :key="t.ref"
              class="flex items-center justify-between p-2 rounded hover:bg-[#122033]">
              <div>
                <div class="font-medium">{{ t.type }} — {{ t.asset }}</div>
                <div class="text-xs text-gray-400">{{ t.date }} • ref: {{ t.ref }}</div>
              </div>
              <div class="text-right">
                <div class="font-medium">₦{{ Number(t.amount).toLocaleString() }}</div>
                <div class="text-xs text-gray-400">{{ t.status }}</div>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <div v-if="error" class="mt-6 text-sm text-gray-400">{{ error }}</div>
    </div>
  </MainLayout>
</template>

<script>
import { defineComponent } from "vue";
export default defineComponent({
  components: { apexchart: VueApexCharts },
});
</script>
