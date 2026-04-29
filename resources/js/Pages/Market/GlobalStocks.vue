<template>
  <MainLayout>
    <div class="space-y-6">
      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">🌍 Global Stocks</h1>
        <div class="flex gap-3">
            <div class="relative">
              <input ref="searchInput" v-model="search" @input="handleSearchInput" @keydown.enter="selectFirstSuggestion" type="text" placeholder="Search global stocks..."
                class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none w-64 transition-all" />
              <span v-if="searchLoading" class="absolute text-xs text-gray-400 -translate-y-1/2 right-3 top-1/2">Searching…</span>
              
              <!-- Autocomplete Dropdown -->
              <div v-if="searchSuggestions.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-[#0F1724] border border-[#1f3348] rounded-lg shadow-2xl z-50 max-h-48 overflow-y-auto">
                <div v-for="suggestion in searchSuggestions" :key="suggestion.symbol" @click="selectSuggestion(suggestion)" 
                  class="p-3 hover:bg-[#16213A] cursor-pointer border-b border-[#1f3348]/50 last:border-0">
                  <div class="font-semibold text-[#00D4FF]">{{ suggestion.symbol }}</div>
                  <div class="text-xs text-gray-400">{{ suggestion.name }}</div>
                </div>
              </div>
            </div>
        </div>
      </div>

      <!-- Portfolio Summary Bar -->
      <div class="flex items-center gap-4 mb-6 ">
        <div>
          <p class="text-[12px] uppercase tracking-widest text-white font-bold mb-1">USD Wallet Balance: <span>${{ walletBalances.cleared_balance_usd.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span></p>
        </div>
        <div>
          <p class="text-[12px] uppercase tracking-widest text-white font-bold mb-1">Global Stocks Value: <span>${{ totalValue.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</span></p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
          <!-- Chart Toggle -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
            <div class="flex p-1 bg-[#0B121D] border border-[#1f3348] rounded-lg">
              <button 
                @click="activeChart = 'holdings'"
                class="px-4 py-2 text-xs font-bold uppercase transition-all rounded-md"
                :class="activeChart === 'holdings' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-300'"
              >
                My Holdings
              </button>
              <button 
                @click="activeChart = 'market'"
                class="px-4 py-2 text-xs font-bold uppercase transition-all rounded-md"
                :class="activeChart === 'market' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-300'"
              >
                Live Chart
              </button>
            </div>
            </div>
            
            <div v-if="activeChart === 'market'" class="flex flex-wrap items-center gap-2">
              <span class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Market:</span>
              <span class="px-2 py-0.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded font-mono text-xs uppercase">{{ selectedMarketSymbol }}</span>
              <div class="relative">
                <input v-model="chartSearch" @input="handleChartSearch" type="text" placeholder="Quick find symbol..." 
                  class="bg-[#0B121D] border border-[#1f3348] rounded px-3 py-1 text-xs text-gray-300 focus:border-[#00D4FF] outline-none w-40" />
                <span v-if="chartSearchLoading" class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-500 italic">Searching...</span>
                <div v-if="chartSearchResults.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-[#0F1724] border border-[#1f3348] rounded-lg shadow-2xl z-50 max-h-48 overflow-y-auto">
                  <div v-for="res in chartSearchResults" :key="res.symbol" @click="selectForChart(res)" class="p-2 hover:bg-[#16213A] cursor-pointer border-b border-[#1f3348]/50 last:border-0 text-xs">
                    <span class="font-bold text-[#00D4FF]">{{ res.symbol }}</span> - {{ res.name }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <HoldingPerformanceChart 
            v-if="activeChart === 'holdings'"
            title="Your Global Stocks Holdings" 
            currencySymbol="$" 
            :seriesData="portfolioData" 
            :totalValue="totalValue"
            :percentageChange="changePercent" 
            :loading="isGraphLoading" 
            @rangeChange="fetchPortfolioPerformance" 
          />
          <div v-else class="space-y-4">
            <div class="flex items-center gap-3">
              <span class="text-[10px] text-gray-500 uppercase font-black tracking-widest whitespace-nowrap bg-[#16213A] px-2 py-1 rounded border border-[#1f3348]">Your favorite tickers</span>
              <MarketTicker class="flex-1" :selected-symbol="selectedMarketSymbol" :additional-tickers="searchedTickers" @select-symbol="selectedMarketSymbol = $event" />
            </div>
            <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 overflow-hidden min-h-[400px]">
              <MarketChart :symbol="selectedMarketSymbol" />
            </div>
          </div>
        </div>

        <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] flex flex-col h-full min-h-[540px]">
          <div class="p-4 border-b border-[#1f3348] flex items-center justify-between gap-2">
            <h3 class="text-sm font-bold tracking-wider text-gray-400 uppercase">Market Insights</h3>
          </div>
          
          <div class="flex border-b border-[#1f3348] text-[10px] font-bold uppercase bg-[#0B121D]">
            <button v-for="tab in marketTabs" :key="tab.id" @click="setActiveTab(tab.id)"
              class="flex-1 px-2 py-3 transition-all border-b-2 whitespace-nowrap"
              :class="activeTab === tab.id ? 'border-[#00D4FF] text-[#00D4FF] bg-[#00D4FF]/5' : 'border-transparent text-gray-500 hover:text-gray-300'">
              {{ tab.name }}
            </button>
          </div>

          <!-- Market Insights List Container -->
          <div class="flex-1 p-4">
            <div v-if="isInsightsLoading" class="flex flex-col items-center justify-center h-full space-y-2 opacity-50">
              <div class="w-8 h-8 border-t-2 border-blue-500 rounded-full animate-spin"></div>
              <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Syncing...</span>
            </div>
            
            <div v-else-if="marketData.length === 0" class="flex flex-col items-center justify-center py-10 text-xs text-center text-gray-500">
              <p>No market data available for {{ activeTab }}</p>
            </div>

            <div v-else>
              <table class="min-w-full text-sm border-separate border-spacing-0">
                <thead class="text-[10px] uppercase tracking-wider text-gray-500 bg-[#0B121D]">
                  <tr>
                    <th class="px-4 py-2 text-left">Symbol</th>
                    <th class="px-4 py-2 text-right">Price</th>
                    <th class="px-4 py-2 text-right">Change%</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#1f3348]">
                  <tr v-for="item in marketData" :key="item.symbol" @click="selectForChart({ symbol: item.symbol, name: item.name })" class="hover:bg-[#16213A] transition cursor-pointer">
                    <td class="px-4 py-3 font-bold text-white">{{ item.symbol }}</td>
                    <td class="px-4 py-3 text-right text-white">${{ item.price.toFixed(2) }}</td>
                    <td class="px-4 py-3 text-right">
                      <span :class="['inline-flex items-center justify-end rounded-full px-2 py-1 text-[10px] font-semibold', item.change >= 0 ? 'bg-green-500/10 text-green-300' : 'bg-red-500/10 text-red-300']">
                        {{ item.change >= 0 ? '+' : '' }}{{ item.change.toFixed(2) }}%
                      </span>
                    </td>
                    <td class="w-24 px-4 py-3 text-right">
                      <div class="h-8">
                        <apexchart type="line" height="32" :options="{ ...sparkOptions, colors: [item.change >= 0 ? '#10B981' : '#EF4444'] }" :series="[{ data: item.spark || [] }]" />
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-hidden w-full">
        <div class="p-4 border-b border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
          <div>
            <h2 class="font-semibold text-gray-200">Your Holdings</h2>
          </div>
          <span class="text-xs text-gray-500">{{ showSearchResults ? searchResults.length : userHoldings.length }} Assets</span>
        </div>
        <div class="overflow-x-auto">
          <div v-if="showSearchResults" class="p-4">
            <div class="flex items-center justify-between mb-3">
              <div>
                <h3 class="text-sm font-semibold text-white">Search results</h3>
                <p class="text-xs text-gray-400">Showing up to 20 matching symbols.</p>
              </div>
              <span class="text-xs text-gray-400">{{ searchResults.length }} matches</span>
            </div>

            <div class="divide-y divide-[#1f3348]">
              <div v-for="stock in searchResults" :key="stock.symbol" class="py-4 px-4 hover:bg-[#16213A] transition flex items-center justify-between gap-4">
                <div>
                  <div class="font-semibold text-[#00D4FF]">{{ stock.symbol }}</div>
                  <div class="text-gray-400 text-sm truncate max-w-[320px]">{{ stock.name }}</div>
                </div>
                <div class="flex gap-2">
                  <button @click="openDetails(stock)"
                    class="bg-[#1f3348] text-gray-300 px-3 py-2 rounded-md hover:text-white hover:bg-[#2d4a66] transition text-xs">
                    Details
                  </button>
                  <button @click="openTrade(stock)"
                    class="bg-[#00D4FF] text-[#0F1724] px-3 py-2 rounded-md font-bold hover:bg-[#00b8e6] transition text-xs">
                    Trade
                  </button>
                  <button v-if="!isStockInHoldings(stock.symbol)" @click="addToHoldings(stock)"
                    class="px-3 py-2 text-xs text-blue-400 transition border rounded-md border-blue-500/50 hover:bg-blue-500/10">
                    + Track
                  </button>
                </div>
              </div>

              <div v-if="!searchLoading && searchResults.length === 0" class="p-10 text-center text-gray-500">
                No symbols match your search "{{ search }}"
              </div>
              <div v-if="searchLoading" class="p-10 text-center text-gray-400">Searching symbols…</div>
            </div>
          </div>

          <div v-else>
            <div v-if="holdingsLoading" class="p-10 text-center text-gray-400">
              <div class="w-8 h-8 mx-auto mb-2 border-t-2 border-blue-500 rounded-full animate-spin"></div>
              Loading your holdings...
            </div>
            <div v-else-if="userHoldings.length === 0" class="p-10 text-center text-gray-400">
              No global stock holdings yet. Start trading to see your assets here.
            </div>
            <table v-else class="w-full text-sm">
              <thead class="text-gray-400 border-b border-[#1f3348] bg-[#0B121D]">
                <tr>
                  <th class="px-6 py-4 font-medium text-left">Symbol</th>
                  <th class="font-medium text-left">Company</th>
                  <th class="font-medium text-right">Quantity</th>
                  <th class="font-medium text-right">Price ($)</th>
                  <th class="font-medium text-right">Value ($)</th>
                  <th class="font-medium text-right">24h Change</th>
                  <th class="text-right fonts-medium">Volume</th>
                  <th class="px-6 font-medium text-right">Trend</th>
                  <th class="font-medium text-center" colspan="2">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#1f3348]">
                <tr v-for="holding in userHoldings" :key="holding.symbol" class="hover:bg-[#16213A] transition">
                  <td class="px-6 py-4 font-bold text-[#00D4FF]">{{ holding.symbol }}</td>
                  <td class="text-gray-300">{{ holding.name }}</td>
                  <td class="text-right text-gray-300">{{ holding.quantity }}</td>
                  <td class="font-mono font-semibold text-right text-white">${{ holding.price ? holding.price.toFixed(2) : '0.00' }}</td>
                  <td class="text-right" :class="holding.change >= 0 ? 'text-green-400' : 'text-red-400'">
                    {{ holding.change >= 0 ? '+' : '' }}{{ holding.change || 0 }}%
                  </td>
                  <td class="text-right text-gray-300">{{ holding.volume ? holding.volume.toLocaleString() : '0' }}</td>
                  <td class="w-32 px-6 text-right">
                    <apexchart type="line" height="30" :options="{ ...sparkOptions, colors: [holding.change >= 0 ? '#10B981' : '#EF4444'] }" :series="[{ data: holding.spark || [] }]" />
                  </td>
                  <td class="px-2 text-center">
                    <button @click="openDetails(holding)"
                      class="bg-[#1f3348] text-gray-300 px-3 py-1.5 rounded-md hover:text-white hover:bg-[#2d4a66] transition text-xs">
                      Details
                    </button>
                  </td>
                  <td class="px-2 text-center">
                    <button @click="openTrade(holding)"
                      class="bg-[#00D4FF] text-[#0F1724] px-4 py-1.5 rounded-md font-bold hover:bg-[#00b8e6] transition text-xs">
                      Trade
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Open Positions / Orders Monitor -->
      <PositionsMonitor />

      <MarketDetailsModal :isOpen="isModalOpen" :item="selectedItem" currency-symbol="$" @close="isModalOpen = false" />

      <!-- Trade Modal -->
      <div v-if="showTradeModal" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape="showTradeModal = false">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showTradeModal = false"></div>
          <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-[#0F1724] border border-[#1f3348] shadow-xl rounded-lg">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-medium text-white">Trade {{ selectedTradeStock ? selectedTradeStock.symbol : '' }}</h3>
              <button @click="showTradeModal = false" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
              </button>
            </div>
            <TradePanel 
              v-if="selectedTradeStock"
              :initialSymbol="selectedTradeStock.symbol" 
              @order-placed="() => { showTradeModal = false; fetchHoldings(); fetchPortfolioPerformance(); fetchWalletBalances(); }" 
            />
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import apexchart from "vue3-apexcharts"; 
import MarketDetailsModal from "@/Components/MarketDetailsModal.vue";
import HoldingPerformanceChart from "@/Components/HoldingPerformanceChart.vue";
import MarketChart from "@/Components/MarketChart.vue";
import MarketTicker from "@/Components/MarketTicker.vue";
import TradePanel from "@/Components/TradePanel.vue";
import PositionsMonitor from "@/Components/PositionsMonitor.vue";
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import api from "@/api";
import { useRouter } from 'vue-router';
import Echo from 'laravel-echo';

// State
const user = ref(JSON.parse(localStorage.getItem('user') || '{}'));
const isDemo = ref(user.value.trading_mode === 'demo');
const showPrompt = ref(false);
const isModalOpen = ref(false);
const selectedItem = ref(null);
const showTradeModal = ref(false);
const selectedTradeStock = ref(null);
const search = ref("");
const searchResults = ref([]);
const searchLoading = ref(false);
const searchInputRef = ref(null);
const searchInput = ref(null);
let refreshInterval = null;

// Favorites storage
const favoriteTickers = ref([]);
const searchSuggestions = ref([]);


const walletBalances = ref({ cleared_balance_usd: 0, balance_usd: 0 });

// Chart Toggle State
const activeChart = ref('holdings'); // 'holdings' or 'market'
const selectedMarketSymbol = ref('AAPL');
const isInsightsLoading = ref(false);
const chartSearchLoading = ref(false);
const marketInsights = ref({});
const marketNameMap = {
  AAPL: 'Apple Inc',
  TSLA: 'Tesla Inc',
  MSFT: 'Microsoft Corp',
  NVDA: 'Nvidia Corp',
  META: 'Meta Platforms',
  AMZN: 'Amazon.com Inc',
  GOOGL: 'Alphabet Inc',
  NFLX: 'Netflix Inc',
};
const marketTabSymbols = {
  gainers: ['AAPL', 'MSFT', 'NVDA'],
  losers: ['TSLA', 'NFLX', 'META'],
  most_traded: ['AAPL', 'AMZN', 'GOOGL'],
};

let searchTimer = null;
let chartSearchTimer = null;
const chartSearch = ref("");
const chartSearchResults = ref([]);

const router = useRouter()

const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  return role.includes('admin');
};

const isUserVerified = computed(() => {
  const u = user.value || {};
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

const activeTab = ref('gainers');
const isGraphLoading = ref(false);

const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);
const assetCategories = [{ id: 'GLOBAL', name: 'Global Stocks (USD)', description: 'US Markets' }];

const marketTabs = [
  { id: 'gainers', name: 'Gainers' },
  { id: 'losers', name: 'Losers' },
  { id: 'most_traded', name: 'Most Traded' },
];

const insightData = {
  gainers: [],
  losers: [],
  most_traded: [],
};

const marketData = computed(() => {
  return marketInsights.value[activeTab.value] || [];
});

const stocks = ref([
  { symbol: "AAPL", name: "Apple Inc", price: 0, change: 0, volume: 0, spark: [] },
  { symbol: "TSLA", name: "Tesla Inc", price: 0, change: 0, volume: 0, spark: [] },
  { symbol: "MSFT", name: "Microsoft Corp", price: 0, change: 0, volume: 0, spark: [] },
]);

const holdings = ref([]);
const holdingsLoading = ref(false);

const sparkOptions = {
  chart: { toolbar: { show: false }, sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false },
};

// Computed
const tradeTickers = computed(() => ({ GLOBAL: stocks.value.map(s => ({ ...s, currency: 'USD' })) }));
const showSearchResults = computed(() => search.value.trim().length >= 2);
const searchedTickers = computed(() => favoriteTickers.value);

const userHoldings = computed(() => {
  // Normalize category checking and provide fallback if category is missing
  return holdings.value.filter(h => {
    const cat = (h.category || '').toUpperCase();
    return ['GLOBAL', 'STOCKS', 'FOREIGN'].includes(cat) || !h.category;
  });
});

const isStockInHoldings = (symbol) => stocks.value.some(s => s.symbol === symbol);

const addToHoldings = async (stock) => {
  if (!isStockInHoldings(stock.symbol)) {
    stocks.value.push({ ...stock, price: 0, change: 0, volume: 0, spark: [] });
    // Immediately fetch the quote for the new stock
    await fetchHoldingsQuotes(false);
  }
};

const focusSearch = () => {
  if (searchInputRef.value && typeof searchInputRef.value.focus === 'function') {
    searchInputRef.value.focus();
  }
};

const setActiveTab = (tab) => {
  activeTab.value = tab;
  fetchMarketInsights(tab);
  const symbols = marketTabSymbols[tab] || [];
  if (symbols.length > 0) {
    api.post('/stocks/track', { symbols }).catch(() => {});
  }
};

const fetchMarketInsights = async (tab = activeTab.value, silent = false) => {
  const symbols = marketTabSymbols[tab] || [];
  if (symbols.length === 0) return;

  if (!silent) {
    isInsightsLoading.value = true;
  }

  try {
    const response = await api.get('/market/quotes', {
      params: { symbols: marketTabSymbols[tab].join(',') }
    });

    const rawData = response.data.data || [];

    marketInsights.value[tab] = rawData.map(item => ({
      symbol: item.symbol,
      name: marketNameMap[item.symbol] || item.name || item.symbol,
      price: item.price || 0,
      change: item.change || 0,
      volume: item.volume || 0,
      spark: item.spark || []
    }));
  } catch (error) {
    console.error('Market Insights fetch failed:', error);
    marketInsights.value[tab] = [];
  } finally {
    isInsightsLoading.value = false;
  }
};

const fetchHoldingsQuotes = async (silent = false) => {
  const symbolList = stocks.value.map(stock => stock.symbol);
  const symbols = symbolList.join(',');

  if (!symbols) return;
  if (!silent) isGraphLoading.value = true;

  try {
    const response = await api.get('/market/quotes', {
      params: { symbols }
    });

    // Notify backend to keep streaming these symbols (prevents the 5-min timeout)
    if (symbolList.length > 0) {
      api.post('/stocks/track', { symbols: symbolList }).catch(() => {});
    }

    const quoteMap = response.data.data.reduce((map, quote) => {
      map[quote.symbol] = quote;
      return map;
    }, {});

    stocks.value = stocks.value.map(stock => {
      const quote = quoteMap[stock.symbol];
      if (! quote) {
        return stock;
      }

      return {
        ...stock,
        price: quote.price,
        change: quote.change ?? stock.change,
      };
    });
  } catch (error) {
    if (!silent) console.error('Failed to fetch holdings quotes', error);
  } finally {
    if (!silent) isGraphLoading.value = false;

  }
};

const fetchSymbolSearch = async (queryOverride = null) => {
  const query = (queryOverride || search.value).trim();

  if (query.length < 3) { // Changed from 2 to 3
    searchSuggestions.value = [];
    searchLoading.value = false;
    return;
  }

  searchLoading.value = true;

  try {
    const response = await api.get('/stocks/search', {
      params: { q: query, limit: 10 }
    });

    searchSuggestions.value = response.data;
  } catch (error) {
    console.error('Symbol search failed', error);
    searchSuggestions.value = [];
  } finally {
    searchLoading.value = false;
  }
};

const handleSearchInput = () => {
  searchLoading.value = true;
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchSymbolSearch(), 500); // Changed from 300 to 500
};

const selectSuggestion = (stock) => {
  // Add to favorites if not already there, instead of replacing
  const exists = favoriteTickers.value.some(s => s.symbol === stock.symbol);
  if (!exists) {
    favoriteTickers.value.push(stock);
  }
  
  // Switch to market chart view immediately to show the new ticker
  activeChart.value = 'market';
  selectedMarketSymbol.value = stock.symbol;
  
  // Save to localStorage
  localStorage.setItem('global_favorite_tickers', JSON.stringify(favoriteTickers.value));
  
  // Clear search
  search.value = "";
  searchSuggestions.value = [];
};

const selectFirstSuggestion = () => {
  if (searchSuggestions.value.length > 0) {
    selectSuggestion(searchSuggestions.value[0]);
  }
};

const handleChartSearch = async () => {
  const query = chartSearch.value.trim();
  if (query.length < 2) {
    chartSearchResults.value = []; 
    chartSearchLoading.value = false;
    return;
  }
  chartSearchLoading.value = true;
  clearTimeout(chartSearchTimer);
  chartSearchTimer = setTimeout(async () => {
    try {
      const response = await api.get('/stocks/search', { params: { q: query, limit: 5 } });
      chartSearchResults.value = response.data;
    } catch (e) {
      chartSearchResults.value = [];
    } finally {
      chartSearchLoading.value = false;
    }
  }, 300);
};

const selectForChart = (stock) => {
  // Add to favorites if not already there
  const exists = favoriteTickers.value.some(s => s.symbol === stock.symbol);
  if (!exists) {
    favoriteTickers.value.push(stock);
    localStorage.setItem('global_favorite_tickers', JSON.stringify(favoriteTickers.value));
  }
  selectedMarketSymbol.value = stock.symbol;
  chartSearch.value = "";
  chartSearchResults.value = [];
};

// Methods
const openDetails = (item) => { 
  selectedItem.value = item; 
  isModalOpen.value = true; 
  selectedMarketSymbol.value = item.symbol;
};
const openTrade = (stock) => { 
  if (!isUserVerified.value && !isDemo.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  selectedTradeStock.value = { ...stock, currency: 'USD' }; showTradeModal.value = true; 
};

const fetchPortfolioPerformance = async (range = '1W') => {
  try {
    const response = await api.get(`/portfolio/history`, { params: { category: 'foreign', range } });
    portfolioData.value = response.data.series;
    totalValue.value = response.data.total;
    changePercent.value = response.data.change;
  } catch (e) {
    console.error('Failed to fetch history', e);
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

const fetchHoldings = async () => {
  holdingsLoading.value = true;
  try {
    const response = await api.get('/portfolio');
    const data = response.data.data || response.data;
    const holdingsData = data.holdings || [];
    
    // Filter global holdings
    const globalHoldings = holdingsData.filter(h => ['GLOBAL', 'STOCKS', 'FOREIGN'].includes(h.category ? h.category.toUpperCase() : ''));
    
    // Fetch live quotes for holdings in parallel
    if (globalHoldings.length > 0) {
      const symbols = globalHoldings.map(h => h.symbol).join(',');
      try {
        const quotesResponse = await api.get('/market/quotes', { params: { symbols } });
        const quotes = quotesResponse.data.data || [];
        const quotesMap = quotes.reduce((map, q) => {
          map[q.symbol] = q;
          return map;
        }, {});
        
        // Merge holdings with live quotes
        holdings.value = globalHoldings.map(h => ({
          ...h,
          price: quotesMap[h.symbol] ? quotesMap[h.symbol].price : 0,
          change: quotesMap[h.symbol] ? quotesMap[h.symbol].change : 0,
          volume: quotesMap[h.symbol] ? quotesMap[h.symbol].volume : 0,
          spark: quotesMap[h.symbol] ? quotesMap[h.symbol].spark : []
        }));
      } catch (quoteError) {
        console.error('Failed to fetch holdings quotes', quoteError);
        holdings.value = globalHoldings;
      }
    } else {
      holdings.value = [];
    }
  } catch (error) {
    console.error('Failed to fetch holdings', error);
    holdings.value = [];
  } finally {
    holdingsLoading.value = false;
  }
};

// Step 4: Consolidated Dashboard Initialization
const initDashboard = async () => {
  isGraphLoading.value = true;
  holdingsLoading.value = true;
  
  try {
    // Make parallel API calls for faster loading
    const [portfolioResponse, walletResponse, performanceResponse] = await Promise.allSettled([
      api.get('/portfolio'),
      api.get('/wallet/balances'),
      api.get('/portfolio/history', { params: { category: 'foreign', range: '1W' } })
    ]);

    // Process portfolio/holdings data
    if (portfolioResponse.status === 'fulfilled') {
      const data = portfolioResponse.value.data.data || portfolioResponse.value.data;
      const holdingsData = data.holdings || [];
      const globalHoldings = holdingsData.filter(h => ['GLOBAL', 'STOCKS', 'FOREIGN'].includes(h.category ? h.category.toUpperCase() : ''));
      
      // Fetch quotes for holdings in parallel
      if (globalHoldings.length > 0) {
        try {
          const symbols = globalHoldings.map(h => h.symbol).join(',');
          const quotesResponse = await api.get('/market/quotes', { params: { symbols } });
          const quotes = quotesResponse.data.data || [];
          const quotesMap = quotes.reduce((map, q) => {
            map[q.symbol] = q;
            return map;
          }, {});
          
          holdings.value = globalHoldings.map(h => ({
            ...h,
            price: quotesMap[h.symbol] ? quotesMap[h.symbol].price : 0,
            change: quotesMap[h.symbol] ? quotesMap[h.symbol].change : 0,
            volume: quotesMap[h.symbol] ? quotesMap[h.symbol].volume : 0,
            spark: quotesMap[h.symbol] ? quotesMap[h.symbol].spark : []
          }));
        } catch (quoteError) {
          console.error('Failed to fetch holdings quotes', quoteError);
          holdings.value = globalHoldings;
        }
      } else {
        holdings.value = [];
      }
    } else {
      console.error('Failed to fetch portfolio', portfolioResponse.reason);
      holdings.value = [];
    }

    // Process wallet data
    if (walletResponse.status === 'fulfilled') {
      walletBalances.value = walletResponse.value.data.data;
    } else {
      console.error('Failed to fetch wallet balances', walletResponse.reason);
    }

    // Process performance data
    if (performanceResponse.status === 'fulfilled') {
      portfolioData.value = performanceResponse.value.data.series;
      totalValue.value = performanceResponse.value.data.total;
      changePercent.value = performanceResponse.value.data.change;
    } else {
      console.error('Failed to fetch performance', performanceResponse.reason);
    }
    
    // Fetch market insights separately as they are tab-dependent
    fetchMarketInsights();
  } catch (e) {
    console.error('Dashboard init failed, falling back to individual calls', e);
    // Fallback to individual calls if parallel calls fail
    await Promise.all([
      fetchPortfolioPerformance(),
      fetchHoldings(),
      fetchWalletBalances()
    ]);
    fetchMarketInsights();
  } finally {
    isGraphLoading.value = false;
    holdingsLoading.value = false;
  }
};

onMounted(() => {
  // Load favorite tickers from localStorage
  const saved = localStorage.getItem('global_favorite_tickers');
  if (saved) {
    try {
      favoriteTickers.value = JSON.parse(saved);
      if (favoriteTickers.value.length > 0) {
        selectedMarketSymbol.value = favoriteTickers.value[0].symbol;
      }
    } catch (e) {
      favoriteTickers.value = [];
    }
  }

  // Replace individual calls with consolidated init
  initDashboard();
  
  // Ensure default insights are loaded if initDashboard fallback happens
  if (!user.value.has_active_subscription) {
     fetchMarketInsights('gainers');
  }
  fetchHoldingsQuotes(false);

  // --- REAL-TIME ECHO LISTENER ---
  window.Echo.channel('market-channel')
    .listen('MarketUpdated', (e) => {
      const updates = Array.isArray(e) ? e : (e.data || []);

      updates.forEach(trade => {
        const symbol = trade.s;
        const price = trade.p;
        const volume = trade.v;

        // 1. Update holdings
        const holdingIndex = holdings.value.findIndex(h => h.symbol === symbol);
        if (holdingIndex !== -1) {
          const h = holdings.value[holdingIndex];
          h.price = price;
          if (volume) h.volume = volume;
          
          // Trigger reactivity by replacing the array reference
          const updatedSpark = h.spark ? [...h.spark] : [];
          updatedSpark.push(price);
          if (updatedSpark.length > 20) updatedSpark.shift();
          h.spark = updatedSpark;
          
          // Update change percentage based on first vs last point in spark if available
        }
        const stockIndex = stocks.value.findIndex(s => s.symbol === symbol);
        if (stockIndex !== -1) {
          stocks.value[stockIndex].price = price;
        }

       
        const insightList = marketInsights.value[activeTab.value];
        if (insightList) {
          const insightIndex = insightList.findIndex(i => i.symbol === symbol);
          if (insightIndex !== -1) {
            insightList[insightIndex].price = price;
          }
        }
      });
    });

  // Setup real-time refresh every 10 seconds
  refreshInterval = setInterval(() => {
    fetchHoldingsQuotes(true);
  }, 10000);
});

onUnmounted(() => {
  window.Echo.leave('market-channel'); // Clean up
  clearInterval(refreshInterval);
});

</script>
<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #1f3348; border-radius: 10px; }
.no-scrollbar::-webkit-scrollbar { display: none; }
</style>