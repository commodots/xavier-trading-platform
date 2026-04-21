<template>
  <MainLayout>
    <div>
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold">🔁 Transactions</h1>
          <p class="text-sm text-gray-400">Track all your wallet and trading activities.</p>
        </div>
      </div>

      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5 mb-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div>
            <label class="text-xs text-gray-400">Type</label>
            <select v-model="filters.type" class="w-full bg-[#1C2541] text-gray-300 rounded-lg px-3 py-2 mt-1 border border-[#1f3348] focus:border-[#00D4FF] focus:ring-0">
              <option value="">All</option>
              <option value="deposit">Deposit</option>
              <option value="withdrawal">Withdrawal</option>
              <option value="buy">Buy</option>
              <option value="sell">Sell</option>
              <option value="transfer">Transfer</option>
            </select>
          </div>

          <div>
            <label class="text-xs text-gray-400">Status</label>
            <select v-model="filters.status" class="w-full bg-[#1C2541] text-gray-300 rounded-lg px-3 py-2 mt-1 border border-[#1f3348] focus:border-[#00D4FF] focus:ring-0">
              <option value="">All</option>
              <option value="completed">Completed</option>
              <option value="pending">Pending</option>
              <option value="failed">Failed</option>
            </select>
          </div>

          <div>
            <label class="text-xs text-gray-400">From</label>
            <input type="date" v-model="filters.from" class="w-full bg-[#1C2541] text-gray-300 rounded-lg px-3 py-2 mt-1 border border-[#1f3348] focus:border-[#00D4FF] focus:ring-0" />
          </div>

          <div>
            <label class="text-xs text-gray-400">To</label>
            <input type="date" v-model="filters.to" class="w-full bg-[#1C2541] text-gray-300 rounded-lg px-3 py-2 mt-1 border border-[#1f3348] focus:border-[#00D4FF] focus:ring-0" />
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-4">
          <button
            @click="clearFilters"
            class="bg-[#1C2541] text-gray-400 px-6 py-2 rounded-lg font-medium hover:text-white transition"
          >
            Clear Filters
          </button>
          <button
            @click="applyFilters"
            class="bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white px-6 py-2 rounded-lg font-medium hover:opacity-90 transition"
          >
            Apply Filters
          </button>
        </div>
      </div>

      <!-- Transactions Table -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">Transaction History</h2>
          <span class="text-sm text-gray-400">Page {{ currentPage }} of {{ totalPages || 1 }}</span>
        </div>

        <div v-if="loading" class="py-6 text-center">
          <div class="text-gray-400">Loading transactions...</div>
        </div>

        <div v-else-if="paginatedTransactions.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="px-2 py-2 text-left">Date</th>
                <th class="px-2 text-left">Type</th>
                <th class="px-2 text-left">ID</th>
                <th class="px-2 text-right">Amount</th>
                <th class="px-2 text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="t in paginatedTransactions"
                :key="t.id || t.ref"
                @click="openTransactionDetails(t)"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition cursor-pointer"
              >
                <td class="px-2 py-3">{{ t.date }}</td>
                <td class="px-2 capitalize">{{ t.type }}</td>
                <td class="px-2 font-mono text-[11px] text-gray-500 uppercase">{{ t.ref?.toString().substring(0, 10) }}</td>
                <td
                  class="px-2 font-semibold text-right"
                  :class="['deposit', 'sell_crypto', 'refund'].includes(t.type?.toLowerCase()) ? 'text-green-400' : 'text-red-400'"
                >
                  {{ formatAmount(t.amount, t.currency) }}
                </td>
                <td class="px-2 text-center">
                  <span
                    :class="[
                      'px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-tighter',
                      t.status?.toLowerCase() === 'completed' || t.status?.toLowerCase() === 'success'
                        ? 'bg-green-500/10 text-green-400 border border-green-500/20'
                        : t.status?.toLowerCase() === 'pending' || t.status?.toLowerCase() === 'processing'
                        ? 'bg-yellow-500/10 text-yellow-300 border border-yellow-500/20'
                        : 'bg-red-500/10 text-red-400 border border-red-500/20'
                    ]"
                  >
                    {{ t.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="py-12 text-center text-gray-500">
          No transactions found matching those filters.
        </div>

        <div v-if="totalPages > 1" class="flex items-center justify-between mt-6">
          <button
            @click="prevPage"
            :disabled="currentPage === 1"
            class="bg-[#1C2541] px-4 py-2 rounded-lg text-sm hover:bg-[#24395C] disabled:opacity-40"
          >
            ← Prev
          </button>
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="bg-[#1C2541] px-4 py-2 rounded-lg text-sm hover:bg-[#24395C] disabled:opacity-40"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <TransactionDetailsModal 
      :show="showDetailsModal" 
      :txn="selectedTransaction" 
      @close="showDetailsModal = false" 
    />
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";
import TransactionDetailsModal from "@/Components/TransactionDetailsModal.vue";

const filters = ref({ type: "", status: "", from: "", to: "" });
const currentPage = ref(1);
const perPage = 8;
const transactions = ref([]);
const loading = ref(false);

const selectedTransaction = ref(null);
const showDetailsModal = ref(false);

const formatAmount = (amt, currency) => {
  if (amt === null || amt === undefined) return '---';
  const value = Number(amt);
  return currency === "USD" ? `$${value.toLocaleString()}` : `₦${value.toLocaleString()}`;
};

onMounted(async () => {
  await fetchTransactions();
});

const fetchTransactions = async () => {
  loading.value = true;
  try {
    const token = localStorage.getItem("xavier_token");
    const response = await api.get("/transactions", {
      headers: { Authorization: `Bearer ${token}` }
    });
    // Format incoming API data
    transactions.value = response.data.map(t => ({
      ...t,
      date: new Date(t.created_at).toISOString().split('T')[0],
      ref: t.id,
      amount: Number(t.amount),
      currency: t.currency || 'NGN',
      type: t.type,
      status: t.status?.toLowerCase()
    }));
  } catch (error) {
    console.error("Failed to fetch transactions:", error);
    // Fallback to sample data if API fails
    transactions.value = [
      { date: "2025-10-25", type: "Deposit", ref: "DEP-00128", amount: 500000, status: "Completed" },
      { date: "2025-10-26", type: "Withdrawal", ref: "WTH-00129", amount: 200000, status: "Completed" },
      { date: "2025-10-27", type: "Buy", ref: "BUY-00130", amount: 12500, status: "Completed" },
      { date: "2025-10-28", type: "Sell", ref: "SEL-00131", amount: 11500, status: "Pending" },
      { date: "2025-10-29", type: "Transfer", ref: "TRF-00132", amount: 80000, status: "Completed" },
      { date: "2025-10-29", type: "Buy", ref: "BUY-00133", amount: 12000, status: "Failed" },
      { date: "2025-10-29", type: "Deposit", ref: "DEP-00134", amount: 300000, status: "Completed" },
      { date: "2025-10-29", type: "Withdrawal", ref: "WTH-00135", amount: 100000, status: "Pending" },
      { date: "2025-10-29", type: "Sell", ref: "SEL-00136", amount: 25000, status: "Completed" },
      { date: "2025-10-29", type: "Deposit", ref: "DEP-00137", amount: 400000, status: "Completed" },
    ];
  } finally {
    loading.value = false;
  }
};

const filteredTransactions = computed(() => {
  return transactions.value.filter((t) => {
    // Convert to normalized date objects (YYYY-MM-DD string comparison is safer)
    const txnDateStr = t.date; 
    const fromDateStr = filters.value.from;
    const toDateStr = filters.value.to;

    const matchesType = !filters.value.type || t.type.toLowerCase() === filters.value.type.toLowerCase();
    const matchesStatus = !filters.value.status || t.status.toLowerCase() === filters.value.status.toLowerCase();
    const matchesFrom = !fromDateStr || txnDateStr >= fromDateStr;
    const matchesTo = !toDateStr || txnDateStr <= toDateStr;

    return matchesType && matchesStatus && matchesFrom && matchesTo;
  });
});

const totalPages = computed(() => Math.ceil(filteredTransactions.value.length / perPage));

const paginatedTransactions = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return filteredTransactions.value.slice(start, start + perPage);
});

function applyFilters() {
  currentPage.value = 1;
}

function clearFilters() {
  filters.value = { type: "", status: "", from: "", to: "" };
  currentPage.value = 1;
}

function nextPage() {
  if (currentPage.value < totalPages.value) currentPage.value++;
}

function prevPage() {
  if (currentPage.value > 1) currentPage.value--;
}

const openTransactionDetails = async (t) => {
  selectedTransaction.value = t;
  showDetailsModal.value = true;
};
</script>

<style scoped>
select,
input[type="date"] {
  appearance: none;
}
</style>
