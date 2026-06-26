<template>
  <MainLayout>
    <div class="space-y-8">

      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">💱 FX Market</h1>
          <p class="text-sm text-gray-400">Real-time foreign exchange rates powered by {{ activeProvider }}.</p>
        </div>

        <div class="flex items-center gap-3">
          <div class="text-xs text-gray-500">
            Provider: <span class="font-semibold" :class="activeProvider === 'Fincra' ? 'text-purple-400' : 'text-green-400'">{{ activeProvider }}</span>
          </div>
          <button @click="fetchFxRates" class="text-xs text-blue-400 hover:text-blue-300 transition">
            ⟳ Refresh
          </button>
        </div>
      </div>

      <!-- Market Table -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">Currency Pairs</h2>
          <div class="w-64">
            <input v-model="search" type="text" placeholder="Search currency pair..."
              class="w-full bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm outline-none focus:border-[#00D4FF]" />
          </div>
        </div>

        <div v-if="loading" class="py-4">
          <SkeletonLoader type="table" class="opacity-40" />
        </div>

        <div v-else-if="filteredRates.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="px-2 py-2 text-left">Currency Pair</th>
                <th class="px-2 text-left">Buy Rate</th>
                <th class="px-2 text-left">Sell Rate</th>
                <th class="px-2 text-right">Action</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="rate in filteredRates" :key="rate.base_currency + rate.quote_currency"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition">
                <td class="px-2 py-3 font-semibold">{{ rate.base_currency }}/{{ rate.quote_currency }}</td>
                <td class="px-2 text-green-400">{{ formatCurrency(rate.buy_rate, rate.quote_currency) }}</td>
                <td class="px-2 text-red-400">{{ formatCurrency(rate.sell_rate, rate.quote_currency) }}</td>
                <td class="px-2 text-right">
                  <button @click="openQuote(rate)"
                    class="bg-[#0047AB] hover:bg-[#0057D4] px-3 py-1 rounded-lg text-white text-xs">
                    Convert
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="py-6 text-center text-gray-400">
          No FX rates available. Run the seeder or switch to a provider.
        </div>
      </div>

      <!-- FX Quote Modal -->
      <div v-if="quoteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1C1F2E] p-8 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A]">
          <button @click="quoteModal = false" class="absolute text-gray-400 top-4 right-4 hover:text-white">✖</button>
          <h2 class="mb-4 text-xl font-semibold">Convert Currency</h2>

          <p class="mb-4 text-sm text-gray-400">
            Available to convert:
            <span class="font-bold text-white">
              {{ quoteData.from_currency === 'NGN' ? '₦' + Number(userBalances.ngn).toLocaleString() : '$' + Number(userBalances.usd).toLocaleString() }}
            </span>
          </p>

          <form @submit.prevent="executeConversion">
            <label class="text-sm text-gray-400">From Currency</label>
            <select v-model="quoteData.from_currency"
              class="w-full px-4 py-2 mt-1 mb-4 text-white bg-[#151a27] border border-gray-600 rounded-lg">
              <option value="NGN">NGN → USD</option>
              <option value="USD">USD → NGN</option>
            </select>
            
            <label class="text-sm text-gray-400">Amount</label>
            <input v-model="quoteData.amount" type="number" step="0.01" min="0"
              class="w-full px-4 py-2 mt-1 text-white bg-transparent border border-gray-600 rounded-lg"
              placeholder="Enter amount" @input="onAmountChange" />

            <div v-if="quoteData.rate > 0" class="p-3 mt-4 border rounded-lg bg-blue-500/10 border-blue-500/30">
              <div class="text-[10px] text-blue-400 uppercase font-bold">Estimated Receipt</div>
              <div class="text-lg font-bold text-white">
                {{ formatCurrency(quoteData.receive_amount, quoteData.to_currency) }}
              </div>
              <div class="text-xs text-gray-500 mt-1">
                Rate: 1 {{ quoteData.from_currency }} = {{ formatCurrency(quoteData.rate, quoteData.to_currency, true) }}
                <span class="ml-2 text-[10px] uppercase" :class="quoteData.provider === 'fincra' ? 'text-purple-400' : 'text-green-400'">via {{ quoteData.provider }}</span>
              </div>
            </div>
            <div v-else-if="quoteData.amount > 0" class="p-3 mt-4 border rounded-lg bg-blue-500/10 border-blue-500/30 animate-pulse">
              <div class="text-xs text-blue-400">Fetching live rate...</div>
            </div>

            <button :disabled="loading || !canConvert" class="w-full py-2 mt-5 font-semibold rounded-lg disabled:opacity-50 bg-gradient-to-r from-[#0047AB] to-[#00D4FF]">
              {{ loading ? 'Converting...' : 'Convert Now' }}
            </button>
          </form>
          <p v-if="message" :class="messageType === 'success' ? 'text-green-400' : 'text-red-400'"
            class="mt-4 text-sm font-medium text-center">{{ message }}</p>
        </div>
      </div>

      <!-- Conversion Success Modal -->
      <div v-if="showSuccessModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
        <div class="bg-[#1C1F2E] rounded-2xl p-8 shadow-xl w-full max-w-md relative border border-green-500/30">
          <h2 class="mb-4 text-xl font-bold text-green-400 text-center">✅ Conversion Successful!</h2>
          <div class="p-4 mb-4 border rounded-lg bg-[#151a27] border-green-500/20">
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="text-gray-500">Debited</p>
                <p class="text-white">{{ formatCurrency(conversionResult.amount, conversionResult.from_currency) }}</p>
              </div>
              <div>
                <p class="text-gray-500">Credited</p>
                <p class="text-green-400">{{ formatCurrency(conversionResult.converted_amount, conversionResult.to_currency) }}</p>
              </div>
              <div class="col-span-2">
                <p class="text-gray-500">Rate</p>
                <p class="text-[#00D4FF]">1 {{ conversionResult.from_currency }} = {{ formatCurrency(conversionResult.rate, conversionResult.to_currency, true) }}</p>
              </div>
            </div>
          </div>
          <button @click="closeSuccess"
            class="w-full py-2 font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
            Done
          </button>
        </div>
      </div>

      <!-- Conversion History -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold">📋 My FX Conversion History</h2>
          <button @click="fetchHistory" class="text-xs text-blue-400 hover:text-blue-300">⟳ Refresh</button>
        </div>

        <div v-if="historyLoading" class="py-4">
          <SkeletonLoader type="table" class="opacity-40" />
        </div>

        <div v-else-if="conversionHistory.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="px-2 py-2 text-left">Date</th>
                <th class="px-2 text-left">Reference</th>
                <th class="px-2 text-left">Direction</th>
                <th class="px-2 text-right">Amount</th>
                <th class="px-2 text-right">Rate</th>
                <th class="px-2 text-right">Converted</th>
                <th class="px-2 text-left">Provider</th>
                <th class="px-2 text-left">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="conv in conversionHistory" :key="conv.id"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition">
                <td class="px-2 py-3 text-xs text-gray-400">{{ formatDate(conv.created_at) }}</td>
                <td class="px-2 font-mono text-xs">{{ conv.reference }}</td>
                <td class="px-2">{{ conv.from_currency }} → {{ conv.to_currency }}</td>
                <td class="px-2 text-right">{{ formatCurrency(conv.amount, conv.from_currency) }}</td>
                <td class="px-2 text-right text-[#00D4FF]">{{ formatCurrency(conv.rate, conv.to_currency, true) }}</td>
                <td class="px-2 text-right text-green-400">{{ formatCurrency(conv.converted_amount, conv.to_currency) }}</td>
                <td class="px-2">
                  <span :class="conv.provider === 'fincra' ? 'text-purple-400' : 'text-green-400'" class="text-xs">
                    {{ conv.provider }}
                  </span>
                </td>
                <td class="px-2">
                  <span class="text-xs text-green-400" v-if="conv.status === 'completed'">Completed</span>
                  <span class="text-xs text-yellow-400" v-else>{{ conv.status }}</span>
                </td>
              </tr>
              <tr v-if="conversionHistory.length === 0">
                <td colspan="8" class="px-2 py-4 text-center text-gray-500">No conversions yet.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="py-6 text-center text-gray-400">
          <p>No FX conversion history yet.</p>
          <p class="text-xs mt-1">Use the Convert button above to start a conversion.</p>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { useRouter } from "vue-router";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import SkeletonLoader from "@/Components/SkeletonLoader.vue";

const router = useRouter();

// STATE
const user = ref(JSON.parse(localStorage.getItem('user') || '{}'));
const isDemo = ref(user.value.trading_mode === 'demo');
const showPrompt = ref(false);
const search = ref("");
const rates = ref([]);
const activeProvider = ref('Manual');
const loading = ref(false);

// Quote Modal
const quoteModal = ref(false);
const quoteData = ref({
  from_currency: 'NGN',
  to_currency: 'USD',
  amount: 0,
  rate: 0,
  receive_amount: 0,
  provider: '...',
  available_balance: 0
});
const message = ref("");
const messageType = ref("success");

// Success Modal
const showSuccessModal = ref(false);
const conversionResult = ref({});

// User Balances
const userBalances = ref({
  usd: 0,
  ngn: 0
});

// Conversion History
const conversionHistory = ref([]);
const historyLoading = ref(false);

const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  return role.includes('admin');
};

const isUserVerified = computed(() => {
  const u = user.value || {};
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

// FILTER TABLE
const filteredRates = computed(() => {
  if (!search.value) return rates.value;
  return rates.value.filter(r =>
    `${r.base_currency}/${r.quote_currency}`.toLowerCase().includes(search.value.toLowerCase())
  );
});

const formatDate = (dateStr) => {
  if (!dateStr) return "Just now";
  const date = new Date(dateStr);
  return isNaN(date.getTime()) ? dateStr : date.toLocaleDateString('en-NG', {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
  });
};


const formatCurrency = (amount, currency, showFullDecimals = false) => {
  if (amount === null || amount === undefined) return '---';
  const value = Number(amount);
  if (currency === 'USD') {
    
    if (showFullDecimals || value < 0.01) {
      return '$' + value.toLocaleString('en-US', { minimumFractionDigits: 6, maximumFractionDigits: 6 });
    }
    return '$' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  } else if (currency === 'NGN') {
    return '₦' + value.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  return value.toLocaleString();
};

// FETCH FX RATES FROM API
const fetchFxRates = async () => {
  loading.value = true;
  try {
    const response = await api.get("/fx-rates");
    const data = response.data;
    
    if (data.fx_pairs && Array.isArray(data.fx_pairs)) {
      rates.value = data.fx_pairs;
      activeProvider.value = data.provider || 'Manual';
    } else if (data.rates && Array.isArray(data.rates)) {
      rates.value = data.rates.map(r => ({
        base_currency: r.to_currency,
        quote_currency: r.from_currency,
        buy_rate: r.effective_rate || r.base_rate,
        sell_rate: (r.effective_rate || r.base_rate) * 0.99,
      }));
      activeProvider.value = data.provider || 'Manual';
    } else if (data.data?.pairs) {
      rates.value = data.data.pairs;
      activeProvider.value = data.data.settings?.provider || 'Manual';
    } else if (Array.isArray(data)) {
      rates.value = data;
      activeProvider.value = 'Manual';
    } else {
      rates.value = [];
    }
  } catch (error) {
    console.error("Failed to fetch FX rates:", error);
    rates.value = [];
    message.value = "Failed to load FX rates. Make sure FX pairs are seeded.";
    messageType.value = "error";
    setTimeout(() => { message.value = ''; }, 3000);
  } finally {
    loading.value = false;
  }
};

// Fetch user balances
const fetchUserBalances = async () => {
  try {
    const response = await api.get("/wallet/balances");
    const data = response.data.data;
    userBalances.value = {
      usd: data.balance_usd || 0,
      ngn: data.balance_ngn || 0
    };
  } catch (e) {
    console.error("Failed to fetch balances", e);
  }
};

// Set conversion direction
const setDirection = (fromCurrency) => {
  quoteData.value.from_currency = fromCurrency;
  quoteData.value.to_currency = fromCurrency === 'USD' ? 'NGN' : 'USD';
  quoteData.value.amount = 0;
  quoteData.value.rate = 0;
  quoteData.value.receive_amount = 0;
  quoteData.value.reference = null;
};

// Fetch quote when amount changes (skip for manual provider)
let quoteTimeout = null;
const onAmountChange = () => {
  if (quoteTimeout) clearTimeout(quoteTimeout);
  
  const amount = parseFloat(quoteData.value.amount);
  if (!amount || amount <= 0) {
    quoteData.value.rate = 0;
    quoteData.value.receive_amount = 0;
    return;
  }

  // For manual provider, calculate immediately without API call
  if (activeProvider.value === 'Manual') {
    const pair = rates.value.find(r => 
      r.base_currency === quoteData.value.to_currency && 
      r.quote_currency === quoteData.value.from_currency
    );
    
    if (pair) {
      // Rate is stored as how much quote currency per 1 base currency
      // E.g., USD/NGN = 1385 means 1 USD = 1385 NGN
      // So for NGN -> USD: we need inverse rate = 1/1385
      const storedRate = pair.buy_rate;
      const inverseRate = 1 / storedRate;
      const receiveAmount = amount * inverseRate;
      
      quoteData.value.rate = inverseRate;
      quoteData.value.receive_amount = receiveAmount;
      quoteData.value.provider = 'manual';
      quoteData.value.available_balance = userBalances.value[quoteData.value.from_currency.toLowerCase()] || 0;
      message.value = "";
    }
    return;
  }

  // For live providers (Fincra), fetch quote from API
  quoteTimeout = setTimeout(async () => {
    try {
      const response = await api.post("/fx/quote", {
        from_currency: quoteData.value.from_currency,
        to_currency: quoteData.value.to_currency,
        amount: amount
      });

      if (response.data.success) {
        quoteData.value.rate = response.data.data.rate;
        quoteData.value.receive_amount = response.data.data.receive_amount;
        quoteData.value.provider = response.data.data.provider;
        quoteData.value.reference = response.data.data.reference || response.data.data.quoteReference;
        quoteData.value.available_balance = response.data.data.available_balance || 0;
        message.value = "";
      }
    } catch (e) {
      message.value = e.response?.data?.message || "Failed to get quote";
      messageType.value = "error";
    }
  }, 500);
};

// Check if user can convert
const canConvert = computed(() => {
  const amount = parseFloat(quoteData.value.amount);
  const available = quoteData.value.available_balance || 0;
  return amount > 0 && quoteData.value.rate > 0 && amount <= available;
});

// QUOTE FLOW
async function openQuote(rate) {
  if (!canTrade.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  
  if (!isUserVerified.value && !isDemo.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  // Set default direction based on rate pair
  const fromCurrency = rate.quote_currency; // NGN
  const toCurrency = rate.base_currency; // USD

  quoteData.value = {
    from_currency: fromCurrency,
    to_currency: toCurrency,
    amount: 0,
    rate: 0,
    receive_amount: 0,
    provider: '...',
    available_balance: 0
  };

  await fetchUserBalances();
  quoteModal.value = true;
  message.value = "";
}

// EXECUTE CONVERSION
async function executeConversion() {
  if (!canConvert.value) {
    message.value = "Please enter a valid amount within your available balance";
    messageType.value = "error";
    return;
  }

  loading.value = true;
  message.value = "";
  try {
    const response = await api.post("/fx/convert", {
      from_currency: quoteData.value.from_currency,
      to_currency: quoteData.value.to_currency,
      amount: quoteData.value.amount
    });

    if (response.data.success) {
      conversionResult.value = response.data.data;
      quoteModal.value = false;
      showSuccessModal.value = true;
      message.value = "";
      await fetchHistory();
      await fetchUserBalances();

      // Redirect to wallet after 2 seconds
      setTimeout(() => {
        router.push('/wallet');
      }, 2000);
    } else {
      throw new Error(response.data.message || 'Conversion failed');
    }
  } catch (e) {
    message.value = e.response?.data?.message || e.message || "Conversion failed";
    messageType.value = "error";
  } finally {
    loading.value = false;
  }
}

function closeSuccess() {
  showSuccessModal.value = false;
  conversionResult.value = {};
  router.push('/wallet');
}

// FETCH CONVERSION HISTORY
const fetchHistory = async () => {
  historyLoading.value = true;
  try {
    const response = await api.get("/fx/history");
    if (response.data.success) {
      conversionHistory.value = response.data.data || [];
    }
  } catch (e) {
    console.error("Failed to fetch FX history", e);
    conversionHistory.value = [];
  } finally {
    historyLoading.value = false;
  }
};

onMounted(async () => {
  Promise.allSettled([
    fetchFxRates(),
    fetchHistory(),
    fetchUserBalances()
  ]);
  window.addEventListener('trading-mode-changed', () => {
    user.value = JSON.parse(localStorage.getItem('user') || '{}');
    isDemo.value = user.value.trading_mode === 'demo';
  });
});

onUnmounted(() => {
  window.removeEventListener('trading-mode-changed', fetchFxRates);
  if (quoteTimeout) clearTimeout(quoteTimeout);
});
</script>