<template>
  <MainLayout>
    <div class="max-w-6xl mx-auto">

      <!-- HEADER -->
      <h1 class="text-2xl font-bold mb-6">Transactions</h1>

      <!-- FILTER BAR -->
      <div class="flex items-center gap-4 mb-6">

        <input
          v-model="filters.q"
          @input="fetchTransactions"
          type="text"
          placeholder="Search email or Tx ID"
          class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg w-64"
        />

        <select
          v-model="filters.type"
          @change="fetchTransactions"
          class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg"
        >
          <option value="">All Types</option>
          <option value="deposit">Deposit</option>
          <option value="withdrawal">Withdrawal</option>
          <option value="trade">Trade</option>
        </select>

        <select
          v-model="filters.status"
          @change="fetchTransactions"
          class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg"
        >
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="successful">Successful</option>
          <option value="failed">Failed</option>
        </select>
      </div>

      <!-- TABLE -->
      <div class="bg-[#1C1F2E] rounded-xl border border-[#2A314A] overflow-hidden">

        <table class="w-full text-left text-sm">
          <thead class="bg-[#151a27] text-gray-400 uppercase tracking-wider">
            <tr>
              <th class="px-4 py-3">User</th>
              <th class="px-4 py-3">Type</th>
              <th class="px-4 py-3">Amount</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Date</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="tx in transactions.data" :key="tx.id" class="border-t border-[#2A314A]">
              <td class="px-4 py-3">{{ tx.user?.email }}</td>
              <td class="px-4 py-3 capitalize">{{ tx.type }}</td>
              <td class="px-4 py-3">₦{{ tx.amount.toLocaleString() }}</td>
              <td class="px-4 py-3">
                <span :class="statusColor(tx.status)">
                  {{ tx.status }}
                </span>
              </td>
              <td class="px-4 py-3">{{ new Date(tx.created_at).toLocaleString() }}</td>
            </tr>
          </tbody>

        </table>

      </div>

      <!-- PAGINATION -->
      <div class="flex justify-between mt-6">
        <button
          @click="changePage(transactions.prev_page_url)"
          :disabled="!transactions.prev_page_url"
          class="px-4 py-2 rounded-lg border border-[#2A314A] disabled:opacity-30"
        >
          Previous
        </button>

        <button
          @click="changePage(transactions.next_page_url)"
          :disabled="!transactions.next_page_url"
          class="px-4 py-2 rounded-lg border border-[#2A314A] disabled:opacity-30"
        >
          Next
        </button>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import MainLayout from "@/Layouts/MainLayout.vue";

const filters = ref({
  q: "",
  type: "",
  status: "",
});

const transactions = ref({ data: [] });

const fetchTransactions = async (url = "/api/admin/transactions") => {
  const res = await axios.get(url, { params: filters.value });
  transactions.value = res.data.data;
};

const changePage = (url) => {
  if (!url) return;
  fetchTransactions(url);
};

const statusColor = (status) => ({
  pending: "text-yellow-400",
  successful: "text-green-400",
  failed: "text-red-400",
})[status];

onMounted(fetchTransactions);
</script>
