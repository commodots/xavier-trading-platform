<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import MainLayout from '@/Layouts/MainLayout.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const router = useRouter();

const activeTab = ref('TRENDING');
const search = ref('');
const allAssets = ref([]);
const isFetching = ref(false);
const watchlistSymbols = ref([]);
const watchlistMap = ref({}); // symbol -> watchlist item id
let pollInterval = null;

const tabs = [
    { id: 'TRENDING', label: 'Trending' },
    { id: 'US',       label: 'US Markets' },
    { id: 'UK',       label: 'UK Markets' },
    { id: 'NGX',      label: 'NGX (Local)' },
    { id: 'CRYPTO',   label: 'Crypto' },
];

// Map tab id to API params
const tabParams = {
    TRENDING: { trending: 1 },
    US:       { market: 'US', type: 'stock' },
    UK:       { market: 'UK', type: 'stock' },
    NGX:      { market: 'NGX', type: 'stock' },
    CRYPTO:   { type: 'crypto' },
};

const fetchMarkets = async (background = false) => {
    if (isFetching.value && !background) return;
    if (!background) isFetching.value = true;

    try {
        const params = tabParams[activeTab.value] ?? {};
        const res = await api.get('/markets', { params });
        allAssets.value = normalise(res.data.data ?? res.data ?? []);
    } catch (e) {
        console.error('Market fetch failed:', e);
    } finally {
        isFetching.value = false;
    }
};

// Normalise raw Symbol rows into the shape the template needs
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

// Fetch user's watchlist once to know which symbols are already watched
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
</script>


<template>
  <MainLayout>
    <div class="p-4">
      <MarketList />
    </div>
  </MainLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
</style>
 
