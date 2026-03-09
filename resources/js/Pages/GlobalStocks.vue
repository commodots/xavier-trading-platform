<template>
  <MainLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">🌍 Global Stocks</h1>
        <div class="relative">
          <input v-model="search" type="text" placeholder="Search global stocks..."
            class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none w-64 transition-all" />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
          <HoldingPerformanceChart 
            title="Your Global Stocks Holdings" 
            currencySymbol="$" 
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
                    <div class="font-semibold text-white">${{ item.price.toLocaleString() }}</div>
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
          <h2 class="font-semibold text-gray-200">World Equity Markets</h2>
          <span class="text-xs text-gray-500">{{ filteredStocks.length }} Assets available</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 border-b border-[#1f3348] bg-[#0B121D]">
              <tr>
                <th class="px-6 py-4 font-medium text-left">Symbol</th>
                <th class="font-medium text-left">Company</th>
                <th class="font-medium text-right">Price ($)</th>
                <th class="font-medium text-right">24h Change</th>
                <th class="font-medium text-right">Volume</th>
                <th class="px-6 font-medium text-right">Trend</th>
                <th class="font-medium text-center" colspan="2">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#1f3348]">
              <tr v-for="stock in filteredStocks" :key="stock.symbol" class="hover:bg-[#16213A] transition">
                <td class="px-6 py-4 font-bold text-[#00D4FF]">{{ stock.symbol }}</td>
                <td class="text-gray-300">{{ stock.name }}</td>
                <td class="font-mono font-semibold text-right text-white">${{ stock.price.toFixed(2) }}</td>
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
            No global stocks match your search "{{ search }}"
          </div>
        </div>
      </div>

      <MarketDetailsModal :isOpen="isModalOpen" :item="selectedItem" currency-symbol="$" @close="isModalOpen = false" />
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
import { ref, computed, onMounted } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import apexchart from "vue3-apexcharts"; 
import MarketDetailsModal from "@/Components/MarketDetailsModal.vue";
import HoldingPerformanceChart from "@/Components/HoldingPerformanceChart.vue";
import TradeModal from "@/Components/TradeModal.vue";
import api from "@/api";

// State
const isModalOpen = ref(false);
const selectedItem = ref(null);
const showTradeModal = ref(false);
const selectedTradeStock = ref(null);
const search = ref("");
const activeTab = ref('gainers');
const isGraphLoading = ref(false);

const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);
const assetCategories = [{ id: 'GLOBAL', name: 'Global Stocks (USD)', description: 'US Markets' }];

const marketTabs = [
  { id: 'gainers', name: 'Gainers' },
  { id: 'losers', name: 'Losers' },
  { id: 'most_traded', name: 'Most Traded' }
];

const insightData = {
  gainers: [
    { symbol: 'NVDA', name: 'Nvidia Corp', price: 875.2, change: 4.5, volume: 45000000, spark: [820, 840, 875] },
    { symbol: 'META', name: 'Meta Platforms', price: 490.1, change: 2.1, volume: 18000000, spark: [470, 480, 490] },
  ],
  losers: [
    { symbol: 'TSLA', name: 'Tesla Inc', price: 244.2, change: -0.8, volume: 78000000, spark: [255, 250, 244] },
    { symbol: 'NFLX', name: 'Netflix Inc', price: 610.5, change: -1.2, volume: 5000000, spark: [630, 620, 610] },
  ],
  most_traded: [
    { symbol: 'AAPL', name: 'Apple Inc', price: 175.6, change: 1.2, volume: 98000000, spark: [170, 172, 175] },
    { symbol: 'MSFT', name: 'Microsoft Corp', price: 420.5, change: 2.1, volume: 68000000, spark: [410, 415, 420] },
  ]
};

const stocks = ref([
  { symbol: "AAPL", name: "Apple Inc", price: 175.6, change: 1.2, volume: 98200000, spark: [170, 171, 173, 175, 175.6] },
  { symbol: "TSLA", name: "Tesla Inc", price: 244.2, change: -0.8, volume: 78400000, spark: [250, 248, 247, 245, 244] },
  { symbol: "MSFT", name: "Microsoft Corp", price: 420.5, change: 2.1, volume: 68200000, spark: [412, 414, 417, 419, 420.5] },
  { symbol: "GOOG", name: "Alphabet Inc", price: 160.3, change: 0.5, volume: 57200000, spark: [158, 158.5, 159, 160, 160.3] },
]);

const sparkOptions = {
  chart: { toolbar: { show: false }, sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false },
};

// Computed
const currentMarketData = computed(() => insightData[activeTab.value]);
const tradeTickers = computed(() => ({ GLOBAL: stocks.value.map(s => ({ ...s, currency: 'USD' })) }));
const filteredStocks = computed(() =>
  stocks.value.filter(s =>
    s.name.toLowerCase().includes(search.value.toLowerCase()) ||
    s.symbol.toLowerCase().includes(search.value.toLowerCase())
  )
);

// Methods
const openDetails = (item) => { selectedItem.value = item; isModalOpen.value = true; };
const openTrade = (stock) => { selectedTradeStock.value = { ...stock, currency: 'USD' }; showTradeModal.value = true; };

const fetchPortfolioPerformance = async (range = '1W') => {
  isGraphLoading.value = true;
  try {
    const response = await api.get(`/portfolio/history`, { params: { category: 'foreign', range } });
    portfolioData.value = response.data.series;
    totalValue.value = response.data.total;
    changePercent.value = response.data.change;
  } catch (e) {
    console.error('Failed to fetch history', e);
  } finally {
    isGraphLoading.value = false;
  }
};

onMounted(() => fetchPortfolioPerformance());
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #1f3348; border-radius: 10px; }
.no-scrollbar::-webkit-scrollbar { display: none; }
</style>