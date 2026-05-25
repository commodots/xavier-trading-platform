<template>
  <MainLayout>
    <div class="space-y-6">
      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      
      <!-- Top Bar: Header and Search -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">📈 NGX</h1>
        <div class="relative">
          <input 
            v-model="search" 
            type="text" 
            placeholder="Search stocks..."
            class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none w-64 transition-all" 
          />
        </div>
      </div>

      <!-- Main Display Structural Grid Layout -->
      <div class="space-y-4 lg:col-span-2">
        <!-- View Toggle Segment Buttons -->
        <div class="flex items-center justify-between">
          <div class="flex p-1 bg-[#0B121D] border border-[#1f3348] rounded-lg">
            <button 
              @click="activeView = 'holdings'"
              class="px-4 py-2 text-xs font-bold uppercase transition-all rounded-md"
              :class="activeView === 'holdings' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-300'"
            >
              My Holdings
            </button>

            <button 
              @click="activeView = 'market'"
              class="px-4 py-2 text-xs font-bold uppercase transition-all rounded-md"
              :class="activeView === 'market' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-300'"
            >
              Market
            </button>
            <button 
              @click="activeView = 'history'"
              class="px-4 py-2 text-xs font-bold uppercase transition-all rounded-md"
              :class="activeView === 'history' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-300'"
            >
              History
            </button>
          </div>

          <button 
            @click="openTrade(null)"
            class="px-6 py-2 text-xs font-bold uppercase transition-all rounded-lg bg-blue-600 text-white shadow-lg hover:bg-blue-700"
          >
            Buy / Sell
          </button>
        </div>

        <!-- Dashboard Workspace Switchboards -->
        <div>
          <!-- Tab A: Local Portfolio Account Positions -->
          <div v-if="activeView === 'holdings'">
            <HoldingPerformanceChart 
              title="My NGX Holdings" 
              currencySymbol="₦" 
              :seriesData="portfolioData"
              :totalValue="totalValue" 
              :percentageChange="changePercent" 
              :loading="isGraphLoading || loading"
              @rangeChange="fetchPortfolioPerformance" 
            />

            <!-- Holdings Table Section -->
            <div v-if="loading" class="mt-6">
              <SkeletonLoader type="table" class="opacity-40" />
            </div>
            <div v-else class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-hidden mt-6 transition-all duration-300">
              <div class="p-4 border-b border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
                <h2 class="font-semibold text-gray-200">My Holdings</h2>
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
                    <tr v-for="stock in filteredStocks" :key="stock.symbol" class="hover:bg-[#16213A] transition">
                      <td class="px-6 py-4 font-bold text-[#00D4FF]">{{ stock.symbol }}</td>
                      <td class="text-gray-300">{{ stock.name }}</td>
                      <td class="font-mono font-semibold text-right text-white">₦{{ stock.price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</td>
                      <td class="text-right font-mono" :class="stock.change >= 0 ? 'text-green-400' : 'text-red-400'">
                        {{ stock.change >= 0 ? '+' : '' }}{{ stock.change }}%
                      </td>
                      <td class="text-right text-gray-400 font-mono">{{ stock.volume.toLocaleString() }}</td>
                      <td class="w-32 px-6 text-right">
                        <apexchart type="line" height="30" :options="sparkOptions" :series="[{ data: stock.spark || [] }]" />
                      </td>
                      <td class="px-2 text-center">
                        <button @click="openDetails(stock)" class="bg-[#1f3348] text-gray-300 px-3 py-1.5 rounded-md text-xs hover:bg-[#2a435e] transition">
                          Details
                        </button>
                      </td>
                      <td class="px-2 pr-6 text-center">
                        <button @click="openTrade(stock)" class="bg-[#00D4FF] text-[#0F1724] px-4 py-1.5 rounded-md font-bold text-xs hover:bg-[#00b8e6] transition">
                          Trade
                        </button>
                      </td>
                    </tr>
                    <tr v-if="filteredStocks.length === 0">
                      <td colspan="8" class="p-10 text-center text-gray-500 italic">No tickers matching your criteria are listed.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Tab B: Active Live Market Exchange Trends -->
          <div v-else-if="activeView === 'market'">
            <MarketInsights 
              :insightData="ngxMarketInsights" 
              :loading="isInsightsLoading || loading" 
              currencySymbol="₦" 
              @trade="openTrade"
            />
          </div>

          <div v-else-if="activeView === 'history'" class="py-20 text-center text-gray-500 italic border border-dashed border-[#1f3348] rounded-xl">
            Local market transaction history coming soon.
          </div>

        </div>
      </div>

      <!-- Modal Injection Ports -->
      <MarketDetailsModal :isOpen="isModalOpen" :item="selectedItem" currencySymbol="₦" @close="isModalOpen = false" />
      <TradeModal 
        :show="showTradeModal" 
        :tickers="tradeTickers" 
        :assetCategories="assetCategories" 
        :initialTicker="selectedTradeStock" 
        initialCategory="NGX" 
        @close="showTradeModal = false" 
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
import MarketInsights from "@/Components/Markets/MarketInsights.vue";
import SkeletonLoader from "@/Components/SkeletonLoader.vue"
import api from "@/api";

// Authentication & Identity States
const user = ref(JSON.parse(localStorage.getItem('user') || '{}'));
const isDemo = ref(user.value.trading_mode === 'demo');
const showPrompt = ref(false);

// Structural Layout Navigation Switchboards
const search = ref("");
const activeView = ref('holdings');
const isModalOpen = ref(false);
const selectedItem = ref(null);
const showTradeModal = ref(false);
const selectedTradeStock = ref(null);

// Quantitative Data Pipelines
const isGraphLoading = ref(false);
const isInsightsLoading = ref(false);
const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);
const currentMarketType = ref('ngx');
const assetCategories = [{ id: 'NGX', name: 'Local Stocks (NGX)', description: 'Nigerian Stock Exchange' }];

const ngxMarketInsights = ref({ gainers: [], losers: [], most_traded: [] });

const stocks = ref([
  { symbol: "ZENITH", name: "Zenith Bank", price: 51.2, change: 1.5, volume: 1240000, spark: [49, 49.5, 50, 51, 51.2] },
  { symbol: "GTCO", name: "GT Holdings", price: 45.8, change: -0.8, volume: 870000, spark: [47, 46.8, 46, 45.9, 45.8] },
  { symbol: "MTNN", name: "MTN Nigeria", price: 235, change: 2.2, volume: 215000, spark: [228, 230, 232, 233, 235] },
  { symbol: "NB", name: "Nigerian Breweries", price: 72, change: 0.5, volume: 154000, spark: [70, 70.5, 71, 71.5, 72] },
]);

const sparkOptions = {
  chart: { toolbar: { show: false }, sparkline: { enabled: true }, animations: { enabled: false } },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false },
};

// Computed Properties
const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  return role.includes('admin');
};

const isUserVerified = computed(() => {
  const u = user.value || {};
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

const tradeTickers = computed(() => ({
  NGX: stocks.value.map(s => ({ ...s, currency: 'NGN' }))
}));

const filteredStocks = computed(() => {
  const query = search.value.toLowerCase().trim();
  if (!query) return stocks.value;
  return stocks.value.filter(s =>
    s.name.toLowerCase().includes(query) ||
    s.symbol.toLowerCase().includes(query)
  );
});

// Structural Actions
const openDetails = (item) => { 
  selectedItem.value = item; 
  isModalOpen.value = true; 
};

const openTrade = (stock) => {
  if (!isUserVerified.value && !isDemo.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  selectedTradeStock.value = stock ? { ...stock, currency: 'NGN' } : null;
  showTradeModal.value = true;
};

// Async Communications Interfaces
const fetchMarketInsights = async (silent = false) => {
  if (!silent) isInsightsLoading.value = true;
  try {
    const response = await api.get(`/market/${currentMarketType.value}/insights`);
    if (response.data) {
      ngxMarketInsights.value = response.data;
    }
  } catch (error) {
    console.error('Insights fetch payload failure:', error);
  } finally {
    isInsightsLoading.value = false;
  }
};

const fetchPortfolioPerformance = async (range = '1W') => {
  isGraphLoading.value = true;
  try {
    const params = { category: 'local', range: typeof range === 'object' ? 'CUSTOM' : range };
    if (params.range === 'CUSTOM') { 
      params.start = range.start; 
      params.end = range.end; 
    }
    const response = await api.get(`/portfolio/history`, { params });
    portfolioData.value = response.data.series;
    totalValue.value = response.data.total;
    changePercent.value = response.data.change;
  } catch (e) {
    console.error("Failed to fetch account historical metrics stream:", e);
  } finally {
    isGraphLoading.value = false;
  }
};

const updateMarketPrices = async () => {
  let pricesUpdated = false;
  
  await Promise.all(stocks.value.map(async (stock) => {
    try {
      const res = await api.get(`/dummy/ngx/market/${stock.symbol}`);
      const oldPrice = stock.price;
      
      stock.price = Number(res.data.bid);
      stock.volume = Number(res.data.volume);
      stock.change = Number((((res.data.bid - 150) / 150) * 100).toFixed(2));
      
      stock.spark.push(res.data.bid);
      if (stock.spark.length > 10) stock.spark.shift();
      
      if (oldPrice !== stock.price) pricesUpdated = true;
    } catch (e) {
      console.warn(`Could not fetch dummy tick for asset ${stock.symbol}`);
    }
  }));

  // Trigger historical synchronization safely if price mutations occurred
  if (pricesUpdated && activeView.value === 'holdings') {
    fetchPortfolioPerformance();
  }
};

// Lifecycle Hooks 
let pollingInterval = null;

onMounted(() => {
  fetchPortfolioPerformance();
  updateMarketPrices();
  fetchMarketInsights();
  pollingInterval = setInterval(updateMarketPrices, 10000);
});

onUnmounted(() => {
  if (pollingInterval) clearInterval(pollingInterval);
});
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
