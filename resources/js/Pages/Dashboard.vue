<template>
  <MainLayout :isDemo="isDemo">
    <div class="space-y-6">
      <EmailVerificationPrompt v-if="showPrompt" :user="user" />

      <!-- Enhanced Subscription & Billing Management -->
      <DashboardBillingBanner 
        :subscription="{ 
          status: user.subscription_status || (user.on_trial ? 'trial' : 'active'), 
          days_left: daysUntil(user.trial_expires_at),
          debt: user.wallet_debt || 0
        }"
        @actionClicked="$router.push({ name: 'wallet' })"
      />

      
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-semibold">Hi, {{ userName }}</h1>
          <p class="text-sm text-gray-400">Here's your dashboard with an overview of your portfolio.</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-3 relative">
        
          <div class="relative">
            <button 
              @click="toggleWatchlistMenu"
              v-click-outside="closeWatchlistMenu"
              class="flex flex-col items-center justify-center p-2 rounded-xl border border-[#1f3348] bg-[#111827]/60 hover:bg-[#1f3348]/40 transition text-gray-300 hover:text-[#00D4FF] min-w-[100px]"
            >
              <span class="text-xl">⭐</span>
              <span class="text-[10px] font-medium tracking-wide mt-0.5 uppercase">Watchlist</span>
            </button>

            
            <div 
              v-if="showWatchlistMenu" 
              class="absolute right-0 mt-2 w-72 bg-[#0F1724] border border-[#1f3348] rounded-xl shadow-2xl z-50 p-4 space-y-3 animate-fadeIn"
            >
              <div class="flex items-center justify-between border-b border-[#1f3348] pb-2">
                <div class="font-semibold text-xs text-gray-300">⭐ Watchlist Preview</div>
                <button @click="$router.push({ name: 'watchlist' })" class="text-[11px] text-blue-400 hover:underline">View All</button>
              </div>

              <div v-if="watchlistItems.length === 0" class="py-4 italic text-center text-gray-500 text-xs">
                No assets watched yet.
              </div>
              <div v-else class="space-y-2 max-h-60 overflow-y-auto pr-1">
                <div v-for="item in watchlistItems.slice(0, 5)" :key="item.id" class="flex items-center justify-between p-1.5 rounded hover:bg-[#16213A] transition">
                  <div>
                    <div class="text-xs font-bold text-white">{{ item.symbol }}</div>
                    <div class="text-[9px] text-gray-500 uppercase">{{ item.market }}</div>
                  </div>
                  <div class="text-right">
                    <div class="text-xs font-semibold text-[#00D4FF]">₦{{ Number(item.added_price).toLocaleString() }}</div>
                    <div class="text-[9px] text-gray-500">Added Price</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <button @click="handleTradeAction" :class="[
            'px-6 py-3 rounded-lg text-white font-bold hover:opacity-90 transition shadow-lg text-sm h-full',
            isDemo ? 'bg-gradient-to-r from-yellow-600 to-orange-500' : 'bg-gradient-to-r from-[#0047AB] to-[#00D4FF]'
          ]">
            {{ isDemo ? '⇄ Demo Trade' : '⇄ Trade' }}
          </button>
          
          <div @click="$router.push({ name: 'wallet' })"
            class="text-right hidden sm:block p-3 px-5 rounded-xl border border-[#1f3348] cursor-pointer transition-all active:scale-95"
            :class="isDemo ? 'border-yellow-600 bg-yellow-600/10 hover:bg-yellow-600/40' : 'border-[#1f3348] bg-[#111827]/60 hover:bg-[#1f3348]/40'">
            <div class="text-[10px] text-gray-400 uppercase tracking-wider">
              <span v-if="isDemo" class="mr-1 font-bold text-yellow-500">DEMO</span> Wallet Balance
            </div>
            <div class="text-xl font-black transition-all duration-300"
              :class="[isDemo ? 'text-yellow-400' : 'text-white', loading ? 'opacity-30' : '']">
              ₦{{ walletBalance.toLocaleString() }}
            </div>
          </div>
          
          <TradeModal :show="showTradeModal" :tickers="tickers" :assetCategories="assetCategories"
            @close="showTradeModal = false" @trade-success="fetchDashboard" />
        </div>
      </div>

      <div v-if="loading" class="grid grid-cols-1 gap-4 md:grid-cols-4 lg:grid-cols-6">
        <SkeletonLoader type="card" class="col-span-1 md:col-span-2 opacity-40" />
        <SkeletonLoader type="card" class="opacity-40" v-for="n in 4" :key="n" />
      </div>
      <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-4 lg:grid-cols-6">
        <div @click="$router.push({ name: 'portfolio' })"
          class="col-span-1 p-4 transition-all border cursor-pointer md:col-span-2 rounded-xl active:scale-95"
          :class="isDemo ? 'border-yellow-600 bg-yellow-600/10 hover:bg-yellow-600/40' : 'border-[#1f3348] bg-[#111827]/60 hover:bg-[#1f3348]/40'">
          <div class="text-xs text-gray-400">
            <span v-if="isDemo" class="mr-1 font-bold text-yellow-500">DEMO</span> Total Portfolio Value
          </div>
          <div class="text-2xl font-bold transition-colors" :class="isDemo ? 'text-yellow-400' : 'text-white'">
            ₦{{ totalEquity.toLocaleString() }}
          </div>
          <div v-if="data?.daily_change" class="text-[10px] font-bold mt-1" :class="data.daily_change >= 0 ? 'text-emerald-400' : 'text-rose-400'">
            {{ data.daily_change >= 0 ? '▲' : '▼' }} ₦{{ Math.abs(data.daily_change).toLocaleString() }} today
          </div>
        </div>
        <div @click="$router.push({ name: 'ngx' })"
          class="p-4 transition-all border cursor-pointer rounded-xl active:scale-95"
          :class="isDemo ? 'border-yellow-600 bg-yellow-600/10 hover:bg-yellow-600/40' : 'border-[#1f3348] bg-[#111827]/60 hover:bg-[#1f3348]/40'">
          <div class="text-xs text-gray-400">NGX</div>
          <div class="text-xl font-semibold">₦{{ ngxValue.toLocaleString() }}</div>
        </div>
        <div @click="$router.push({ name: 'global-stocks' })"
          class="p-4 transition-all border cursor-pointer rounded-xl active:scale-95"
          :class="isDemo ? 'border-yellow-600 bg-yellow-600/10 hover:bg-yellow-600/40' : 'border-[#1f3348] bg-[#111827]/60 hover:bg-[#1f3348]/40'">
          <div class="text-xs text-gray-400">US Stocks</div>
          <div class="text-xl font-semibold">{{ formatCurrency(globalValueUSD, 'USD') }}</div>
        </div>
        <div @click="$router.push({ name: 'crypto' })"
          class="p-4 rounded-xl border cursor-pointer hover:bg-[#1f3348]/40 transition-all active:scale-95"
          :class="isDemo ? 'border-yellow-600 bg-yellow-600/10 hover:bg-yellow-600/40' : 'border-[#1f3348] bg-[#111827]/60 hover:bg-[#1f3348]/40'">
          <div class="text-xs text-gray-400">Crypto</div>
          <div class="text-xl font-semibold">{{ formatCurrency(cryptoValueUSD, 'USD') }}</div>
        </div>
        <div @click="$router.push({ name: 'fixed-income' })"
          class="p-4 transition-all border cursor-pointer rounded-xl active:scale-95"
          :class="isDemo ? 'border-yellow-600 bg-yellow-600/10 hover:bg-yellow-600/40' : 'border-[#1f3348] bg-[#111827]/60 hover:bg-[#1f3348]/40'">
          <div class="text-xs text-gray-400">Fixed Income</div>
          <div class="text-xl font-semibold">₦{{ fixedIncomeValue.toLocaleString() }}</div>
        </div>
      </div>

      <!-- Charts Data Presentation Section -->
      <div v-if="loading" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <SkeletonLoader type="card" class="lg:col-span-2 opacity-40" />
        <SkeletonLoader type="card" class="opacity-40" />
      </div>
      <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 bg-[#0F1724] p-4 rounded-xl border" :class="isDemo ? 'border-yellow-600' : 'border-[#1f3348]'">
          <div class="mb-3 font-semibold">Performance</div>
          <apexchart type="line" height="260" :options="perfOptions" :series="perfSeries" />
        </div>
        <div class="bg-[#0F1724] p-4 rounded-xl border" :class="isDemo ? 'border-yellow-600' : 'border-[#1f3348]'">
          <div class="mb-2 font-semibold">Distribution</div>
          <apexchart type="donut" height="260" :options="donutOptions" :series="donutSeries" />
        </div>
      </div>

      <div v-if="loading" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <SkeletonLoader type="table" class="opacity-40" />
        <SkeletonLoader type="table" class="opacity-40" />
      </div>
      <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Holdings Table Card Container -->
        <div class="bg-[#0F1724] p-4 rounded-xl border" :class="isDemo ? 'border-yellow-600' : 'border-[#1f3348]'">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Holdings</div>
            <div class="text-xs text-gray-400">Sort by value</div>
          </div>

          <table class="w-full text-[11px] md:text-sm text-center">
            <thead class="text-gray-400 text-xs border-b border-[#1f2a44]">
              <tr>
                <th class="text-left pb-2">Asset</th>
                <th class="pb-2">Qty</th>
                <th class="pb-2">Avg Cost</th>
                <th class="pb-2">Market Price</th>
                <th class="pb-2">Unrealised</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!data?.holdings || data.holdings.length === 0">
                <td colspan="5" class="py-6 italic text-center text-gray-500">You currently hold no assets.</td>
              </tr>
              <tr v-else v-for="h in data.holdings" :key="h.symbol" class="border-b border-[#1f2a44] last:border-0">
                <td class="py-3 text-left">
                  <div class="font-medium">{{ h.symbol }}</div>
                  <div class="text-xs text-gray-400">{{ h.name }}</div>
                </td>
                <td>{{ h.quantity }}</td>
                <td>₦{{ Number(h.avg_price_ngn || h.avg_price).toLocaleString() }}</td>
                <td>{{ h.currency === 'USD' ? '$' : '₦' }}{{ Number(h.market_price).toLocaleString() }}</td>
                <td :class="(h.total_value_ngn - (h.avg_price_ngn * h.quantity)) >= 0 ? 'text-green-400' : 'text-red-400'">
                  {{ (h.total_value_ngn - (h.avg_price_ngn * h.quantity)) >= 0 ? '+' : '' }}₦{{ ((h.total_value_ngn || 0) - ((h.avg_price_ngn || 0) * h.quantity)).toLocaleString() }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="bg-[#0F1724] p-4 rounded-xl border" :class="isDemo ? 'border-yellow-600' : 'border-[#1f3348]'">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Recent Transactions</div>
            <div class="text-xs text-gray-400">All activity</div>
          </div>

          <div v-if="transactions.length === 0" class="py-6 italic text-center text-gray-500">No recent activity.</div>
          <ul v-else class="space-y-2 text-sm text-gray-300">
            <li v-for="t in transactions" :key="t.id || t.ref" @click="openTransactionDetails(t)"
              class="flex items-center justify-between p-2 rounded hover:bg-[#122033] cursor-pointer transition-colors group">
              <div>
                <div class="font-medium">{{ t.type }} — {{ t.currency || 'NGN' }}</div>
                <div class="text-xs text-gray-400">
                  {{ formatDate(t.created_at) }} • ref: {{ t.reference || t.id || 'DEMO' }}
                </div>
              </div>
              <div class="text-right">
                <div class="font-medium" :class="t.type.toLowerCase().includes('deposit') || t.type === 'buy' ? 'text-green-400' : 'text-white'">
                  {{ t.type === 'withdrawal' ? '-' : '' }}{{ t.currency === 'USD' ? '$' : '₦' }}{{ Number(t.amount || t.total || 0).toLocaleString() }}
                </div>
                <div class="text-xs text-gray-400">{{ t.status || 'Completed' }}</div>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <TransactionDetailsModal :show="showDetailsModal" :txn="selectedTransaction" @close="showDetailsModal = false" />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from "vue";
import api from "@/api";
import { formatCurrency } from '@/lib/formatters';
import VueApexCharts from "vue3-apexcharts";
import MainLayout from "@/Layouts/MainLayout.vue";
import TradeModal from "@/Components/TradeModal.vue";
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import TransactionDetailsModal from "@/Components/TransactionDetailsModal.vue";
import DashboardBillingBanner from "@/Components/DashboardBillingBanner.vue";
import SkeletonLoader from "@/Components/SkeletonLoader.vue";

const apexchart = VueApexCharts;

// state
const loading = ref(true);
const data = ref({});
const transactions = ref([]);
const watchlistItems = ref([]);
const error = ref(null);
const showTradeModal = ref(false);
const selectedTransaction = ref(null);
const showDetailsModal = ref(false);
const showPrompt = ref(false);
const showWatchlistMenu = ref(false);

const getUser = () => JSON.parse(localStorage.getItem('user') || '{}'); 
const user = ref(getUser());
const isDemo = ref(user.value.trading_mode === 'demo');

const userName = computed(() => user.value.name || 'User');

// Menu toggle method logic
const toggleWatchlistMenu = () => {
  showWatchlistMenu.value = !showWatchlistMenu.value;
};
const closeWatchlistMenu = () => {
  showWatchlistMenu.value = false;
};

const syncModeWithStorage = () => {
  const updatedUser = getUser();
  user.value = updatedUser;
  isDemo.value = updatedUser.trading_mode === 'demo';
};

const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  if (role.includes('admin')) return true;
  if (Array.isArray(u.roles)) {
    return u.roles.some((r) => {
      const candidate = (typeof r === 'string' ? r : r?.name || '').toString().toLowerCase();
      return candidate.includes('admin');
    });
  }
  return false;
};

const syncUserProfile = async () => {
  try {
    const res = await api.get('/profile/me');
    const updatedUser = res.data?.data || res.data;
    if (updatedUser) {
      user.value = updatedUser;
      localStorage.setItem("user", JSON.stringify(updatedUser));
    }
  } catch (err) {
    console.error("Failed to sync user profile:", err);
  }
};

const isUserVerified = computed(() => {
  const u = user.value;
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

const handleTradeAction = async () => {
  if (!isUserVerified.value && !isDemo.value) {
    await syncUserProfile();
    if (isUserVerified.value) return (showTradeModal.value = true);
    showPrompt.value = true;
    return;
  }
  showTradeModal.value = true;
};

// Asset Mapping
const assetCategories = [
  { id: 'NGX', name: 'Local Stocks (NGX)', description: 'Nigerian Stock Exchange' },
  { id: 'GLOBAL', name: 'Global Stocks (USD)', description: 'US Markets (Tesla, Apple, etc.)' },
  { id: 'CRYPTO', name: 'Cryptocurrency (USD)', description: 'Bitcoin & Digital Assets' },
  { id: 'FIXED_INCOME', name: 'Fixed Income', description: 'Fixed Income Market' }
];

const tickers = {
  NGX: [
    { symbol: 'MTNN', name: 'MTN Nigeria', price: 245.50, currency: 'NGN' },
    { symbol: 'DANGCEM', name: 'Dangote Cement', price: 320.00, currency: 'NGN' },
    { symbol: 'ZENITH', name: 'Zenith Bank', price: 35.20, currency: 'NGN' }
  ],
  GLOBAL: [
    { symbol: 'TSLA', name: 'Tesla Inc.', price: 175.40, currency: 'USD' },
    { symbol: 'AAPL', name: 'Apple Inc.', price: 189.10, currency: 'USD' },
    { symbol: 'NVDA', name: 'Nvidia Corp.', price: 820.50, currency: 'USD' }
  ],
  CRYPTO: [
    { symbol: 'BTC', name: 'Bitcoin', price: 64250.00, currency: 'USD' },
    { symbol: 'ETH', name: 'Ethereum', price: 3450.00, currency: 'USD' },
    { symbol: 'SOL', name: 'Solana', price: 145.00, currency: 'USD' }
  ],
  FIXED_INCOME: [
    { symbol: 'FGNSB_2027', name: 'FGN Savings Bond 2027', price: 1000.00, currency: 'NGN' },
    { symbol: 'CP_MTN_I', name: 'MTN Commercial Paper', price: 1000.00, currency: 'NGN' },
    { symbol: 'ABB2026S0', name: 'FGN Bond Jan 2026', price: 1000.00, currency: 'NGN' }
  ]
};

const walletBalance = computed(() => (data.value?.wallet_balance ?? 0));
const ngxValue = computed(() => data.value?.ngx_value ?? 0);
const fixedIncomeValue = computed(() => data.value?.fixed_income_value ?? 0);
const globalValueUSD = computed(() => data.value?.global_stocks_value_usd ?? 0);
const cryptoValueUSD = computed(() => data.value?.crypto_value_usd ?? 0);
const totalEquity = computed(() => data.value?.total_equity ?? 0);

const perfSeries = ref([]);
const perfOptions = ref({
  chart: { id: "performance", toolbar: { show: false }, animations: { enabled: true } },
  xaxis: { categories: ["7d-6", "7d-5", "7d-4", "7d-3", "7d-2", "7d-1", "Today"] },
  stroke: { curve: "smooth", width: 2 },
  markers: { size: 3 },
  theme: { mode: "dark" },
  colors: ["#00D4FF", "#0047AB"],
  legend: { position: "top" },
  yaxis: { labels: { formatter: (val) => "₦" + Number(val).toLocaleString() } }
});

const donutOptions = ref({
  chart: { type: "donut", toolbar: { show: false } },
  labels: ["Wallet", "NGX", "Global Stocks (USD)", "Crypto (USD)", "Fixed Income"],
  theme: { mode: "dark" },
  legend: { position: "bottom" },
  colors: ["#00D4FF", "#0047AB", "#00A3FF", "#8CFF66", "#4d5c72"],
});

const donutSeries = ref([0, 0, 0, 0, 0]);

const formatDate = (dateStr) => {
  if (!dateStr) return "";
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-NG', { year: 'numeric', month: 'short', day: 'numeric' });
};

const daysUntil = (dateStr) => {
  if (!dateStr) return 0;
  const diff = new Date(dateStr) - new Date();
  return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
};

function generateTrend(currentValue) {
  const val = Number(currentValue || 0);
  const points = [];
  let lastVal = val;
  for (let i = 0; i < 7; i++) {
    points.unshift(lastVal);
    const variance = 1 + (Math.random() * 0.02 - 0.01);
    lastVal = lastVal / variance;
  }
  return points;
}

async function openTransactionDetails(t) {
  const localTxn = typeof t === 'object' ? t : transactions.value.find(t => t.id === t);
  if (localTxn) {
    selectedTransaction.value = { ...localTxn };
    showDetailsModal.value = true;
    try {
      const resp = await api.get(`/transactions/${localTxn.id}`);
      selectedTransaction.value = resp.data.data;
    } catch (e) {
      console.error("Background detail fetch failed", e);
    }
  }
}

const handleModeSwitching = (e) => {
  isDemo.value = (e.detail.mode === 'demo' || e.detail === 'demo');
  fetchDashboard(); 
};

async function fetchDashboard() {
  loading.value = true;
  error.value = null;
  try {
    const [portfolioResp, transResp, watchlistResp] = await Promise.all([
      api.get(`/portfolio`),
      api.get(`/transactions`),
      api.get(`/watchlist`)
    ]);

    const rawData = portfolioResp.data.data;
    data.value = rawData;
    watchlistItems.value = watchlistResp.data.data || watchlistResp.data || [];

    const rawTxns = transResp.data.data || transResp.data;
    transactions.value = Array.isArray(rawTxns) ? rawTxns : (rawTxns.transactions || []);

    if (data.value.portfolio_distribution) {
      donutSeries.value = data.value.portfolio_distribution.map(p => Number(p.value));
      donutOptions.value.labels = data.value.portfolio_distribution.map(p => p.label);
    } else {
      donutSeries.value = [
        Number(data.value.wallet_balance || 0),
        Number(data.value.ngx_value || 0),
        Number(data.value.global_stocks_value_usd || 0),
        Number(data.value.crypto_value_usd || 0),
        Number(data.value.fixed_income_value || 0)
      ];
    }

    if (data.value.series_performance && data.value.series_performance.length > 0) {
      perfSeries.value = data.value.series_performance.map(s => ({
        name: s.name,
        data: s.data.map(val => Number(val))
      }));
    } else {
      perfSeries.value = [
        { name: "Total Equity", data: generateTrend(Number(data.value.total_equity || 0)) },
        { name: "Wallet", data: generateTrend(Number(data.value.wallet_balance || 0)) }
      ];
    }
  } catch (e) {
    console.error("Dashboard Load Error:", e);
    error.value = "Dashboard unavailable.";
  } finally {
    loading.value = false;
  }
}


const vClickOutside = {
  mounted(el, binding) {
    el.clickOutsideEvent = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value(event);
      }
    };
    document.addEventListener("click", el.clickOutsideEvent);
  },
  unmounted(el) {
    document.removeEventListener("click", el.clickOutsideEvent);
  }
};

onMounted(async () => {
  syncModeWithStorage(); 
  await syncUserProfile();
  await fetchDashboard();

  window.addEventListener('trading-mode-switching', handleModeSwitching);
  window.addEventListener('trading-mode-changed', fetchDashboard);
});

onUnmounted(() => {
  window.removeEventListener('trading-mode-switching', handleModeSwitching);
  window.removeEventListener('trading-mode-changed', fetchDashboard);
});
</script>

<script>
import { defineComponent } from "vue";
export default defineComponent({
  components: { apexchart: VueApexCharts },
});
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.15s ease-out forwards;
}
</style>