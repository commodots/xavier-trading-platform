<template>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-gray-400 text-xs border-b border-[#1F2A44]">
        <tr>
          <th class="text-left py-2">Symbol</th>
          <th class="text-left">Name</th>
          <th class="text-right">Price</th>
          <th class="text-right">Change</th>
          <th class="text-right">Trend</th>
        </tr>
      </thead>

      <tbody>
        <tr
          v-for="row in rows"
          :key="row.symbol"
          class="border-b border-[#1F2A44] hover:bg-[#16213A] transition"
        >
          <td class="py-3 font-semibold">{{ row.symbol }}</td>
          <td class="text-gray-300">{{ row.name }}</td>
          <td class="text-right">{{ currency }}{{ row.price.toLocaleString() }}</td>

          <td
            class="text-right font-semibold"
            :class="row.change >= 0 ? 'text-green-400' : 'text-red-400'"
          >
            {{ row.change >= 0 ? '+' : '' }}{{ row.change }}%
          </td>

          <td class="text-right">
            <sparkline :data="row.spark" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  rows: Array,
  currency: { type: String, default: "₦" },
});
</script>
