<template>
  <MainLayout>
    <div class="space-y-6">

      <!-- Header -->
      <div>
        <h1 class="text-2xl font-bold text-white">📊 Market Monitor</h1>
        <p class="text-gray-400 text-sm">Live market prices for NGX, Global Stocks & Crypto</p>
      </div>

      <!-- Tabs -->
      <div class="flex gap-3 border-b border-[#1F2A44] pb-2">
        <button
          v-for="t in tabs"
          :key="t.key"
          @click="tab = t.key"
          class="px-4 py-2 rounded-lg"
          :class="tab === t.key ? 'bg-[#1C2541] text-white' : 'text-gray-400 hover:text-white'"
        >
          {{ t.label }}
        </button>
      </div>

      <!-- NGX MARKET -->
      <div v-if="tab === 'ngx'" class="bg-[#111827] border border-[#1F2A44] rounded-xl p-6">
        <h2 class="text-xl mb-4 font-semibold text-white">📈 NGX Market</h2>

        <market-table :rows="ngx" />
      </div>

      <!-- GLOBAL STOCKS -->
      <div v-if="tab === 'global'" class="bg-[#111827] border border-[#1F2A44] rounded-xl p-6">
        <h2 class="text-xl mb-4 font-semibold text-white">🌍 Global Stocks</h2>

        <market-table :rows="global" currency="$" />
      </div>

      <!-- CRYPTO -->
      <div v-if="tab === 'crypto'" class="bg-[#111827] border border-[#1F2A44] rounded-xl p-6">
        <h2 class="text-xl mb-4 font-semibold text-white">₿ Crypto Market</h2>

        <market-table :rows="crypto" currency="$" />
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import MainLayout from "@/layouts/MainLayout.vue";
import MarketTable from "@/components/admin/MarketTable.vue";

const tab = ref("ngx");

const tabs = [
  { key: "ngx", label: "NGX" },
  { key: "global", label: "Global Stocks" },
  { key: "crypto", label: "Crypto" },
];

const ngx = ref([]);
const global = ref([]);
const crypto = ref([]);

// Dummy simulation (Replace when API is ready)
function simulatePrice(base) {
  return base + (Math.random() * 2 - 1);
}

// Fetch Dummy Data
function loadDummyData() {
  ngx.value = [
    { symbol: "ZENITH", name: "Zenith Bank", price: simulatePrice(48.5), change: -0.24, spark: [48, 49, 48.7, 48.5] },
    { symbol: "GTCO", name: "GTBank", price: simulatePrice(42.1), change: +1.14, spark: [41.7, 41.9, 42.0, 42.1] },
    { symbol: "MTN", name: "MTN Nigeria", price: simulatePrice(210.3), change: -0.05, spark: [210.5, 210.4, 210.35, 210.3] },
  ];

  global.value = [
    { symbol: "AAPL", name: "Apple", price: simulatePrice(172.5), change: +0.83, spark: [170, 171, 172, 172.5] },
    { symbol: "TSLA", name: "Tesla", price: simulatePrice(890.4), change: -0.54, spark: [892, 891, 890.6, 890.4] },
    { symbol: "AMZN", name: "Amazon", price: simulatePrice(132.5), change: +0.34, spark: [131, 132, 132.3, 132.5] },
  ];

  crypto.value = [
    { symbol: "BTC", name: "Bitcoin", price: simulatePrice(64700), change: +1.2, spark: [64000, 64500, 64600, 64700] },
    { symbol: "ETH", name: "Ethereum", price: simulatePrice(3320), change: +0.5, spark: [3300, 3310, 3320, 3320] },
    { symbol: "SOL", name: "Solana", price: simulatePrice(145), change: -1.4, spark: [150, 148, 146, 145] },
  ];
}

onMounted(() => {
  loadDummyData();
  setInterval(loadDummyData, 3000); // auto-refresh every 3 secs
});
</script>
