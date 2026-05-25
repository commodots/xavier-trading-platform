<template>
  <MainLayout>
    <div class="space-y-6">
      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      
      <!-- Top Bar: Header and Search -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">₿ Crypto</h1>
        <div class="relative">
          <input 
            v-model="search" 
            type="text" 
            placeholder="Search crypto..."
            class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] outline-none w-64 transition-all" 
          />
        </div>
      </div>

      <!-- Segmented Control View Switching Tabs -->
      <div class="flex items-center justify-between mb-4">
        <div class="flex p-1 bg-[#0B121D] border border-[#1f3348] rounded-lg w-fit">
          <button 
            v-for="view in ['holdings', 'market', 'history']" 
            :key="view"
            @click="activeView = view"
            class="px-4 py-2 text-xs font-bold uppercase transition-all rounded-md"
            :class="activeView === view ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-300'"
          >
            {{ view === 'holdings' ? 'My Holdings' : view === 'market' ? 'Market' : 'History' }}
          </button>
        </div>
        <button 
          @click="activeView = 'trading'"
          class="px-6 py-2 text-xs font-bold uppercase transition-all rounded-lg bg-blue-600 text-white shadow-lg hover:bg-blue-700"
        >
          Buy / Sell
        </button>
      </div>

      <!-- Main Display Dynamic Switchboards -->
      <div class="space-y-6">
        <!-- Tab A: My Personal Wallet Holdings -->
        <div v-if="activeView === 'holdings'" class="space-y-6">
          <HoldingPerformanceChart 
            title="My Crypto Holdings" 
            currencySymbol="$" 
            :seriesData="portfolioData"
            :totalValue="totalValue" 
            :percentageChange="changePercent" 
            :loading="isGraphLoading || loading"
            @rangeChange="fetchPortfolioPerformance" 
          />

          <div v-if="loading" class="mt-6">
            <SkeletonLoader type="table" class="opacity-40" />
          </div>
          <div v-else class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-hidden">
            <div class="p-4 border-b border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
              <h2 class="font-semibold text-gray-200">My Holdings</h2>
              <span class="text-xs text-gray-500">{{ filteredHoldings.length }} Cryptos in Wallet</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="text-gray-400 border-b border-[#1f3348] bg-[#0B121D]">
                  <tr>
                    <th class="px-6 py-4 font-medium text-left">Symbol</th>
                    <th class="font-medium text-left">Asset</th>
                    <th class="font-medium text-right">Price</th>
                    <th class="font-medium text-right">Quantity</th>
                    <th class="font-medium text-right">Value (USD)</th>
                    <th class="px-6 font-medium text-right">Trend</th>
                    <th class="px-6 font-medium text-center">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#1f3348]">
                  <tr v-for="asset in filteredHoldings" :key="asset.symbol" class="hover:bg-[#16213A] transition">
                    <td class="px-6 py-4 font-bold text-[#F7931A]">{{ asset.symbol }}</td>
                    <td class="text-gray-300">{{ asset.name }}</td>
                    <td class="font-mono text-right text-white">${{ asset.price?.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
                    <td class="text-right text-gray-300 font-mono">{{ asset.balance?.toLocaleString(undefined, { maximumFractionDigits: 6 }) }} {{ asset.symbol }}</td>
                    <td class="font-bold text-right text-white font-mono">${{ (asset.balance * asset.price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</td>
                    <td class="w-32 px-6 text-right">
                      <apexchart type="line" height="25" :options="sparkOptions" :series="[{ data: asset.spark || [] }]" />
                    </td>
                    <td class="px-6 text-center">
                      <button @click="openBuyModal(asset)" class="bg-[#00D4FF]/10 text-[#00D4FF] border border-[#00D4FF]/20 px-3 py-1 rounded hover:bg-[#00D4FF] hover:text-[#0F1724] transition text-xs font-bold">
                        Buy
                      </button>
                    </td>
                  </tr>
                  <tr v-if="filteredHoldings.length === 0">
                    <td colspan="7" class="p-10 text-center text-gray-500 italic">No holdings found matching your search.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Tab B: Global Live Coins Market Asset Spotboards -->
        <div v-else-if="activeView === 'market'">
           <CryptoMarketAssets :coins="coins" :searchQuery="search" :loading="loading" @view-details="openDetails" @trade="openTrade" />
        </div>

        <!-- Tab C: Trade Executive Interface Panel Layout -->
        <div v-else-if="activeView === 'trading'">
           <Trading />
        </div>

        <div v-else-if="activeView === 'history'" class="py-20 text-center text-gray-500 italic border border-dashed border-[#1f3348] rounded-xl">
           Crypto transaction history coming soon.
        </div>
      </div>

      <!-- Details Information Pop-up Modal Box Container -->
      <MarketDetailsModal :isOpen="isModalOpen" :item="selectedItem" currencySymbol="$" @close="isModalOpen = false" />
      
      <!-- Instant Checkout Quick Buy Form Modal Overlay Wrapper -->
      <div v-if="buyModal.show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl w-full max-w-md p-6 shadow-2xl">
          <h3 class="text-xl font-bold text-white mb-4">Buy {{ buyModal.asset?.name }}</h3>

          <div class="space-y-4">
            <div>
              <label class="block mb-2 text-sm text-gray-400">Current Price</label>
              <p class="text-[#00D4FF] font-bold">${{ (buyModal.asset?.price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</p>
            </div>

            <div>
              <label class="block mb-2 text-sm text-gray-400">Amount (USD)</label>
              <input v-model.number="buyForm.amount" type="number" placeholder="1000" min="1" step="1"
                class="w-full px-4 py-2 bg-[#111827] border border-[#1f3348] rounded-lg text-white placeholder-gray-600 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none" />
            </div>

            <div>
              <label class="block mb-2 text-sm text-gray-400">Estimated Quantity</label>
              <p class="text-gray-300 font-mono">{{ estimatedQuantity }} {{ buyModal.asset?.symbol }}</p>
            </div>

            <div v-if="errorMessage" class="text-xs text-red-400 font-medium">{{ errorMessage }}</div>
            <div v-if="successMessage" class="text-xs text-green-400 font-medium">{{ successMessage }}</div>

            <div class="flex gap-3 pt-2">
              <button @click="buyModal.show = false"
                class="flex-1 px-4 py-2 text-gray-300 transition bg-gray-800 rounded-lg hover:bg-gray-700">Cancel</button>
              <button @click="executeBuy" :disabled="tradeLoading || !buyForm.amount"
                class="flex-1 px-4 py-2 font-bold text-black transition bg-[#00D4FF] rounded-lg hover:bg-[#00b8e6] disabled:opacity-50">
                {{ tradeLoading ? 'Processing...' : 'Confirm Buy' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import apexchart from "vue3-apexcharts";
import MarketDetailsModal from "@/Components/MarketDetailsModal.vue";
import HoldingPerformanceChart from "@/Components/HoldingPerformanceChart.vue";
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import api from "@/api";
import { useRoute } from 'vue-router';

import CryptoMarketAssets from "@/Components/Markets/CryptoMarketAssets.vue";
import Trading from "@/Components/Markets/Trading.vue";
import SkeletonLoader from "@/Components/SkeletonLoader.vue"

const route = useRoute();

// Authentication & App Environment Configuration States
const user = ref(JSON.parse(localStorage.getItem('user') || '{}'));
const isDemo = ref(user.value.trading_mode === 'demo');
const showPrompt = ref(false);

// Structural Layout Navigation Control Switches
const activeView = ref(route.query.view || 'holdings');
const isModalOpen = ref(false);
const selectedItem = ref(null);
const search = ref("");

// Chart, History Streams, Metrics Calculations Hooks
const isGraphLoading = ref(false);
const portfolioData = ref([]);
const totalValue = ref(0);
const changePercent = ref(0);

// Order Entry Formulation State Containers
const buyModal = ref({ show: false, asset: null });
const buyForm = ref({ amount: 1000 });
const tradeLoading = ref(false);
const errorMessage = ref("");
const successMessage = ref("");

const holdings = ref([]);
const coins = ref([]);

const sparkOptions = {
  chart: { 
    sparkline: { enabled: true }, 
    animations: { enabled: false },
    toolbar: { show: false } 
  },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false }
};

// Computed User Identity Access Profiles
const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  return role.includes('admin');
};

const isUserVerified = computed(() => {
  const u = user.value || {};
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

// Computed Multi-Currency Calculated Assets Lists Evaluation
const filteredHoldings = computed(() => {
  if (activeView.value !== 'holdings') return [];
  const q = search.value.toLowerCase().trim();
  if (!q) return holdings.value;
  return holdings.value.filter(h => 
    h.name.toLowerCase().includes(q) || 
    h.symbol.toLowerCase().includes(q)
  );
});

const estimatedQuantity = computed(() => {
  if (!buyModal.value.asset?.price || !buyForm.value.amount) return (0).toFixed(6);
  return (buyForm.value.amount / buyModal.value.asset.price).toFixed(6);
});

// Network Communications Control Architecture Channels
const fetchCoins = async () => {
  try {
    const res = await api.get('/market/crypto');
    coins.value = (res.data.data || []).map(item => ({
      ...item,
      change: item.change ?? 0,
      marketcap: item.marketcap ?? 0,
      spark: item.spark ?? [item.price, item.price, item.price],
    }));
    
    // Sync prices with active open records immediately inside continuous callbacks
    await fetchHoldings();
  } catch (e) {
    console.error('Coins payload collection failure:', e);
  }
};

const fetchHoldings = async () => {
  try {
    const res = await api.get('/trade/positions', { params: { category: 'crypto' } });
    const positions = (res.data.data || []).filter(t => t.status === 'open');
    
    holdings.value = positions.map(p => {
      const symbol = p.pair.split('/')[0];
      const coinData = coins.value.find(c => c.symbol.toUpperCase() === symbol.toUpperCase());
      return {
        symbol: symbol,
        name: coinData ? coinData.name : symbol,
        price: coinData ? Number(coinData.price) : Number(p.entry_price),
        balance: Number(p.amount / p.entry_price),
        spark: coinData ? coinData.spark : []
      };
    });
  } catch (e) {
    console.error("Holdings mapping failure:", e);
  }
};

const fetchPortfolioPerformance = async (range = '1W') => {
  isGraphLoading.value = true;
  try {
    const response = await api.get(`/portfolio/history`, { params: { category: 'crypto', range } });
    portfolioData.value = response.data.series;
    totalValue.value = response.data.total;
    changePercent.value = response.data.change;
  } catch (e) { 
    console.error('Portfolio performance history fetch failed:', e); 
  } finally { 
    isGraphLoading.value = false; 
  }
};

// Event Submissions Action Pipelines
const openDetails = (item) => { 
  selectedItem.value = item; 
  isModalOpen.value = true; 
};

const openTrade = (coin) => {
  if (!isUserVerified.value && !isDemo.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  openBuyModal(coin);
};

const openBuyModal = (asset) => {
  buyModal.value = { show: true, asset: asset };
  buyForm.value.amount = 1000;
  errorMessage.value = "";
  successMessage.value = "";
};

const executeBuy = async () => {
  if (!buyForm.value.amount || buyForm.value.amount <= 0) {
    errorMessage.value = 'Please enter a valid amount';
    return;
  }

  tradeLoading.value = true;
  errorMessage.value = "";
  try {
    await api.post('/trade/open', {
      pair: buyModal.value.asset.symbol.toUpperCase() + '/USDT',
      amount: buyForm.value.amount,
      type: 'buy',
    });

    successMessage.value = `Successfully bought ${buyModal.value.asset.name}!`;

    await Promise.allSettled([
      fetchPortfolioPerformance(),
      fetchCoins()
    ]);

    setTimeout(() => {
      buyModal.value.show = false;
      successMessage.value = "";
    }, 2000);
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Failed to execute trade';
  } finally {
    tradeLoading.value = false;
  }
};

// Dynamic Watch Routing Pipelines 
watch(() => route.query.view, (newView) => {
  if (newView) activeView.value = newView;
});

watch(activeView, (newVal) => {
  if (newVal === 'holdings') fetchHoldings();
});

onMounted(() => {
  fetchPortfolioPerformance();
  fetchCoins();
});
</script>