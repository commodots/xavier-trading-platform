<template>
  <MainLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">💼 Wallet</h1>
          <p class="text-gray-400 text-sm">Manage your NGN & USD balances</p>
        </div>

        <div class="flex gap-3">
          <button
            @click="openConvert = true"
            class="bg-gradient-to-r from-[#0047AB] to-[#00D4FF] px-4 py-2 rounded-lg text-white font-semibold hover:opacity-90 transition"
          >
            ⇄ Convert Currency
          </button>
        </div>
      </div>

      <!-- Wallet Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- NGN -->
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
          <h2 class="text-gray-300 mb-1">NGN Wallet</h2>
          <div class="text-3xl font-bold text-white">₦{{ balances.balance_ngn.toLocaleString() }}</div>

          <div class="mt-4">
            <apexchart
              type="area"
              height="120"
              :options="chartOptions"
              :series="sparkNgn"
            />
          </div>
        </div>

        <!-- USD -->
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
          <h2 class="text-gray-300 mb-1">USD Wallet</h2>
          <div class="text-3xl font-bold text-white">${{ balances.balance_usd.toLocaleString() }}</div>

          <div class="mt-4">
            <apexchart
              type="area"
              height="120"
              :options="chartOptions"
              :series="sparkUsd"
            />
          </div>
        </div>
      </div>

      <!-- Recent Transactions -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h2 class="font-semibold text-lg mb-3">Recent Transactions</h2>

        <div v-if="transactions.length === 0" class="text-gray-500 text-center py-6">
          No recent transactions.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="text-left py-2 px-2">Date</th>
                <th class="text-left px-2">Type</th>
                <th class="text-left px-2">Currency</th>
                <th class="text-right px-2">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="t in transactions"
                :key="t.id"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition"
              >
                <td class="px-2 py-3">{{ t.date }}</td>
                <td class="px-2">{{ t.type }}</td>
                <td class="px-2">{{ t.currency }}</td>
                <td class="px-2 text-right">{{ formatAmount(t.amount, t.currency) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Convert Modal -->
      <div
        v-if="openConvert"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50"
      >
        <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative">
          <button
            @click="openConvert = false"
            class="absolute top-3 right-3 text-gray-400 hover:text-white"
          >
            ✖
          </button>

          <h2 class="text-xl font-semibold mb-4">Convert Currency</h2>

          <form @submit.prevent="convertCurrency">
            <label class="text-sm text-gray-400">From Currency</label>
            <select
              v-model="from"
              class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 mt-1 mb-4"
            >
              <option value="NGN">NGN → USD</option>
              <option value="USD">USD → NGN</option>
            </select>

            <label class="text-sm text-gray-400">Amount</label>
            <input
              v-model.number="amount"
              type="number"
              class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 mt-1"
              placeholder="Enter amount"
              required
            />

            <button
              class="w-full mt-5 bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-2 rounded-lg font-semibold"
            >
              Convert Now
            </button>
          </form>

          <p v-if="message" class="mt-4 text-yellow-300 text-sm text-center">
            {{ message }}
          </p>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import MainLayout from "@/layouts/MainLayout.vue";
import VueApexCharts from "vue3-apexcharts";

const apexchart = VueApexCharts;

// --- Reactive State ---
const balances = ref({
  balance_ngn: 0,
  balance_usd: 0,
});
const transactions = ref([]);

const openConvert = ref(false);
const message = ref("");
const from = ref("NGN");
const amount = ref(0);

// Sparkline dummy
const sparkNgn = ref([{ name: "NGN", data: [20000, 50000, 120000, 90000, 150000] }]);
const sparkUsd = ref([{ name: "USD", data: [10, 40, 50, 30, 80] }]);

const chartOptions = {
  chart: { sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  fill: { opacity: 0.2 },
  colors: ["#00D4FF"],
};

// Format Currency
const formatAmount = (amt, currency) => {
  return currency === "USD" ? `$${amt}` : `₦${amt.toLocaleString()}`;
};

onMounted(async () => {
  const token = localStorage.getItem("xavier_token");

  const res = await axios.get("/api/wallet/balances", {
    headers: { Authorization: `Bearer ${token}` },
  });

  balances.value = res.data.data;

  // dummy transactions
  transactions.value = [
    { id: 1, date: "2025-11-01", type: "Convert", currency: "USD", amount: 50 },
    { id: 2, date: "2025-10-29", type: "Deposit", currency: "NGN", amount: 200000 },
  ];
});

// Currency Conversion
const convertCurrency = async () => {
  message.value = "Processing...";

  const token = localStorage.getItem("xavier_token");
  try {
    const res = await axios.post(
      "/api/wallet/convert",
      { from: from.value, amount: amount.value },
      { headers: { Authorization: `Bearer ${token}` } }
    );

    if (res.data.success) {
      balances.value = res.data.data;
      message.value = "Conversion successful ✔";
    } else {
      message.value = res.data.message;
    }
  } catch (e) {
    message.value = "Server error";
  }
};
</script>
git 