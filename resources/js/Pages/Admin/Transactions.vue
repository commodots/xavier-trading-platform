<template>
  <MainLayout>
    <div class="max-w-6xl mx-auto">
      <h1 class="mb-6 text-2xl font-bold">Transactions</h1>

      <div class="flex items-center gap-4 mb-6">
        <input v-model="filters.q" @input="handleSearch" type="text" placeholder="Search email or Tx ID"
          class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg w-64 text-white focus:outline-none focus:border-blue-500" />

        <select v-model="filters.type" @change="fetchTransactions()"
          class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white">
          <option value="">All Types</option>
          <option value="deposit">Deposit</option>
          <option value="withdrawal">Withdrawal</option>
          <option value="trade">Trade</option>
        </select>

        <select v-model="filters.status" @change="fetchTransactions()"
          class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="successful">Successful</option>
          <option value="failed">Failed</option>
        </select>
        
        <button @click="exportTransactions" :disabled="loading"
          class="flex items-center gap-2 px-4 py-2 ml-auto text-sm font-medium text-white transition-colors bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 16v1a2 2 0 002 2h12 a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Export CSV
        </button>

        <button v-if="hasFilters" @click="clearFilters" class="text-sm text-gray-400 underline hover:text-white">
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
              <th class="px-4 py-3">Type</th>
              <th class="px-4 py-3">Amount</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Date</th>
            </tr>
          </thead>

          <tbody v-if="transactions?.data?.length">
            <tr v-for="tx in transactions.data" :key="tx.id"
              class="border-t border-[#2A314A] hover:bg-[#252a3d] transition-colors">
              <td class="px-4 py-3">{{ tx.user?.email || 'N/A' }}</td>
              <td class="px-4 py-3 capitalize">{{ tx.type }}</td>
              <td class="px-4 py-3">₦{{ tx.amount?.toLocaleString() }}</td>
              <td class="px-4 py-3">
                <span :class="statusColor(tx.status)" class="font-medium">
                  {{ tx.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-400">
                {{ tx.created_at ? new Date(tx.created_at).toLocaleString() : 'N/A' }}
              </td>
            </tr>
          </tbody>

          <tbody v-else-if="!loading">
            <tr>
              <td colspan="5" class="py-20 text-center text-gray-500">
                No transactions found matching your criteria.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex items-center justify-between mt-6">
        <span class="text-xs text-gray-500">
          Showing page {{ transactions.current_page }} of {{ transactions.last_page }}
        </span>

        <div class="flex gap-2">
          <button @click="changePage(transactions.prev_page_url)" :disabled="!transactions.prev_page_url || loading"
            class="px-4 py-2 rounded-lg border border-[#2A314A] text-white disabled:opacity-20 hover:bg-[#2A314A] transition-all">
            Previous
          </button>

          <button @click="changePage(transactions.next_page_url)" :disabled="!transactions.next_page_url || loading"
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
  type: "",
  status: "",
});

const transactions = ref({
  data: [],
  prev_page_url: null,
  next_page_url: null,
  current_page: 1,
  last_page: 1
});

const hasFilters = computed(() => {
  return filters.value.q || filters.value.type || filters.value.status;
});


const fetchTransactions = async (url = null) => {
  loading.value = true;
  try {

    const endpoint = url || 'admin/transactions';

    const res = await api.get(endpoint, {
      params: url ? {} : filters.value
    });

    transactions.value = res.data.data;
  } catch (error) {
    console.error("Failed to fetch transactions", error);
  } finally {
    loading.value = false;
  }
};

const exportTransactions = async () => {
  loading.value = true;
  try {
    const res = await api.get('admin/transactions/export', {
      params: filters.value,
      responseType: 'blob'
    });
    const url = window.URL.createObjectURL(new Blob([res.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `transactions-${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
  } catch (error) {
    console.error("Export failed", error);
  } finally {
    loading.value = false;
  }
};

let searchTimer;
const handleSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    fetchTransactions();
  }, 500);
};

const clearFilters = () => {
  filters.value = { q: "", type: "", status: "" };
  fetchTransactions();
};

const changePage = (url) => {
  if (url && !loading.value) fetchTransactions(url);
};

const statusColor = (status) => {
  const colors = {
    pending: "text-yellow-400",
    successful: "text-green-400",
    failed: "text-red-400",
  };
  return colors[status] || "text-gray-200";
};

onMounted(fetchTransactions);
</script>