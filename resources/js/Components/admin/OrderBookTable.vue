<template>
  <div class="text-sm">
    <table class="w-full">
      <thead class="text-xs text-gray-400">
        <tr>
          <th class="text-left">Price</th>
          <th class="text-right">Amount</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(r, idx) in rows" :key="idx" class="hover:bg-[#122033]">
          <td :class="side === 'bid' ? 'text-green-400' : 'text-red-400'">{{ r.price.toFixed(2) }}</td>
          <td class="text-right text-gray-300">{{ r.amount }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  rows: { type: Array, default: () => [] },
  side: { type: String, default: 'bid' } // 'bid' | 'ask'
});
</script>
