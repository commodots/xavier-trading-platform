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

      <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:gap-8">
        <div>
          <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold mb-0.5">NGN Balance</p>
          <p class="font-mono text-base font-bold text-white">
             ₦{{ walletBalances?.cleared_balance_ngn ? walletBalances.cleared_balance_ngn.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00' }}
          </p>
        </div>
        <div class="hidden sm:block h-8 w-px bg-[#1f3348]"></div>
        <div>
          <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold mb-0.5">NGX Assets</p>
          <p class="text-base font-mono font-bold text-[#00D4FF]">
            ₦{{ totalValue ? totalValue.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00' }}
          </p>
        </div>
      </div>

      <div v-if="marketDataError" class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-300">
        {{ marketDataError }}
      </div>
      <div v-if="marketInsightsError" class="rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-300">
        {{ marketInsightsError }}
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
            Holdings
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
            @click="handleBuySellClick"
            :disabled="!canTrade"
            class="px-6 py-2 text-xs font-bold text-white uppercase transition-all bg-blue-600 rounded-lg shadow-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-blue-600"
          >
            {{ !canTrade ? 'Verification Required' : 'Buy / Sell' }}
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
                <span class="text-xs text-gray-500">{{ filteredStocks.length }} Assets Available</span>
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
                      <td class="font-mono text-right" :class="stock.change >= 0 ? 'text-green-400' : 'text-red-400'">
                        {{ stock.change >= 0 ? '+' : '' }}{{ stock.change }}%
                      </td>
                      <td class="font-mono text-right text-gray-400">{{ stock.volume.toLocaleString() }}</td>
                      <td class="w-32 px-6 text-right">
                        <apexchart v-if="stock.spark && stock.spark.length > 0" type="line" height="30" :options="sparkOptions" :series="[{ data: stock.spark }]" />
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
                      <td colspan="8" class="p-10 italic text-center text-gray-500">No tickers matching your criteria are listed.</td>
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
              @select="openDetails"
              @trade="openTrade"
            />
          </div>

          <div v-else-if="activeView === 'history'">
            <div v-if="historyLoading" class="mt-6">
              <SkeletonLoader type="table" class="opacity-40" />
            </div>
            <div v-else class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-hidden">
              <div class="p-4 border-b border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
                <span class="text-sm font-semibold text-gray-300">Transaction History</span>
                <span class="text-xs text-gray-500">{{ historyRecords.length }} Transactions</span>
              </div>
              <div v-if="historyRecords.length === 0" class="p-10 text-center text-gray-500 italic">
                No transaction history available yet.
              </div>
              <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead class="text-gray-400 border-b border-[#1f3348] bg-[#0B121D]">
                    <tr>
                      <th class="px-6 py-4 font-medium text-left">Date</th>
                      <th class="px-6 font-medium text-left">Type</th>
                      <th class="px-6 font-medium text-left">Asset</th>
                      <th class="px-6 font-medium text-right">Quantity</th>
                      <th class="px-6 font-medium text-right">Amount</th>
                      <th class="px-6 font-medium text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-[#1f3348]">
                    <tr v-for="record in historyRecords" :key="record.id" class="hover:bg-[#16213A] transition">
                      <td class="px-6 py-4 text-gray-400">{{ formatDate(record.date) }}</td>
                      <td class="px-6 font-semibold" :class="record.type === 'BUY' ? 'text-green-400' : 'text-red-400'">
                        {{ record.type }}
                      </td>
                      <td class="px-6 text-[#00D4FF] font-medium">{{ record.symbol }}</td>
                      <td class="px-6 text-right font-mono text-gray-300">{{ record.quantity.toFixed(2) }}</td>
                      <td class="px-6 text-right font-mono font-semibold text-white">₦{{ formatCurrency(record.amount) }}</td>
                      <td class="px-6 text-center">
                        <span v-if="record.status === 'completed'" class="bg-green-500/20 text-green-400 px-3 py-1 rounded-full text-xs font-bold">
                          Completed
                        </span>
                        <span v-else-if="record.status === 'pending'" class="bg-yellow-500/20 text-yellow-400 px-3 py-1 rounded-full text-xs font-bold">
                          Pending
                        </span>
                        <span v-else class="bg-red-500/20 text-red-400 px-3 py-1 rounded-full text-xs font-bold">
                          {{ record.status }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
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
const loading = ref(true);
const isGraphLoading = ref(false);
const isInsightsLoading = ref(false);
const marketDataError = ref("");
const marketInsightsError = ref("");
const walletBalances = ref({ cleared_balance_ngn: 0 });
const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);
const assetCategories = [{ id: 'NGX', name: 'Local Stocks (NGX)', description: 'Nigerian Stock Exchange' }];

const ngxMarketInsights = ref({ gainers: [], losers: [], most_traded: [] });
const stocks = ref([]);

const sparkOptions = {
  chart: { toolbar: { show: false }, sparkline: { enabled: true }, animations: { enabled: false } },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false },
};
const historyRecords = ref([]);
const historyLoading = ref(false);

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

const canTrade = computed(() => {
  if (isDemo.value) return true;
  const level = user.value.verification_level || 0;
  return level >= 2;
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

const handleBuySellClick = () => {
  if (!canTrade.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  openTrade(null);
};

const openTrade = (stock) => {
  if (!canTrade.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  
  // Also check email verification  
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
    const response = await api.get('/market/ngx/insights');
    const insights = response.data?.data;

    if (!insights || !Array.isArray(insights.gainers) || !Array.isArray(insights.losers)) {
      throw new Error('CSL returned an invalid NGX market insights response.');
    }
    ngxMarketInsights.value = insights;
    marketInsightsError.value = "";
  } catch (error) {
    console.error('Insights fetch payload failure:', error);
    marketInsightsError.value = error.response?.data?.message || error.message || 'Unable to load NGX market insights from CSL.';
  } finally {
    isInsightsLoading.value = false;
  }
};

const fetchWalletBalances = async () => {
  try {
    const response = await api.get('/wallet/balances');
    walletBalances.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch wallet balances', error);
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

const updateMarketPrices = async (silent = false) => {
  try {
    const response = await api.get('/market/ngx/prices');
    const prices = response.data?.data;

    if (!Array.isArray(prices)) {
      throw new Error('CSL returned an invalid NGX market prices response.');
    }

    const previousStocks = new Map(stocks.value.map(stock => [stock.symbol, stock]));
    stocks.value = prices.map((quote) => {
      const symbol = String(quote.symbol || '').toUpperCase();
      const previous = previousStocks.get(symbol);
      const price = Number(quote.price || 0);
      const spark = previous?.spark ? [...previous.spark] : [];

      if (price > 0 && spark[spark.length - 1] !== price) {
        spark.push(price);
      }

      return {
        ...quote,
        symbol,
        name: quote.name || symbol,
        price,
        change: Number(quote.change_percent ?? quote.change ?? 0),
        volume: Number(quote.volume || 0),
        spark: spark.slice(-10),
        market: 'NGX',
        currency: 'NGN',
      };
    }).filter(stock => stock.symbol && Number.isFinite(stock.price) && stock.price > 0);
    marketDataError.value = "";
  } catch (error) {
    marketDataError.value = error.response?.data?.message || error.message || 'Unable to load NGX market prices from CSL.';
    console.error('CSL NGX market prices fetch failed:', error);
  } finally {
    if (!silent) loading.value = false;
  }
};

// Lifecycle Hooks 
let pollingInterval = null;

onMounted(() => {
  Promise.allSettled([
    fetchPortfolioPerformance(),
    updateMarketPrices(),
    fetchWalletBalances(),
    fetchMarketInsights()
  ]);
  pollingInterval = setInterval(() => {
    updateMarketPrices(true);
    fetchMarketInsights(true);
  }, 30000);
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