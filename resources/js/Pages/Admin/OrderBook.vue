<template>
  <MainLayout>
    <div class="space-y-6">
      <!-- header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">🧾 Order Book Monitor</h1>
          <p class="text-gray-400 text-sm">Live bid/ask lists and market depth. Uses simulated data until backend is connected.</p>
        </div>

        <div class="flex items-center gap-4">
          <!-- optional uploaded screenshot used as small preview (change to public URL when available) -->
          <img src="/mnt/data/register_screen.png" alt="snapshot" class="h-10 object-contain rounded" />
          <button @click="refresh" class="px-3 py-2 rounded bg-[#00D4FF] text-black font-semibold">Refresh</button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Order book (bids / asks) -->
        <div class="lg:col-span-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Order Book</div>
            <div class="text-xs text-gray-400">Pair: {{ pair }}</div>
          </div>

          <div class="grid grid-cols-2 gap-2 text-sm">
            <div>
              <div class="text-xs text-gray-400 mb-2">Bids (buy)</div>
              <order-book-table :rows="bids" side="bid" />
            </div>
            <div>
              <div class="text-xs text-gray-400 mb-2">Asks (sell)</div>
              <order-book-table :rows="asks" side="ask" />
            </div>
          </div>
        </div>

        <!-- Depth (chart) -->
        <div class="lg:col-span-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <div class="font-semibold mb-3">Market Depth</div>
          <order-depth-chart :bids="bids" :asks="asks" />
        </div>

        <!-- Recent trades / summary -->
        <div class="lg:col-span-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Recent Trades</div>
            <div class="text-xs text-gray-400">Last {{ trades.length }}</div>
          </div>

          <div class="text-sm">
            <ul class="space-y-2">
              <li v-for="t in trades" :key="t.id" class="flex justify-between items-center p-2 rounded hover:bg-[#122033]">
                <div>
                  <div class="font-medium">{{ t.side.toUpperCase() }} {{ t.amount }} @ {{ t.price }}</div>
                  <div class="text-xs text-gray-400">{{ t.time }}</div>
                </div>
                <div :class="t.side === 'buy' ? 'text-green-400' : 'text-red-400'">₦{{ (t.amount * t.price).toLocaleString() }}</div>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div class="text-xs text-gray-400 mt-2">Note: This view uses simulated data. Replace simulation calls with your OMS endpoints to show real book & depth.</div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import OrderBookTable from '@/components/admin/OrderBookTable.vue';
import OrderDepthChart from '@/components/admin/OrderDepthChart.vue';
import axios from 'axios';

const pair = ref('NGN/NGX'); // show pair label
const bids = ref([]);
const asks = ref([]);
const trades = ref([]);

// helper: generate simulated book rows (price, amount, cumulative)
function genSide(basePrice, side) {
  const rows = [];
  for (let i = 0; i < 10; i++) {
    const offset = (Math.random() * (i + 1) * 0.5).toFixed(2);
    const price = side === 'bid' ? Number((basePrice - offset).toFixed(2)) : Number((basePrice + offset).toFixed(2));
    const amount = Number((Math.random() * 200 + 1).toFixed(4));
    rows.push({ price, amount });
  }
  // sort bids desc, asks asc
  return rows.sort((a, b) => side === 'bid' ? b.price - a.price : a.price - b.price);
}

// simulated trades
function genTrades(basePrice) {
  const out = [];
  for (let i = 0; i < 10; i++) {
    const side = Math.random() > 0.5 ? 'buy' : 'sell';
    const price = Number((basePrice + (Math.random() * 2 - 1)).toFixed(2));
    const amount = Number((Math.random() * 50 + 1).toFixed(2));
    out.push({ id: `${Date.now()}-${i}`, side, price, amount, time: new Date().toLocaleTimeString() });
  }
  return out;
}

async function loadData() {
  // Try to fetch real book from API if present; fallback to simulated
  try {
    // EXAMPLE: replace with your real endpoint
    // const resp = await axios.get(`/api/orders/book?pair=${pair.value}`);
    // bids.value = resp.data.bids;
    // asks.value = resp.data.asks;
    // trades.value = resp.data.trades;

    // simulated:
    const base = 48.5 + Math.random() * 2 - 1;
    bids.value = genSide(base, 'bid');
    asks.value = genSide(base, 'ask');
    trades.value = genTrades(base);
  } catch (e) {
    // fall back to simulation on error
    const base = 48.5 + Math.random() * 2 - 1;
    bids.value = genSide(base, 'bid');
    asks.value = genSide(base, 'ask');
    trades.value = genTrades(base);
  }
}

onMounted(() => {
  loadData();
  // auto-refresh book every 2.5s
  setInterval(loadData, 2500);
});

function refresh() {
  loadData();
}
</script>
