<template>
  <MainLayout>
    <div class="space-y-6">
      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      
      <!-- Top Actions Bar -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
          <span>🌍</span> Global Stocks
        </h1>
        
        <div class="relative w-full sm:w-64">
          <input 
            ref="searchInputRef" 
            v-model="search" 
            @input="handleSearchInput"
            @keydown.enter="selectFirstSuggestion" 
            type="text" 
            placeholder="Search global stocks..."
            class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none w-full transition-all" 
          />
          <span v-if="searchLoading" class="absolute text-xs text-gray-400 -translate-y-1/2 right-3 top-1/2">
            Searching…
          </span>

          <!-- Autocomplete Dropdown -->
          <div v-if="searchSuggestions.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-[#0F1724] border border-[#1f3348] rounded-lg shadow-2xl z-50 max-h-48 overflow-y-auto">
            <div 
              v-for="suggestion in searchSuggestions" 
              :key="suggestion.symbol"
              @click="selectSuggestion(suggestion)"
              class="p-3 hover:bg-[#16213A] cursor-pointer border-b border-[#1f3348]/50 last:border-0"
            >
              <div class="flex items-center justify-between">
                <div class="font-semibold text-[#00D4FF] font-mono">{{ suggestion.symbol }}</div>
                <span class="text-[10px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded font-bold">{{ suggestion.market || 'US' }}</span>
              </div>
              <div class="text-xs text-gray-400 truncate">{{ suggestion.name }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Portfolio Summary Bar -->
      <div class="flex flex-col gap-4 p-4 bg-[#0F1724]/40 border border-[#1f3348]/40 rounded-xl sm:flex-row sm:items-center sm:gap-8">
        <div>
          <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold mb-0.5">USD Wallet Balance</p>
          <p class="text-base font-mono font-bold text-white">
            ${{ walletBalances?.cleared_balance_usd ? walletBalances.cleared_balance_usd.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00' }}
          </p>
        </div>
        <div class="hidden sm:block h-8 w-px bg-[#1f3348]"></div>
        <div>
          <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold mb-0.5">Global Stocks Value</p>
          <p class="text-base font-mono font-bold text-[#00D4FF]">
            ${{ totalValue ? totalValue.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00' }}
          </p>
        </div>
      </div>

      <!-- View Navigation & Layout Engine -->
      <div class="space-y-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex p-1 bg-[#0B121D] border border-[#1f3348] rounded-lg self-start overflow-x-auto max-w-full">
            <button 
              @click="activeChart = 'holdings'"
              class="px-4 py-2 text-xs font-bold uppercase transition-all rounded-md whitespace-nowrap"
              :class="activeChart === 'holdings' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-300'"
            >
              My Holdings
            </button>
            <button 
              @click="activeChart = 'market'"
              class="px-4 py-2 text-xs font-bold uppercase transition-all rounded-md whitespace-nowrap"
              :class="activeChart === 'market' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-300'"
            >
              Live Chart
            </button>
            <button 
              @click="activeChart = 'insights'"
              class="px-4 py-2 text-xs font-bold uppercase transition-all rounded-md whitespace-nowrap"
              :class="activeChart === 'insights' ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-300'"
            >
              Market Insights
            </button>
            <button 
              @click="stocks && stocks.length > 0 ? openTrade(stocks[0]) : openTrade(null)"
              class="px-4 py-2 text-xs font-bold text-gray-500 uppercase transition-all rounded-md hover:text-gray-300 whitespace-nowrap"
            >
              Buy / Sell
            </button>
          </div>

          <!-- Market Chart Specific Controls -->
          <div v-if="activeChart === 'market'" class="flex flex-wrap items-center gap-2">
            <span class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Market:</span>
            <span class="px-2 py-0.5 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded font-mono text-xs uppercase">
              {{ selectedMarketSymbol }}
            </span>
            <div class="relative" ref="chartSearchContainer">
              <input 
                v-model="chartSearch" 
                @input="handleChartSearch" 
                type="text" 
                placeholder="Quick find symbol..."
                class="bg-[#0B121D] border border-[#1f3348] rounded px-3 py-1 text-xs text-gray-300 focus:border-[#00D4FF] outline-none w-40" 
              />
              <span v-if="chartSearchLoading" class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-500 italic">
                Searching...
              </span>
              <div v-if="chartSearchResults.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-[#0F1724] border border-[#1f3348] rounded-lg shadow-2xl z-50 max-h-48 overflow-y-auto">
                <div 
                  v-for="res in chartSearchResults" 
                  :key="res.symbol" 
                  @click="selectForChart(res)"
                  class="p-2 hover:bg-[#16213A] cursor-pointer border-b border-[#1f3348]/50 last:border-0 text-xs text-gray-300"
                >
                  <span class="font-bold text-[#00D4FF] font-mono">{{ res.symbol }}</span> - {{ res.name }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Render Target Switch -->
        <div class="mt-4">
          <!-- Switch View 1: Market Insights Component -->
          <div v-if="activeChart === 'insights'">
            <div v-if="isInsightsLoading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 space-y-4">
              <div class="flex justify-between items-center">
                <SkeletonLoader class="h-5 w-40 bg-gray-800" />
                <SkeletonLoader class="h-4 w-24 bg-gray-800" />
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <SkeletonLoader v-for="i in 3" :key="i" class="h-28 w-full rounded-lg bg-gray-800/60" />
              </div>
              <SkeletonLoader class="h-32 w-full rounded-lg bg-gray-800/40" />
            </div>
            <MarketInsights 
              v-else
              :insightData="globalApiInsights" 
              :loading="isInsightsLoading" 
              currencySymbol="$"
              @trade="openTrade" 
            />
          </div>

          <!-- Switch View 2: Portfolio Performance & Holdings Table -->
          <div v-else-if="activeChart === 'holdings'">
            <!-- Performance Chart Area / Skeleton State -->
            <div v-if="isGraphLoading" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 space-y-4">
              <div class="flex justify-between items-start">
                <div class="space-y-2">
                  <SkeletonLoader class="h-4 w-48 bg-gray-800" />
                  <SkeletonLoader class="h-6 w-32 bg-gray-700/80" />
                </div>
                <SkeletonLoader class="h-8 w-36 rounded-md bg-gray-800" />
              </div>
              <SkeletonLoader class="h-52 w-full rounded-lg bg-gray-800/40" />
            </div>
            <HoldingPerformanceChart 
              v-else
              title="My Global Stocks Holdings" 
              currencySymbol="$" 
              :seriesData="portfolioData"
              :totalValue="totalValue" 
              :percentageChange="changePercent" 
              :loading="isGraphLoading"
              @rangeChange="fetchPortfolioPerformance" 
            />

            <!-- Holdings Panel Wrapper -->
            <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-hidden w-full mt-4">
              <div class="p-4 border-b border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
                <div>
                  <h2 class="font-semibold text-gray-200 mb-0.5">My Holdings</h2>
                  <p class="text-[10px] text-gray-400 uppercase tracking-tight">
                    Orders marked <span class="font-bold text-yellow-500">Pending</span> are awaiting execution or settlement.
                  </p>
                </div>
                <span class="text-xs text-gray-500">
                  {{ showSearchResults ? searchResults.length : userHoldings.length }} Assets
                </span>
              </div>
              
              <div class="overflow-x-auto">
                <!-- Search Filter Context -->
                <div v-if="showSearchResults" class="p-4">
                  <div class="flex items-center justify-between mb-3">
                    <div>
                      <h3 class="text-sm font-semibold text-white">Search Results</h3>
                      <p class="text-xs text-gray-400">Showing up to 20 matching symbols.</p>
                    </div>
                    <span class="text-xs text-gray-400">{{ searchResults.length }} matches</span>
                  </div>

                  <div class="divide-y divide-[#1f3348]">
                    <div 
                      v-for="stock in searchResults" 
                      :key="stock.symbol"
                      class="py-4 px-4 hover:bg-[#16213A] transition flex items-center justify-between gap-4"
                    >
                      <div>
                        <div class="flex items-center gap-2 mb-1">
                          <div class="font-bold text-white font-mono">{{ stock.symbol }}</div>
                          <span class="text-[8px] bg-gray-800 text-gray-400 px-1.5 py-0.5 rounded font-black tracking-widest">{{ stock.market || 'US' }}</span>
                          <span :class="['text-xs font-bold', stock.change >= 0 ? 'text-emerald-500' : 'text-rose-500']">
                            {{ stock.change >= 0 ? '▲' : '▼' }} {{ Math.abs(stock.change).toFixed(2) }}%
                          </span>
                        </div>
                        <div class="text-gray-400 text-sm truncate max-w-[240px] sm:max-w-[320px]">{{ stock.name }}</div>
                      </div>
                      <div class="flex gap-2">
                        <button 
                          @click="openDetails(stock)"
                          class="bg-[#1f3348] text-gray-300 px-3 py-2 rounded-md hover:text-white hover:bg-[#2d4a66] transition text-xs"
                        >
                          Details
                        </button>
                        <button 
                          @click="openTrade(stock)"
                          class="bg-[#00D4FF] text-[#0F1724] px-3 py-2 rounded-md font-bold hover:bg-[#00b8e6] transition text-xs"
                        >
                          Buy
                        </button>
                        <button 
                          v-if="!isStockInHoldings(stock.symbol)" 
                          @click="addToHoldings(stock)"
                          class="px-3 py-2 text-xs text-blue-400 transition border rounded-md border-blue-500/50 hover:bg-blue-500/10"
                        >
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

                <!-- Primary Holdings Grid Table -->
                <div v-else>
                  <div v-if="holdingsLoading" class="p-6">
                    <SkeletonLoader type="table" class="opacity-40" />
                  </div>
                  
                  <div v-else-if="userHoldings.length === 0" class="p-10 text-center text-gray-400">
                    No global stock holdings yet. Start trading to see your assets here.
                  </div>
                  <table v-else class="w-full text-sm min-w-[800px]">
                    <thead class="text-gray-400 border-b border-[#1f3348] bg-[#0B121D]">
                      <tr>
                        <th class="px-6 py-4 font-medium text-left">Symbol</th>
                        <th class="font-medium text-left">Company</th>
                        <th class="font-medium text-right">Qty</th>
                        <th class="font-medium text-right">Avg. Cost</th>
                        <th class="font-medium text-right">Live Price</th>
                        <th class="font-medium text-right">Total Value</th>
                        <th class="font-medium text-right">P&L %</th>
                        <th class="font-medium text-center" colspan="2">Action</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-[#1f3348]">
                      <tr v-for="holding in userHoldings" :key="holding.symbol" class="hover:bg-[#16213A] transition">
                        <td class="px-6 py-5 font-bold text-[#00D4FF] font-mono">{{ holding.symbol }}</td>
                        <td class="text-gray-300 uppercase truncate max-w-[180px]">{{ holding.name }}</td>
                        <td class="text-right text-gray-300 font-mono">{{ holding.quantity }}</td>
                        <td class="font-mono text-right text-gray-400">
                          ${{ Number(holding.entry_price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                        </td>
                        <td class="font-mono font-semibold text-right text-white">
                          ${{ holding.price ? holding.price.toFixed(2) : '0.00' }}
                        </td>
                        <td class="font-mono text-right text-white">
                          ${{ (holding.quantity * (holding.price || 0)).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
                        </td>
                        <td class="text-right" :class="holding.change >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                          <div class="flex flex-col items-end">
                            <div v-if="holding.status === 'open' || !holding.is_settled" class="flex items-center gap-1 mb-0.5">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                </span>
                                <span class="text-[8px] uppercase font-black text-amber-500 tracking-tighter">Unsettled</span>
                            </div>
                            <span class="font-mono font-bold">
                              {{ holding.change >= 0 ? '+' : '' }}{{ Number(holding.change || 0).toFixed(2) }}%
                            </span>
                          </div>
                        </td>
                        <td class="px-2 text-center">
                          <button 
                            @click="openDetails(holding)"
                            class="bg-[#1f3348] text-gray-300 px-3 py-1.5 rounded-md hover:text-white hover:bg-[#2d4a66] transition text-xs"
                          >
                            Details
                          </button>
                        </td>
                        <td class="px-2 text-center">
                          <button 
                            v-if="holding.status === 'open'" 
                            @click="cancelOrder(holding.id)"
                            :disabled="cancellingId === holding.id"
                            class="bg-rose-500/10 text-rose-400 px-4 py-1.5 rounded-md font-bold hover:bg-rose-500 hover:text-white transition text-xs border border-rose-500/20 disabled:opacity-50"
                          >
                            {{ cancellingId === holding.id ? '...' : 'Cancel' }}
                          </button>
                          <button 
                            v-else 
                            @click="openTrade(holding)"
                            class="bg-[#00D4FF] text-[#0F1724] px-4 py-1.5 rounded-md font-bold hover:bg-[#00b8e6] transition text-xs"
                          >
                            Buy
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- Switch View 3: Live Chart Window Area -->
          <div v-else class="space-y-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
              <span class="text-[10px] text-gray-400 uppercase font-bold tracking-widest whitespace-nowrap bg-[#16213A] px-2 py-1 rounded border border-[#1f3348] self-start">
                My Favorite Tickers
              </span>
              <MarketTicker 
                class="flex-1 w-full" 
                :selected-symbol="selectedMarketSymbol" 
                :additional-tickers="searchedTickers"
                @select-symbol="selectedMarketSymbol = $event" 
              />
            </div>
            
            
            <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4 sm:p-6 overflow-hidden min-h-[400px]">
              <div v-if="!selectedMarketSymbol" class="space-y-6 animate-pulse">
                <div class="flex justify-between items-center">
                  <div class="space-y-2">
                    <SkeletonLoader class="h-6 w-24 bg-gray-800" />
                    <SkeletonLoader class="h-4 w-40 bg-gray-800/60" />
                  </div>
                  <div class="flex gap-2">
                    <SkeletonLoader v-for="i in 4" :key="i" class="h-6 w-10 rounded bg-gray-800" />
                  </div>
                </div>
                <SkeletonLoader class="h-64 w-full rounded-xl bg-gray-800/30" />
              </div>
              <MarketChart v-else :symbol="selectedMarketSymbol" />
            </div>
          </div>
        </div>
      </div>

      <!-- Modals and Workflow Overlay Components -->
      <MarketDetailsModal 
        :isOpen="isModalOpen" 
        :item="selectedItem" 
        currency-symbol="$" 
        @close="isModalOpen = false" 
      />

      <TradePanel 
        v-if="showTradeModal && selectedTradeStock" 
        :initialSymbol="selectedTradeStock.symbol"
        @close="showTradeModal = false"
        @order-placed="handleOrderPlaced" 
      />
     
      <!-- Transaction Order Notification Overlay -->
      <div v-if="showOrderSuccessModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm">
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl animate-in zoom-in duration-200">
          <div class="flex items-center justify-center w-16 h-16 mx-auto mb-5 text-emerald-400 rounded-full bg-emerald-500/10">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
            </svg>
          </div>
          <h3 class="mb-1 text-xl font-bold text-white">Order Placed!</h3>
          <p class="mb-6 text-xs text-gray-400 leading-relaxed">
            Your order for <span class="text-[#00D4FF] font-bold font-mono">{{ orderSuccessData?.symbol }}</span> has been submitted successfully.
          </p>
          <button 
            @click="showOrderSuccessModal = false" 
            class="w-full bg-[#00D4FF] text-[#0F1724] py-3 rounded-xl font-bold uppercase tracking-wider hover:bg-[#00b8e6] transition-all text-sm"
          >
            Done
          </button>
        </div>
      </div>

    </div>
  </MainLayout>
</template>


<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import MarketDetailsModal from "@/Components/MarketDetailsModal.vue";
import HoldingPerformanceChart from "@/Components/HoldingPerformanceChart.vue";
import MarketChart from "@/Components/MarketChart.vue";
import MarketTicker from "@/Components/MarketTicker.vue";
import TradePanel from "@/Components/TradePanel.vue";
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import api from "@/api";
import MarketInsights from '@/Components/Markets/MarketInsights.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';

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
const chartSearchContainer = ref(null);
let refreshInterval = null;

// Favorites & Autocomplete Cache
const favoriteTickers = ref([]);
const searchSuggestions = ref([]);

// Portfolio Data Metrics
const walletBalances = ref({ cleared_balance_usd: 0, balance_usd: 0 });
const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);

// Chart Toggle & Navigation States
const activeChart = ref('holdings'); // 'holdings', 'market', 'insights'
const selectedMarketSymbol = ref('AAPL');
const isInsightsLoading = ref(false);
const isGraphLoading = ref(false);
const chartSearchLoading = ref(false);
const activeTab = ref('gainers');
const currentMarketType = ref('global');

const marketTabSymbols = {
  gainers: ['AAPL', 'MSFT', 'NVDA'],
  losers: ['TSLA', 'NFLX', 'META'],
  most_traded: ['AAPL', 'AMZN', 'GOOGL'],
};

let searchTimer = null;
let chartSearchTimer = null;
const chartSearch = ref("");
const chartSearchResults = ref([]);

const globalApiInsights = ref({
  gainers: [],
  losers: [],
  most_traded: []
});

const stocks = ref([
  { symbol: "AAPL", name: "Apple Inc", price: 0, change: 0, volume: 0, spark: [] },
  { symbol: "TSLA", name: "Tesla Inc", price: 0, change: 0, volume: 0, spark: [] },
  { symbol: "MSFT", name: "Microsoft Corp", price: 0, change: 0, volume: 0, spark: [] },
]);

const holdings = ref([]);
const holdingsLoading = ref(false);

// Computed Layout Bounds
const showSearchResults = computed(() => search.value.trim().length >= 2);
const searchedTickers = computed(() => favoriteTickers.value);

const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  return role.includes('admin');
};

const isUserVerified = computed(() => {
  const u = user.value || {};
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

const userHoldings = computed(() => {
  return holdings.value.filter(h => {
    const cat = (h.category || '').toUpperCase();
    return ['GLOBAL', 'STOCKS', 'FOREIGN'].includes(cat) || !h.category;
  });
});

const isStockInHoldings = (symbol) => holdings.value.some(h => h.symbol === symbol);

// Methods
const addToHoldings = async (stock) => {
  if (!isStockInHoldings(stock.symbol)) {
    stocks.value.push({ ...stock, price: 0, change: 0, volume: 0, spark: [] });
    await fetchHoldingsQuotes(false);
  }
};

const focusSearch = () => {
  searchInputRef.value?.focus();
};

const setActiveTab = (tab) => {
  activeTab.value = tab;
  fetchMarketInsights();
  const symbols = marketTabSymbols[tab] || [];
  if (symbols.length > 0) {
    api.post('/stocks/track', { symbols }).catch(() => { });
  }
};

const fetchMarketInsights = async (silent = false) => {
  if (!silent) isInsightsLoading.value = true;
  try {
    const response = await api.get(`/market/${currentMarketType.value}/insights`);
    if (response.data) {
      globalApiInsights.value = response.data;
    }
  } catch (error) {
    console.error('Market Insights fetch failed:', error);
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
    const response = await api.get('/market/quotes', { params: { symbols } });

    if (symbolList.length > 0) {
      api.post('/stocks/track', { symbols: symbolList }).catch(() => { });
    }

    const quoteMap = response.data.data.reduce((map, quote) => {
      map[quote.symbol] = quote;
      return map;
    }, {});

    stocks.value = stocks.value.map(stock => {
      const quote = quoteMap[stock.symbol];
      if (!quote) return stock;
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
  if (query.length < 3) {
    searchSuggestions.value = [];
    searchLoading.value = false;
    return;
  }

  searchLoading.value = true;
  try {
    const response = await api.get('/stocks/search', { params: { q: query, limit: 10 } });
    searchSuggestions.value = response.data;
    searchResults.value = response.data;
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
  searchTimer = setTimeout(() => fetchSymbolSearch(), 500);
};

const selectSuggestion = (stock) => {
  const exists = favoriteTickers.value.some(s => s.symbol === stock.symbol);
  if (!exists) {
    favoriteTickers.value.push(stock);
  }

  activeChart.value = 'market';
  selectedMarketSymbol.value = stock.symbol;

  localStorage.setItem('global_favorite_tickers', JSON.stringify(favoriteTickers.value));
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
  const exists = favoriteTickers.value.some(s => s.symbol === stock.symbol);
  if (!exists) {
    favoriteTickers.value.push(stock);
    localStorage.setItem('global_favorite_tickers', JSON.stringify(favoriteTickers.value));
  }
  selectedMarketSymbol.value = stock.symbol;
  chartSearch.value = "";
  chartSearchResults.value = [];
};

const handleClickOutside = (event) => {
  if (chartSearchContainer.value && !chartSearchContainer.value.contains(event.target)) {
    chartSearchResults.value = [];
  }
};

const openDetails = (item) => {
  selectedItem.value = item;
  isModalOpen.value = true;
  selectedMarketSymbol.value = item.symbol;
};

const cancellingId = ref(null);
const cancelOrder = async (id) => {
  if (!confirm("Are you sure you want to cancel this order?")) return;
  cancellingId.value = id;
  try {
    await api.post(`/orders/${id}/cancel`);
    await fetchHoldings();
    await fetchWalletBalances();
  } catch (error) {
    console.error('Failed to cancel order', error);
  } finally {
    cancellingId.value = null;
  }
};

const orderSuccessData = ref(null);
const showOrderSuccessModal = ref(false);

const openTrade = (stock) => {
  if (!isUserVerified.value && !isDemo.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  selectedTradeStock.value = stock ? { ...stock, currency: 'USD' } : null; 
  showTradeModal.value = true;
};

const handleOrderPlaced = (order) => {
  showTradeModal.value = false;
  orderSuccessData.value = order;
  showOrderSuccessModal.value = true;
  
  nextTick(() => {
    fetchHoldings();
    fetchPortfolioPerformance();
    fetchWalletBalances();
  });
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
    const [portfolioRes, positionsRes] = await Promise.allSettled([
      api.get('/portfolio'),
      api.get('/trade/positions', { params: { category: 'GLOBAL' } })
    ]);

    let mergedItems = [];

    if (portfolioRes.status === 'fulfilled') {
      const data = portfolioRes.value.data.data || portfolioRes.value.data;
      const holdingsData = data.holdings || [];
      const globalHoldings = holdingsData
        .filter(h => ['GLOBAL', 'STOCKS', 'FOREIGN'].includes(h.category ? h.category.toUpperCase() : ''))
        .map(h => ({
          ...h,
          entry_price: h.avg_price || 0,
          price: h.price || h.current_price || 0,
          status: 'filled'
        }));
      mergedItems = [...globalHoldings];
    }

    if (positionsRes.status === 'fulfilled') {
      const data = positionsRes.value.data.data || positionsRes.value.data;
      const pendingOrders = data.filter(p => p.position_type === 'order' && p.status === 'open')
        .map(p => ({
          ...p,
          entry_price: p.entry_price || 0,
          price: p.market_price || 0
        }));
      mergedItems = [...mergedItems, ...pendingOrders];
    }

    if (mergedItems.length > 0) {
      const symbols = [...new Set(mergedItems.map(m => m.symbol))].join(',');
      try {
        const quotesResponse = await api.get('/market/quotes', { params: { symbols } });
        const quotes = quotesResponse.data.data || [];
        const quotesMap = quotes.reduce((map, q) => {
          map[q.symbol] = q; return map;
        }, {});

        holdings.value = mergedItems.map(m => {
          const quote = quotesMap[m.symbol];
          const marketPrice = (quote && quote.price) ? quote.price : m.price;
          const companyName = m.name || (quote ? quote.name : m.symbol);
          const entryPrice = m.entry_price || 0;
          
          let plPercent = m.unrealized_pl_percent;
          if (entryPrice > 0 && plPercent === undefined) {
            plPercent = ((Number(marketPrice) - Number(entryPrice)) / Number(entryPrice)) * 100;
          }

          return {
            ...m,
            name: companyName,
            price: Number(marketPrice),
            change: plPercent !== undefined ? Number(plPercent) : (quote ? Number(quote.change) : 0),
            volume: quote ? quote.volume : (m.volume || 0),
            spark: quote ? quote.spark : (m.spark || [])
          };
        });
      } catch (quoteError) {
        console.error('Failed to fetch holdings quotes', quoteError);
        holdings.value = mergedItems;
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

const initDashboard = async () => {
  isGraphLoading.value = true;
  holdingsLoading.value = true;
  try {
    await Promise.allSettled([
      fetchHoldings(),
      fetchWalletBalances(),
      fetchPortfolioPerformance(),
      fetchMarketInsights()
    ]);
  } catch (e) {
    console.error('Dashboard init failed', e);
  } finally {
    isGraphLoading.value = false;
    holdingsLoading.value = false;
  }
};

onMounted(() => {
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

  window.addEventListener('click', handleClickOutside);
  initDashboard();

  // --- REAL-TIME REVERSED ECHO SUITE LISTENER ---
  window.Echo.channel('market-channel')
    .listen('MarketUpdated', (e) => {
      const updates = Array.isArray(e) ? e : (e.data || []);

      updates.forEach(trade => {
        const symbol = trade.s;
        const price = Number(trade.p);
        const volume = trade.v;

        // 1. Live stream updates directly into User Portfolio Holdings list
        const holdingIndex = holdings.value.findIndex(h => h.symbol === symbol);
        if (holdingIndex !== -1) {
          const h = holdings.value[holdingIndex];
          h.price = price;
          if (volume) h.volume = volume;

          const updatedSpark = h.spark ? [...h.spark] : [];
          updatedSpark.push(price);
          if (updatedSpark.length > 20) updatedSpark.shift();
          h.spark = updatedSpark;
        }

        // 2. Track fallback tracking references arrays
        const stockIndex = stocks.value.findIndex(s => s.symbol === symbol);
        if (stockIndex !== -1) {
          stocks.value[stockIndex].price = price;
        }

        // 3. Keep target reactive sub-tab layouts aligned (Fixed targeting here)
        const insightList = globalApiInsights.value[activeTab.value];
        if (insightList && Array.isArray(insightList)) {
          const insightIndex = insightList.findIndex(i => i.symbol === symbol);
          if (insightIndex !== -1) {
            insightList[insightIndex].price = price;
          }
        }
      });
    });

  refreshInterval = setInterval(() => {
    fetchHoldingsQuotes(true);
  }, 30000);
});

onUnmounted(() => {
  window.Echo.leave('market-channel');
  clearInterval(refreshInterval);
  window.removeEventListener('click', handleClickOutside);
});
</script>
<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #1f3348;
  border-radius: 10px;
}

.no-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>