<template>
  <MainLayout>
    <div class="space-y-8">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">🌍 Global Market</h1>
          <p class="text-sm text-gray-400">Trade global stocks from US & other international markets.</p>
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
                <th class="px-2 py-2 text-left">Symbol</th>
                <th class="px-2 text-left">Company</th>
                <th class="px-2 text-left">Price</th>
                <th class="px-2 text-left">Change</th>
                <th class="px-2 text-left">Trend</th>
                <th class="px-2 text-right">Action</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="stock in filteredStocks"
                :key="stock.symbol"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition"
              >
                <td class="px-2 py-3 font-semibold">{{ stock.symbol }}</td>
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

      <!-- Holdings Table -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold text-white">Your Holdings</h2>
          <span class="text-xs text-gray-400">Global stock positions</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 text-xs border-b border-[#1f3348]">
              <tr>
                <th class="px-2 py-2 text-left">Symbol</th>
                <th class="px-2 text-left">Company</th>
                <th class="px-2 text-left">Quantity</th>
                <th class="px-2 text-left">Avg Cost</th>
                <th class="px-2 text-left">Current Price</th>
                <th class="px-2 text-left">Volume</th>
                <th class="px-2 text-left">Trend</th>
                <th class="px-2 text-left">P/L</th>
              </tr>
            </thead>

            <tbody>
              <tr v-if="holdings.length === 0">
                <td colspan="8" class="py-6 text-center text-gray-500">
                  You currently hold no global stocks.
                </td>
              </tr>
              <tr
                v-else
                v-for="holding in holdings"
                :key="holding.symbol"
                class="border-b border-[#1f3348] hover:bg-[#16213A] transition"
              >
                <td class="px-2 py-3 font-semibold">{{ holding.symbol }}</td>
                <td class="px-2">{{ holding.name || holding.symbol }}</td>
                <td class="px-2">{{ holding.quantity ? holding.quantity.toLocaleString() : 0 }}</td>
                <td class="px-2">${{ holding.avg_price ? holding.avg_price.toLocaleString() : '0.00' }}</td>
                <td class="px-2 font-medium">${{ holding.market_price ? holding.market_price.toLocaleString() : '0.00' }}</td>
                <td class="px-2 text-gray-400">{{ holding.volume ? holding.volume.toLocaleString() : 'N/A' }}</td>
                <td class="px-2">
                  <apexchart
                    v-if="holding.sparkline && holding.sparkline.length > 0"
                    type="area"
                    height="35"
                    width="80"
                    :options="sparkOptions"
                    :series="[{ data: holding.sparkline }]"
                  />
                  <span v-else class="text-gray-500">-</span>
                </td>
                <td
                  class="px-2"
                  :class="{
                    'text-green-400': (holding.market_price - holding.avg_price) >= 0,
                    'text-red-400': (holding.market_price - holding.avg_price) < 0
                  }"
                >
                  ${{ (((holding.market_price || 0) - (holding.avg_price || 0)) * (holding.quantity || 0)).toLocaleString() || '0.00' }}
                  <span class="text-xs">
                    ({{ ((((holding.market_price || 0) - (holding.avg_price || 0)) / (holding.avg_price || 1)) * 100).toFixed(2) || '0.00' }}%)
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Buy Modal -->
      <div
        v-if="buyModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
      >
        <div class="bg-[#1C1F2E] rounded-2xl p-8 shadow-xl w-full max-w-lg relative">
          <button
            @click="buyModal = false"
            class="absolute text-gray-400 top-3 right-3 hover:text-white"
          >✖</button>

          <h2 class="mb-4 text-xl font-semibold">
            Buy {{ selectedStock.symbol }}
          </h2>

          <form @submit.prevent="placeOrder">
            <div class="mb-4">
              <label class="text-sm text-gray-400">Amount (USD)</label>
              <input
                v-model.number="amount"
                type="number"
                class="w-full px-4 py-2 mt-1 bg-transparent border border-gray-600 rounded-lg"
                @input="calcUnits"
              />
            </div>

            <div class="mb-4">
              <label class="text-sm text-gray-400">Units</label>
              <input
                type="text"
                class="w-full px-4 py-2 mt-1 bg-transparent border border-gray-600 rounded-lg"
                :value="units"
                disabled
              />
            </div>

            <button
              class="w-full bg-gradient-to-r from-[#0052CC] to-[#00E1FF] py-2 rounded-lg mt-2"
            >
              Place Order
            </button>

            <p class="mt-3 text-sm text-center text-yellow-400">{{ message }}</p>
          </form>
        </div>
      </div>

    </div>
  </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import axios from "axios";
import MainLayout from "@/Layouts/MainLayout.vue";
import VueApexCharts from "vue3-apexcharts";

const apexchart = VueApexCharts;

// STATES
const search = ref("");
const suggestions = ref([]);
const stocks = ref([]);
const holdings = ref([]);
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

  // Load user holdings
  await fetchHoldings();
});

// FETCH USER HOLDINGS
async function fetchHoldings() {
  try {
    const token = localStorage.getItem("xavier_token");
    const res = await axios.get("/api/portfolio", {
      headers: { Authorization: `Bearer ${token}` }
    });

    // Filter for global stocks only and enrich with volume/trend data
    const portfolioData = res.data.data && res.data.data.holdings ? res.data.data.holdings : [];
    const globalHoldings = portfolioData.filter(h =>
      h.category === 'stocks' || h.market === 'STOCKS'
    );

    // Enrich holdings with current market data
    holdings.value = globalHoldings.map(holding => {
      const marketData = stocks.value.find(s => s.symbol === holding.symbol);
      return {
        ...holding,
        market_price: marketData ? marketData.market_price : holding.market_price || 0,
        volume: marketData ? marketData.volume : 0,
        sparkline: marketData ? marketData.sparkline : []
      };
    });
  } catch (error) {
    console.error("Failed to fetch holdings:", error);
    holdings.value = [];
  }
}

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
      message.value = "Global stock order placed!";
      await fetchHoldings(); // Refresh holdings after order
      setTimeout(() => (buyModal.value = false), 1000);
    }
  } catch (e) {
    message.value = "Could not place order";
  }
}
</script>
