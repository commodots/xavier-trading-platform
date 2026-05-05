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
      <div class="min-w-[900px] overflow-hidden rounded-b-xl">
        <table class="w-full min-w-full text-left border-collapse">
          <thead>
          <tr class="text-[10px] font-bold text-gray-500 uppercase tracking-wider border-b border-[#2A314A] bg-black/20">
            <th class="px-4 py-3">Asset</th>
            <th class="hidden px-4 py-3 sm:table-cell">Side</th>
            <th class="hidden px-4 py-3 md:table-cell">Type</th>
            <th class="px-4 py-3 text-right">Size</th>
            <th class="hidden px-4 py-3 text-right sm:table-cell">Entry Price</th>
            <th class="px-4 py-3 text-right">Market Price</th>
            <th class="hidden px-4 py-3 text-right md:table-cell">Unrealized P&L %</th>
            <th class="px-4 py-3 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[#2A314A]/30">
          <tr v-for="pos in paginatedPositions" :key="pos.id" class="hover:bg-[#16213A] transition-colors group">
            <td class="px-4 py-3">
              <div class="font-bold text-white">{{ pos.symbol }}</div>
            </td>
            <td class="hidden px-4 py-3 sm:table-cell">
              <span :class="pos.side === 'buy' ? 'text-green-400' : 'text-red-400'" class="font-black text-[10px] uppercase">
                {{ pos.side }}
              </span>
            </td>
            <td class="hidden px-4 py-3 md:table-cell">
              <span class="text-[10px] font-medium text-gray-500 uppercase tracking-tighter bg-gray-800/50 px-1.5 py-0.5 rounded">{{ pos.type }}</span>
            </td>
            <td class="px-4 py-3 font-mono text-right text-gray-300">{{ pos.quantity }}</td>
            <td class="hidden px-4 py-3 font-mono text-right text-gray-300 sm:table-cell">
              {{ pos.currency === 'USD' ? '$' : '₦' }}{{ Number(pos.entry_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
            </td>
            <td class="px-4 py-3 font-mono text-right text-white">
              {{ pos.currency === 'USD' ? '$' : '₦' }}{{ Number(pos.market_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
            </td>
            <td class="px-4 py-3 font-mono font-bold text-right" :class="(pos.unrealized_pl_percent ?? getPLPercentage(pos)) >= 0 ? 'text-green-400' : 'text-red-400'">
              {{ (pos.unrealized_pl_percent ?? getPLPercentage(pos)) >= 0 ? '+' : '' }}{{ Number(pos.unrealized_pl_percent ?? getPLPercentage(pos)).toLocaleString(undefined, {minimumFractionDigits: 2}) }}%
            </td>
            
            <td class="px-4 py-3 text-center">
              <button @click="closePosition(pos.id, pos)" :disabled="closingId === pos.id"
                class="bg-red-500/10 text-red-500 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded text-[10px] font-black uppercase transition-all border border-red-500/20 disabled:opacity-50">
                {{ closingId === pos.id ? 'Cancelling' : (isOrderPosition(pos) ? 'Cancel' : 'Close') }}
              </button>
            </td>
          </tr>
          <tr v-if="filteredPositions.length === 0 && !loading">
            <td colspan="9" class="px-4 py-12 text-xs italic text-center text-gray-500">
              No active global stock positions.
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

    <!-- Cancel Confirmation Modal -->
    <div v-if="showCancelModal" class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape="showCancelModal = false">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showCancelModal = false"></div>
        <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-[#0F1724] border border-[#1f3348] shadow-xl rounded-lg">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-white">Order Cancelled</h3>
            <button @click="showCancelModal = false" class="text-gray-400 hover:text-white">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          <p class="text-gray-300">{{ cancelMessage }}</p>
          <div class="mt-4">
            <button @click="showCancelModal = false" class="bg-[#00D4FF] text-[#0F1724] px-4 py-2 rounded-md font-bold hover:bg-[#00b8e6] transition">
              OK
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '@/api';

const positions = ref([]);
const loading = ref(false);
const closingId = ref(null);
const livePrices = ref({});
let autoRefreshInterval = null;

const currentPage = ref(1);
const itemsPerPage = 10;

// Modal for cancel confirmation
const showCancelModal = ref(false);
const cancelMessage = ref('');

const totalPages = computed(() => {
  return Math.ceil(filteredPositions.value.length / itemsPerPage) || 0;
});

const paginatedPositions = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  return filteredPositions.value.slice(start, start + itemsPerPage);
});

const filteredPositions = computed(() => {
  return positions.value.filter(pos => {
    return (pos.category || '').toUpperCase() === 'GLOBAL';
  });
});

const nextPage = () => {
  if (currentPage.value < totalPages.value) currentPage.value++;
};

const fetchPositions = async () => {
  loading.value = true;
  try {
    const res = await api.get('/trade/positions');
    positions.value = res.data.data || res.data;
  } catch (err) {
    console.error('Failed to fetch positions', err);
  } finally {
    loading.value = false;
  }
};

const getMarkPrice = (pos) => pos.market_price ?? livePrices.value[pos.symbol] ?? pos.entry_price ?? 0;

const getPLPercentage = (pos) => {
  if (typeof pos.unrealized_pl_percent === 'number') {
    return pos.unrealized_pl_percent;
  }

  const mark = getMarkPrice(pos);
  const entry = parseFloat(pos.entry_price || 0);
  if (entry === 0) return 0;

  const priceDiff = pos.side === 'buy' ? (mark - entry) : (entry - mark);
  return (priceDiff / entry) * 100;
};

const getPL = (pos) => {
  if (typeof pos.unrealized_pl === 'number') {
    return pos.unrealized_pl;
  }

  const mark = getMarkPrice(pos);
  const entry = parseFloat(pos.entry_price || 0);
  if (entry === 0) return 0;

  const priceDiff = pos.side === 'buy' ? (mark - entry) : (entry - mark);
  return priceDiff * pos.amount;
};

const isOrderPosition = (pos) => pos.position_type === 'order';

const closePosition = async (id, pos) => {
  closingId.value = id;
  try {
    const endpoint = isOrderPosition(pos) ? `/orders/${id}/cancel` : `/trade/close/${id}`;
    await api.post(endpoint);
    await fetchPositions();
    window.dispatchEvent(new CustomEvent('wallet-refresh'));
    if (isOrderPosition(pos)) {
      cancelMessage.value = `Order for ${pos.symbol} has been cancelled.`;
      showCancelModal.value = true;
    }
  } catch (err) {
    console.error('Failed to close position', err);
  } finally {
    closingId.value = null;
  }
};

const handleOrderPlaced = () => {
  fetchPositions();
};

onMounted(() => {
  fetchPositions();

  const startAutoRefresh = () => {
    autoRefreshInterval = setTimeout(async () => {
      await fetchPositions();
      startAutoRefresh();
    }, 5000);
  };

  startAutoRefresh();

  if (window.Echo) {
    window.Echo.channel('market-channel').listen('MarketUpdated', (e) => {
      const data = Array.isArray(e) ? e : (e.data || []);
      data.forEach(ticker => { livePrices.value[ticker.s] = parseFloat(ticker.p); });
    });
  }
  
  window.addEventListener('order-placed', handleOrderPlaced);
});

onUnmounted(() => {
  if (autoRefreshInterval) clearTimeout(autoRefreshInterval);
  if (window.Echo) window.Echo.leave('market-channel');
  window.removeEventListener('order-placed', handleOrderPlaced);
});
</script>