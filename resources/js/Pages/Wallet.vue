<template>
  <MainLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">💼 Wallet</h1>
          <p class="text-sm text-gray-400">Manage your NGN & USD balances</p>
        </div>

        <div class="flex gap-3">
          <button @click="openTransaction('deposit')"
            class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white font-semibold hover:bg-[#252a3d] transition">
            + Deposit
          </button>
          <button @click="openTransaction('withdrawal')"
            class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white font-semibold hover:bg-[#252a3d] transition">
            - Withdraw
          </button>
          <button @click="openConvert = true"
            class="bg-gradient-to-r from-[#0047AB] to-[#00D4FF] px-4 py-2 rounded-lg text-white font-semibold hover:opacity-90 transition">
            ⇄ Convert Currency
          </button>
        </div>
      </div>

      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-8 overflow-hidden">
        <div class="flex items-center gap-12 mb-6 border-b border-[#1f3348] pb-6">
          <div class="flex items-center gap-3">
            <div class="w-2 h-2 bg-white rounded-full"></div>
            <div>
              <h2 class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">NGN Wallet</h2>
              <div class="text-xl font-bold text-white">₦{{ Number(balances.balance_ngn).toLocaleString() }}</div>
            </div>
          </div>

          <div class="flex items-center gap-3 border-l border-[#1f3348] pl-12">
            <div class="w-2 h-2 rounded-full bg-[#00D4FF]"></div>
            <div>
              <h2 class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">USD Wallet</h2>
              <div class="text-xl font-bold text-[#00D4FF]">${{ Number(balances.balance_usd).toLocaleString() }}</div>
            </div>
          </div>
        </div>

        <div class="h-[180px] -mx-4">
          <apexchart type="line" height="100%" :options="combinedOptions" :series="combinedSeries" />
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
                <td class="px-2 capitalize">
                  <div class="font-medium">{{ t.type }}</div>
                  <div v-if="t.meta && t.meta.bank_name" class="text-[10px] text-gray-500 leading-tight">
                    to {{ t.meta.bank_name }} ({{ t.meta.account_number }})
                  </div>
                </td>
                <td class="px-2 text-right">{{ formatAmount(t.amount, t.currency) }}</td>
                <td class="px-2 text-right text-red-400">-{{ formatAmount(t.charge, t.currency) }}</td>
                <td class="px-2 font-bold text-right text-green-400">{{ formatAmount(t.net_amount || t.amount,
                  t.currency) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
          <button @click="showModal = false" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>
          <h2 class="mb-4 text-xl font-semibold capitalize">{{ txnType }} Funds</h2>

          <p class="mb-4 text-sm text-gray-400">
            Current {{ form.currency }} Balance:
            <span class="font-bold text-white">
              {{ form.currency === 'NGN' ? '₦' + Number(balances.balance_ngn).toLocaleString() : '$' +
                Number(balances.balance_usd).toLocaleString() }}
            </span>
          </p>

          <form @submit.prevent="submitTransaction">
            <div class="space-y-4">
              <div>
                <label class="text-sm text-gray-400">Select Wallet</label>
                <select v-model="form.currency"
                  class="w-full px-4 py-2 mt-1 text-white bg-[#151a27] border border-gray-600 rounded-lg">
                  <option value="NGN">NGN Wallet</option>
                  <option value="USD">USD Wallet</option>
                </select>
              </div>

              <div v-if="txnType === 'withdrawal'">
                <label class="text-sm text-gray-400">Withdraw to Account</label>
                <select v-model="selectedAccountId" required
                  class="w-full px-4 py-2 mt-1 text-white bg-[#151a27] border border-gray-600 rounded-lg">
                  <option value="" disabled>Select a verified account</option>
                  <option v-for="acc in linkedAccounts" :key="acc.id" :value="acc.id">
                    {{ acc.provider }} - {{ acc.account_number }} ({{ acc.is_verified ? 'Verified' : 'Pending' }})
                  </option>
                </select>
                <p v-if="linkedAccounts.length === 0" class="mt-1 text-[10px] text-yellow-500">
                  No verified linked accounts found. Please add one in settings.
                </p>
              </div>

              <div>
                <label class="text-sm text-gray-400">Amount ({{ form.currency }})</label>
                <input v-model.number="form.amount" type="number" step="0.01"
                  class="w-full px-4 py-2 mt-1 text-white bg-transparent border border-gray-600 rounded-lg focus:outline-none focus:border-blue-500"
                  required />
              </div>

              <div class="p-3 border rounded-lg bg-black/20 border-blue-500/20">
                <p class="text-[10px] text-gray-400 uppercase tracking-widest">Fee Information</p>
                <p class="text-xs text-blue-300">Standard platform fees will be applied to this {{ txnType }}.</p>
              </div>
            </div>

            <button :disabled="loading"
              class="w-full mt-6 bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-3 rounded-lg font-bold disabled:opacity-50">
              {{ loading ? 'Processing...' : 'Confirm ' + txnType }}
            </button>
          </form>
          <p v-if="message" :class="message.includes('Success') ? 'text-green-400' : 'text-yellow-300'"
            class="mt-4 text-sm font-medium text-center">{{ message }}</p>
        </div>
      </div>

      <div v-if="openConvert" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
          <button @click="openConvert = false" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>
          <h2 class="mb-4 text-xl font-semibold">Convert Currency</h2>

          <p class="mb-4 text-sm text-gray-400">
            Available to convert:
            <span class="font-bold text-white">
              {{ from === 'NGN' ? '₦' + Number(balances.balance_ngn).toLocaleString() : '$' +
                Number(balances.balance_usd).toLocaleString() }}
            </span>
          </p>

          <form @submit.prevent="convertCurrency">
            <label class="text-sm text-gray-400">From Currency</label>
            <select v-model="from"
              class="w-full px-4 py-2 mt-1 mb-4 text-white bg-[#151a27] border border-gray-600 rounded-lg">
              <option value="NGN">NGN → USD</option>
              <option value="USD">USD → NGN</option>
            </select>
            <label class="text-sm text-gray-400">Amount</label>
            <input v-model.number="amount" type="number"
              class="w-full px-4 py-2 mt-1 text-white bg-transparent border border-gray-600 rounded-lg"
              placeholder="Enter amount" required />

            <div v-if="amount > 0" class="p-3 mt-4 border rounded-lg bg-blue-500/10 border-blue-500/30 animate-pulse">
              <div class="text-[10px] text-blue-400 uppercase font-bold">Estimated Receipt</div>
              <div class="text-lg font-bold text-white">
                {{ from === 'NGN' ? '$' + (amount * 0.00065).toFixed(2) : '₦' + (amount / 0.00065).toLocaleString() }}
              </div>
              <div class="text-[9px] text-gray-500 italic mt-1">Rate: 1 NGN = 0.00065 USD</div>
            </div>

            <button :disabled="loading"
              class="w-full mt-5 bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-2 rounded-lg font-semibold disabled:opacity-50">
              {{ loading ? 'Converting...' : 'Convert Now' }}
            </button>
          </form>
          <p v-if="message" :class="message.includes('Success') ? 'text-green-400' : 'text-yellow-300'"
            class="mt-4 text-sm font-medium text-center">{{ message }}</p>
        </div>
      </div>

      <!-- Payment Result Modal -->
      <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
          <button @click="closePaymentModal" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>
          <div class="text-center">
            <div class="mb-4 text-6xl" v-if="paymentResult.success">✅</div>
            <div class="mb-4 text-6xl" v-else>❌</div>

            <h2 class="mb-4 text-2xl font-bold" :class="paymentResult.success ? 'text-green-400' : 'text-red-400'">
              {{ paymentResult.success ? 'Payment Successful!' : 'Payment Failed' }}
            </h2>

            <p class="mb-6 text-gray-400" v-if="paymentResult.success">
              Your wallet has been credited with ₦{{ paymentResult.amount.toLocaleString() }}
            </p>
            <p class="mb-6 text-gray-400" v-else>
              {{ paymentResult.message }}
            </p>

            <button @click="closePaymentModal"
              class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-3 rounded-lg font-semibold">
              Return to Wallet
            </button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed, watch } from "vue";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";
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

const linkedAccounts = ref([]);
const selectedAccountId = ref("");

const form = ref({
  amount: 0,
  currency: "NGN"
});

// Payment result modal
const showPaymentModal = ref(false);
const paymentResult = ref({
  success: false,
  message: '',
  amount: 0
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
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
  });
};

// --- API Methods ---
const fetchLinkedAccountsFor = async (currency) => {
  try {
    const accRes = await api.get(`/user/linked-accounts/index?currency=${currency}`);
    linkedAccounts.value = accRes.data.data.filter(acc => acc.is_verified);
  } catch (e) {
    console.error('Failed to fetch linked accounts for', currency, e);
    linkedAccounts.value = [];
  }
};

const refreshData = async () => {
  balances.value = { balance_ngn: 0, balance_usd: 0 };
  try {
    const [balRes, txnRes, accRes] = await Promise.all([
      api.get("/wallet/balances"),
      api.get("/transactions"),
      api.get("/user/linked-accounts/index")
    ]);

    balances.value = balRes.data.data;
    // general list (no currency filter) for settings and other screens
    linkedAccounts.value = accRes.data.data.filter(acc => acc.is_verified);

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
  form.value = {
    amount: 0,
    currency: type === 'deposit' ? 'NGN' : 'NGN' // Force NGN for deposits (Paystack only)
  };
  selectedAccountId.value = "";
  message.value = "";
  showModal.value = true;
  if (type === 'withdrawal') {
    fetchLinkedAccountsFor(form.value.currency);
  }
};

const submitTransaction = async () => {
  if (form.value.amount <= 0) return;
  if (txnType.value === 'withdrawal' && !selectedAccountId.value) {
    message.value = "Please select a destination account";
    return;
  }

  loading.value = true;
  message.value = "Processing...";

  if (txnType.value === 'deposit') {
    // Use Paystack for deposits
    try {
      const response = await api.post('/paystack/initiate', {
        amount: form.value.amount
      });

      if (response.data.success) {
        // Redirect to Paystack checkout
        window.location.href = response.data.data.authorization_url;
        return; // Don't close modal yet
      }
    } catch (e) {
      message.value = e.response?.data?.message || "Payment initiation failed";
      loading.value = false;
      return;
    }
  } else {
    // Handle withdrawals with existing logic
    const endpoint = '/withdraw';
    try {
      await api.post(endpoint, {
        amount: form.value.amount,
        currency: form.value.currency,
        linked_account_id: selectedAccountId.value
      });

      message.value = "Successful!";
      setTimeout(() => {
        showModal.value = false;
        refreshData();
      }, 1500);
    } catch (e) {
      message.value = e.response?.data?.message || "Transaction failed";
    } finally {
      loading.value = false;
    }
  }
};

const convertCurrency = async () => {
  if (amount.value <= 0) return;
  loading.value = true;
  message.value = "Converting...";

  try {
    await api.post("/wallet/convert", {
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

const FX_CONVERSION_RATE = 1538;

const combinedSeries = computed(() => [
  { 
    name: "NGN", 
    // Plotting actual Naira values
    data: [95000, 110000, 140000, 130000, 160000, balances.value.balance_ngn] 
  },
  { 
    name: "USD (Valued in NGN)", 
    // Plotting converted USD values so the chart scale is accurate
    data: [
      400 * FX_CONVERSION_RATE, 
      550 * FX_CONVERSION_RATE, 
      480 * FX_CONVERSION_RATE, 
      600 * FX_CONVERSION_RATE, 
      1000 * FX_CONVERSION_RATE, 
      balances.value.balance_usd * FX_CONVERSION_RATE
    ] 
  }
]);

const combinedOptions = {
  chart: {
    type: 'line',
    toolbar: { show: false },
    animations: { enabled: true }
  },
  grid: {
    show: true,
    borderColor: '#1f3348',
    strokeDashArray: 4,
    padding: { top: 10, right: 20, bottom: 0, left: 20 }
  },
  stroke: { curve: "smooth", width: 3 },
  markers: { size: 0 },
  colors: ["#FFFFFF", "#00D4FF"],
  xaxis: {
    labels: { show: false },
    axisBorder: { show: false },
    axisTicks: { show: false }
  },
  yaxis: {
   show: false,
  },
  tooltip: { 
    theme: 'dark',
  },
  legend: { show: false }
};

// Check for payment result parameters on page load
const checkPaymentResult = () => {
  const urlParams = new URLSearchParams(window.location.search);
  const paymentSuccess = urlParams.get('payment_success');
  const paymentError = urlParams.get('payment_error');

  if (paymentSuccess) {
    const amount = parseFloat(paymentSuccess);

    // Successful payment
    paymentResult.value = {
      success: true,
      message: 'Payment completed successfully!',
      amount: amount
    };
    showPaymentModal.value = true;

    // Clear URL parameters
    const newUrl = window.location.pathname;
    window.history.replaceState({}, document.title, newUrl);

    // Refresh wallet data multiple times to catch webhook processing
    refreshWithRetry();
  } else if (paymentError) {
    // Failed payment
    let errorMessage = 'Payment failed. Please try again.';
    switch (paymentError) {
      case 'payment_failed':
        errorMessage = 'Payment was not successful. Please contact support if amount was debited.';
        break;
      case 'verification_error':
        errorMessage = 'Unable to verify payment. Please contact support.';
        break;
      case 'no_reference':
        errorMessage = 'Payment reference missing. Please contact support.';
        break;
    }

    paymentResult.value = {
      success: false,
      message: errorMessage,
      amount: 0
    };
    showPaymentModal.value = true;

    // Clear URL parameters
    const newUrl = window.location.pathname;
    window.history.replaceState({}, document.title, newUrl);
  }
};

const closePaymentModal = () => {
  showPaymentModal.value = false;
  paymentResult.value = { success: false, message: '', amount: 0 };

  // Ensure transaction list is updated after closing modal
  refreshData();
};

// Retry refresh until balance/transaction updates are detected
const refreshWithRetry = async (attempts = 0, maxAttempts = 10) => {
  if (attempts >= maxAttempts) {
    console.warn('Max refresh attempts reached, balance may not have updated yet');
    return;
  }

  const previousBalance = balances.value.balance_ngn;
  const previousTxnCount = transactions.value.length;

  await refreshData();

  // Check if balance or transaction count changed
  const balanceUpdated = balances.value.balance_ngn !== previousBalance;
  const transactionsUpdated = transactions.value.length !== previousTxnCount;

  if (balanceUpdated || transactionsUpdated) {
    console.log('Payment data updated successfully');
    return; // Success, data has updated
  }

  // Try again in 2 seconds
  setTimeout(() => refreshWithRetry(attempts + 1, maxAttempts), 2000);
};

onMounted(() => {
  refreshData();
  checkPaymentResult();
});

// refresh linked accounts when wallet currency toggles while withdrawing
watch(() => form.value.currency, (val) => {
  if (txnType.value === 'withdrawal') {
    fetchLinkedAccountsFor(val);
  }
});
</script>