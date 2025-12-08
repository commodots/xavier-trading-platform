<template>
  <MainLayout>
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold">🔁 Transactions</h1>
          <p class="text-gray-400 text-sm">Track all your wallet and trading activities.</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="text-xs text-gray-400">Type</label>
            <select v-model="filters.type" class="w-full bg-[#1C2541] text-gray-300 rounded-lg px-3 py-2 mt-1 border border-[#1f3348] focus:border-[#00D4FF] focus:ring-0">
              <option value="">All</option>
              <option>Deposit</option>
              <option>Withdrawal</option>
              <option>Buy</option>
              <option>Sell</option>
              <option>Transfer</option>
            </select>
          </div>

          <div>
            <label class="text-xs text-gray-400">Status</label>
            <select v-model="filters.status" class="w-full bg-[#1C2541] text-gray-300 rounded-lg px-3 py-2 mt-1 border border-[#1f3348] focus:border-[#00D4FF] focus:ring-0">
              <option value="">All</option>
              <option>Completed</option>
              <option>Pending</option>
              <option>Failed</option>
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

        <div class="mt-4 flex justify-end">
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
          <h2 class="font-semibold text-lg">Transaction History</h2>
          <span class="text-sm text-gray-400">Page {{ currentPage }} of {{ totalPages }}</span>
        </div>

        <div v-if="paginatedTransactions.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="py-2 text-left px-2">Date</th>
                <th class="text-left px-2">Type</th>
                <th class="text-left px-2">Reference</th>
                <th class="text-right px-2">Amount</th>
                <th class="text-center px-2">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="t in paginatedTransactions"
                :key="t.ref"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition"
              >
                <td class="py-3 px-2">{{ t.date }}</td>
                <td class="px-2">{{ t.type }}</td>
                <td class="px-2">{{ t.ref }}</td>
                <td
                  class="px-2 text-right"
                  :class="t.type === 'Deposit' ? 'text-green-400' : 'text-red-400'"
                >
                  ₦{{ t.amount.toLocaleString() }}
                </td>
                <td class="px-2 text-center">
                  <span
                    :class="[
                      'px-3 py-1 rounded-full text-xs',
                      t.status === 'Completed'
                        ? 'bg-green-500/20 text-green-400'
                        : t.status === 'Pending'
                        ? 'bg-yellow-500/20 text-yellow-300'
                        : 'bg-red-500/20 text-red-400'
                    ]"
                  >
                    {{ t.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-gray-400 text-center py-6">
          No transactions match the selected filters.
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center mt-6">
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
  </MainLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import MainLayout from "@/layouts/MainLayout.vue";

const filters = ref({ type: "", status: "", from: "", to: "" });
const currentPage = ref(1);
const perPage = 8;

// sample transactions
const allTransactions = ref([
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
]);

const filteredTransactions = computed(() => {
  return allTransactions.value.filter((t) => {
    return (
      (!filters.value.type || t.type === filters.value.type) &&
      (!filters.value.status || t.status === filters.value.status) &&
      (!filters.value.from || new Date(t.date) >= new Date(filters.value.from)) &&
      (!filters.value.to || new Date(t.date) <= new Date(filters.value.to))
    );
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

function nextPage() {
  if (currentPage.value < totalPages.value) currentPage.value++;
}

function prevPage() {
  if (currentPage.value > 1) currentPage.value--;
}
</script>

<style scoped>
select,
input[type="date"] {
  appearance: none;
}
</style>
