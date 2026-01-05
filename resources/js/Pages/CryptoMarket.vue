<template>
  <MainLayout>
    <div>
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold">₿ Crypto Market</h1>
        <input v-model="search" type="text" placeholder="Search crypto..."
          class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-0 outline-none" />
      </div>

      <HoldingPerformanceChart title="Crypto" currencySymbol="₦" :seriesData="portfolioData" :totalValue="totalValue"
        :percentageChange="changePercent" :loading="isGraphLoading" @rangeChange="fetchPortfolioPerformance" />

      <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-gray-400 border-b border-[#1f3348]">
            <tr>
              <th class="py-3 px-4 text-left">Symbol</th>
              <th class="text-left">Name</th>
              <th class="text-right">Price (₦)</th>
              <th class="text-right">24h Change</th>
              <th class="text-right">Market Cap</th>
              <th class="text-right">Trend</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="coin in filteredCoins" :key="coin.symbol"
              class="border-b border-[#1f3348] hover:bg-[#16213A] transition">
              <td class="py-3 px-4 font-medium">{{ coin.symbol }}</td>
              <td>{{ coin.name }}</td>
              <td class="text-right">{{ coin.price.toLocaleString() }}</td>
              <td :class="coin.change >= 0 ? 'text-green-400 text-right' : 'text-red-400 text-right'">
                {{ coin.change }}%
              </td>
              <td class="text-right">{{ coin.marketcap.toLocaleString() }}</td>
              <td class="text-right w-32">
                <apexchart type="line" height="40" :options="sparkOptions" :series="[{ data: coin.spark }]" />
              </td>
              <td class="text-center">
                <button 
                @click="openDetails(coin)"
                  class="bg-[#00D4FF]/20 text-[#00D4FF] px-3 py-1 rounded-md hover:bg-[#00D4FF]/30 transition">
                  Details
                </button>
              </td>
              <td class="text-center">
                <button class="bg-[#00D4FF]/20 text-[#00D4FF] px-3 py-1 rounded-md hover:bg-[#00D4FF]/30 transition">
                  Trade
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <MarketDetailsModal 
      :isOpen="isModalOpen" 
      :item="selectedItem" 
      :currencySymbol="'₦'"
        @close="isModalOpen = false" />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import VueApexCharts from "vue3-apexcharts";
import MarketDetailsModal from "@/Components/MarketDetailsModal.vue";
import HoldingPerformanceChart from "@/Components/HoldingPerformanceChart.vue";
import api from "@/api";

const isModalOpen = ref(false);
const selectedItem = ref(null);

const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);
const isGraphLoading = ref(false);

const fetchPortfolioPerformance = async (range = '1W') => {
  isGraphLoading.value = true;
  try {
    const response = await api.get(`/portfolio/history`, {
      params: { category: 'crypto', range }
    });

    portfolioData.value = response.data.series;
    totalValue.value = response.data.total;
    changePercent.value = response.data.change;
  } catch (e) {
    console.error('Failed to fetch crypto history', e);
  } finally {
    isGraphLoading.value = false;
  }
};

onMounted(() => fetchPortfolioPerformance());

const openDetails = (item) => {
  selectedItem.value = item;
  isModalOpen.value = true;
};

const search = ref("");
const coins = ref([
  { symbol: "BTC", name: "Bitcoin", price: 24761904, change: 2.4, marketcap: 900000000000, spark: [24000000, 24300000, 24500000, 24650000, 24761904] },
  { symbol: "ETH", name: "Ethereum", price: 1550000, change: -1.2, marketcap: 380000000000, spark: [1580000, 1570000, 1560000, 1555000, 1550000] },
  { symbol: "BNB", name: "Binance Coin", price: 435000, change: 0.8, marketcap: 67000000000, spark: [430000, 432000, 433000, 434500, 435000] },
  { symbol: "SOL", name: "Solana", price: 155000, change: 3.1, marketcap: 68000000000, spark: [150000, 152000, 153000, 154000, 155000] },
]);

const filteredCoins = computed(() =>
  coins.value.filter(c =>
    c.name.toLowerCase().includes(search.value.toLowerCase()) ||
    c.symbol.toLowerCase().includes(search.value.toLowerCase())
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
