<template>
  <MainLayout>
    <div class="space-y-6">
      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      <div class="flex items-center justify-between gap-1">
        <div>
          <h1 class="flex items-center gap-2 text-2xl font-semibold" :class="isDemo ? 'text-yellow-500' : 'text-white'">
            {{ isDemo ? 'Demo Wallet' : '💼 Wallet ' }}
          </h1>
          <p class="text-sm text-gray-400">
            {{ isDemo ? 'Manage Your Demo Wallet Balances - NGN & USD' : 'Manage Your Wallet Balances - NGN & USD' }}
          </p>
        </div>

        <div class="flex gap-2">
          <template v-if="!isDemo">
            <button @click="openTransaction('deposit')"
              class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white font-semibold hover:bg-[#252a3d] transition">
              + Deposit
            </button>
            <button @click="openTransaction('withdrawal')"
              :disabled="(balances.cleared_balance_ngn <= 0 && balances.cleared_balance_usd <= 0)"
              class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white font-semibold hover:bg-[#252a3d] transition">
              - Withdraw
            </button>
            <button @click="openConvertModal"
              class="bg-gradient-to-r from-[#0047AB] to-[#00D4FF] px-4 py-2 rounded-lg text-white font-semibold hover:opacity-90 transition">
              ⇄ Convert Currency
            </button>
          </template>
          <template v-else>
            <div class="flex gap-2 ml-1 l-3">
              <button @click="openConvertModal"
                class="px-4 py-2 font-semibold text-white transition border border-yellow-600 rounded-lg hover:opacity-90 bg-yellow-600/20">
                ⇄ Convert Currency
              </button>
              <button @click="refillDemo" :disabled="loading"
                class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-yellow-500 transition border border-yellow-600 rounded-lg bg-yellow-600/10 hover:bg-yellow-600/20">
                <span v-if="loading && actionType === 'refill'"
                  class="w-3 h-3 border-2 border-yellow-500 rounded-full animate-spin border-t-transparent"></span>
                Refill Demo Account
              </button>
              <button @click="promptResetDemo" :disabled="loading"
                class="px-4 py-2 text-xs font-bold text-red-500 transition border border-red-600 rounded-lg bg-red-600/10 hover:bg-red-600/20">
                Reset Demo Account
              </button>
            </div>
          </template>
        </div>
      </div>

      <div :class="loading && !actionType ? 'blur-sm animate-pulse' : ''" class="transition-all duration-300">
        <div class="p-8 border rounded-xl"
          :class="isDemo ? 'border-yellow-600  bg-yellow-600/10' : 'border-[#1f3348] bg-[#0F1724]'">
          <div class="flex items-center gap-12 mb-6 border-b border-[#1f3348] pb-6">

            <div class="flex items-center gap-3">
              <div class="w-2 h-2 rounded-full" :class="isDemo ? 'bg-yellow-500' : 'bg-white'"></div>
              <div>
                <h2 class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">NGN WALLET</h2>
                <div class="text-2xl font-bold transition-colors" :class="isDemo ? 'text-yellow-400' : 'text-white'">
                  ₦{{ Number(balances.balance_ngn).toLocaleString() }}
                </div>
                <div class="text-sm text-gray-400">
                  Cleared Balance: ₦{{ Number(balances.cleared_balance_ngn).toLocaleString() }}
                </div>
                <div class="text-sm text-yellow-400">
                  Uncleared Balance: ₦{{ Number(balances.uncleared_balance_ngn).toLocaleString() }}
                </div>
                <div class="text-sm text-red-400" v-if="balances.locked_balance_ngn > 0">
                  Locked (In Orders): ₦{{ Number(balances.locked_balance_ngn).toLocaleString() }}
                </div>
              </div>
            </div>

            <div class="flex items-center gap-3 border-l border-[#1f3348] pl-12">
              <div class="w-2 h-2 rounded-full" :class="isDemo ? 'bg-yellow-500' : 'bg-white'"></div>
              <div>
                <h2 class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">USD Wallet</h2>
                <div class="text-xl font-bold" :class="isDemo ? 'text-yellow-400' : 'text-white'">${{
                  Number(balances.balance_usd).toLocaleString() }}</div>
                <div class="text-sm text-gray-400">
                  Cleared Balance: ${{ Number(balances.cleared_balance_usd).toLocaleString() }}
                </div>
                <div class="text-sm text-yellow-400">
                  Uncleared Balance: ${{ Number(balances.uncleared_balance_usd).toLocaleString() }}
                </div>
                <div class="text-sm text-red-400" v-if="balances.locked_balance_usd > 0">
                  Locked (In Orders): ${{ Number(balances.locked_balance_usd).toLocaleString() }}
                </div>
              </div>
            </div>
          </div>

          <div class="h-[180px] -mx-4">
            <apexchart type="line" height="100%" :options="combinedOptions" :series="combinedSeries" />
          </div>
        </div>
      </div>

      <div
        :class="loading && !actionType ? 'blur-sm animate-pulse opacity-50 pointer-events-none transition-all duration-300' : 'transition-all duration-300'">
        <div class="p-5 border rounded-xl"
          :class="isDemo ? 'border-yellow-600  bg-yellow-600/10' : 'border-[#1f3348] bg-[#0F1724]'">
          <h2 class="mb-3 text-lg font-semibold">{{ isDemo ? 'Demo Transactions' : 'Recent Transactions' }}</h2>

          <div v-if="transactions.length === 0" class="py-10 text-center">
            <div class="font-medium text-gray-500">No recent {{ isDemo ? 'demo activity' : 'transactions' }}.</div>
          </div>

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
                <tr v-for="t in transactions" :key="t.id" @click="openTransactionDetails(t)"
                  class="border-b border-[#1f3348] hover:bg-[#16213A] transition cursor-pointer">
                  <td class="px-2 py-3 text-gray-400">{{ formatDate(t.created_at) }}</td>
                  <td class="px-2 capitalize">
                    <div class="font-medium">{{ t.type }}</div>
                    <div v-if="t.meta && t.meta.bank_name" class="text-[10px] text-gray-500 leading-tight">
                      to {{ t.meta.bank_name }} ({{ t.meta.account_number }})
                    </div>
                  </td>
                  <td class="px-2 text-right">{{ formatAmount(t.amount || t.total, t.currency || 'NGN') }}</td>
                  <td class="px-2 text-right text-red-400">-{{ formatAmount(t.charge || 0, t.currency || 'NGN') }}</td>
                  <td class="px-2 font-bold text-right text-green-400">{{ formatAmount(t.net_amount || t.amount ||
                    t.total,
                    t.currency || 'NGN') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
          <button @click="showModal = false" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>
          <h2 class="mb-4 text-xl font-semibold capitalize">{{ txnType }} Funds</h2>

          <p class="mb-4 text-sm text-gray-400">
            {{ txnType === 'withdrawal' ? 'Available to Withdraw:' : 'Current Balance:' }}
            <span class="font-bold text-white">
              {{ form.currency === 'NGN' ? '₦' + Number(balances.cleared_balance_ngn).toLocaleString() : '$' +
                Number(balances.cleared_balance_usd).toLocaleString() }}
            </span>
          </p>

          <div
            v-if="txnType === 'withdrawal' && (form.currency === 'NGN' ? balances.uncleared_balance_ngn : balances.uncleared_balance_usd) > 0"
            class="p-2 mb-4 text-[10px] bg-yellow-500/10 border border-yellow-500/20 rounded text-yellow-500">
            You have pending settlements. These funds will be available for withdrawal in 2 business days.
          </div>

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
                <input v-model="formattedAmount"
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
              {{ loading && actionType === 'submit' ? 'Processing...' : 'Confirm ' + txnType
              }}
            </button>
          </form>
          <p v-if="message" :class="message.includes('Success') ? 'text-green-400' : 'text-yellow-300'"
            class="mt-4 text-sm font-medium text-center">{{ message }}</p>
        </div>
      </div>

      <div v-if="openConvert" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border"
          :class="isDemo ? 'border-yellow-600' : 'border-[#2A314A]'">
          <button @click="openConvert = false" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>
          <h2 class="mb-4 text-xl font-semibold">Convert Currency</h2>

          <p class="mb-4 text-sm text-gray-400">
            Available to convert:
            <span class="font-bold text-white">
              {{ from === 'NGN' ? '₦' + Number(balances.cleared_balance_ngn).toLocaleString() : '$' +
                Number(balances.cleared_balance_usd).toLocaleString() }}
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
            <input v-model="formattedConvertAmount"
              class="w-full px-4 py-2 mt-1 text-white bg-transparent border border-gray-600 rounded-lg"
              placeholder="Enter amount" required />

            <div v-if="amount > 0" class="p-3 mt-4 border rounded-lg bg-blue-500/10 border-blue-500/30 animate-pulse">
              <div class="text-[10px] text-blue-400 uppercase font-bold">Estimated Receipt</div>
              <div class="text-lg font-bold text-white">
                {{ from === 'NGN' ? '$' + (amount * 0.00065).toFixed(2) : '₦' + (amount / 0.00065).toLocaleString() }}
              </div>
              <div class="text-[9px] text-gray-500 italic mt-1">Rate: 1 NGN = 0.00065 USD</div>
            </div>

            <button :disabled="loading" class="w-full py-2 mt-5 font-semibold rounded-lg disabled:opacity-50"
              :class="isDemo ? 'bg-yellow-600' : 'bg-gradient-to-r from-[#0047AB] to-[#00D4FF]'">
              {{ loading && actionType === 'convert' ? 'Converting...' : 'Convert Now' }}
            </button>
          </form>
          <p v-if="message"
            :class="message.includes('Success') || message.includes('successfully') ? 'text-green-400' : 'text-yellow-300'"
            class="mt-4 text-sm font-medium text-center">{{ message }}</p>
        </div>
      </div>

      <div v-if="showPaymentModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
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

      <div v-if="showConfirmModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div
          class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-sm relative border border-[#2A314A] text-center">
          <h2 class="mb-2 text-xl font-bold text-white">Reset Demo Account?</h2>
          <p class="mb-6 text-sm text-gray-400">All simulated trades will be erased. This action cannot be undone.</p>
          <div class="flex gap-3">
            <button @click="showConfirmModal = false"
              class="flex-1 py-3 font-semibold text-gray-300 transition rounded-lg bg-gray-700/50 hover:bg-gray-700">
              Cancel
            </button>
            <button @click="executeResetDemo"
              class="flex-1 py-3 font-semibold text-white transition bg-red-600 rounded-lg hover:bg-red-700">
              Yes, Reset
            </button>
          </div>
        </div>
      </div>

      <div v-if="showNotificationModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div
          class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-sm relative border border-[#2A314A] text-center">
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
      <TransactionDetailsModal :show="showDetailsModal" :txn="selectedTransaction" @close="showDetailsModal = false" />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed, watch, onUnmounted } from "vue";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";
import VueApexCharts from "vue3-apexcharts";
import TransactionDetailsModal from "@/Components/TransactionDetailsModal.vue";
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';

const apexchart = VueApexCharts;

// --- State ---
const getUser = () => JSON.parse(localStorage.getItem('user') || '{}');
const user = ref(getUser());
const isDemo = ref(false);

const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  if (role.includes('admin')) return true;
  if (Array.isArray(u.roles)) {
    return u.roles.some((r) => {
      const candidate = (typeof r === 'string' ? r : r?.name || '').toString().toLowerCase();
      return candidate.includes('admin');
    });
  }
  return false;
};

const isUserVerified = computed(() => {
  const u = user.value || {};
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

const balances = ref({
  balance_ngn: 0, balance_usd: 0,
  cleared_balance_ngn: 0, uncleared_balance_ngn: 0, locked_balance_ngn: 0,
  cleared_balance_usd: 0, uncleared_balance_usd: 0, locked_balance_usd: 0
});
const transactions = ref([]);
const message = ref("");
const loading = ref(true); // Default to true for initial blur
const actionType = ref("");

// Modal States
const openConvert = ref(false);
const showModal = ref(false);
const showPaymentModal = ref(false);
const showConfirmModal = ref(false);
const showNotificationModal = ref(false);
const showPrompt = ref(false);

const txnType = ref("");
const from = ref("NGN");
const amount = ref(0);

const selectedTransaction = ref(null);
const showDetailsModal = ref(false);

const notificationData = ref({ success: true, title: '', message: '' });
const paymentResult = ref({ success: false, message: '', amount: 0 });

const formattedConvertAmount = computed({
  get() { return amount.value.toLocaleString(); },
  set(value) { amount.value = Number(value.replace(/,/g, '')); }
});

const linkedAccounts = ref([]);
const selectedAccountId = ref("");

const form = ref({ amount: 0, currency: "NGN" });

const formattedAmount = computed({
  get() { return form.value.amount.toLocaleString(); },
  set(value) { form.value.amount = Number(value.replace(/,/g, '')); }
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

const triggerNotification = (success, title, msg) => {
  notificationData.value = { success, title, message: msg };
  showNotificationModal.value = true;
};

// Immediately trigger the blur and update the text colors optimistically
const handleModeSwitching = (e) => {
  isDemo.value = e.detail === 'demo';
  loading.value = true;
  actionType.value = "";
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
  loading.value = true;
  try {
    isDemo.value = user.value.trading_mode === 'demo';

    const [balRes, txnRes] = await Promise.all([
      api.get("/wallet/balances"),
      api.get("/transactions?limit=10")
    ]);

    const data = balRes.data.data
    balances.value = {
      balance_ngn: data.balance_ngn ?? 0,
      cleared_balance_ngn: data.cleared_balance_ngn ?? 0,
      uncleared_balance_ngn: data.uncleared_balance_ngn ?? 0,
      locked_balance_ngn: data.locked_balance_ngn ?? 0,

      balance_usd: data.balance_usd ?? 0,
      cleared_balance_usd: data.cleared_balance_usd ?? 0,
      uncleared_balance_usd: data.uncleared_balance_usd ?? 0,
      locked_balance_usd: data.locked_balance_usd ?? 0,
    };

    if (!isDemo.value) {
      const accRes = await api.get("/user/linked-accounts/index");
      linkedAccounts.value = accRes.data.data.filter(acc => acc.is_verified);
    }

    transactions.value = Array.isArray(txnRes.data) ? txnRes.data.slice(0, 10) : (txnRes.data.transactions ? txnRes.data.transactions.slice(0, 10) : []);

  } catch (e) {
    console.error("Failed to refresh wallet data", e);
    triggerNotification(false, 'Connection Error', 'Unable to load wallet data. Please refresh the page.');
  } finally {
    loading.value = false;
  }
};

// --- DEMO METHODS ---
const refillDemo = async () => {
  loading.value = true;
  actionType.value = "refill";
  try {
    await api.post('/demo/start', { amount: 1000000 });
    await refreshData();
    triggerNotification(true, 'Refill Successful', 'Demo account refilled with ₦1,000,000.');
  } catch (e) {
    console.error(e);
    triggerNotification(false, 'Refill Failed', 'Failed to refill demo account.');
  } finally {
    loading.value = false;
    actionType.value = "";
  }
};

const promptResetDemo = () => {
  showConfirmModal.value = true;
};

const executeResetDemo = async () => {
  showConfirmModal.value = false;
  loading.value = true;
  actionType.value = "reset";
  try {
    await api.post('/demo/reset');
    await refreshData();
    triggerNotification(true, 'Reset Successful', 'Demo account reset successfully.');
  } catch (e) {
    console.error(e);
    triggerNotification(false, 'Reset Failed', 'Failed to reset demo account.');
  } finally {
    loading.value = false;
    actionType.value = "";
  }
};

// --- STANDARD METHODS ---
const openTransaction = (type) => {
  if (!isUserVerified.value) {
    showPrompt.value = true;
    return;
  }
  txnType.value = type;
  form.value = { amount: 0, currency: 'NGN' };
  selectedAccountId.value = "";
  message.value = "";
  showModal.value = true;
  if (type === 'withdrawal') fetchLinkedAccountsFor(form.value.currency);
};

const openConvertModal = () => {
  if (!isUserVerified.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  message.value = "";
  amount.value = 0;
  openConvert.value = true;
};

const submitTransaction = async () => {
  if (form.value.amount <= 0) return;
  if (txnType.value === 'withdrawal' && !selectedAccountId.value) return;
  loading.value = true;
  actionType.value = "submit";

  if (txnType.value === 'deposit') {
    try {
      const response = await api.post('/paystack/initiate', { amount: form.value.amount, currency: form.value.currency });
      if (response.data.success) {
        window.location.href = response.data.data.authorization_url;
        return;
      }
      message.value = response.data.message || "Payment initiation failed";
    } catch (e) {
      message.value = e.response?.data?.message || "Payment initiation failed";
    } finally {
      loading.value = false;
      actionType.value = "";
    }
  } else {
    try {
      await api.post('/withdraw', { amount: form.value.amount, currency: form.value.currency, linked_account_id: selectedAccountId.value });
      message.value = "Successful!"; setTimeout(() => { showModal.value = false; refreshData(); }, 1500);
    } catch (e) { message.value = e.response?.data?.message || "Transaction failed"; } finally { loading.value = false; actionType.value = ""; }
  }
};

const convertCurrency = async () => {
  if (amount.value <= 0) return;
  loading.value = true;
  actionType.value = "convert";
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
    actionType.value = "";
  }
};

const FX_CONVERSION_RATE = 1538;
const combinedSeries = computed(() => [
  {
    name: "NGN",
    data: [95000, 110000, 140000, 130000, 160000, balances.value.balance_ngn]
  },
  {
    name: "USD (Valued in NGN)",
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
  chart: { type: 'line', toolbar: { show: false }, animations: { enabled: true } },
  grid: { show: true, borderColor: '#1f3348', strokeDashArray: 4, padding: { top: 10, right: 20, bottom: 0, left: 20 } },
  stroke: { curve: "smooth", width: 3 },
  markers: { size: 0 },
  colors: ["#FFFFFF", "#00D4FF"],
  xaxis: { labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
  yaxis: { show: false },
  tooltip: { theme: 'dark' },
  legend: { show: false }
};

// Check for payment result parameters on page load
const checkPaymentResult = async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const paymentSuccess = urlParams.get('payment_success');
  const paymentError = urlParams.get('payment_error');
  const reference = urlParams.get('reference');

  if (paymentSuccess && reference) {
    loading.value = true;

    try {

      await api.get(`/paystack/verify/${reference}`);

      const amount = parseFloat(paymentSuccess);
      paymentResult.value = { success: true, message: 'Payment verified and wallet credited!', amount: amount };
      showPaymentModal.value = true;

      // 3. Clean the URL and refresh actual balances
      const newUrl = window.location.pathname;
      window.history.replaceState({}, document.title, newUrl);

      await refreshData();
    } catch (e) {
      paymentResult.value = { success: false, message: 'Payment succeeded, but verification failed. Contact support.', amount: 0 };
      showPaymentModal.value = true;
    } finally {
      loading.value = false;
    }

  } else if (paymentError) {
    let errorMessage = 'Payment failed. Please try again.';
    switch (paymentError) {
      case 'payment_failed': errorMessage = 'Payment was not successful. Please contact support if amount was debited.'; break;
      case 'verification_error': errorMessage = 'Unable to verify payment. Please contact support.'; break;
      case 'no_reference': errorMessage = 'Payment reference missing. Please contact support.'; break;
    }
    paymentResult.value = { success: false, message: errorMessage, amount: 0 };
    showPaymentModal.value = true;
    window.history.replaceState({}, document.title, window.location.pathname);
  }
};

const closePaymentModal = () => {
  showPaymentModal.value = false;
  paymentResult.value = { success: false, message: '', amount: 0 };
  refreshData();
};

const refreshWithRetry = async (attempts = 0, maxAttempts = 10) => {
  if (attempts >= maxAttempts) {
    console.warn('Max refresh attempts reached, balance may not have updated yet');
    return;
  }
  const previousBalance = balances.value.balance_ngn;
  const previousTxnCount = transactions.value.length;
  await refreshData();
  const balanceUpdated = balances.value.balance_ngn !== previousBalance;
  const transactionsUpdated = transactions.value.length !== previousTxnCount;

  if (balanceUpdated || transactionsUpdated) {
    console.log('Payment data updated successfully');
    return;
  }
  setTimeout(() => refreshWithRetry(attempts + 1, maxAttempts), 2000);
};

async function openTransactionDetails(t) {
 
  const localTxn = typeof t === 'object' ? t : transactions.value.find(t => t.id === t);

  if (localTxn) {
    selectedTransaction.value = { ...localTxn };
    showDetailsModal.value = true;

    
    try {
      const resp = await api.get(`/transactions/${localTxn.id}`);
      
      selectedTransaction.value = resp.data.data;
    } catch (e) {
      console.error("Background detail fetch failed", e);
    }
  }
}

onMounted(() => {
  refreshData();
  checkPaymentResult();

  // Listen for the toggle switch and quietly fetch new data
  window.addEventListener('trading-mode-switching', handleModeSwitching);
  // Listen for the actual fetch event
  window.addEventListener('trading-mode-changed', refreshData);
});

onUnmounted(() => {
  window.removeEventListener('trading-mode-switching', handleModeSwitching);
  window.removeEventListener('trading-mode-changed', refreshData);
});

watch(() => form.value.currency, (val) => {
  if (txnType.value === 'withdrawal') fetchLinkedAccountsFor(val);
});
</script>