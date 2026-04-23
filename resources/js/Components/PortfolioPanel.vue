<template>
  <div class="bg-[#1f2937] p-5 rounded-xl border border-[#374151]">
    <h3 class="mb-4 text-lg font-semibold">Portfolio</h3>

    <div v-if="loading" class="flex justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00D4FF]"></div>
    </div>

    <div v-else-if="portfolio.length === 0" class="py-8 text-center text-gray-400">
      No positions yet. Place your first trade!
    </div>

    <div v-else class="space-y-3">
      <div v-for="position in portfolio" :key="position.symbol" class="bg-[#111827] p-4 rounded-lg border border-[#374151]">
          <div class="flex items-center justify-between">
          <div>
            <div class="font-semibold text-white">{{ position.symbol }}</div>
            <div class="text-sm text-gray-400">{{ position.qty }} shares</div>
          </div>
          <div class="text-right">
            <div class="font-semibold text-[#00D4FF]">${{ Number(position.avg_entry_price)?.toFixed(2) }}</div>
            <div class="text-sm text-gray-400">Avg Price</div>
          </div>
        </div>
        <div class="mt-2 text-sm">
          <span class="text-green-400">Market Value: ${{ (position.qty * (position.current_price || position.avg_entry_price))?.toFixed(2) }}</span>
        </div>
      </div>
    </div>

    <!-- Account Balance -->
    <div v-if="account" class="mt-6 p-4 bg-[#111827] rounded-lg border border-[#374151]">
      <h4 class="mb-2 font-semibold text-white">Account Balance</h4>
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
          <span class="text-gray-400">Buying Power:</span>
          <span class="ml-2 text-white">${{ Number(account.buying_power)?.toFixed(2) }}</span>
        </div>
        <div>
          <span class="text-gray-400">Cash:</span>
          <span class="ml-2 text-white">${{ Number(account.cash)?.toFixed(2) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import axios from "axios";

const portfolio = ref([]);
const account = ref(null);
const loading = ref(true);
let channel = null;

const fetchPortfolio = async () => {
  try {
    loading.value = true;
    const tradingRes = await axios.get("portfolio/trading");

    portfolio.value = tradingRes.data.positions || [];
    account.value = tradingRes.data.account || null;
  } catch (error) {
    console.error('Failed to fetch portfolio:', error);
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await fetchPortfolio();

  // Listen for real-time updates
  channel = window.Echo.channel("market-channel")
    .listen("MarketUpdated", (e) => {
      // Update current prices for positions
      if (e.data && Array.isArray(e.data)) {
        portfolio.value.forEach(position => {
          const update = e.data.find(u => u.s === position.symbol);
          if (update) {
            position.current_price = Number(update.p);
          }
        });
      }
    });
});

onUnmounted(() => {
  if (channel) {
    window.Echo.leave("market-channel");
  }
});
</script>

