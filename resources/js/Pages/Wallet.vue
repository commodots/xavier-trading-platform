<template>
  <MainLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">💼 Wallet</h1>
          <p class="text-sm text-gray-400">Manage your NGN & USD balances</p>
        </div>

        <div class="flex gap-3">
          <button
            @click="openTransaction('deposit')"
            class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white font-semibold hover:bg-[#252a3d] transition"
          >
            + Deposit
          </button>
          <button
            @click="openTransaction('withdrawal')"
            class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white font-semibold hover:bg-[#252a3d] transition"
          >
            - Withdraw
          </button>
          <button
            @click="openConvert = true"
            class="bg-gradient-to-r from-[#0047AB] to-[#00D4FF] px-4 py-2 rounded-lg text-white font-semibold hover:opacity-90 transition"
          >
            ⇄ Convert Currency
          </button>
        </div>
      </div>

      <!-- Wallet Cards -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <!-- NGN -->
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
          <h2 class="mb-1 text-gray-300">NGN Wallet</h2>
          <div class="text-3xl font-bold text-white">₦{{ Number(balances.balance_ngn).toLocaleString() }}</div>
          <div class="mt-4">
            <apexchart type="area" height="120" :options="chartOptions" :series="sparkNgn" />
          </div>
        </div>

        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
          <h2 class="mb-1 text-gray-300">USD Wallet</h2>
          <div class="text-3xl font-bold text-white">${{ Number(balances.balance_usd).toLocaleString() }}</div>
          <div class="mt-4">
            <apexchart type="area" height="120" :options="chartOptions" :series="sparkUsd" />
          </div>
        </div>
      </div>

      <!-- Recent Transactions -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h2 class="mb-3 text-lg font-semibold">Recent Transactions</h2>
        <div v-if="transactions.length === 0" class="py-6 text-center text-gray-500">No recent transactions.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="px-2 py-2 text-left">Date</th>
                <th class="px-2 text-left">Type</th>
                <th class="px-2 text-right">Gross</th>
                <th class="px-2 text-right">Fee</th>
                <th class="px-2 text-right">Net</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in transactions" :key="t.id" class="border-b border-[#1f3348] hover:bg-[#16213A] transition">
                <td class="px-2 py-3 text-gray-400">{{ formatDate(t.created_at) }}</td>
                <td class="px-2 capitalize">{{ t.type }}</td>
                <td class="px-2 text-right">{{ formatAmount(t.amount, t.currency) }}</td>
                <td class="px-2 text-right text-red-400">-{{ formatAmount(t.charge, t.currency) }}</td>
                <td class="px-2 text-right font-bold text-green-400">{{ formatAmount(t.net_amount || t.amount, t.currency) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
          <button @click="showModal = false" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>
          <h2 class="mb-4 text-xl font-semibold capitalize">{{ txnType }} Funds</h2>
          
          <form @submit.prevent="submitTransaction">
            <div class="space-y-4">
              <div>
                <label class="text-sm text-gray-400">Amount ({{ form.currency }})</label>
                <input v-model.number="form.amount" type="number" step="0.01" class="w-full px-4 py-2 mt-1 bg-transparent border border-gray-600 rounded-lg text-white focus:outline-none focus:border-blue-500" required />
              </div>

              <div class="p-3 bg-black/20 rounded-lg border border-blue-500/20">
                <p class="text-[10px] text-gray-400 uppercase tracking-widest">Fee Information</p>
                <p class="text-xs text-blue-300">Standard platform fees will be applied to this {{ txnType }}.</p>
              </div>
            </div>

            <button :disabled="loading" class="w-full mt-6 bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-3 rounded-lg font-bold disabled:opacity-50">
              {{ loading ? 'Processing...' : 'Confirm ' + txnType }}
            </button>
          </form>
          <p v-if="message" class="mt-4 text-sm text-center text-yellow-300">{{ message }}</p>
        </div>
      </div>

      <div v-if="openConvert" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
          <button @click="openConvert = false" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>
          <h2 class="mb-4 text-xl font-semibold">Convert Currency</h2>
          <form @submit.prevent="convertCurrency">
            <label class="text-sm text-gray-400">From Currency</label>
            <select v-model="from" class="w-full px-4 py-2 mt-1 mb-4 text-white bg-[#151a27] border border-gray-600 rounded-lg">
              <option value="NGN">NGN → USD</option>
              <option value="USD">USD → NGN</option>
            </select>
            <label class="text-sm text-gray-400">Amount</label>
            <input v-model.number="amount" type="number" class="w-full px-4 py-2 mt-1 bg-transparent border border-gray-600 rounded-lg text-white" placeholder="Enter amount" required />
            <button :disabled="loading" class="w-full mt-5 bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-2 rounded-lg font-semibold disabled:opacity-50">
              {{ loading ? 'Converting...' : 'Convert Now' }}
            </button>
          </form>
          <p v-if="message" class="mt-4 text-sm text-center text-yellow-300">{{ message }}</p>
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

// --- State ---
const balances = ref({ balance_ngn: 0, balance_usd: 0 });
const transactions = ref([]);
const message = ref("");
const loading = ref(false);

const openConvert = ref(false);
const showModal = ref(false);
const txnType = ref(""); 
const from = ref("NGN");
const amount = ref(0);

const form = ref({
  amount: 0,
  currency: "NGN"
});

// --- Formatting Helpers ---
const formatAmount = (amt, currency) => {
  if (amt === null || amt === undefined) return '---';
  const value = Number(amt);
  return currency === "USD" ? `$${value.toLocaleString()}` : `₦${value.toLocaleString()}`;
};

const formatDate = (dateStr) => {
  if (!dateStr) return "Just now";
  const date = new Date(dateStr);
  return isNaN(date.getTime()) ? dateStr : date.toLocaleDateString('en-NG', { 
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' 
  });
};

// --- API Methods ---
const refreshData = async () => {
  try {
    const [balRes, txnRes] = await Promise.all([
      axios.get("/wallet/balances"),
      axios.get("/transactions")
    ]);
    balances.value = balRes.data.data;

console.log("Transaction Data:", txnRes.data);

    if (Array.isArray(txnRes.data)) {
        transactions.value = txnRes.data;
    } 
    else if (txnRes.data.transactions) {
        transactions.value = txnRes.data.transactions;
    }
  } catch (e) {
    console.error("Fetch error", e);
  }
};

const openTransaction = (type) => {
  txnType.value = type;
  form.value = { amount: 0, currency: "NGN" };
  message.value = "";
  showModal.value = true;
};

const submitTransaction = async () => {
  if (form.value.amount <= 0) return;
  
  loading.value = true;
  message.value = "Processing...";
  
  // Mapping the UI type to the specific route
  const endpoint = txnType.value === 'deposit' ? '/deposit' : '/withdraw';
  
  try {
    const res = await axios.post(endpoint, {
      amount: form.value.amount,
      currency: form.value.currency
    });

    message.value = "Successful!";
    setTimeout(() => { 
      showModal.value = false; 
      refreshData(); 
    }, 1500);
  } catch (e) {
    console.error (e.response?.data?.message);
    message.value = "Transaction failed";
  } finally {
    loading.value = false;
  }
};

const convertCurrency = async () => {
  if (amount.value <= 0) return;
  
  loading.value = true;
  message.value = "Converting...";
  
  try {
    const res = await axios.post("/wallet/convert", {
      from: from.value,
      amount: amount.value 
    });

    message.value = "Converted successfully!";
    setTimeout(() => {
      openConvert.value = false;
      refreshData();
    }, 1500);
  } catch (e) {
    message.value = e.response?.data?.message || "Conversion failed";
  } finally {
    loading.value = false;
  }
};

// Chart Data
const sparkNgn = ref([{ name: "NGN", data: [20000, 50000, 120000, 90000, 150000] }]);
const sparkUsd = ref([{ name: "USD", data: [10, 40, 50, 30, 80] }]);
const chartOptions = {
  chart: { sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  fill: { opacity: 0.2 },
  colors: ["#00D4FF"],
};

onMounted(refreshData);
</script>