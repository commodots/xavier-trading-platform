<template>
  <MainLayout>
    <div class="space-y-8">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">📈 NGX Market</h1>
          <p class="text-gray-400 text-sm">Trade listed Nigerian stocks in real-time.</p>
        </div>

        <div class="w-64">
          <input
            v-model="search"
            type="text"
            placeholder="Search company or symbol..."
            class="w-full bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm outline-none focus:border-[#00D4FF]"
          />
        </div>
      </div>

      <!-- Search Suggestions -->
      <div
        v-if="showSuggestions"
        class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-3 w-64 absolute z-50"
      >
        <div
          v-for="c in suggestions"
          :key="c.symbol"
          @click="selectSuggestion(c)"
          class="px-3 py-2 hover:bg-[#12203a] rounded cursor-pointer"
        >
          <div class="font-medium">{{ c.symbol }} • {{ c.name }}</div>
          <div class="text-xs text-gray-400">₦{{ c.market_price }}</div>
        </div>
      </div>

      <!-- Market Table -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">Market Overview</h2>
          <span class="text-xs text-gray-400">Updated 2 mins ago</span>
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
                <!-- Symbol -->
                <td class="py-3 px-2 font-semibold">{{ stock.symbol }}</td>

                <!-- Company name -->
                <td class="px-2">{{ stock.name }}</td>

                <!-- Price -->
                <td class="px-2 font-medium">₦{{ stock.market_price.toLocaleString() }}</td>

                <!-- Change -->
                <td
                  class="px-2"
                  :class="{
                    'text-green-400': stock.change >= 0,
                    'text-red-400': stock.change < 0
                  }"
                >
                  {{ stock.change }}%
                </td>

                <!-- Sparkline -->
                <td class="px-2">
                  <apexchart
                    type="area"
                    height="45"
                    width="110"
                    :options="sparkOptions"
                    :series="[{ data: stock.sparkline }]"
                  />
                </td>

                <!-- Action -->
                <td class="px-2 text-right">
                  <button
                    @click="openBuy(stock)"
                    class="bg-[#0047AB] hover:bg-[#0057D4] px-3 py-1 rounded-lg text-white text-xs"
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
          >
            ✖
          </button>

          <h2 class="text-xl font-semibold mb-4">Buy {{ selectedStock.symbol }}</h2>

          <form @submit.prevent="placeOrder">
            <div class="mb-4">
              <label class="text-sm text-gray-400">Amount (₦)</label>
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
              class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] py-2 rounded-lg mt-2"
              type="submit"
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

// Load NGX market data
onMounted(async () => {
  try {
    const res = await axios.get("/api/market/ngx");
    stocks.value = res.data.data;
  } catch {
    // fallback dummy data
    stocks.value = [
      { symbol: "ZENITH", name: "Zenith Bank", market_price: 48.5, change: 1.5, sparkline: [45, 46, 46.5, 47, 48, 48.2, 48.5] },
      { symbol: "MTN", name: "MTN Nigeria", market_price: 210.3, change: -0.8, sparkline: [215, 214, 213, 212, 211, 210.5, 210.3] },
      { symbol: "GTCO", name: "GTBank Holdings", market_price: 39.7, change: 0.9, sparkline: [38, 38.5, 39, 39.2, 39.7] },
    ];
  }
});

// SEARCH AUTOCOMPLETE
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

// FILTER TABLE
const filteredStocks = computed(() => {
  if (!search.value) return stocks.value;
  return stocks.value.filter(s =>
    s.symbol.toLowerCase().includes(search.value.toLowerCase()) ||
    s.name.toLowerCase().includes(search.value.toLowerCase())
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
  units.value = (amount.value / selectedStock.value.market_price).toFixed(2);
}

// PLACE ORDER API
async function placeOrder() {
  try {
    const token = localStorage.getItem("xavier_token");

    const payload = {
      company: selectedStock.value.name,
      symbol: selectedStock.value.symbol,
      market_price: selectedStock.value.market_price,
      amount: amount.value,
      units: units.value,
    };

    const res = await axios.post("/api/orders", payload, {
      headers: { Authorization: `Bearer ${token}` }
    });

    if (res.data.success) {
      message.value = "✅ Order placed!";
      setTimeout(() => (buyModal.value = false), 1200);
    }
  } catch (e) {
    message.value = "❌ Could not place order";
  }
}
</script>
