<template>
  <MainLayout>
    <h1 class="text-2xl font-bold mb-4">₿ Crypto Market</h1>

    <div class="bg-[#0F1724] rounded-xl p-6 border border-[#1F2A44]">
      <h2 class="text-xl font-semibold mb-4">Market Overview</h2>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-[#111827]/70 p-4 rounded-lg border border-[#1f3348]">
          <div class="text-xs text-gray-400">Top Gainer</div>
          <div class="text-lg font-semibold text-green-400">SOL (+7.3%)</div>
        </div>
        <div class="bg-[#111827]/70 p-4 rounded-lg border border-[#1f3348]">
          <div class="text-xs text-gray-400">Top Loser</div>
          <div class="text-lg font-semibold text-red-400">DOGE (-4.1%)</div>
        </div>
        <div class="bg-[#111827]/70 p-4 rounded-lg border border-[#1f3348]">
          <div class="text-xs text-gray-400">24h Volume</div>
          <div class="text-lg font-semibold">$38.2B</div>
        </div>
      </div>

      <table class="w-full text-sm">
        <thead class="text-left text-gray-400 text-xs border-b border-[#1f2a44]">
          <tr>
            <th class="py-2">Symbol</th>
            <th>Name</th>
            <th>Price</th>
            <th>Change (24h)</th>
            <th>Market Cap</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="coin in coins" :key="coin.symbol" class="border-b border-[#1f2a44] hover:bg-[#122033]">
            <td class="py-3 font-medium">{{ coin.symbol }}</td>
            <td>{{ coin.name }}</td>
            <td>${{ coin.price.toLocaleString() }}</td>
            <td :class="coin.change > 0 ? 'text-green-400' : 'text-red-400'">
              {{ coin.change > 0 ? '+' : '' }}{{ coin.change }}%
            </td>
            <td>${{ coin.cap.toLocaleString() }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from "@/layouts/MainLayout.vue";

const coins = [
  { symbol: "BTC", name: "Bitcoin", price: 68000, change: 2.3, cap: 1320000000000 },
  { symbol: "ETH", name: "Ethereum", price: 3550, change: 1.8, cap: 420000000000 },
  { symbol: "BNB", name: "Binance Coin", price: 565, change: -0.7, cap: 86000000000 },
  { symbol: "SOL", name: "Solana", price: 175, change: 7.3, cap: 76000000000 },
];
</script>
