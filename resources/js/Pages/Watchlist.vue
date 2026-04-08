<template>
  <MainLayout>
    <div class="space-y-8">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold">⭐ Watchlist</h1>
          <p class="text-sm text-gray-400">Track stocks you care about and compare added price with the latest market
            price.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <input v-model="searchQuery" type="text" placeholder="Search watchlist..."
            class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-white outline-none focus:border-[#00D4FF] focus:ring-0 w-full md:w-72" />

          <select v-model="filterMarket"
            class="bg-[#16213A] border border-[#1f3348] text-sm text-white rounded-lg py-2 outline-none focus:border-blue-500">
            <option value="">All Markets</option>
            <option value="NGX">NGX</option>
            <option value="GLOBAL">Global</option>
            <option value="CRYPTO">Crypto</option>
            <option value="FixedIncome">Fixed Income</option>

          </select>
        </div>
      </div>

      <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-hidden">
        <div class="p-4 border-b border-[#1f3348] flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-lg font-semibold">Your Watchlist</h2>
            <p class="text-sm text-gray-400">Stocks are saved locally and updated with the latest available price.</p>
          </div>
          <span class="text-xs text-gray-500">{{ filteredWatchlist.length }} items</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348] bg-[#0B121D]">
              <tr>
                <th class="px-4 py-3 text-left">Symbol</th>
                <th class="px-4 text-left">Name</th>
                <th class="px-4 text-left">Market</th>
                <th class="px-4 text-right">Added Price</th>
                <th class="px-4 text-right">Current Price</th>
                <th class="px-4 text-right">Change</th>
                <th class="px-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#1f3348]">
              <tr v-for="item in filteredWatchlist" :key="item.id"
                class="hover:bg-[#16213A] transition">
                <td class="px-4 py-4 font-semibold text-[#00D4FF]">{{ item.symbol }}</td>
                <td class="px-4 text-gray-300">{{ item.name }}</td>
                <td class="px-4 text-gray-400">{{ item.market }}</td>
                <td class="px-4 text-right text-white">{{ formatCurrency(item.addedPrice, item.currency) }}</td>
                <td class="px-4 text-right text-white">{{ formatCurrency(item.currentPrice, item.currency) }}</td>
                <td class="px-4 text-right" :class="item.changePercent >= 0 ? 'text-green-400' : 'text-red-400'">
                  {{ item.changePercent >= 0 ? '+' : '' }}{{ item.changePercent.toFixed(2) }}%
                </td>
                <td class="px-4 space-x-2 text-right whitespace-nowrap">
                  <button @click="openBuy(item)"
                    class="bg-[#00D4FF] text-[#0F1724] px-3 py-1.5 rounded-lg text-[11px] font-bold hover:bg-[#00b8e6] transition">
                    Buy/Sell
                  </button>
                  <button @click="removeFromWatchlist(item)"
                    class="bg-red-500/10 text-red-400 px-3 py-1.5 rounded-lg text-[11px] font-bold hover:bg-red-500/20 transition">
                    Remove
                  </button>
                </td>
              </tr>

              <tr v-if="filteredWatchlist.length === 0">
                <td colspan="7" class="py-16 text-center text-gray-500">
                  Your watchlist is empty. Add stocks from the markets to start tracking prices.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <TradeModal :show="showTradeModal" :tickers="tradeTickers" :assetCategories="assetCategories"
        :initialTicker="selectedWatchItem" @close="showTradeModal = false" @trade-success="onTradeSuccess" />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";
import TradeModal from "@/Components/TradeModal.vue";

const searchQuery = ref("");
const filterMarket = ref("");
const showTradeModal = ref(false);
const selectedWatchItem = ref(null);
const watchlistItems = ref([]);
const loading = ref(false);

// Ticker refs stay here for "Current Price" comparison
const ngxTickers = ref([]); 
const globalTickers = ref([]);
const cryptoTickers = ref([]);
const fixedIncomeTickers = ref([]);

const assetCategories = [
  { id: 'NGX', name: 'Local Stocks', description: 'Nigerian Exchange' },
  { id: 'GLOBAL', name: 'Global Stocks', description: 'International Markets' },
  { id: 'CRYPTO', name: 'Cryptocurrency', description: 'Digital Assets' },
  { id: 'FixedIncome', name: 'Fixed Income', description: 'Bonds & Bills' },
];

const tradeTickers = computed(() => ({
  NGX: ngxTickers.value,
  GLOBAL: globalTickers.value,
  CRYPTO: cryptoTickers.value,
  FixedIncome: fixedIncomeTickers.value,
}));

const formatCurrency = (value, currency) => {
  return (currency === 'USD' ? '$' : '₦') + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// FETCH FROM BACKEND
const fetchWatchlist = async () => {
  loading.value = true;
  try {
    const token = localStorage.getItem("xavier_token");
    const response = await api.get("/watchlist", {
      headers: { Authorization: `Bearer ${token}` }
    });
    // Laravel returns 'added_price', ensure it's treated as a number
    const data = response.data.data || response.data;
    watchlistItems.value = data.map(item => ({
      ...item,
      addedPrice: parseFloat(item.added_price) 
    }));
  } catch (error) {
    console.error("Failed to fetch watchlist:", error);
  } finally {
    loading.value = false;
  }
};

// FETCH MARKET PRICES
const fetchMarketPrices = async () => {
  try {
    const [ngx, glob, cryp] = await Promise.all([
      api.get('/market/ngx'),
      api.get('/market/global'),
      api.get('/market/crypto')
    ]);
    ngxTickers.value = ngx.data.data || ngx.data;
    globalTickers.value = glob.data.data || glob.data;
    cryptoTickers.value = cryp.data.data || cryp.data;
  } catch (error) {
    console.error("Failed to fetch market data", error);
  }
};

const filteredWatchlist = computed(() => {
  return watchlistWithPrices.value.filter(item => {
    const matchesSearch = item.symbol.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
                         item.name.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchesMarket = !filterMarket.value || item.market === filterMarket.value;
    return matchesSearch && matchesMarket;
  });
});

// HYDRATE WITH CURRENT PRICES
const watchlistWithPrices = computed(() => {
  return watchlistItems.value.map((item) => {
    const marketMap = {
      NGX: ngxTickers.value,
      GLOBAL: globalTickers.value,
      CRYPTO: cryptoTickers.value,
      FixedIncome: fixedIncomeTickers.value,
    };

    const marketPrices = marketMap[item.market] || [];
    const current = marketPrices.find(t => t.symbol === item.symbol)?.price ?? item.addedPrice;
    const changePercent = item.addedPrice > 0 ? ((current - item.addedPrice) / item.addedPrice) * 100 : 0;

    return { ...item, currentPrice: current, changePercent };
  });
});

// REMOVE FROM BACKEND
const removeFromWatchlist = async (item) => {
  try {
    const token = localStorage.getItem("xavier_token");
    const response = await api.delete(`/watchlist/${item.id}`, {
      headers: { Authorization: `Bearer ${token}` }
    });
    
    if (response.data.success) {
      // Remove from local array to update UI immediately
      watchlistItems.value = watchlistItems.value.filter(i => i.id !== item.id);
    }
  } catch (error) {
    alert("Could not remove item. Please try again.");
  }
};

const openBuy = (item) => {
  selectedWatchItem.value = item;
  showTradeModal.value = true;
};

onMounted(() => {
  fetchWatchlist();
  fetchMarketPrices();
});
</script>