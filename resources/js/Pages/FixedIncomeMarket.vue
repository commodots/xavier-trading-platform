<template>
  <MainLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">📈 Fixed Income Market</h1>
        <div class="relative">
          <input v-model="search" type="text" placeholder="Search instruments..."
            class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none w-64 transition-all" />
        </div>
      </div>

      <HoldingPerformanceChart 
        title="Your Fixed Income Holdings" 
        currencySymbol="₦" 
        :seriesData="portfolioData" 
        :totalValue="totalValue"
        :percentageChange="changePercent" 
        :loading="isGraphLoading" 
        @rangeChange="fetchPortfolioPerformance" 
      />

      <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-hidden">
        <div class="p-4 border-b border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
          <h2 class="font-semibold text-gray-200">Available Instruments</h2>
          <span class="text-xs text-gray-500">{{ filteredInstruments.length }} Listings</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 border-b border-[#1f3348] bg-[#0B121D]">
              <tr>
                <th class="px-6 py-4 font-medium text-left">Instrument</th>
                <th class="font-medium text-left">Issuer</th>
                <th class="font-medium text-right">Yield (%)</th>
                <th class="font-medium text-right">24h Change</th>
                <th class="font-medium text-right">Volume</th>
                <th class="px-6 font-medium text-right">Trend</th>
                <th class="font-medium text-center" colspan="2">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#1f3348]">
              <tr v-for="instrument in filteredInstruments" :key="instrument.symbol" class="hover:bg-[#16213A] transition">
                <td class="px-6 py-4 font-bold text-[#00D4FF]">{{ instrument.symbol }}</td>
                <td class="text-gray-300">{{ instrument.name }}</td>
                <td class="font-mono font-semibold text-right text-white">{{ instrument.yield.toFixed(2) }}%</td>
                <td class="text-right" :class="instrument.change >= 0 ? 'text-green-400' : 'text-red-400'">
                  {{ instrument.change >= 0 ? '+' : '' }}{{ instrument.change }}%
                </td>
                <td class="text-right text-gray-400">{{ instrument.volume.toLocaleString() }}</td>
                <td class="w-32 px-6 text-right">
                  <apexchart type="line" height="30" :options="sparkOptions" :series="[{ data: instrument.spark }]" />
                </td>
                <td class="px-2 text-center">
                  <button @click="openDetails(instrument)" class="bg-[#1f3348] text-gray-300 px-3 py-1.5 rounded-md hover:bg-[#2d4a66] transition text-xs">Details</button>
                </td>
                <td class="px-2 pr-6 text-center">
                  <button @click="openTrade(instrument)" class="bg-[#00D4FF] text-[#0F1724] px-4 py-1.5 rounded-md font-bold hover:bg-[#00b8e6] transition text-xs">Trade</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <MarketDetailsModal :isOpen="isModalOpen" :item="selectedItem" currencySymbol="₦" @close="isModalOpen = false" />
      <TradeModal :show="showTradeModal" :tickers="tradeTickers" :assetCategories="assetCategories" :initialTicker="selectedTradeInstrument" @close="showTradeModal = false" @trade-success="fetchPortfolioPerformance" />
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
import api from "@/api";

const isModalOpen = ref(false);
const selectedItem = ref(null);
const showTradeModal = ref(false);
const selectedTradeInstrument = ref(null);
const search = ref("");
const isGraphLoading = ref(false);
const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);

const assetCategories = [{ id: 'FixedIncome', name: 'Fixed Income', description: 'Bonds, Treasury Bills, Funds, Commercial Papers' }];

const instruments = ref([
  { symbol: "FG132026S1", name: "FGN Bond Jan 2026", yield: 12.5, change: 0.2, volume: 50000, price: 1000, spark: [12.3, 12.4, 12.5, 12.5, 12.5] },
  { symbol: "ABB2026S0", name: "Access Bank July 2026", yield: 10.8, change: -0.1, volume: 200000, price: 1000, spark: [11.0, 10.9, 10.8, 10.8, 10.8] },
  { symbol: "FGNSB_2027", name: "FGN Savings Bond 2027", yield: 8.5, change: 0.3, volume: 150000, price: 1000, spark: [8.2, 8.3, 8.4, 8.5, 8.5] },
  { symbol: "CP_MTN_I", name: "MTN Commercial Paper", yield: 9.2, change: 0.1, volume: 75000, price: 1000, spark: [9.1, 9.1, 9.2, 9.2, 9.2] },
]);

const sparkOptions = {
  chart: { toolbar: { show: false }, sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false }
};

const tradeTickers = computed(() => ({
  FIXED_INCOME: instruments.value.map(i => ({ ...i, currency: 'NGN' }))
}));

const filteredInstruments = computed(() => 
  instruments.value.filter(i => i.name.toLowerCase().includes(search.value.toLowerCase()) || i.symbol.toLowerCase().includes(search.value.toLowerCase()))
);

const fetchPortfolioPerformance = async (range = '1W') => {
  isGraphLoading.value = true;
  try {
    const params = { category: 'fixed_income', range: typeof range === 'object' ? 'CUSTOM' : range };
    if (params.range === 'CUSTOM') { params.start = range.start; params.end = range.end; }
    const response = await api.get(`/portfolio/history`, { params });
    portfolioData.value = response.data.series;
    totalValue.value = response.data.total;
    changePercent.value = response.data.change;
  } catch (e) { console.error(e); } finally { isGraphLoading.value = false; }
};

const updateMarketPrices = () => {
  instruments.value.forEach(inst => {
    const change = (Math.random() - 0.5) * 0.1;
    inst.yield += change;
    inst.change = change.toFixed(2);
    inst.spark.push(inst.yield);
    if (inst.spark.length > 10) inst.spark.shift();
  });
};

const openDetails = (item) => { selectedItem.value = item; isModalOpen.value = true; };
const openTrade = (instrument) => { selectedTradeInstrument.value = { ...instrument, currency: 'NGN' }; showTradeModal.value = true; };

let interval;
onMounted(() => {
  fetchPortfolioPerformance();
  interval = setInterval(updateMarketPrices, 5000);
});
onUnmounted(() => clearInterval(interval));
</script>