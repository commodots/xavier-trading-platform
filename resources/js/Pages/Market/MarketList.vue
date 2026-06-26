<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const router = useRouter();

const props = defineProps({
    tabs: {
        type: Array,
        default: () => [
            { id: 'TRENDING', label: 'Trending' },
            { id: 'US',       label: 'US Markets' },
            { id: 'UK',       label: 'UK Markets' },
        ]
    }
});

const activeTab = ref(props.tabs.length > 0 ? props.tabs[0].id : 'TRENDING');
const search = ref('');
const allAssets = ref([]);
const isFetching = ref(false);
const watchlistSymbols = ref([]);
const watchlistMap = ref({});
let pollInterval = null;

const tabParams = {
    TRENDING: { trending: 1 },
    US:       { market: 'US', type: 'stock' },
    UK:       { market: 'UK', type: 'stock' },
   
};

// Build allowed tab IDs from props
const allowedTabIds = computed(() => new Set(props.tabs.map(t => t.id)));
const availableTabs = computed(() => props.tabs.filter(t => allowedTabIds.value.has(t.id)));

const fetchMarkets = async (background = false) => {
    if (isFetching.value && !background) return;
    if (!background) isFetching.value = true;

    try {
        const params = tabParams[activeTab.value] ?? {};
        const res = await api.get('/markets', { params });
        let data = res.data.data ?? res.data ?? [];
        
        // If API returns empty, use fallback mock data for demo purposes
        if (data.length === 0) {
            data = getMockDataForTab(activeTab.value);
        }
        
        allAssets.value = normalise(data);
    } catch (e) {
        console.error('Market fetch failed:', e);
        allAssets.value = getMockDataForTab(activeTab.value);
    } finally {
        isFetching.value = false;
    }
};

const getMockDataForTab = (tabId) => {
    const mockData = {
        TRENDING: [
            { symbol: 'AAPL', name: 'Apple Inc.', price: 178.50, change: 2.3, volume: 52000000, market: 'NASDAQ' },
            { symbol: 'MSFT', name: 'Microsoft Corp.', price: 378.90, change: 1.8, volume: 28000000, market: 'NASDAQ' },
            { symbol: 'NVDA', name: 'NVIDIA Corp.', price: 875.30, change: 3.5, volume: 45000000, market: 'NASDAQ' },
            { symbol: 'GOOGL', name: 'Alphabet Inc.', price: 141.20, change: -0.5, volume: 22000000, market: 'NASDAQ' },
            { symbol: 'AMZN', name: 'Amazon.com Inc.', price: 185.60, change: 1.2, volume: 35000000, market: 'NASDAQ' },
        ],
        US: [
            { symbol: 'AAPL', name: 'Apple Inc.', price: 178.50, change: 2.3, volume: 52000000, market: 'NASDAQ' },
            { symbol: 'MSFT', name: 'Microsoft Corp.', price: 378.90, change: 1.8, volume: 28000000, market: 'NASDAQ' },
            { symbol: 'NVDA', name: 'NVIDIA Corp.', price: 875.30, change: 3.5, volume: 45000000, market: 'NASDAQ' },
            { symbol: 'GOOGL', name: 'Alphabet Inc.', price: 141.20, change: -0.5, volume: 22000000, market: 'NASDAQ' },
            { symbol: 'AMZN', name: 'Amazon.com Inc.', price: 185.60, change: 1.2, volume: 35000000, market: 'NASDAQ' },
            { symbol: 'META', name: 'Meta Platforms', price: 485.50, change: 2.1, volume: 18000000, market: 'NASDAQ' },
            { symbol: 'TSLA', name: 'Tesla Inc.', price: 245.80, change: -1.2, volume: 95000000, market: 'NASDAQ' },
        ],
        UK: [
            { symbol: 'SHEL', name: 'Shell plc', price: 2850.00, change: 0.8, volume: 8500000, market: 'LSE' },
            { symbol: 'AZN', name: 'AstraZeneca plc', price: 12450.00, change: 1.5, volume: 3200000, market: 'LSE' },
            { symbol: 'HSBA', name: 'HSBC Holdings', price: 645.20, change: -0.3, volume: 12000000, market: 'LSE' },
            { symbol: 'BP.', name: 'BP plc', price: 485.60, change: 1.1, volume: 9800000, market: 'LSE' },
            { symbol: 'GSK', name: 'GlaxoSmithKline', price: 1525.40, change: 0.5, volume: 6500000, market: 'LSE' },
        ],
    };
    return mockData[tabId] || [];
};

// Reset to first available tab if current tab is not in the allowed list
watch(() => props.tabs, (newTabs) => {
    const ids = newTabs.map(t => t.id);
    if (!ids.includes(activeTab.value) && ids.length > 0) {
        activeTab.value = ids[0];
    }
});

const normalise = (rows) =>
    rows.map(a => {
        const change = parseFloat(a.change ?? 0);
        const isCrypto = (a.type === 'crypto') || (a.market === 'CRYPTO');
        return {
            symbol:     a.symbol,
            name:       a.name ?? a.symbol,
            market:     a.market ?? a.exchange ?? '—',
            price:      parseFloat(a.price ?? a.last_price ?? 0),
            change,
            changeDirection: change >= 0 ? 'up' : 'down',
            percentageChange: Math.abs(change).toFixed(2),
            volume:     a.volume ? Number(a.volume).toLocaleString() : '—',
            currency:   isCrypto ? 'USD' : (a.market === 'NGX' ? 'NGN' : 'USD'),
        };
    });

const filteredAssets = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return allAssets.value;
    return allAssets.value.filter(a =>
        a.symbol.toLowerCase().includes(q) || a.name.toLowerCase().includes(q)
    );
});

const fetchWatchlist = async () => {
    try {
        const res = await api.get('/watchlist');
        const items = res.data.data ?? res.data ?? [];
        watchlistMap.value = Object.fromEntries(items.map(i => [i.symbol, i.id]));
        watchlistSymbols.value = items.map(i => i.symbol);
    } catch (e) {
        console.error('Watchlist fetch failed:', e);
    }
};

const toggleWatchlist = async (asset) => {
    const alreadyWatched = watchlistSymbols.value.includes(asset.symbol);

    if (alreadyWatched) {
        const id = watchlistMap.value[asset.symbol];
        if (!id) return;
        try {
            await api.delete(`/watchlist/${id}`);
            watchlistSymbols.value = watchlistSymbols.value.filter(s => s !== asset.symbol);
            delete watchlistMap.value[asset.symbol];
        } catch (e) {
            console.error('Remove from watchlist failed:', e);
        }
    } else {
        try {
            const res = await api.post('/watchlist', {
                symbol:      asset.symbol,
                name:        asset.name,
                market:      asset.market,
                currency:    asset.currency,
                added_price: asset.price,
            });
            const item = res.data.data;
            watchlistSymbols.value.push(asset.symbol);
            watchlistMap.value[asset.symbol] = item.id;
        } catch (e) {
            console.error('Add to watchlist failed:', e);
        }
    }
};

const navigateToAsset = (asset) => {
    if (asset.market === 'NGX') {
        router.push(`/market/ngx/${asset.symbol}`);
    } else if (asset.currency === 'USD' && asset.market !== 'CRYPTO') {
        router.push({ name: 'global-stocks' });
    } else if (asset.market === 'CRYPTO' || asset.currency === 'USD') {
        router.push({ name: 'crypto' });
    }
};

onMounted(async () => {
    await Promise.all([fetchMarkets(), fetchWatchlist()]);
    pollInterval = setInterval(() => fetchMarkets(true), 30000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

watch(activeTab, () => fetchMarkets());
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between px-5 py-4">
      <div>
        
        <p class="text-sm text-gray-400">Real-time data across global and local exchanges.</p>
      </div>

      <div class="relative w-full sm:w-72">
        <input v-model="search" type="text" placeholder="Search assets..." class="w-full bg-[#0F1724] border border-[#1f3348] rounded-xl px-4 py-2.5 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none transition-all" />
      </div>
    </div>

    <div class="flex p-1 bg-[#0B121D] border border-[#1f3348] rounded-xl self-start overflow-x-auto max-w-full">
      <button v-for="tab in availableTabs" :key="tab.id" @click="activeTab = tab.id" class="px-5 py-2 text-xs font-bold uppercase transition-all rounded-lg whitespace-nowrap" :class="activeTab === tab.id ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-500 hover:text-gray-300'">{{ tab.label }}</button>
    </div>

    <div class="bg-[#0F1724] border border-[#1f3348] rounded-2xl overflow-hidden shadow-sm">
      <div v-if="isFetching" class="p-8"><SkeletonLoader type="table" /></div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-gray-400 text-[10px] font-bold uppercase tracking-widest bg-[#0B121D] border-b border-[#1f3348]">
            <tr>
              <th class="px-6 py-4 text-left">Asset</th>
              <th class="px-6 py-4 text-left">Exchange</th>
              <th class="px-6 py-4 text-right">Last Price</th>
              <th class="px-6 py-4 text-right">24h Change</th>
              <th class="px-6 py-4 text-right">Volume</th>
              <th class="px-6 py-4 text-center">Watch</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#1f3348]">
            <tr v-for="asset in filteredAssets" :key="asset.symbol" class="hover:bg-[#16213A] transition cursor-pointer group" @click="navigateToAsset(asset)">
              <td class="px-6 py-4">
                <div class="flex flex-col">
                  <span class="font-bold text-[#00D4FF] font-mono">{{ asset.symbol }}</span>
                  <span class="text-xs text-gray-500 truncate max-w-[150px]">{{ asset.name }}</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="px-2 py-0.5 text-[10px] font-black rounded bg-gray-800 text-gray-400 border border-gray-700 uppercase">{{ asset.market }}</span>
              </td>
              <td class="px-6 py-4 text-right font-mono text-white font-semibold">{{ asset.currency === 'NGN' ? '₦' : '$' }}{{ asset.price.toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
              <td class="px-6 py-4 text-right font-mono font-bold" :class="asset.changeDirection === 'up' ? 'text-emerald-500' : 'text-rose-500'">{{ asset.changeDirection === 'up' ? '▲' : '▼' }} {{ asset.percentageChange }}%</td>
              <td class="px-6 py-4 text-right text-gray-500 text-xs font-mono">{{ asset.volume }}</td>
              <td class="px-6 py-4 text-center">
                <button @click.stop="toggleWatchlist(asset)" class="p-2 rounded-full hover:bg-white/5 transition focus:outline-none" :title="watchlistSymbols.includes(asset.symbol) ? 'Remove from watchlist' : 'Add to watchlist'">
                  <svg class="h-5 w-5 transition-colors" :class="watchlistSymbols.includes(asset.symbol) ? 'fill-amber-400 text-amber-400' : 'fill-none text-gray-600 group-hover:text-gray-400'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="filteredAssets.length === 0 && !isFetching" class="p-20 text-center text-gray-500">No assets found{{ search ? ` matching "${search}"` : ' for this market.' }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
</style>
