<template>
  <MainLayout>
    <div class="space-y-6">
      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      
      <!-- Top Section: Header & Quick Query Input -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">📈 Fixed Income</h1>
        <div class="relative">
          <input 
            v-model="search" 
            type="text" 
            placeholder="Search instruments..."
            class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none w-64 transition-all" 
          />
        </div>
      </div>

      <!-- Portfolio Summary Bar -->
      <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:gap-8">
        <div>
          <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold mb-0.5">NGN Balance</p>
          <p class="font-mono text-base font-bold text-white">
             ₦{{ walletBalances?.cleared_balance_ngn ? walletBalances.cleared_balance_ngn.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00' }}
          </p>
        </div>
        <div class="hidden sm:block h-8 w-px bg-[#1f3348]"></div>
        <div>
          <p class="text-[11px] uppercase tracking-wider text-gray-400 font-bold mb-0.5">Fixed Income</p>
          <p class="text-base font-mono font-bold text-[#00D4FF]">
            ₦{{ totalValue ? totalValue.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00' }}
          </p>
        </div>
      </div>

      <!-- Quick Control Action Row -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex p-1 bg-[#0B121D] border border-[#1f3348] rounded-lg w-fit">
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

      <div v-if="activeView === 'holdings'" class="space-y-6">
        <!-- Performance Stream Rendering Component -->
        <HoldingPerformanceChart 
          title="My Fixed Income Holdings" 
          currencySymbol="₦" 
          :seriesData="portfolioData" 
          :totalValue="totalValue"
          :percentageChange="changePercent" 
          :loading="isGraphLoading || loading" 
          @rangeChange="fetchPortfolioPerformance" 
        />

        <!-- Asset Grid Portfolio List Table -->
        <div v-if="loading" class="mt-6">
          <SkeletonLoader type="table" class="opacity-40" />
        </div>
        
        <div v-else class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-hidden mt-6">
          <div class="p-4 border-b border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
            
            <span class="text-xs text-gray-500">{{ filteredInstruments.length }} Assets Available</span>
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
                  <td class="font-mono font-semibold text-right text-white">{{ instrument.yield?.toFixed(2) }}%</td>
                  <td class="font-mono text-right" :class="instrument.change >= 0 ? 'text-green-400' : 'text-red-400'">
                    {{ instrument.change >= 0 ? '+' : '' }}{{ instrument.change }}%
                  </td>
                  <td class="font-mono text-right text-gray-400">{{ instrument.volume?.toLocaleString() }}</td>
                  <td class="w-32 px-6 text-right">
                    <apexchart type="line" height="30" :options="sparkOptions" :series="[{ data: instrument.spark || [] }]" />
                  </td>
                  <td class="px-2 text-center">
                    <button @click="openDetails(instrument)" class="bg-[#1f3348] text-gray-300 px-3 py-1.5 rounded-md hover:bg-[#2d4a66] transition text-xs">
                      Details
                    </button>
                  </td>
                  <td class="px-2 pr-6 text-center">
                    <button @click="openTrade(instrument)" class="bg-[#00D4FF] text-[#0F1724] px-4 py-1.5 rounded-md font-bold hover:bg-[#00b8e6] transition text-xs">
                      Trade
                    </button>
                  </td>
                </tr>
                <tr v-if="filteredInstruments.length === 0">
                  <td colspan="8" class="p-10 italic text-center text-gray-500">No asset lines matched your query parameters.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-else-if="activeView === 'market'" class="space-y-4">
        <p v-if="marketError" class="text-red-400">{{ marketError }}</p>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="instrument in filteredInstruments" :key="instrument.symbol"
            class="rounded-xl border border-[#1f3348] bg-[#0F1724] p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs uppercase tracking-wider text-cyan-400">{{ instrument.symbol }}</p>
                <h2 class="mt-1 text-lg font-semibold text-white">{{ instrument.name }}</h2>
                <p class="mt-1 text-sm text-gray-400">{{ instrument.issuer || 'Fixed Income product' }}</p>
              </div>
              <span class="text-sm text-gray-400">{{ instrument.currency }}</span>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="text-gray-500">Yield</p>
                <p class="mt-1 text-xl font-semibold text-white">{{ instrument.yield.toFixed(2) }}%</p>
              </div>
              <div>
                <p class="text-gray-500">Minimum</p>
                <p class="mt-1 text-white">{{ fixedIncomeCurrency(instrument.price, instrument.currency) }}</p>
              </div>
            </div>
            <button class="mt-5 w-full rounded-lg border border-cyan-400/40 px-4 py-2 text-sm font-semibold text-cyan-400 hover:bg-cyan-400 hover:text-[#0F1724]"
              @click="openTrade(instrument)">
              View trading options
            </button>
          </article>
        </div>
        <p v-if="!filteredInstruments.length" class="rounded-xl border border-dashed border-[#1f3348] p-10 text-center text-gray-500">
          No active products match your search.
        </p>
      </div>

      <div v-else-if="activeView === 'history'" class="overflow-x-auto rounded-xl border border-[#1f3348] bg-[#0F1724]">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-[#1f3348] bg-[#0B121D] text-gray-400">
            <tr>
              <th class="p-4">Reference</th>
              <th class="p-4">Product</th>
              <th class="p-4 text-right">Principal</th>
              <th class="p-4 text-right">Expected interest</th>
              <th class="p-4">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#1f3348]">
            <tr v-for="investment in investments" :key="investment.id" class="text-gray-200">
              <td class="p-4 text-cyan-400">{{ investment.reference }}</td>
              <td class="p-4">{{ investment.product?.name || '-' }}</td>
              <td class="p-4 text-right">{{ investment.principal_amount?.toLocaleString() }}</td>
              <td class="p-4 text-right">{{ investment.expected_interest?.toLocaleString() }}</td>
              <td class="p-4">{{ investment.status }}</td>
            </tr>
          </tbody>
        </table>
        <p v-if="!investments.length" class="p-10 text-center text-gray-500">No Fixed Income investments yet.</p>
      </div>

      <!-- Modal Overlays Injection Portals -->
      <MarketDetailsModal :isOpen="isModalOpen" :item="selectedItem" currencySymbol="₦" @close="isModalOpen = false" />
      
      <TradeModal 
        :show="showTradeModal" 
        :tickers="tradeTickers" 
        :assetCategories="assetCategories" 
        :initialTicker="selectedTradeInstrument" 
        initialCategory="FIXED_INCOME"
        @close="showTradeModal = false" 
        @trade-success="fetchPortfolioPerformance" 
      />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from 'vue-router';
import MainLayout from "@/Layouts/MainLayout.vue";
import apexchart from "vue3-apexcharts";
import MarketDetailsModal from "@/Components/MarketDetailsModal.vue";
import HoldingPerformanceChart from "@/Components/HoldingPerformanceChart.vue";
import TradeModal from "@/Components/TradeModal.vue";
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import SkeletonLoader from "@/Components/SkeletonLoader.vue"
import api from "@/api";
import { fixedIncomeCurrency } from '@/lib/fixedIncomeFormatters'

const route = useRoute();

// Core Identity / Session Access Management Configuration 
const user = ref(JSON.parse(localStorage.getItem('user') || '{}'));
const isDemo = ref(user.value.trading_mode === 'demo');
const showPrompt = ref(false);

// Interface Structural Switch Panels
const activeView = ref(route.query.view || 'holdings');
const isModalOpen = ref(false);
const selectedItem = ref(null);
const showTradeModal = ref(false);
const selectedTradeInstrument = ref(null);
const search = ref("");

// Quantitative Streams Containers
const isGraphLoading = ref(false);
const loading = ref(false);
const walletBalances = ref({ cleared_balance_ngn: 0 });
const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);
const investments = ref([]);

const canTrade = computed(() => {
  if (isDemo.value) return true;
  return Number(user.value?.verification_level || 0) >= 2;
});

const assetCategories = [
  { id: 'FIXED_INCOME', name: 'Fixed Income', description: 'Bonds, Treasury Bills, Funds, Commercial Papers' }
];

const instruments = ref([]);
const isMarketLoading = ref(true);
const marketError = ref('');

const sparkOptions = {
  chart: { 
    toolbar: { show: false }, 
    sparkline: { enabled: true },
    animations: { enabled: false }
  },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false }
};

// Computed Operational Filters & Properties
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
  FIXED_INCOME: instruments.value.map(i => ({ ...i, currency: i.currency || 'NGN' }))
}));

const filteredInstruments = computed(() => {
  const query = search.value.toLowerCase().trim();
  if (!query) return instruments.value;
  return instruments.value.filter(i => 
    i.name.toLowerCase().includes(query) || 
    i.symbol.toLowerCase().includes(query)
  );
});

// Dynamic Network/Calculation Interfaces
const fetchWalletBalances = async () => {
  try {
    const response = await api.get('/wallet/balances');
    walletBalances.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch wallet balances', error);
  }
};

const fetchInstruments = async () => {
  isMarketLoading.value = true;
  loading.value = true;
  marketError.value = '';

  try {
    const response = await api.get('/fixed-income/products');
    instruments.value = (response.data.data || []).map((product) => ({
      symbol: product.code,
      name: product.name,
      issuer: product.issuer,
      yield: Number(product.interest_rate || 0),
      change: 0,
      volume: Number(product.maximum_capacity || 0),
      price: Number(product.minimum_amount || 0),
      spark: [Number(product.interest_rate || 0)],
      productId: product.id,
      currency: product.currency,
    }));
  } catch (error) {
    marketError.value = error.response?.data?.message || 'Unable to load fixed income products.';
  } finally {
    isMarketLoading.value = false;
    loading.value = false;
  }
};

const fetchPortfolioPerformance = async (range = '1W') => {
  isGraphLoading.value = true;
  try {
    const params = { category: 'fixed_income', range: typeof range === 'object' ? 'CUSTOM' : range };
    if (params.range === 'CUSTOM') { 
      params.start = range.start; 
      params.end = range.end; 
    }
    const response = await api.get(`/portfolio/history`, { params });
    portfolioData.value = response.data.series;
    totalValue.value = response.data.total;
    changePercent.value = response.data.change;
  } catch (e) { 
    console.error('Fixed income metrics payload gathering failed:', e); 
  } finally { 
    isGraphLoading.value = false; 
  }
};

const fetchInvestmentHistory = async () => {
  try {
    const response = await api.get('/fixed-income/investments');
    investments.value = response.data.data?.data || [];
  } catch (error) {
    console.error('Fixed income history payload gathering failed:', error);
  }
};

// Interactive Layout Emission Controls
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
  openTrade(instruments.value[0] || {});
};

const openTrade = (instrument) => { 
  if (!instrument || !instrument.symbol) return;
  
  if (!canTrade.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  
  if (!isUserVerified.value && !isDemo.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  
  selectedTradeInstrument.value = { ...instrument, currency: instrument.currency || 'NGN' }; 
  showTradeModal.value = true; 
};

onMounted(() => {
  Promise.allSettled([
    fetchInstruments(),
    fetchPortfolioPerformance(),
    fetchWalletBalances(),
    fetchInvestmentHistory()
  ]);
});
</script>