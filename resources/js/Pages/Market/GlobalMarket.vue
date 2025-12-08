<template>
  <MainLayout>
    <div class="space-y-8">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">🌍 Global Market</h1>
          <p class="text-gray-400 text-sm">Trade global stocks from US & other international markets.</p>
        </div>

        <!-- Search -->
        <div class="w-64">
          <input
            v-model="search"
            type="text"
            placeholder="Search global stocks..."
            class="w-full bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm outline-none focus:border-[#00E1FF]"
          />
        </div>
      </div>

      <!-- Search Suggestions -->
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
          <div class="font-medium">{{ c.symbol }} • {{ c.name }}</div>
          <div class="text-xs text-gray-400">${{ c.market_price }}</div>
        </div>
      </div>

      <!-- Market Table -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold text-white">US Market Overview</h2>
          <span class="text-xs text-gray-400">Live prices (delayed 15m)</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="text-left py-2 px-2">Symbol</th>
                <th class="text-left px-2">Company</th>
                <th class="text-left px-2">Price</th>
                <th class="text-left px-2">Change</th>
                <th class="text-left px-2">Trend</th>
                <th class="text-right px-2">Action</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="stock in filteredStocks"
                :key="stock.symbol"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition"
              >
                <td class="py-3 px-2 font-semibold">{{ stock.symbol }}</td>
                <td class="px-2">{{ stock.name }}</td>
                <td class="px-2 font-medium">${{ stock.market_price.toLocaleString() }}</td>

                <td
                  class="px-2"
                  :class="{
                    'text-green-400': stock.change >= 0,
                    'text-red-400': stock.change < 0
                  }"
                >
                  {{ stock.change }}%
                </td>

                <td class="px-2">
                  <apexchart
                    type="area"
                    height="45"
                    width="110"
                    :options="sparkOptions"
                    :series="[{ data: stock.sparkline }]"
                  />
                </td>

                <td class="px-2 text-right">
                  <button
                    @click="openBuy(stock)"
                    class="bg-[#0052CC] hover:bg-[#006AFF] px-3 py-1 rounded-lg text-white text-xs"
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
            Buy {{ selectedStock.symbol }}
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
              <label class="text-sm text-gray-400">Units</label>
              <input
                type="text"
                class="w-full bg-transparent border border-gray-600 rounded-lg px-4 py-2 mt-1"
                :value="units"
                disabled
              />
            </div>

            <button
              class="w-full bg-gradient-to-r from-[#0052CC] to-[#00E1FF] py-2 rounded-lg mt-2"
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
import { ref, computed, onMounted, watch } from "vue";
import axios from "axios";
import MainLayout from "@/layouts/MainLayout.vue";
import VueApexCharts from "vue3-apexcharts";

const apexchart = VueApexCharts;

// STATES
const search = ref("");
const suggestions = ref([]);
const stocks = ref([]);
const buyModal = ref(false);
const selectedStock = ref({});
const amount = ref(0);
const units = ref(0);
const message = ref("");

// LOAD GLOBAL MARKET DATA
onMounted(async () => {
  try {
    const res = await axios.get("/api/market/global");
    stocks.value = res.data.data;
  } catch {
    // fallback dummy
    stocks.value = [
      { symbol: "AAPL", name: "Apple Inc", market_price: 175.3, change: 1.2, sparkline: [170, 171, 172, 173, 174, 175, 175.3] },
      { symbol: "TSLA", name: "Tesla", market_price: 246.8, change: -0.5, sparkline: [252, 250, 249, 247, 246.8] },
      { symbol: "AMZN", name: "Amazon", market_price: 136.1, change: 0.7, sparkline: [132, 133, 134, 135, 136.1] },
    ];
  }
});

// AUTOCOMPLETE
watch(search, async (val) => {
  if (val.length < 2) return (suggestions.value = []);

  const res = await axios.get(`/api/companies/search/${val}`);
  suggestions.value = res.data.data;
});

const showSuggestions = computed(() => suggestions.value.length > 0);

function selectSuggestion(company) {
  search.value = company.symbol;
  suggestions.value = [];
}

// FILTER
const filteredStocks = computed(() => {
  if (!search.value) return stocks.value;
  return stocks.value.filter(s =>
    s.symbol.toLowerCase().includes(search.value.toLowerCase()) ||
    s.name.toLowerCase().includes(search.value.toLowerCase())
  );
});

// SPARKLINE OPTIONS
const sparkOptions = {
  chart: { sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  fill: {
    type: "gradient",
    gradient: { opacityFrom: 0.5, opacityTo: 0.1 }
  },
  colors: ["#00E1FF"],
  tooltip: { enabled: false }
};

// BUY MODAL
function openBuy(stock) {
  selectedStock.value = stock;
  amount.value = 0;
  units.value = 0;
  message.value = "";
  buyModal.value = true;
}

function calcUnits() {
  if (!selectedStock.value.market_price || !amount.value) return;
  units.value = (amount.value / selectedStock.value.market_price).toFixed(3);
}

// PLACE ORDER
async function placeOrder() {
  try {
    const token = localStorage.getItem("xavier_token");

    const payload = {
      company: selectedStock.value.name,
      symbol: selectedStock.value.symbol,
      market_price: selectedStock.value.market_price,
      amount: amount.value,
      units: units.value,
      type: "global_stock",
    };

    const res = await axios.post("/api/orders", payload, {
      headers: { Authorization: `Bearer ${token}` }
    });

    if (res.data.success) {
      message.value = "✅ Global stock order placed!";
      setTimeout(() => (buyModal.value = false), 1000);
    }
  } catch (e) {
    message.value = "❌ Could not place order";
  }
}
</script>
