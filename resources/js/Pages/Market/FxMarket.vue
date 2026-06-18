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
                <td class="px-2 text-green-400">{{ Number(rate.buy_rate).toLocaleString() }}</td>
                <td class="px-2 text-red-400">{{ Number(rate.sell_rate).toLocaleString() }}</td>
                <td class="px-2 text-right">
                  <button @click="openQuote(rate)"
                    class="bg-[#0047AB] hover:bg-[#0057D4] px-3 py-1 rounded-lg text-white text-xs">
                    Quote
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
        <div class="bg-[#1C1F2E] rounded-2xl p-8 shadow-xl w-full max-w-lg relative border border-[#2A314A]">
          <button @click="quoteModal = false" class="absolute text-gray-400 top-3 right-3 hover:text-white">✖</button>

          <h2 class="mb-4 text-xl font-semibold">FX Quote</h2>

          <div class="p-4 mb-4 border rounded-lg bg-[#151a27] border-[#2A314A]">
            <div class="flex justify-between items-center">
              <span class="text-gray-400">{{ quoteData.from_currency }} → {{ quoteData.to_currency }}</span>
              <span class="text-xs text-gray-500">Provider: {{ quoteData.provider }}</span>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-4 text-center">
              <div>
                <p class="text-xs text-gray-500 uppercase">Amount</p>
                <p class="text-lg font-bold text-white">{{ Number(quoteData.amount).toLocaleString() }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase">Rate</p>
                <p class="text-lg font-bold text-[#00D4FF]">{{ Number(quoteData.rate).toFixed(4) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase">You Receive</p>
                <p class="text-lg font-bold text-green-400">{{ Number(quoteData.receive_amount).toFixed(4) }}</p>
              </div>
            </div>
          </div>

          <div class="flex gap-3">
            <button @click="quoteModal = false"
              class="flex-1 py-2 text-gray-300 bg-gray-700 rounded-lg hover:bg-gray-600 transition">
              Cancel
            </button>
            <button @click="executeConversion" :disabled="loading"
              class="flex-1 py-2 font-semibold text-white bg-gradient-to-r from-[#0047AB] to-[#00D4FF] rounded-lg disabled:opacity-50 transition">
              {{ loading ? 'Converting...' : '✓ Confirm Convert' }}
            </button>
          </div>

          <p v-if="message" :class="messageType === 'success' ? 'text-green-400' : 'text-red-400'" class="mt-3 text-sm text-center">
            {{ message }}
          </p>
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
                <p class="text-gray-500">Reference</p>
                <p class="font-mono text-white">{{ conversionResult.reference }}</p>
              </div>
              <div>
                <p class="text-gray-500">Provider</p>
                <p class="text-white capitalize">{{ conversionResult.provider }}</p>
              </div>
              <div>
                <p class="text-gray-500">Debited</p>
                <p class="text-white">{{ Number(conversionResult.amount).toLocaleString() }} {{ conversionResult.from_currency }}</p>
              </div>
              <div>
                <p class="text-gray-500">Credited</p>
                <p class="text-green-400">{{ Number(conversionResult.converted_amount).toFixed(4) }} {{ conversionResult.to_currency }}</p>
              </div>
              <div class="col-span-2">
                <p class="text-gray-500">Rate</p>
                <p class="text-[#00D4FF]">1 {{ conversionResult.from_currency }} = {{ Number(conversionResult.rate).toFixed(6) }} {{ conversionResult.to_currency }}</p>
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
                <td class="px-2 text-right">{{ Number(conv.amount).toLocaleString() }}</td>
                <td class="px-2 text-right text-[#00D4FF]">{{ Number(conv.rate).toFixed(4) }}</td>
                <td class="px-2 text-right text-green-400">{{ Number(conv.converted_amount).toFixed(4) }}</td>
                <td class="px-2">
                  <span :class="conv.provider === 'fincra' ? 'text-purple-400' : 'text-green-400'" class="text-xs">
                    {{ conv.provider }}
                  </span>
                </td>
                <td class="px-2">
                  <span class="text-xs text-green-400" v-if="conv.status === 'completed'">✓ Completed</span>
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
          <p class="text-xs mt-1">Use the Quote button above to start a conversion.</p>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import SkeletonLoader from "@/Components/SkeletonLoader.vue";

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
const quoteData = ref({});
const message = ref("");
const messageType = ref("success");

// Success Modal
const showSuccessModal = ref(false);
const conversionResult = ref({});

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

// QUOTE FLOW
async function openQuote(rate) {
  if (!isUserVerified.value && !isDemo.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  // Convert rate pair: base_currency=USD, quote_currency=NGN => from=NGN, to=USD
  // We want to convert NGN -> USD
  const fromCurrency = rate.quote_currency;
  const toCurrency = rate.base_currency;

  try {
    loading.value = true;
    // First show modal with loading state
    quoteData.value = {
      from_currency: fromCurrency,
      to_currency: toCurrency,
      amount: 0,
      rate: 0,
      receive_amount: 0,
      provider: '...'
    };
    quoteModal.value = true;

    // Ask user for amount via a simple prompt
    const userAmount = prompt(`Enter amount in ${fromCurrency} to convert to ${toCurrency}:`, "1000");
    if (!userAmount || isNaN(Number(userAmount)) || Number(userAmount) <= 0) {
      quoteModal.value = false;
      return;
    }

    const amount = Number(userAmount);

    // Get quote from API
    const response = await api.post("/fx/quote", {
      from_currency: fromCurrency,
      to_currency: toCurrency,
      amount: amount
    });

    if (response.data.success) {
      quoteData.value = {
        from_currency: fromCurrency,
        to_currency: toCurrency,
        amount: amount,
        rate: response.data.data.rate,
        receive_amount: response.data.data.receive_amount,
        provider: response.data.data.provider
      };
      message.value = "";
    } else {
      throw new Error(response.data.message || 'Quote failed');
    }
  } catch (e) {
    message.value = e.response?.data?.message || e.message || "Failed to get quote";
    messageType.value = "error";
  } finally {
    loading.value = false;
  }
}

// EXECUTE CONVERSION
async function executeConversion() {
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
      fetchHistory();
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
  await fetchFxRates();
  await fetchHistory();
  window.addEventListener('trading-mode-changed', () => {
    user.value = JSON.parse(localStorage.getItem('user') || '{}');
    isDemo.value = user.value.trading_mode === 'demo';
  });
});

onUnmounted(() => {
  window.removeEventListener('trading-mode-changed', fetchFxRates);
});
</script>