<template>
  <div class="bg-[#1C1F2E] border border-[#1f3348] rounded-xl overflow-hidden shadow-lg mt-6 text-white">
    <div class="p-4 border-b border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
      <div class="flex items-center gap-2">
        <h3 class="text-xs font-bold tracking-widest text-white uppercase">Positions Monitor</h3>
      </div>
      <div class="flex items-center gap-4">
        <div v-if="loading" class="w-3 h-3 border-2 border-[#00D4FF] rounded-full animate-spin border-t-transparent"></div>
        <button @click="fetchPositions" class="text-[10px] font-black text-[#00D4FF] uppercase hover:text-white transition-colors">Refresh</button>
      </div>
    </div>

    <div class="overflow-x-auto custom-scrollbar">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="text-[10px] font-bold text-gray-500 uppercase tracking-wider border-b border-[#2A314A] bg-black/20">
            <th class="px-4 py-3">Asset</th>
            <th class="px-4 py-3">Side</th>
            <th class="px-4 py-3">Type</th>
            <th class="px-4 py-3 text-right">Size</th>
            <th class="px-4 py-3 text-right">Entry Price</th>
            <th class="px-4 py-3 text-right">Market Price</th>
            <th class="px-4 py-3 text-right">Unrealized P&L %</th>
            <th class="px-4 py-3 text-right">Unrealized P&L</th>
            <th class="px-4 py-3 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[#2A314A]/30">
          <tr v-for="pos in paginatedPositions" :key="pos.id" class="hover:bg-[#16213A] transition-colors group">
            <td class="px-4 py-3">
              <div class="font-bold text-white">{{ pos.symbol }}</div>
            </td>
            <td class="px-4 py-3">
              <span :class="pos.side === 'buy' ? 'text-green-400' : 'text-red-400'" class="font-black text-[10px] uppercase">
                {{ pos.side }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="text-[10px] font-medium text-gray-500 uppercase tracking-tighter bg-gray-800/50 px-1.5 py-0.5 rounded">{{ pos.type }}</span>
            </td>
            <td class="px-4 py-3 font-mono text-right text-gray-300">{{ pos.quantity }}</td>
            <td class="px-4 py-3 font-mono text-right text-gray-300">
              {{ pos.currency === 'USD' ? '$' : '₦' }}{{ Number(pos.market_price || pos.entry_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
            </td>
            <td class="px-4 py-3 font-mono text-right text-white">
              {{ pos.currency === 'USD' ? '$' : '₦' }}{{ getMarkPrice(pos).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
            </td>
            <td class="px-4 py-3 font-mono font-bold text-right" :class="getPLPercentage(pos) >= 0 ? 'text-green-400' : 'text-red-400'">
              {{ getPLPercentage(pos) >= 0 ? '+' : '' }}{{ getPLPercentage(pos).toLocaleString(undefined, {minimumFractionDigits: 2}) }}%
            </td>
            <td class="px-4 py-3 font-mono font-bold text-right" :class="getPL(pos) >= 0 ? 'text-green-400' : 'text-red-400'">
              {{ getPL(pos) >= 0 ? '+' : '' }}{{ pos.currency === 'USD' ? '$' : '₦' }}{{ getPL(pos).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
            </td>
            <td class="px-4 py-3 text-center">
              <button @click="closePosition(pos.id)" :disabled="closingId === pos.id"
                class="bg-red-500/10 text-red-500 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded text-[10px] font-black uppercase transition-all border border-red-500/20 disabled:opacity-50">
                {{ closingId === pos.id ? '...' : 'Close' }}
              </button>
            </td>
          </tr>
          <tr v-if="positions.length === 0 && !loading">
            <td colspan="9" class="px-4 py-12 text-xs italic text-center text-gray-500">
              No active positions.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination Controls -->
    <div v-if="totalPages > 1" class="p-4 border-t border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
      <button @click="prevPage" :disabled="currentPage === 1"
        class="px-3 py-1 text-xs font-bold text-[#00D4FF] uppercase hover:text-white transition-colors disabled:opacity-50">
        ← Previous
      </button>
      <span class="text-xs text-gray-400">
        Page {{ currentPage }} of {{ totalPages }}
      </span>
      <button @click="nextPage" :disabled="currentPage === totalPages"
        class="px-3 py-1 text-xs font-bold text-[#00D4FF] uppercase hover:text-white transition-colors disabled:opacity-50">
        Next →
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const positions = ref([]);
const loading = ref(false);
const closingId = ref(null);
const livePrices = ref({});

const currentPage = ref(1);
const itemsPerPage = 10;

const totalPages = computed(() => {
  return Math.ceil(positions.value.length / itemsPerPage) || 0;
});

const paginatedPositions = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  return positions.value.slice(start, start + itemsPerPage);
});

const prevPage = () => {
  if (currentPage.value > 1) currentPage.value--;
};

const nextPage = () => {
  if (currentPage.value < totalPages.value) currentPage.value++;
};

const fetchPositions = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/trade/positions');
    positions.value = res.data.data || res.data;
  } catch (err) {
    console.error('Failed to fetch positions', err);
  } finally {
    loading.value = false;
  }
};

const getMarkPrice = (pos) => livePrices.value[pos.symbol] || pos.market_price || pos.entry_price || 0;

const getPLPercentage = (pos) => {
  const mark = getMarkPrice(pos);
  const entry = parseFloat(pos.entry_price || 0);
  if (entry === 0) return 0;

  const priceDiff = pos.side === 'buy' ? (mark - entry) : (entry - mark);
  return (priceDiff / entry) * 100;
};

const getPL = (pos) => {
  const mark = getMarkPrice(pos);
  const entry = parseFloat(pos.entry_price || 0); // Use entry_price only
  
  if (entry === 0) return 0;

  // If BUY: (Current - Entry). If SELL: (Entry - Current)
  const priceDiff = pos.side === 'buy' ? (mark - entry) : (entry - mark);
  
  // P&L = (Price Difference / Entry Price) * Initial Investment
  return (priceDiff / entry) * pos.amount;
};

const closePosition = async (id) => {
  closingId.value = id;
  try {
    await axios.post(`/trade/close/${id}`);
    await fetchPositions();
    window.dispatchEvent(new CustomEvent('wallet-refresh'));
  } catch (err) {
    console.error('Failed to close position', err);
  } finally {
    closingId.value = null;
  }
};

onMounted(() => {
  fetchPositions();
  if (window.Echo) {
    window.Echo.channel('market-channel').listen('MarketUpdated', (e) => {
      const data = Array.isArray(e) ? e : (e.data || []);
      data.forEach(ticker => { livePrices.value[ticker.s] = parseFloat(ticker.p); });
    });
  }
  window.addEventListener('order-placed', fetchPositions);
});

onUnmounted(() => {
  if (window.Echo) window.Echo.leave('market-channel');
  window.removeEventListener('order-placed', fetchPositions);
});
</script>