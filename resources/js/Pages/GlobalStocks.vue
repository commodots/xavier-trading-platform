<template>
  <MainLayout>
    <div>
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-semibold">🌍 Global Stocks</h1>
        <input
          v-model="search"
          type="text"
          placeholder="Search global stocks..."
          class="bg-[#0F1724] border border-[#1f3348] rounded-lg px-4 py-2 text-sm text-gray-300 focus:border-[#00D4FF] focus:ring-0 outline-none"
        />
      </div>

      <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-gray-400 border-b border-[#1f3348]">
            <tr>
              <th class="py-3 px-4 text-left">Symbol</th>
              <th class="text-left">Company</th>
              <th class="text-right">Price ($)</th>
              <th class="text-right">24h Change</th>
              <th class="text-right">Volume</th>
              <th class="text-right">Trend</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="stock in filteredStocks"
              :key="stock.symbol"
              class="border-b border-[#1f3348] hover:bg-[#16213A] transition"
            >
              <td class="py-3 px-4 font-medium">{{ stock.symbol }}</td>
              <td>{{ stock.name }}</td>
              <td class="text-right">{{ stock.price.toFixed(2) }}</td>
              <td :class="stock.change >= 0 ? 'text-green-400 text-right' : 'text-red-400 text-right'">
                {{ stock.change }}%
              </td>
              <td class="text-right">{{ stock.volume.toLocaleString() }}</td>
              <td class="text-right w-32">
                <apexchart
                  type="line"
                  height="40"
                  :options="sparkOptions"
                  :series="[{ data: stock.spark }]"
                />
              </td>
              <td class="text-center">
                <button class="bg-[#00D4FF]/20 text-[#00D4FF] px-3 py-1 rounded-md hover:bg-[#00D4FF]/30 transition">
                  Trade
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import MainLayout from "@/layouts/MainLayout.vue";
import VueApexCharts from "vue3-apexcharts";

const search = ref("");
const stocks = ref([
  { symbol: "AAPL", name: "Apple Inc", price: 175.6, change: 1.2, volume: 98200000, spark: [170, 171, 173, 175, 175.6] },
  { symbol: "TSLA", name: "Tesla Inc", price: 244.2, change: -0.8, volume: 78400000, spark: [250, 248, 247, 245, 244] },
  { symbol: "MSFT", name: "Microsoft Corp", price: 420.5, change: 2.1, volume: 68200000, spark: [412, 414, 417, 419, 420.5] },
  { symbol: "GOOG", name: "Alphabet Inc", price: 160.3, change: 0.5, volume: 57200000, spark: [158, 158.5, 159, 160, 160.3] },
]);

const filteredStocks = computed(() =>
  stocks.value.filter(s =>
    s.name.toLowerCase().includes(search.value.toLowerCase()) ||
    s.symbol.toLowerCase().includes(search.value.toLowerCase())
  )
);

const sparkOptions = {
  chart: { toolbar: { show: false }, sparkline: { enabled: true } },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false },
  grid: { show: false },
};
</script>

<script>
export default { components: { apexchart: VueApexCharts } };
</script>
