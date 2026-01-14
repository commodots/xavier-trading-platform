<template>
  <MainLayout>
    <div class="max-w-6xl mx-auto">
      <h1 class="mb-6 text-2xl font-bold">Orders</h1>

      <div class="flex items-center gap-4 mb-6">
        <input v-model="filters.q" @input="handleSearch" type="text" placeholder="Search user email or symbol"
          class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg w-64 text-white focus:outline-none focus:border-blue-500" />

        <select v-model="filters.status" @change="fetchOrders()"
          class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="successful">Successful</option>
          <option value="failed">Failed</option>
        </select>

        <select v-model="filters.side" @change="fetchOrders()"
          class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white">
          <option value="">All Sides</option>
          <option value="buy">Buy</option>
          <option value="sell">Sell</option>
        </select>

        <select v-model="filters.market" @change="fetchOrders()"
          class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white">
          <option value="">All Markets</option>
          <option value="crypto">Crypto</option>
          <option value="stock">Stock</option>
        </select>

        <button v-if="hasFilters" @click="clearFilters" class="ml-auto text-sm text-gray-400 underline hover:text-white">
          Clear Filters
        </button>
      </div>

      <div class="relative bg-[#1C1F2E] rounded-xl border border-[#2A314A] overflow-hidden">

        <div v-if="loading"
          class="absolute inset-0 z-10 flex items-center justify-center bg-black/40 backdrop-blur-[1px]">
          <div class="flex items-center gap-2 px-4 py-2 bg-[#2A314A] rounded-lg border border-gray-600 shadow-xl">
            <span class="w-4 h-4 border-2 border-white rounded-full border-t-transparent animate-spin"></span>
            <span class="text-sm font-medium text-white">Loading...</span>
          </div>
        </div>

        <table class="w-full text-sm text-left" :class="{ 'opacity-40': loading }">
          <thead class="bg-[#151a27] text-gray-400 uppercase tracking-wider">
            <tr>
              <th class="px-4 py-3">User</th>
              <th class="px-4 py-3">Symbol</th>
              <th class="px-4 py-3">Side</th>
              <th class="px-4 py-3">Type</th>
              <th class="px-4 py-3">Quantity</th>
              <th class="px-4 py-3">Price</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Date</th>
            </tr>
          </thead>

          <tbody v-if="orders?.data?.length">
            <tr v-for="order in orders.data" :key="order.id"
              class="border-t border-[#2A314A] hover:bg-[#252a3d] transition-colors">
              <td class="px-4 py-3">{{ order.user?.email || 'N/A' }}</td>
              <td class="px-4 py-3">{{ order.symbol }}</td>
              <td class="px-4 py-3 capitalize">{{ order.side }}</td>
              <td class="px-4 py-3 capitalize">{{ order.type }}</td>
              <td class="px-4 py-3">{{ Number(order.quantity).toLocaleString() }}</td>
              <td class="px-4 py-3">{{ order.price ? '₦' + Number(order.price).toLocaleString() : 'Market' }}</td>
              <td class="px-4 py-3">
                <span :class="statusColor(order.status)" class="font-medium">
                  {{ order.status.replace('_', ' ') }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-400">
                {{ order.created_at ? new Date(order.created_at).toLocaleString() : 'N/A' }}
              </td>
            </tr>
          </tbody>

          <tbody v-else-if="!loading">
            <tr>
              <td colspan="8" class="py-20 text-center text-gray-500">
                No orders found matching your criteria.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between mt-6">
        <span class="text-xs text-gray-500">
          Showing page {{ orders.current_page }} of {{ orders.last_page }}
        </span>

        <div class="flex gap-2">
          <button @click="changePage(orders.prev_page_url)" :disabled="!orders.prev_page_url || loading"
            class="px-4 py-2 rounded-lg border border-[#2A314A] text-white disabled:opacity-20 hover:bg-[#2A314A] transition-all">
            Previous
          </button>

          <button @click="changePage(orders.next_page_url)" :disabled="!orders.next_page_url || loading"
            class="px-4 py-2 rounded-lg border border-[#2A314A] text-white disabled:opacity-20 hover:bg-[#2A314A] transition-all">
            Next
          </button>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import MainLayout from "@/Layouts/MainLayout.vue";
import api from "@/api";

const loading = ref(false);
const filters = ref({
  q: "",
  status: "",
  side: "",
  market: "",
});

const orders = ref({
  data: [],
  prev_page_url: null,
  next_page_url: null,
  current_page: 1,
  last_page: 1
});

const hasFilters = computed(() => {
  return filters.value.q || filters.value.status || filters.value.side || filters.value.market;
});


const fetchOrders = async (url = null) => {
  loading.value = true;
  try {

    const endpoint = url || 'admin/orders';

    const res = await api.get(endpoint, {
      params: url ? {} : filters.value
    });

    orders.value = res.data.data;
  } catch (error) {
    console.error("Failed to fetch orders", error);
  } finally {
    loading.value = false;
  }
};

let searchTimer;
const handleSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    fetchOrders();
  }, 500);
};

const clearFilters = () => {
  filters.value = { q: "", status: "", side: "", market: "" };
  fetchOrders();
};

const changePage = (url) => {
  if (url && !loading.value) fetchOrders(url);
};

const statusColor = (status) => {
  // Map order statuses to transaction-like statuses for consistent coloring
  const mappedStatus = {
    open: 'pending',
    partially_filled: 'pending',
    filled: 'successful',
    canceled: 'failed',
  }[status] || status;

  const colors = {
    pending: "text-yellow-400",
    successful: "text-green-400",
    failed: "text-red-400",
  };
  return colors[mappedStatus] || "text-gray-200";
};

onMounted(fetchOrders);
</script>