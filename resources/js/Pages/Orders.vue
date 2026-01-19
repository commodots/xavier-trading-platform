<template>
  <MainLayout>
    <div class="space-y-8">
      <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
          <h1 class="text-2xl font-semibold">📑 My Orders</h1>
          <p class="text-sm text-gray-400">View and manage all your investment orders.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button @click="showTradeModal = true"
            class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white transition-all bg-blue-600 rounded-lg shadow-lg hover:bg-blue-500 shadow-blue-900/20">
            <span class="text-lg">+</span> New Trade
          </button>
          <div class="relative">
            <select v-model="filterMarket"
              class="bg-[#16213A] border border-[#1f3348] text-sm text-white rounded-lg px-4 py-2 outline-none focus:border-blue-500">
              <option value="">All Markets</option>
              <option value="CRYPTO">Crypto</option>
              <option value="NGX">Local Stocks (NGX)</option>
              <option value="GLOBAL">Global Stocks</option>
            </select>
          </div>

          <div class="relative">
            <input v-model="searchQuery" type="text" placeholder="Search Symbol (e.g. BTC)..."
              class="bg-[#16213A] border border-[#1f3348] text-sm text-white rounded-lg px-4 py-2 outline-none focus:border-blue-500" />
          </div>
        </div>
      </div>

      <div class="flex mb-4 space-x-2">
        <button @click="statusFilter = 'all'"
          :class="statusFilter === 'all' ? 'bg-blue-600' : 'bg-gray-700'"
          class="px-3 py-2 text-sm text-white transition rounded-lg">All</button>
        <button @click="statusFilter = 'active'"
          :class="statusFilter === 'active' ? 'bg-blue-600' : 'bg-gray-700'"
          class="px-3 py-2 text-sm text-white transition rounded-lg">Active</button>
        <button @click="statusFilter = 'completed'"
          :class="statusFilter === 'completed' ? 'bg-blue-600' : 'bg-gray-700'"
          class="px-3 py-2 text-sm text-white transition rounded-lg">Completed</button>
        <button @click="statusFilter = 'canceled'"
          :class="statusFilter === 'canceled' ? 'bg-blue-600' : 'bg-gray-700'"
          class="px-3 py-2 text-sm text-white transition rounded-lg">Canceled</button>
      </div>

      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">Order History</h2>
          <span class="text-xs text-gray-500">{{ filteredOrders.length }} orders found</span>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-12">
          <div class="w-8 h-8 border-b-2 border-blue-600 rounded-full animate-spin"></div>
          <span class="ml-2 text-gray-400">Loading orders...</span>
        </div>

        <div v-if="!loading" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="px-2 py-3 text-left">Date</th>
                <th class="px-2 text-left">Market</th>
                <th class="px-2 text-left">Asset</th>
                <th class="px-2 text-left">Units</th>
                <th class="px-2 text-left">Amount</th>
                <th class="px-2 text-left">Status</th>
                <th class="px-2 text-right">Action</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-[#1f3348]">
              <tr v-for="o in filteredOrders" :key="o.id" class="hover:bg-[#16213A] transition group">
                <td class="px-2 py-4 text-gray-300 whitespace-nowrap">
                  {{ formatDate(o.created_at) }}
                </td>
                <td class="px-2">
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-gray-800 text-gray-400">
                    {{ o.market }}
                  </span>
                </td>
                <td class="px-2">
                  <div class="font-semibold text-white uppercase">{{ o.symbol }}</div>
                  <div class="text-[11px] text-gray-500 truncate max-w-[120px]">{{ o.company }}</div>
                </td>
                <td class="px-2 text-gray-300">{{ Number(o.units).toFixed(8) }}</td>
                <td class="px-2 font-medium text-white">
                  {{ o.currency === 'USD' ? '$' : '₦' }}{{ Number(o.amount).toLocaleString(undefined,
                    {minimumFractionDigits: 2}) }}
                </td>

                <td class="px-2">
                  <span class="px-2.5 py-1 text-[11px] font-medium rounded-full whitespace-nowrap"
                    :class="statusClass(o.status)">
                    {{ beautifyStatus(o.status) }}
                  </span>
                </td>

                <td class="px-2 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <router-link :to="`/orders/${o.id}`"
                      class="text-[11px] text-blue-400 hover:text-blue-300 font-medium underline underline-offset-4">
                      Details
                    </router-link>
                    <button v-if="['open', 'partially_filled', 'pending_market'].includes(o.status)"
                      @click="cancelOrder(o.id)"
                      class="bg-red-500/10 hover:bg-red-500/20 px-3 py-1 rounded-lg text-red-400 text-[10px] font-bold transition border border-red-500/20 uppercase tracking-wider">
                      Cancel
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="filteredOrders.length === 0">
                <td colspan="7" class="py-12 text-center text-gray-500">
                  No orders match your search criteria.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="orderToCancel"
      class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
      <div class="bg-[#0F1724] border border-gray-800 rounded-2xl p-6 w-full max-w-sm shadow-2xl">
        <div class="mb-4 text-center">
          <div
            class="inline-flex items-center justify-center w-12 h-12 mb-4 text-xl font-bold text-red-500 rounded-full bg-red-500/10">
            !</div>
          <h3 class="text-lg font-bold text-white">Cancel Order?</h3>
          <p class="mt-2 text-sm text-gray-400">Are you sure you want to cancel this order? This action cannot be
            undone.</p>
        </div>
        <div class="flex gap-3">
          <button @click="orderToCancel = null"
            class="flex-1 px-4 py-2 text-sm font-semibold text-gray-300 transition bg-gray-800 rounded-lg hover:bg-gray-700">
            No, Keep
          </button>
          <button @click="confirmCancel" :disabled="isCancelling"
            class="flex-1 px-4 py-2 text-sm font-semibold text-white transition bg-red-600 rounded-lg hover:bg-red-500 disabled:opacity-50">
            {{ isCancelling ? 'Cancelling...' : 'Yes, Cancel' }}
          </button>
        </div>
      </div>
    </div>

    <TradeModal :show="showTradeModal" :tickers="tickersData" :assetCategories="categoriesData"
      @close="showTradeModal = false" @trade-success="handleTradeSuccess" />
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";
import TradeModal from "@/Components/TradeModal.vue";

const orders = ref([]);
const loading = ref(true);
const searchQuery = ref("");
const filterMarket = ref("");
const statusFilter = ref("all");
const showTradeModal = ref(false);

// Cancel Modal State
const orderToCancel = ref(null);
const isCancelling = ref(false);

const categoriesData = ref([
  { id: 'stocks', name: 'Global Stocks', description: 'Trade Apple, Tesla, etc.' },
  { id: 'crypto', name: 'Cryptocurrency', description: 'Trade BTC, ETH, SOL' },
  { id: 'ngx', name: 'Local Stocks', description: 'Nigerian Exchange' }
]);

const tickersData = ref({
  stocks: [
    { symbol: 'AAPL', name: 'Apple Inc.', price: 180, currency: 'USD' },
    { symbol: 'TSLA', name: 'Tesla', price: 240, currency: 'USD' }
  ],
  crypto: [
    { symbol: 'BTC', name: 'Bitcoin', price: 65000, currency: 'USD' },
    { symbol: 'ETH', name: 'Ethereum', price: 3500, currency: 'USD' }
  ],
  ngx: [
    { symbol: 'DANGCEM', name: 'Dangote Cement', price: 450, currency: 'NGN' },
    { symbol: 'MTNN', name: 'MTN Nigeria', price: 280, currency: 'NGN' }
  ]
});

const handleTradeSuccess = () => {
  showTradeModal.value = false;
  loadOrders();
};

onMounted(() => loadOrders());

async function loadOrders() {
  try {
    const token = localStorage.getItem("xavier_token");
    const res = await api.get("/orders", {
      headers: { Authorization: `Bearer ${token}` }
    });
    orders.value = res.data.data?.data || res.data.data || res.data || [];
    loading.value = false;
  } catch (e) {
    console.error("Failed to load orders", e);
    loading.value = false;
    
    alert("Unable to load orders. Please check your connection and try again.");
  }
}

const filteredOrders = computed(() => {
  return orders.value.filter(o => {
    const matchesSearch = o.symbol.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchesMarket = filterMarket.value === "" || o.market === filterMarket.value;
    const matchesStatus = statusFilter.value === "all" ||
      (statusFilter.value === "active" && ["open", "pending_market", "partially_filled"].includes(o.status)) ||
      (statusFilter.value === "completed" && o.status === "filled") ||
      (statusFilter.value === "canceled" && ["canceled", "cancelled", "failed"].includes(o.status));
    return matchesSearch && matchesMarket && matchesStatus;
  });
});

function beautifyStatus(s) {
  const statusMap = {
    'open': 'Pending',
    'pending_market': 'Pending (Market)',
    'partially_filled': 'Partially Completed',
    'filled': 'Completed',
    'canceled': 'Canceled',
    'failed': 'Failed'
  };
  return statusMap[s] || s;
}

function statusClass(s) {
  if (['open', 'pending_market'].includes(s)) return 'bg-yellow-500/10 text-yellow-500 border border-yellow-500/20';
  if (s === 'partially_filled') return 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
  if (s === 'filled') return 'bg-green-500/10 text-green-400 border border-green-500/20';
  if (['canceled', 'cancelled', 'failed'].includes(s)) return 'bg-red-500/10 text-red-400 border border-red-500/20';
  return 'bg-gray-500/10 text-gray-400';
}

function formatDate(dateStr) {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'
  });
}


function cancelOrder(id) {
  orderToCancel.value = id;
}

async function confirmCancel() {
  if (!orderToCancel.value) return;

  isCancelling.value = true;
  try {
    const token = localStorage.getItem("xavier_token");
    const res = await api.post(`/orders/${orderToCancel.value}/cancel`, {}, {
      headers: { Authorization: `Bearer ${token}` }
    });

    if (res.data.success) {
      loadOrders();
    } else {
      alert("Failed to cancel order. Please try again.");
    }
  } catch (e) {
    console.error("Cancel failed", e.response?.data?.message);
    alert("Failed to cancel order: " + (e.response?.data?.message || "Unknown error"));
  } finally {
    isCancelling.value = false;
    orderToCancel.value = null;
  }
}
</script>
