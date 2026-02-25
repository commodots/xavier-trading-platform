<template>
  <MainLayout>
    <div class="space-y-8">
      <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
          <h1 class="text-2xl font-semibold">
            <span v-if="isDemo" class="mr-2 font-bold text-yellow-500">DEMO</span>
            📑 My Orders
          </h1>
          <p class="text-sm text-gray-400">
            {{ isDemo ? 'View and manage your simulated paper trades.' : 'View and manage all your investment orders.'
            }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button @click="showTradeModal = true" :class="[
            'flex items-center gap-2 px-4 py-2 text-sm font-bold text-white transition-all rounded-lg shadow-lg',
            isDemo ? 'bg-gradient-to-r from-yellow-600 to-orange-500 hover:opacity-90' : 'bg-blue-600 hover:bg-blue-500 shadow-blue-900/20'
          ]">
            <span class="text-lg">+</span> {{ isDemo ? 'New Demo Trade' : 'New Trade' }}
          </button>

          <div class="relative">
            <select v-model="filterMarket"
              class="bg-[#16213A] border border-[#1f3348] text-sm text-white rounded-lg px-4 py-2 outline-none focus:border-blue-500">
              <option value="">All Markets</option>
              <option value="CRYPTO">Crypto</option>
              <option value="NGX">Local Stocks (NGX)</option>
              <option value="GLOBAL">Global Stocks</option>
              <option value="FIXED INCOME">Fixed Income Market</option>
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
          :class="statusFilter === 'all' ? (isDemo ? 'bg-yellow-600' : 'bg-blue-600') : 'bg-gray-700'"
          class="px-3 py-2 text-sm text-white transition rounded-lg">All</button>
        <button @click="statusFilter = 'active'"
          :class="statusFilter === 'active' ? (isDemo ? 'bg-yellow-600' : 'bg-blue-600') : 'bg-gray-700'"
          class="px-3 py-2 text-sm text-white transition rounded-lg">Active</button>
        <button @click="statusFilter = 'completed'"
          :class="statusFilter === 'completed' ? (isDemo ? 'bg-yellow-600' : 'bg-blue-600') : 'bg-gray-700'"
          class="px-3 py-2 text-sm text-white transition rounded-lg">Completed</button>
        <button @click="statusFilter = 'canceled'"
          :class="statusFilter === 'canceled' ? (isDemo ? 'bg-yellow-600' : 'bg-blue-600') : 'bg-gray-700'"
          class="px-3 py-2 text-sm text-white transition rounded-lg">Canceled</button>
      </div>

      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">{{ isDemo ? 'Simulated Order History' : 'Order History' }}</h2>
          <span class="text-xs text-gray-500">{{ filteredOrders.length }} orders found</span>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-12">
          <div class="w-8 h-8 border-b-2 border-blue-600 rounded-full animate-spin"
            :class="isDemo ? 'border-yellow-500' : 'border-blue-600'"></div>
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
                  <div class="text-[11px] text-gray-500 truncate max-w-[120px]">{{ o.company || o.symbol }}</div>
                </td>
                <td class="px-2 text-gray-300">{{ formatUnits(o.units, o.market) }}</td>
                <td class="px-2 font-medium text-white">
                  {{ o.currency === 'USD' ? '$' : '₦' }}{{ Number(o.amount).toLocaleString(undefined,
                    { minimumFractionDigits: 2 }) }}
                </td>

                <td class="px-2">
                  <span class="px-2.5 py-1 text-[11px] font-medium rounded-full whitespace-nowrap"
                    :class="statusClass(o.status)">
                    {{ beautifyStatus(o.status) }}
                  </span>
                </td>

                <td class="px-2 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <router-link v-if="!isDemo" :to="`/orders/${o.id}`"
                      class="text-[11px] text-blue-400 hover:text-blue-300 font-medium underline underline-offset-4">
                      Details
                    </router-link>
                    <span v-else class="text-[10px] text-gray-600 italic">Demo Trade</span>

                    <button v-if="['open', 'partially_filled', 'pending_market'].includes(o.status) && !isDemo"
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

    <div v-if="showNotificationModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-sm relative border border-[#2A314A] text-center">
        <div class="mb-4 text-5xl">{{ notificationData.success ? '✅' : '❌' }}</div>
        <h2 class="mb-2 text-xl font-bold" :class="notificationData.success ? 'text-green-400' : 'text-red-400'">
          {{ notificationData.title }}
        </h2>
        <p class="mb-6 text-gray-400">{{ notificationData.message }}</p>
        <button @click="showNotificationModal = false"
          class="w-full py-3 font-semibold text-white transition bg-gray-700 rounded-lg hover:bg-gray-600">
          Close
        </button>
      </div>
    </div>

    <TradeModal :show="showTradeModal" :tickers="tickersData" :assetCategories="categoriesData"
      @close="showTradeModal = false" @trade-success="handleTradeSuccess" />
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";
import TradeModal from "@/Components/TradeModal.vue";

const orders = ref([]);
const loading = ref(true);
const searchQuery = ref("");
const filterMarket = ref("");
const statusFilter = ref("all");
const showTradeModal = ref(false);
const isDemo = ref(false);

// Cancel Modal State
const orderToCancel = ref(null);
const isCancelling = ref(false);

// Notification Modal State
const showNotificationModal = ref(false);
const notificationData = ref({ success: true, title: '', message: '' });

const categoriesData = ref([
  { id: 'GLOBAL', name: 'Global Stocks', description: 'Trade Apple, Tesla, etc.' },
  { id: 'CRYPTO', name: 'Cryptocurrency', description: 'Trade BTC, ETH, SOL' },
  { id: 'NGX', name: 'Local Stocks', description: 'Nigerian Exchange' },
  { id: 'FIXED_INCOME', name: 'Fixed Income', description: 'Trade Bonds, Papers, etc' },
]);

const tickersData = ref({
  GLOBAL: [
    { symbol: 'AAPL', name: 'Apple Inc.', price: 180, currency: 'USD' },
    { symbol: 'TSLA', name: 'Tesla', price: 240, currency: 'USD' }
  ],
  CRYPTO: [
    { symbol: 'BTC', name: 'Bitcoin', price: 65000, currency: 'USD' },
    { symbol: 'ETH', name: 'Ethereum', price: 3500, currency: 'USD' }
  ],
  NGX: [
    { symbol: 'DANGCEM', name: 'Dangote Cement', price: 450, currency: 'NGN' },
    { symbol: 'MTNN', name: 'MTN Nigeria', price: 280, currency: 'NGN' }
  ],
  FIXED_INCOME: [
    { symbol: 'FGNSB_2027', name: 'FGN Savings Bond 2027', price: 1000.00, currency: 'NGN' },
    { symbol: 'CP_MTN_I', name: 'MTN Commercial Paper', price: 1000, currency: 'NGN' }
  ]
});

const triggerNotification = (success, title, msg) => {
  notificationData.value = { success, title, message: msg };
  showNotificationModal.value = true;
};

const handleTradeSuccess = async () => {
  showTradeModal.value = false;
  
  await new Promise(resolve => setTimeout(resolve, 800));
  loadOrders();
};

async function loadOrders() {
  loading.value = true;
  try {
    const userStr = localStorage.getItem("user");
    const userObj = userStr ? JSON.parse(userStr) : null;
    isDemo.value = userObj?.trading_mode === 'demo';

    const token = localStorage.getItem("xavier_token");


    const endpoint = isDemo.value ? "/demo/transactions" : "/orders";

    const res = await api.get(endpoint, {
      headers: { Authorization: `Bearer ${token}` }
    });

    const rawOrders = res.data.data?.data || res.data.data || res.data || [];


    orders.value = rawOrders.map(o => {
      const marketRaw = (o.market || o.market_type || '').toUpperCase();
      // Map 'local' to 'NGX', 'international' to 'GLOBAL' for demo orders
      let marketMapped = marketRaw;
      if (marketRaw === 'LOCAL') marketMapped = 'NGX';
      if (marketRaw === 'INTERNATIONAL') marketMapped = 'GLOBAL';

      const actualUnits = o.quantity || o.units  || o.filled_quantity || 0;
      
      return {
        ...o,
        market: marketMapped,
        units: Number(actualUnits),
        amount: o.amount || o.total || 0,
        currency: o.currency || 'NGN', // Demo stores total deduct in NGN
        status: o.status === 'closed' ? 'filled' : o.status // Normalize demo 'closed' to live 'filled'
      };
    });

  } catch (e) {
    console.error("Failed to load orders", e);
    triggerNotification(false, 'Connection Error', 'Unable to load orders. Please check your connection and try again.');
  } finally {
    loading.value = false;
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

function formatUnits(units, market) {
  const num = Number(units);
  if (market === 'CRYPTO' || market === 'GLOBAL' || market === 'INTERNATIONAL') {
    return num.toFixed(4).replace(/\.?0+$/, ''); // Allow decimals for US Stocks
  } else {
    return Math.floor(num).toString();
  }
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
      triggerNotification(true, 'Order Canceled', 'Your order was successfully canceled.');
    } else {
      triggerNotification(false, 'Action Failed', 'Failed to cancel order. Please try again.');
    }
  } catch (e) {
    console.error("Cancel failed", e.response?.data?.message);
    triggerNotification(false, 'Action Failed', "Failed to cancel order: " + (e.response?.data?.message || "Unknown error"));
  } finally {
    isCancelling.value = false;
    orderToCancel.value = null;
  }
}

onMounted(() => {
  loadOrders();
  window.addEventListener('trading-mode-changed', loadOrders);
});

onUnmounted(() => {
  window.removeEventListener('trading-mode-changed', loadOrders);
});
</script>