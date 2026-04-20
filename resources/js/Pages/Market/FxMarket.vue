<template>
  <MainLayout>
    <div class="space-y-8">

      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">💱 FX Market</h1>
          <p class="text-sm text-gray-400">Trade foreign currencies in real-time.</p>
        </div>

        <div class="w-64">
          <input
            v-model="search"
            type="text"
            placeholder="Search currency pair..."
            class="w-full bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm outline-none focus:border-[#00D4FF]"
          />
        </div>
      </div>

      <!-- Market Table -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">Currency Pairs</h2>
          <span class="text-xs text-gray-400">Updated 2 mins ago</span>
        </div>

        <div v-if="loading" class="py-6 text-center">
          <div class="text-gray-400">Loading FX rates...</div>
        </div>

        <div v-else-if="filteredRates.length" class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="px-2 py-2 text-left">Currency Pair</th>
                <th class="px-2 text-left">Base Rate</th>
                <th class="px-2 text-left">Effective Rate</th>
                <th class="px-2 text-left">Trend</th>
                <th class="px-2 text-right">Action</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="rate in filteredRates"
                :key="rate.from_currency + rate.to_currency"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition"
              >
                <!-- Currency Pair -->
                <td class="px-2 py-3 font-semibold">{{ rate.from_currency }}/{{ rate.to_currency }}</td>

                <!-- Base Rate -->
                <td class="px-2">{{ rate.base_rate.toLocaleString() }}</td>

                <!-- Effective Rate -->
                <td class="px-2 font-medium">{{ rate.effective_rate.toLocaleString() }}</td>

                <!-- Sparkline -->
                <td class="px-2">
                  <apexchart
                    type="area"
                    height="45"
                    width="110"
                    :options="sparkOptions"
                    :series="[{ data: rate.sparkline || [rate.effective_rate, rate.effective_rate * 1.01, rate.effective_rate * 0.99, rate.effective_rate * 1.02] }]"
                  />
                </td>

                <!-- Action -->
                <td class="px-2 text-right">
                  <button
                    @click="openConvert(rate)"
                    class="bg-[#0047AB] hover:bg-[#0057D4] px-3 py-1 rounded-lg text-white text-xs"
                  >Convert</button>
                </td>
              </tr>
            </tbody>

          </table>
        </div>

        <div v-else class="py-6 text-center text-gray-400">
          No FX rates available.
        </div>
      </div>

      <!-- Convert Modal -->
      <div
        v-if="convertModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
      >
        <div class="bg-[#1C1F2E] rounded-2xl p-8 shadow-xl w-full max-w-lg relative">
          <button
            @click="convertModal = false"
            class="absolute text-gray-400 top-3 right-3 hover:text-white"
          >
            ✖
          </button>

          <h2 class="mb-4 text-xl font-semibold">Convert {{ selectedRate.from_currency }} to {{ selectedRate.to_currency }}</h2>

          <form @submit.prevent="convertCurrency">
            <div class="mb-4">
              <label class="text-sm text-gray-400">Amount ({{ selectedRate.from_currency }})</label>
              <input
                v-model.number="convertAmount"
                type="number"
                step="0.01"
                class="w-full px-4 py-2 mt-1 bg-transparent border border-gray-600 rounded-lg"
                @input="calcConvertedAmount"
              />
            </div>

            <div class="mb-4">
              <label class="text-sm text-gray-400">Converted Amount ({{ selectedRate.to_currency }})</label>
              <input
                type="text"
                class="w-full px-4 py-2 mt-1 bg-transparent border border-gray-600 rounded-lg"
                :value="convertedAmount"
                disabled
              />
            </div>

            <div class="mb-4 text-sm text-gray-400">
              Exchange Rate: 1 {{ selectedRate.from_currency }} = {{ selectedRate.effective_rate }} {{ selectedRate.to_currency }}
            </div>

            <button
              class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-2 rounded-lg mt-2"
              type="submit"
              :disabled="!convertAmount || convertAmount <= 0"
            >
              Convert Currency
            </button>

            <p class="mt-3 text-sm text-center text-yellow-400">{{ message }}</p>
          </form>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import api from "@/api";
import MainLayout from "@/Layouts/MainLayout.vue";
import VueApexCharts from "vue3-apexcharts";
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';

const apexchart = VueApexCharts;

// STATES
const user = ref(JSON.parse(localStorage.getItem('user') || '{}'));
const isDemo = ref(user.value.trading_mode === 'demo');
const showPrompt = ref(false);
const search = ref("");
const rates = ref([]);
const convertModal = ref(false);
const selectedRate = ref({});
const convertAmount = ref(0);
const convertedAmount = ref(0);
const message = ref("");
const loading = ref(false);

const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  return role.includes('admin');
};

const isUserVerified = computed(() => {
  const u = user.value || {};
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

// Load FX rates
onMounted(async () => {
  await fetchFxRates();
  window.addEventListener('trading-mode-changed', () => {
    user.value = JSON.parse(localStorage.getItem('user') || '{}');
    isDemo.value = user.value.trading_mode === 'demo';
  });
});

onUnmounted(() => {
  window.removeEventListener('trading-mode-changed', fetchFxRates);
});

const fetchFxRates = async () => {
  loading.value = true;
  try {
    const token = localStorage.getItem("xavier_token");
    const response = await api.get("/fx-rates", {
      headers: { Authorization: `Bearer ${token}` }
    });
    rates.value = response.data.rates.map(r => ({
      ...r,
      // Generate 10 random data points around the effective rate for the "behavior" look
      sparkline: Array.from({ length: 10 }, () => 
        r.effective_rate * (1 + (Math.random() * 0.02 - 0.01))
      )
    }));
  } catch (error) {
    console.error("Failed to fetch FX rates:", error);
    // Fallback dummy data
    rates.value = [ // Fallback dummy data
      { from_currency: "NGN", to_currency: "USD", base_rate: 1500, effective_rate: 1530 },
      { from_currency: "USD", to_currency: "NGN", base_rate: 0.00067, effective_rate: 0.000653 },
    ];
  } finally {
    loading.value = false;
  }
};

// FILTER TABLE
const filteredRates = computed(() => {
  if (!search.value) return rates.value;
  return rates.value.filter(r =>
    `${r.from_currency}/${r.to_currency}`.toLowerCase().includes(search.value.toLowerCase())
  );
});

// SPARKLINE CHART OPTIONS
const sparkOptions = {
  chart: { sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  fill: {
    type: "gradient",
    gradient: { opacityFrom: 0.5, opacityTo: 0.1 }
  },
  colors: ["#00D4FF"],
  tooltip: { enabled: false }
};

// CONVERT MODAL
function openConvert(rate) {
  if (!isUserVerified.value && !isDemo.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  selectedRate.value = rate;
  convertAmount.value = 0;
  convertedAmount.value = 0;
  message.value = "";
  convertModal.value = true;
}

function calcConvertedAmount() {
  const rate = selectedRate.value.effective_rate;
  const amount = convertAmount.value;
  
  if (!rate || !amount) {
    convertedAmount.value = 0;
    return;
  }

  /**
   * Logical Rule: 
   * The effective_rate is always "How much of 'TO' do I get for 1 'FROM'?"
   * So we always multiply: Amount * Rate.
   * Example: 10 USD * 1530 (Rate) = 15,300 NGN
   * Example: 1530 NGN * 0.000653 (Rate) = 1 USD
   */
  const result = amount * rate;
  
  // Apply decimals based on currency
  const decimals = selectedRate.value.to_currency === 'USD' ? 2 : 2;
  convertedAmount.value = result.toLocaleString(undefined, { 
    minimumFractionDigits: decimals, 
    maximumFractionDigits: decimals 
  });
}

// CONVERT CURRENCY API
async function convertCurrency() {
  message.value = "Processing...";
  try {
    const token = localStorage.getItem("xavier_token");

    const payload = {
      amount: convertAmount.value,
      from: selectedRate.value.from_currency,
    };

    const response = await api.post("api/wallet/convert", payload, {
      headers: { Authorization: `Bearer ${token}` }
    });

    if (response.data.success) {
      message.value = "✅ Currency conversion successful!";
    setTimeout(() => {
        convertModal.value = false;
        fetchFxRates(); 
      }, 2000);
    }
  } catch (e) {
    console.error(e);
    message.value = "❌ Conversion failed. Please try again.";
  }
}
</script>