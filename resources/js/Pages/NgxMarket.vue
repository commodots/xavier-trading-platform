<template>
  <MainLayout>
    <div>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">📈 NGX Market</h1>
        <input v-model="search" type="text" placeholder="Search stocks..."
          class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-0 outline-none" />
      </div>

      <HoldingPerformanceChart title="NGX" currencySymbol="₦" :seriesData="portfolioData" :totalValue="totalValue"
        :percentageChange="changePercent" :loading="isGraphLoading" @rangeChange="fetchPortfolioPerformance" />

      <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-gray-400 border-b border-[#1f3348]">
            <tr>
              <th class="px-4 py-3 text-left">Symbol</th>
              <th class="text-left">Company</th>
              <th class="text-right">Price (₦)</th>
              <th class="text-right">24h Change</th>
              <th class="text-right">Volume</th>
              <th class="text-right">Trend</th>
              <th class="text-center" colspan="2">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="stock in filteredStocks" :key="stock.symbol"
              class="border-b border-[#1f3348] hover:bg-[#16213A] transition">
              <td class="px-4 py-3 font-medium">{{ stock.symbol }}</td>
              <td>{{ stock.name }}</td>
              <td class="text-right">{{ stock.price.toLocaleString() }}</td>
              <td class="text-right" :class="stock.change >= 0 ? 'text-green-400' : 'text-red-400'">
                {{ stock.change }}%
              </td>
              <td class="text-right">{{ stock.volume.toLocaleString() }}</td>
              <td class="w-32 text-right">
                <apexchart type="line" height="40" :options="sparkOptions" :series="[{ data: stock.spark }]" />
              </td>
              <td class="text-center px-1">
                <button @click="openDetails(stock)"
                  class="bg-[#00D4FF]/20 text-[#00D4FF] px-3 py-1 rounded-md hover:bg-[#00D4FF]/30 transition">
                  Details
                </button>
              </td>
              <td class="text-center px-1">
                <button @click="openTrade(stock)"
                  class="bg-[#00D4FF]/20 text-[#00D4FF] px-3 py-1 rounded-md hover:bg-[#00D4FF]/30 transition">
                  Trade
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <MarketDetailsModal :isOpen="isModalOpen" :item="selectedItem" :currencySymbol="'₦'"
        @close="isModalOpen = false" />

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
import VueApexCharts from "vue3-apexcharts";
import MarketDetailsModal from "@/Components/MarketDetailsModal.vue";
import HoldingPerformanceChart from "@/Components/HoldingPerformanceChart.vue";
import TradeModal from "@/Components/TradeModal.vue";
import api from "@/api";

const isModalOpen = ref(false);
const selectedItem = ref(null);

const showTradeModal = ref(false);
const selectedTradeStock = ref(null);
const assetCategories = [
  { id: 'NGX', name: 'Local Stocks (NGX)', description: 'Nigerian Stock Exchange' }
];

const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);
const isGraphLoading = ref(false);

const fetchPortfolioPerformance = async (range = '1W') => {
  isGraphLoading.value = true;
  try {
    const response = await api.get(`/portfolio/history`, {
      params: { category: 'local', range: range }
    });
    portfolioData.value = response.data.series;
    totalValue.value = response.data.total;
    changePercent.value = response.data.change;
  } catch (e) {
    console.error("Failed to fetch history", e);
  } finally {
    isGraphLoading.value = false;
  }
};

onMounted(() => fetchPortfolioPerformance());

const openDetails = (item) => {
  selectedItem.value = item;
  isModalOpen.value = true;
};

const openTrade = (stock) => {
  selectedTradeStock.value = { ...stock, currency: 'NGN' };
  showTradeModal.value = true;
};

const search = ref("");
const stocks = ref([
  { symbol: "ZENITH", name: "Zenith Bank", price: 51.2, change: 1.5, volume: 1240000, spark: [49, 49.5, 50, 51, 51.2] },
  { symbol: "GTCO", name: "GT Holdings", price: 45.8, change: -0.8, volume: 870000, spark: [47, 46.8, 46, 45.9, 45.8] },
  { symbol: "MTNN", name: "MTN Nigeria", price: 235, change: 2.2, volume: 215000, spark: [228, 230, 232, 233, 235] },
  { symbol: "NB", name: "Nigerian Breweries", price: 72, change: 0.5, volume: 154000, spark: [70, 70.5, 71, 71.5, 72] },
]);

const tradeTickers = computed(() => ({
  NGX: stocks.value.map(s => ({ ...s, currency: 'NGN' }))
}));

const filteredStocks = computed(() =>
  stocks.value.filter(s =>
    s.name.toLowerCase().includes(search.value.toLowerCase()) ||
    s.symbol.toLowerCase().includes(search.value.toLowerCase())
  )
);

const sparkOptions = {
  chart: { toolbar: { show: false }, sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false },
  grid: { show: false },
};
</script>

<script>
export default { components: { apexchart: VueApexCharts } };
</script>