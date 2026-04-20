<template>
  <MainLayout>
    <div class="space-y-6">
      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">📈 NGX Market</h1>
        <div class="relative">
          <input v-model="search" type="text" placeholder="Search stocks..."
            class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none w-64 transition-all" />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
          <HoldingPerformanceChart 
            title="Your NGX Holdings" 
            currencySymbol="₦" 
            :seriesData="portfolioData" 
            :totalValue="totalValue"
            :percentageChange="changePercent" 
            :loading="isGraphLoading" 
            @rangeChange="fetchPortfolioPerformance" 
          />
        </div>

        <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] flex flex-col h-[450px]">
          <div class="p-4 border-b border-[#1f3348]">
            <h3 class="text-sm font-bold tracking-wider text-gray-400 uppercase">Market Insights</h3>
          </div>
          
          <div class="flex border-b border-[#1f3348] text-[10px] font-bold uppercase overflow-x-auto no-scrollbar bg-[#0B121D]">
            <button v-for="tab in marketTabs" :key="tab.id" @click="activeTab = tab.id"
              class="flex-1 px-2 py-3 transition-all border-b-2 whitespace-nowrap"
              :class="activeTab === tab.id ? 'border-[#00D4FF] text-[#00D4FF] bg-[#00D4FF]/5' : 'border-transparent text-gray-500 hover:text-gray-300'">
              {{ tab.name }}
            </button>
          </div>

          <div class="flex-1 overflow-y-auto custom-scrollbar">
            <table class="w-full text-xs">
              <tbody class="divide-y divide-[#1f3348]/50">
                <tr v-for="item in currentMarketData" :key="item.symbol" 
                    class="hover:bg-[#16213A] transition cursor-pointer group" 
                    @click="openDetails(item)">
                  <td class="px-4 py-3">
                    <div class="font-bold text-white group-hover:text-[#00D4FF]">{{ item.symbol }}</div>
                    <div class="text-[10px] text-gray-500 truncate w-24">{{ item.name }}</div>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="font-semibold text-white">₦{{ item.price.toLocaleString() }}</div>
                    <div :class="item.change >= 0 ? 'text-green-400' : 'text-red-400'" class="text-[10px]">
                      {{ item.change >= 0 ? '+' : '' }}{{ item.change }}%
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-hidden">
        <div class="p-4 border-b border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
          <h2 class="font-semibold text-gray-200">All Listed Stocks</h2>
          <span class="text-xs text-gray-500">{{ filteredStocks.length }} Assets available</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 border-b border-[#1f3348] bg-[#0B121D]">
              <tr>
                <th class="px-6 py-4 font-medium text-left">Symbol</th>
                <th class="font-medium text-left">Company</th>
                <th class="font-medium text-right">Price (₦)</th>
                <th class="font-medium text-right">24h Change</th>
                <th class="font-medium text-right">Volume</th>
                <th class="px-6 font-medium text-right">Trend</th>
                <th class="font-medium text-center" colspan="2">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#1f3348]">
              <tr v-for="stock in filteredStocks" :key="stock.symbol"
                class="hover:bg-[#16213A] transition">
                <td class="px-6 py-4 font-bold text-[#00D4FF]">{{ stock.symbol }}</td>
                <td class="text-gray-300">{{ stock.name }}</td>
                <td class="font-mono font-semibold text-right text-white">₦{{ stock.price.toLocaleString() }}</td>
                <td class="text-right" :class="stock.change >= 0 ? 'text-green-400' : 'text-red-400'">
                  {{ stock.change >= 0 ? '+' : '' }}{{ stock.change }}%
                </td>
                <td class="text-right text-gray-400">{{ stock.volume.toLocaleString() }}</td>
                <td class="w-32 px-6 text-right">
                  <apexchart type="line" height="30" :options="sparkOptions" :series="[{ data: stock.spark }]" />
                </td>
                <td class="px-2 text-center">
                  <button @click="openDetails(stock)"
                    class="bg-[#1f3348] text-gray-300 px-3 py-1.5 rounded-md hover:text-white hover:bg-[#2d4a66] transition text-xs">
                    Details
                  </button>
                </td>
                <td class="px-2 pr-6 text-center">
                  <button @click="openTrade(stock)"
                    class="bg-[#00D4FF] text-[#0F1724] px-4 py-1.5 rounded-md font-bold hover:bg-[#00b8e6] transition text-xs">
                    Trade
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="filteredStocks.length === 0" class="p-10 text-center text-gray-500">
            No stocks match your search "{{ search }}"
          </div>
        </div>
      </div>

      <MarketDetailsModal :isOpen="isModalOpen" :item="selectedItem" :currencySymbol="'₦'" @close="isModalOpen = false" />
      <TradeModal 
        :show="showTradeModal" 
        :tickers="tradeTickers" 
        :assetCategories="assetCategories"
        :initialTicker="selectedTradeStock"
        @close="showTradeModal = false"
        @trade-success="fetchPortfolioPerformance" 
      />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import apexchart from "vue3-apexcharts";
import MarketDetailsModal from "@/Components/MarketDetailsModal.vue";
import HoldingPerformanceChart from "@/Components/HoldingPerformanceChart.vue";
import TradeModal from "@/Components/TradeModal.vue";
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import api from "@/api";

// UI State
const user = ref(JSON.parse(localStorage.getItem('user') || '{}'));
const isDemo = ref(user.value.trading_mode === 'demo');
const showPrompt = ref(false);
const search = ref("");
const isModalOpen = ref(false);
const selectedItem = ref(null);
const showTradeModal = ref(false);
const selectedTradeStock = ref(null);
const activeTab = ref('gainers');
const isGraphLoading = ref(false);

// Portfolio & Market State
const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  return role.includes('admin');
};

const isUserVerified = computed(() => {
  const u = user.value || {};
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);
const assetCategories = [{ id: 'NGX', name: 'Local Stocks (NGX)', description: 'Nigerian Stock Exchange' }];

const marketTabs = [
  { id: 'gainers', name: 'Gainers' },
  { id: 'losers', name: 'Losers' },
  { id: 'most_traded', name: 'Most Traded' },
  { id: 'least_traded', name: 'Least' }
];

// Mock Insights Data (Ideally this comes from a Market API)
const insightData = {
  gainers: [
    { symbol: 'DANGCEM', name: 'Dangote Cement', price: 450.50, change: 9.8, volume: 45000, spark: [400, 420, 450.5] },
    { symbol: 'ZENITH', name: 'Zenith Bank', price: 51.2, change: 1.5, volume: 1240000, spark: [49, 50, 51.2] },
    { symbol: 'FBNH', name: 'FBN Holdings', price: 28.4, change: 1.2, volume: 890000, spark: [27, 28, 28.4] },
  ],
  losers: [
    { symbol: 'NESTLE', name: 'Nestle Nigeria', price: 950.00, change: -4.5, volume: 12000, spark: [1000, 980, 950] },
    { symbol: 'GTCO', name: 'GT Holdings', price: 45.8, change: -0.8, volume: 870000, spark: [47, 46, 45.8] },
    { symbol: 'TRANSCORP', name: 'Transcorp PLC', price: 12.1, change: -2.3, volume: 5400000, spark: [13, 12.5, 12.1] },
  ],
  most_traded: [
    { symbol: 'ACCESS', name: 'Access Holdings', price: 24.1, change: 0.4, volume: 15400000, spark: [23, 24, 24.1] },
    { symbol: 'FIDELITY', name: 'Fidelity Bank', price: 10.5, change: 0.1, volume: 12100000, spark: [10, 10.2, 10.5] },
  ],
  least_traded: [
    { symbol: 'AIRTELAFRI', name: 'Airtel Africa', price: 2100.00, change: 0.0, volume: 50, spark: [2100, 2100, 2100] },
    { symbol: 'SEPLAT', name: 'Seplat Energy', price: 3400.00, change: -0.2, volume: 120, spark: [3410, 3405, 3400] },
  ]
};

const stocks = ref([
  { symbol: "ZENITH", name: "Zenith Bank", price: 51.2, change: 1.5, volume: 1240000, spark: [49, 49.5, 50, 51, 51.2] },
  { symbol: "GTCO", name: "GT Holdings", price: 45.8, change: -0.8, volume: 870000, spark: [47, 46.8, 46, 45.9, 45.8] },
  { symbol: "MTNN", name: "MTN Nigeria", price: 235, change: 2.2, volume: 215000, spark: [228, 230, 232, 233, 235] },
  { symbol: "NB", name: "Nigerian Breweries", price: 72, change: 0.5, volume: 154000, spark: [70, 70.5, 71, 71.5, 72] },
]);

// Sparkline Options
const sparkOptions = {
  chart: { toolbar: { show: false }, sparkline: { enabled: true }, animations: { enabled: false } },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false },
};

// Computed
const currentMarketData = computed(() => insightData[activeTab.value]);

const tradeTickers = computed(() => ({
  NGX: stocks.value.map(s => ({ ...s, currency: 'NGN' }))
}));

const filteredStocks = computed(() =>
  stocks.value.filter(s =>
    s.name.toLowerCase().includes(search.value.toLowerCase()) ||
    s.symbol.toLowerCase().includes(search.value.toLowerCase())
  )
);

// Actions
const openDetails = (item) => { selectedItem.value = item; isModalOpen.value = true; };
const openTrade = (stock) => { 
  if (!isUserVerified.value && !isDemo.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  selectedTradeStock.value = { ...stock, currency: 'NGN' }; 
  showTradeModal.value = true; 
};

const fetchPortfolioPerformance = async (range = '1W') => {
  isGraphLoading.value = true;
  try {
    const params = { category: 'local', range: typeof range === 'object' ? 'CUSTOM' : range };
    if (params.range === 'CUSTOM') { params.start = range.start; params.end = range.end; }
    const response = await api.get(`/portfolio/history`, { params });
    portfolioData.value = response.data.series;
    totalValue.value = response.data.total;
    changePercent.value = response.data.change;
  } catch (e) {
    console.error("Failed to fetch history", e);
  } finally {
    isGraphLoading.value = false;
  }
};

const updateMarketPrices = async () => {
  let pricesUpdated = false;
  for (let stock of stocks.value) {
    try {
      const res = await api.get(`/dummy/ngx/market/${stock.symbol}`);
      const oldPrice = stock.price;
      stock.price = res.data.bid;
      stock.volume = res.data.volume;
      stock.change = (((res.data.bid - 150) / 150) * 100).toFixed(2);
      stock.spark.push(res.data.bid);
      if (stock.spark.length > 10) stock.spark.shift();
      if (oldPrice !== stock.price) pricesUpdated = true;
    } catch (e) {
      console.warn(`Could not fetch dummy data for ${stock.symbol}`);
    }
  }
  if (pricesUpdated) await fetchPortfolioPerformance();
};

// Lifecycle
let interval;
onMounted(() => {
  fetchPortfolioPerformance();
  updateMarketPrices();
  interval = setInterval(updateMarketPrices, 10000); // 10s is safer for dummy API
});

onUnmounted(() => clearInterval(interval));
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #1f3348;
  border-radius: 10px;
}
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>