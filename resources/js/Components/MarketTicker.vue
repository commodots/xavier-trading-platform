<template>
  <div class="flex gap-6 p-3 bg-[#0F1724] overflow-x-auto no-scrollbar rounded-lg">
    <div 
      v-for="stock in stocks" 
      :key="stock.s" 
      @click="select(stock.s)"
      :class="[
        'flex items-center gap-3 whitespace-nowrap px-3 py-1.5 cursor-pointer border rounded-md transition-all duration-500',
        stock.s === selectedSymbol ? 'bg-[#0D1728] border-[#00D4FF]' : 'bg-[#111827] border-[#1f3348]'
      ]"
    >
      <span class="font-bold text-[#00D4FF]">{{ stock.s }}</span>

      <span 
        class="font-semibold transition-colors duration-300"
        :class="stock.p >= stock.pc ? 'text-green-400' : 'text-red-400'"
      >
        ${{ Number(stock.p)?.toFixed(2) }}
      </span>

      <span 
        class="text-xs font-medium px-1.5 py-0.5 rounded"
        :class="stock.p >= stock.pc ? 'bg-green-400/10 text-green-400' : 'bg-red-400/10 text-red-400'"
      >
        {{ stock.p >= stock.pc ? '▲' : '▼' }}
        {{ Math.abs(((stock.p - stock.pc) / (stock.pc || 1)) * 100).toFixed(2) }}%
      </span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, defineEmits, defineProps } from "vue";

const props = defineProps({
  selectedSymbol: { type: String, default: 'AAPL' }
});

const emit = defineEmits(['select-symbol']);

const stocks = ref([
  { s: 'AAPL', p: 224.72, pc: 223.65 },
  { s: 'TSLA', p: 248.50, pc: 245.80 },
  { s: 'MSFT', p: 415.26, pc: 412.30 }
]);

let channel = null;

const select = (symbol) => {
  emit('select-symbol', symbol);
};

onMounted(() => {
  channel = window.Echo.channel("market-channel")
    .listen('.MarketUpdated', (e) => {
      if (e.data && Array.isArray(e.data)) {
        e.data.forEach(update => {
          const existing = stocks.value.find(s => s.s === update.s);
          if (existing) {
            existing.pc = existing.p;
            existing.p = Number(update.p);
          } else if (update.s && update.p != null) {
            stocks.value.push({ s: update.s, p: Number(update.p), pc: Number(update.p) });
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

<style scoped>
/* Hidden scrollbar */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>