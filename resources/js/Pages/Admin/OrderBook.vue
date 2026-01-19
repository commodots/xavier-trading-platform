<template>
  <MainLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">🧾 Order Book Monitor</h1>
          <p class="text-sm text-gray-400">Live bid/ask lists and market depth. Uses simulated data until backend is connected.</p>
        </div>

        <div class="flex items-center gap-4">
          <img src="/mnt/data/register_screen.png" alt="snapshot" class="object-contain h-10 rounded" />
          <button @click="refresh" class="px-3 py-2 rounded bg-[#00D4FF] text-black font-semibold">Refresh</button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Order Book</div>
            <div class="text-xs text-gray-400">Pair: {{ pair }}</div>
          </div>

          <div class="grid grid-cols-2 gap-2 text-sm">
            <div>
              <div class="mb-2 text-xs text-gray-400">Bids (buy)</div>
              <order-book-table :rows="bids" side="bid" />
            </div>
            <div>
              <div class="mb-2 text-xs text-gray-400">Asks (sell)</div>
              <order-book-table :rows="asks" side="ask" />
            </div>
          </div>
        </div>

        <div class="lg:col-span-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <div class="mb-3 font-semibold">Market Depth</div>
          <order-depth-chart :bids="bids" :asks="asks" />
        </div>

        <div class="lg:col-span-1 bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <div class="flex items-center justify-between mb-3">
            <div class="font-semibold">Recent Orders</div>
            <div class="flex items-center gap-2">
              <div class="text-xs text-gray-400">Last {{ orders.length }}</div>
              <router-link to="/admin/orders" class="text-xs text-blue-400 hover:underline">View All</router-link>
            </div>
          </div>

          <div class="text-sm">
            <ul class="space-y-2">
              <li v-for="order in orders" :key="order.id" class="p-2 rounded hover:bg-[#122033]">
                <div class="flex items-start justify-between">
                  <div>
                    <div class="font-medium">{{ order.side.toUpperCase() }} {{ order.symbol }}</div>
                    <div class="text-xs text-gray-400">{{ order.user.first_name }} {{ order.user.last_name }}</div>
                  </div>
                  <div class="text-right">
                    <div :class="order.status === 'filled' ? 'text-green-400' : order.status === 'open' ? 'text-blue-400' : 'text-yellow-400'">
                      {{ order.status.replace('_', ' ').toUpperCase() }}
                    </div>
                    <div class="text-xs text-gray-400">Qty: {{ order.quantity }}</div>
                    <div class="text-xs text-gray-400">Price: {{ order.price ? '₦' + Number(order.price).toLocaleString() : 'Market' }}</div>
                  </div>
                </div>
                <div class="mt-1 text-xs text-gray-500">{{ new Date(order.created_at).toLocaleString() }}</div>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div class="mt-2 text-xs text-gray-400">Note: This view uses simulated data. Replace simulation calls with your OMS endpoints to show real book & depth.</div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import OrderBookTable from '@/Components/admin/OrderBookTable.vue';
import OrderDepthChart from '@/Components/admin/OrderDepthChart.vue';
import axios from 'axios';

const pair = ref('NGN/NGX');
const bids = ref([]);
const asks = ref([]);
const orders = ref([]);

// helper: generate simulated book rows (price, amount, cumulative)
function genSide(basePrice, side) {
  const rows = [];
  for (let i = 0; i < 10; i++) {
    const offset = (Math.random() * (i + 1) * 0.5).toFixed(2);
    const base = Number(basePrice) || 0;
const off = Number(offset) || 0;

const price =
  side === 'bid'
    ? Number((base - off).toFixed(2))
    : Number((base + off).toFixed(2));
    const amount = Number((Math.random() * 200 + 1).toFixed(4));
    rows.push({ price, amount });
  }

  // sort: bids desc, asks asc
  rows.sort((a, b) => side === 'bid' ? b.price - a.price : a.price - b.price);

  // calculate cumulative totals for depth chart
  let total = 0;
  return rows.map(r => {
    total += r.amount;
    return { ...r, total: Number(total.toFixed(4)) };
  });
}



async function loadData() {
  try {
    // simulated:
    const base = 48.5 + (Math.random() * 0.4 - 0.2);
    bids.value = genSide(base, 'bid');
    asks.value = genSide(base, 'ask');

    
    const response = await axios.get('/admin/orders');
    orders.value = response.data.data.data.slice(0, 10); 
  } catch (e) {
    console.error("Data load error", e);
  }
}

onMounted(() => {
  loadData();
  // auto-refresh book every 2.5s
  const interval = setInterval(loadData, 2500);

  onUnmounted(() => clearInterval(interval));
});

function refresh() {
  loadData();
}
</script>
