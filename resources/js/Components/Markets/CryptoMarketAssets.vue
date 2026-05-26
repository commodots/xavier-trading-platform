<template>
  <div class="bg-[#0F1724] rounded-xl border border-[#1f3348] overflow-hidden">
        <div class="p-4 border-b border-[#1f3348] flex justify-between items-center bg-[#131C2E]">
          <h2 class="font-semibold text-gray-200">Market Assets</h2>
          <span class="text-xs text-gray-500">Live Prices</span>
        </div>
        <div class="overflow-x-auto">
          <template v-if="loading">
            <div class="p-6">
              <SkeletonLoader type="table" :count="6" class="opacity-40" />
            </div>
          </template>
          <template v-else>
            <table class="w-full text-sm">
              <thead class="text-gray-400 border-b border-[#1f3348] bg-[#0B121D]">
                <tr>
                  <th class="px-6 py-4 font-medium text-left">Symbol</th>
                  <th class="font-medium text-left">Name</th>
                  <th class="font-medium text-right">Price</th>
                  <th class="font-medium text-right">24h Change</th>
                  <th class="font-medium text-right">Market Cap</th>
                  <th class="px-6 font-medium text-right">Trend</th>
                  <th class="font-medium text-center" colspan="2">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#1f3348]">
                <tr v-for="coin in filteredCoins" :key="coin.symbol" class="hover:bg-[#16213A] transition">
                  <td class="px-6 py-4 font-bold text-[#F7931A]">{{ coin.symbol }}</td>
                  <td class="text-gray-300">{{ coin.name }}</td>
                  <td class="font-mono font-semibold text-right text-white">${{ (coin.price || 0).toFixed(2) }}</td>
                  <td class="text-right" :class="coin.change >= 0 ? 'text-green-400' : 'text-red-400'">
                    {{ coin.change >= 0 ? '+' : '' }}{{ coin.change }}%
                  </td>
                  <td class="text-right text-gray-400">${{ (coin.marketcap / 1e9).toFixed(2) }}B</td>
                  <td class="w-32 px-6 text-right">
                    <apexchart type="line" height="30" :options="sparkOptions" :series="[{ data: coin.spark }]" />
                  </td>
                  <td class="px-2 text-center">
                    <button @click="openDetails(coin)" class="bg-[#1f3348] text-gray-300 px-3 py-1.5 rounded-md hover:bg-[#2d4a66] transition text-xs">Details</button>
                  </td>
                  <td class="px-2 pr-6 text-center">
                    <button @click="openTrade(coin)" class="bg-[#00D4FF] text-[#0F1724] px-4 py-1.5 rounded-md font-bold hover:bg-[#00b8e6] transition text-xs">Buy</button>
                  </td>
                </tr>
                <tr v-if="filteredCoins.length === 0">
                  <td colspan="8" class="p-10 italic text-center text-gray-500">No matching assets were found.</td>
                </tr>
              </tbody>
            </table>
          </template>
        </div>
      </div>
</template>

<script setup>
import { computed } from "vue";
import apexchart from "vue3-apexcharts";
import SkeletonLoader from "@/Components/SkeletonLoader.vue";

const props = defineProps({
  coins: Array,
  searchQuery: String,
  loading: Boolean,
  loading: Boolean
});

const emit = defineEmits(['view-details', 'trade']);

const filteredCoins = computed(() => 
  props.coins.filter(c => 
    c.name.toLowerCase().includes(props.searchQuery.toLowerCase()) || 
    c.symbol.toLowerCase().includes(props.searchQuery.toLowerCase())
  )
);


const sparkOptions = {
  chart: { toolbar: { show: false }, sparkline: { enabled: true }, animations: { enabled: false } },
  stroke: { curve: "smooth", width: 2 },
  colors: ["#00D4FF"],
  tooltip: { enabled: false }
};

const openDetails = (coin) => emit('view-details', coin);
const openTrade = (coin) => emit('trade', coin);
</script>