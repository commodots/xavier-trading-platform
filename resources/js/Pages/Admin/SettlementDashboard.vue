<template>
  <MainLayout>
    <div class="p-6 space-y-6 text-white">
      <!-- HEADER -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Settlement Dashboard</h1>
          <p class="text-sm text-gray-400">Monitor and manage trade settlements</p>
        </div>
      </div>

      <!-- METRICS CARDS -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <p class="text-xs text-gray-400 uppercase tracking-wider">Pending</p>
          <p class="mt-1 text-2xl font-bold text-yellow-400">{{ metrics.pending }}</p>
        </div>
        <div class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <p class="text-xs text-gray-400 uppercase tracking-wider">Completed</p>
          <p class="mt-1 text-2xl font-bold text-green-400">{{ metrics.completed }}</p>
        </div>
        <div class="p-5 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <p class="text-xs text-gray-400 uppercase tracking-wider">Failed</p>
          <p class="mt-1 text-2xl font-bold text-red-400">{{ metrics.failed }}</p>
        </div>
      </div>

      <!-- TABS -->
      <div class="flex gap-2 pb-2 border-b border-gray-700">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="switchTab(tab.key)"
          class="px-4 py-2 text-sm rounded-t-lg transition"
          :class="activeTab === tab.key ? 'bg-blue-600 text-white' : 'bg-[#1E293B] text-gray-300 hover:bg-[#2a3a55]'"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- SKELETON LOADING STATE -->
      <div v-if="loading" class="space-y-4 animate-pulse">
        <div class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
          <div class="p-4 border-b border-gray-700">
            <div class="h-4 bg-gray-700 rounded w-1/4"></div>
          </div>
          <div v-for="i in 5" :key="i" class="flex items-center px-4 py-3 border-b border-gray-800 gap-4">
            <div class="h-3 bg-gray-700 rounded w-16"></div>
            <div class="h-3 bg-gray-700 rounded w-24"></div>
            <div class="h-3 bg-gray-700 rounded w-20"></div>
            <div class="h-3 bg-gray-700 rounded w-12"></div>
            <div class="h-3 bg-gray-700 rounded w-24"></div>
            <div class="h-5 bg-gray-700 rounded-full w-16"></div>
            <div class="h-3 bg-gray-700 rounded w-20"></div>
            <div class="h-6 bg-gray-700 rounded w-12"></div>
          </div>
        </div>
        <div class="flex items-center justify-center gap-4">
          <div class="h-6 bg-gray-700 rounded w-14"></div>
          <div class="h-3 bg-gray-700 rounded w-32"></div>
          <div class="h-6 bg-gray-700 rounded w-14"></div>
        </div>
      </div>

      <!-- TRADES TABLE -->
      <div v-else class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
        <div class="p-4 border-b border-gray-700 flex items-center justify-between">
          <h3 class="font-semibold">{{ activeTabLabel }} Trades</h3>
          <span class="text-xs text-gray-400">{{ pagination.total }} total</span>
        </div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-400 border-b border-gray-700">
              <th class="py-3 px-4">Trade ID</th>
              <th class="py-3 px-4">User</th>
              <th class="py-3 px-4">Symbol</th>
              <th class="py-3 px-4">Side</th>
              <th class="py-3 px-4">Amount</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4">Date</th>
              <th class="py-3 px-4"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="trade in trades" :key="trade.id" class="border-b border-gray-800 hover:bg-[#1E293B]">
              <td class="py-3 px-4 font-mono text-xs">#{{ trade.id }}</td>
              <td class="py-3 px-4">{{ trade.user?.name || '—' }}</td>
              <td class="py-3 px-4">{{ trade.order?.symbol || trade.pair || '—' }}</td>
              <td class="py-3 px-4 capitalize">
                <span v-if="trade.order?.side" class="px-2 py-0.5 text-xs rounded" :class="trade.order.side === 'buy' ? 'bg-green-600/30 text-green-300' : 'bg-red-600/30 text-red-300'">
                  {{ trade.order.side }}
                </span>
                <span v-else class="text-gray-500">—</span>
              </td>
              <td class="py-3 px-4">{{ formatAmount(trade) }}</td>
              <td class="py-3 px-4 capitalize">
                <span class="px-2 py-0.5 text-xs rounded" :class="statusClass(trade)">
                  {{ trade.is_settled ? 'Settled' : (trade.settlement_status || 'Pending') }}
                </span>
              </td>
              <td class="py-3 px-4">{{ formatDate(trade.created_at) }}</td>
              <td class="py-3 px-4">
                <button
                  v-if="activeTab === 'pending' || trade.settlement_status === 'failed'"
                  @click="completeSettlement(trade)"
                  class="px-2 py-1 text-xs text-white transition bg-blue-600 rounded hover:bg-blue-700"
                >
                  Complete
                </button>
              </td>
            </tr>
            <tr v-if="!trades.length">
              <td colspan="8" class="py-8 text-center text-gray-500">No trades found.</td>
            </tr>
          </tbody>
        </table>

        <!-- PAGINATION -->
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-700">
          <button
            class="px-3 py-1 text-xs bg-gray-700 rounded disabled:opacity-40"
            :disabled="pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)"
          >
            Prev
          </button>
          <span class="text-xs text-gray-400">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
          <button
            class="px-3 py-1 text-xs bg-gray-700 rounded disabled:opacity-40"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';

const tabs = [
  { key: 'pending', label: 'Pending' },
  { key: 'completed', label: 'Completed' },
  { key: 'failed', label: 'Failed' },
];
const activeTab = ref('pending');
const loading = ref(false);

const activeTabLabel = computed(() => tabs.find(t => t.key === activeTab.value)?.label || '');

const metrics = reactive({ pending: 0, completed: 0, failed: 0 });
const trades = ref([]);
const pagination = reactive({ current_page: 1, last_page: 1, total: 0 });

const currencySymbol = (trade) => {
  const pair = trade.pair || trade.order?.symbol || '';
  const currency = trade.order?.currency || '';
  if (currency === 'NGN' || pair.endsWith('NGN')) return '₦';
  if (currency === 'USD' || pair.endsWith('USD') || pair.endsWith('USDT') || pair.endsWith('USDC')) return '$';
  if (pair.endsWith('BTC') || currency === 'BTC') return '₿';
  if (pair.endsWith('ETH') || currency === 'ETH') return 'Ξ';
  return '';
};

const formatAmount = (trade) => {
  if (trade.amount === null || trade.amount === undefined) return '—';
  const sym = currencySymbol(trade);
  const val = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 8 }).format(Number(trade.amount));
  return sym ? `${sym}${val}` : val;
};
const formatDate = (d) => d ? new Date(d).toLocaleDateString() : '—';

const statusClass = (trade) => {
  if (trade.settlement_status === 'settled' || trade.is_settled) return 'bg-green-600/40 text-green-300';
  if (trade.settlement_status === 'failed') return 'bg-red-600/40 text-red-300';
  return 'bg-yellow-600/40 text-yellow-300';
};

const fetchMetrics = async () => {
  try {
    const res = await api.get('/admin/settlements/metrics');
    Object.assign(metrics, res.data);
  } catch (err) {
    console.error('Metrics fetch error:', err);
  }
};

const fetchTab = async (tab, page = 1) => {
  loading.value = true;
  try {
    const res = await api.get(`/admin/settlements/${tab}`, { params: { page } });
    const data = res.data;
    trades.value = data.data || data || [];
    pagination.current_page = data.current_page || 1;
    pagination.last_page = data.last_page || 1;
    pagination.total = data.total || 0;
  } catch (err) {
    console.error('Tab fetch error:', err);
    trades.value = [];
  } finally {
    loading.value = false;
  }
};

const switchTab = (tab) => {
  activeTab.value = tab;
  fetchTab(tab);
};

const changePage = (page) => fetchTab(activeTab.value, page);

const completeSettlement = async (trade) => {
  if (!confirm(`Complete settlement for trade #${trade.id}?`)) return;
  try {
    await api.post(`/admin/settlements/complete/${trade.id}`);
    fetchTab(activeTab.value, pagination.current_page);
  } catch (err) {
    console.error('Completion error:', err);
  }
};

onMounted(() => {
  fetchMetrics();
  fetchTab('pending');
});
</script>
