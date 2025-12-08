<template>
  <MainLayout>
    <div class="space-y-8">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">💹 Crypto Market</h1>
          <p class="text-gray-400 text-sm">Buy and track cryptocurrencies in real-time.</p>
        </div>

        <div class="w-64">
          <input
            v-model="search"
            type="text"
            placeholder="Search crypto (BTC, ETH...)"
            class="w-full bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm outline-none focus:border-[#00E1FF]"
          />
        </div>
      </div>

      <!-- Suggestions -->
      <div
        v-if="showSuggestions"
        class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-3 w-64 absolute z-40"
      >
        <div
          v-for="c in suggestions"
          :key="c.symbol"
          @click="selectSuggestion(c)"
          class="px-3 py-2 hover:bg-[#12203a] rounded cursor-pointer"
        >
          <div class="font-medium uppercase">{{ c.symbol }} — {{ c.name }}</div>
          <div class="text-xs text-gray-400">${{ c.market_price }}</div>
        </div>
      </div>

      <!-- Crypto Table -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold text-white">Top Cryptocurrencies</h2>
          <span class="text-xs text-gray-400">Live market data (15m delay)</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="text-left py-2 px-2">Asset</th>
                <th class="text-left px-2">Price</th>
                <th class="text-left px-2">24h Change</th>
                <th class="text-left px-2">Trend</th>
                <th class="text-right px-2">Action</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="c in filteredCrypto"
                :key="c.symbol"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition"
              >
                <td class="py-3 px-2 font-semibold uppercase">{{ c.symbol }} <span class="text-gray-400 font-normal">{{ c.name }}</span></td>

                <td class="px-2 font-medium">${{ c.market_price.toLocaleString() }}</td>

                <td
                  class="px-2"
                  :class="{
                    'text-green-400': c.change >= 0,
                    'text-red-400': c.change < 0
                  }"
                >
                  {{ c.change }}%
                </td>

                <td class="px-2">
                  <apexchart
                    type="area"
                    height="45"
                    width="110"
                    :options="sparkOptions"
                    :series="[{ data: c.sparkline }]"
                  />
                </td>

                <td class="px-2 text-right">
                  <button
                    @click="openBuy(c)"
                    class="bg-[#E91E63] hover:bg-[#FF4081] px-3 py-1 rounded-lg text-white text-xs"
                  >Buy</button>
                </td>
              </tr>
            </tbody>

          </table>
        </div>
      </div>

      <!-- Buy Modal -->
      <div
        v-if="buyModal"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50"
      >
        <div class="bg-[#1C1F2E] rounded-2xl p-8 shadow-xl w-full max-w-lg relative">
          <button
            @click="buyModal = false"
            class="absolute top-3 right-3 text-gray-400 hover:text-white"
          >✖</button>

          <h2 class="text-xl font-semibold mb-4">
            Buy {{ selectedCrypto.symbol.toUpperCase() }}
          </h2>

          <form @submit.prevent="placeOrder">
            <div class="mb-4">
              <label class="text-sm text-gray-400">Amount (USD)</label>
              <input
                v-model.number="amount"
                type="number"
                class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 mt-1"
                @input="calcUnits"
              />
            </div>

            <div class="mb-4">
              <label class="text-sm text-gray-400">Coins</label>
              <input
                type="text"
                class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 mt-1"
                :value="units"
                disabled
              />
            </div>

            <button
              class="w-full bg-gradient-to-r from-[#E91E63] to-[#FF4081] py-2 rounded-lg mt-2"
            >
              Place Order
            </button>

            <p class="text-center text-yellow-400 text-sm mt-3">{{ message }}</p>
          </form>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import axios from "axios";
import MainLayout from "@/layouts/MainLayout.vue";
import VueApexCharts from "vue3-apexcharts";

const apexchart = VueApexCharts;

// STATES
const search = ref("");
const cryptoList = ref([]);
const suggestions = ref([]);
const buyModal = ref(false);
const selectedCrypto = ref({});
const amount = ref(0);
const units = ref(0);
const message = ref("");

// LOAD MARKET DATA
onMounted(async () => {
  try {
    const res = await axios.get("/api/market/crypto");
    cryptoList.value = res.data.data;
  } catch {
    // fallback data
    cryptoList.value = [
      {
        symbol: "BTC",
        name: "Bitcoin",
        market_price: 64250,
        change: 1.4,
        sparkline: [62000, 62800, 63000, 63550, 64250],
      },
      {
        symbol: "ETH",
        name: "Ethereum",
        market_price: 3120,
        change: -0.8,
        sparkline: [3180, 3150, 3130, 3120],
      },
      {
        symbol: "SOL",
        name: "Solana",
        market_price: 132.6,
        change: 3.1,
        sparkline: [125, 128, 129, 131, 132.6],
      },
    ];
  }
});

// FILTER
const filteredCrypto = computed(() => {
  if (!search.value) return cryptoList.value;
  return cryptoList.value.filter(c =>
    c.symbol.toLowerCase().includes(search.value.toLowerCase()) ||
    c.name.toLowerCase().includes(search.value.toLowerCase())
  );
});

// AUTOCOMPLETE
watch(search, (val) => {
  if (val.length < 2) return (suggestions.value = []);
  suggestions.value = filteredCrypto.value.slice(0, 5);
});

const showSuggestions = computed(() => suggestions.value.length > 0);

function selectSuggestion(asset) {
  search.value = asset.symbol;
  suggestions.value = [];
}

// SPARKLINE
const sparkOptions = {
  chart: { sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  fill: {
    type: "gradient",
    gradient: { opacityFrom: 0.5, opacityTo: 0.1 }
  },
  colors: ["#FF4081"],
  tooltip: { enabled: false }
};

// BUY MODAL
function openBuy(asset) {
  selectedCrypto.value = asset;
  buyModal.value = true;
  amount.value = 0;
  units.value = 0;
  message.value = "";
}

function calcUnits() {
  if (!selectedCrypto.value.market_price || !amount.value) return;
  units.value = (amount.value / selectedCrypto.value.market_price).toFixed(6);
}

// PLACE ORDER
async function placeOrder() {
  try {
    const token = localStorage.getItem("xavier_token");

    const payload = {
      company: selectedCrypto.value.name,
      symbol: selectedCrypto.value.symbol,
      market_price: selectedCrypto.value.market_price,
      amount: amount.value,
      units: units.value,
      type: "crypto",
    };

    const res = await axios.post("/api/orders", payload, {
      headers: { Authorization: `Bearer ${token}` },
    });

    if (res.data.success) {
      message.value = "✅ Crypto order placed!";
      setTimeout(() => (buyModal.value = false), 1000);
    }
  } catch {
    message.value = "❌ Could not place order";
  }
}
</script>
