<template>
  <MainLayout>
    <div>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">📈 Fixed Income Market</h1>
        <input v-model="search" type="text" placeholder="Search instruments..."
          class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-0 outline-none" />
      </div>

      <HoldingPerformanceChart title="Fixed Income" currencySymbol="₦" :seriesData="portfolioData" :totalValue="totalValue"
        :percentageChange="changePercent" :loading="isGraphLoading" @rangeChange="fetchPortfolioPerformance" />

      <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-gray-400 border-b border-[#1f3348]">
            <tr>
              <th class="px-4 py-3 text-left">Instrument</th>
              <th class="text-left">Issuer</th>
              <th class="text-right">Yield (%)</th>
              <th class="text-right">24h Change</th>
              <th class="text-right">Volume</th>
              <th class="text-right">Trend</th>
              <th class="text-center" colspan="2">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="instrument in filteredInstruments" :key="instrument.symbol"
              class="border-b border-[#1f3348] hover:bg-[#16213A] transition">
              <td class="px-4 py-3 font-medium">{{ instrument.symbol }}</td>
              <td>{{ instrument.name }}</td>
              <td class="text-right">{{ instrument.yield.toFixed(2) }}%</td>
              <td class="text-right" :class="instrument.change >= 0 ? 'text-green-400' : 'text-red-400'">
                {{ instrument.change }}%
              </td>
              <td class="text-right">{{ instrument.volume.toLocaleString() }}</td>
              <td class="w-32 text-right">
                <apexchart type="line" height="40" :options="sparkOptions" :series="[{ data: instrument.spark }]" />
              </td>
              <td class="px-1 text-center">
                <button @click="openDetails(instrument)"
                  class="bg-[#00D4FF]/20 text-[#00D4FF] px-3 py-1 rounded-md hover:bg-[#00D4FF]/30 transition">
                  Details
                </button>
              </td>
              <td class="px-1 text-center">
                <button @click="openTrade(instrument)"
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
        :initialTicker="selectedTradeInstrument"
        @close="showTradeModal = false"
        @trade-success="fetchPortfolioPerformance"
      />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import VueApexCharts from "vue3-apexcharts";
import MarketDetailsModal from "@/Components/MarketDetailsModal.vue";
import HoldingPerformanceChart from "@/Components/HoldingPerformanceChart.vue";
import TradeModal from "@/Components/TradeModal.vue";
import api from "@/api";

const isModalOpen = ref(false);
const selectedItem = ref(null);

const showTradeModal = ref(false);
const selectedTradeInstrument = ref(null);
const assetCategories = [
  { id: 'FixedIncome', name: 'Fixed Income', description: 'Bonds, Treasury Bills, Funds, Commercial Papers' }
];

const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);
const isGraphLoading = ref(false);

const fetchPortfolioPerformance = async (range = '1W') => {
  isGraphLoading.value = true;
  try {
    const params = { category: 'fixed_income' };

    if (typeof range === 'object' && range.start && range.end) {
      
      params.start = range.start;
      params.end = range.end;
      params.range = 'CUSTOM';
    } else {
      
      params.range = range;
    }

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

onMounted(() => {
  fetchPortfolioPerformance();
  updateMarketPrices();
});

const openDetails = (item) => {
  selectedItem.value = item;
  isModalOpen.value = true;
};

const openTrade = (instrument) => {
  selectedTradeInstrument.value = { ...instrument, currency: 'NGN' };
  showTradeModal.value = true;
};

const search = ref("");
const instruments = ref([
  { symbol: "FG132026S1", name: "FGN Bond Jan 2026", yield: 12.5, change: 0.2, volume: 50000, price: 1000, spark: [12.3, 12.4, 12.5, 12.5, 12.5] },
  { symbol: "ABB2026S0", name: "Access Bank July 2026", yield: 10.8, change: -0.1, volume: 200000, price: 1000, spark: [11.0, 10.9, 10.8, 10.8, 10.8] },
  { symbol: "FGNSB_2027", name: "FGN Savings Bond 2027", yield: 8.5, change: 0.3, volume: 150000, price: 1000, spark: [8.2, 8.3, 8.4, 8.5, 8.5] },
  { symbol: "CP_MTN_I", name: "MTN Commercial Paper", yield: 9.2, change: 0.1, volume: 75000, price: 1000, spark: [9.1, 9.1, 9.2, 9.2, 9.2] },
]);


const updateMarketPrices = async () => {
  let pricesUpdated = false;
  for (let instrument of instruments.value) {
    try {
      // Mock update, perhaps call API if available
      // For now, simulate change
      const change = (Math.random() - 0.5) * 0.5;
      instrument.yield += change;
      instrument.change = change.toFixed(2);
      instrument.spark.push(instrument.yield);
      if (instrument.spark.length > 10) instrument.spark.shift();
      pricesUpdated = true;
    } catch (e) {
      console.warn(`Could not update ${instrument.symbol}`);
    }
  }

  if (pricesUpdated) {
    await fetchPortfolioPerformance();
  }
};

const tradeTickers = computed(() => ({
  FIXED_INCOME: instruments.value.map(i => ({ ...i, currency: 'NGN' }))
}));

const filteredInstruments = computed(() =>
  instruments.value.filter(i =>
    i.name.toLowerCase().includes(search.value.toLowerCase()) ||
    i.symbol.toLowerCase().includes(search.value.toLowerCase())
  )
);

const sparkOptions = {
  chart: { toolbar: { show: false }, sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false },
  grid: { show: false },
};

const interval = setInterval(updateMarketPrices, 5000);

onUnmounted(() => clearInterval(interval));

</script>

<script>
export default { components: { apexchart: VueApexCharts } };
</script>