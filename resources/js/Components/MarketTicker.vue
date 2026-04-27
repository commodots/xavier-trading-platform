<template>
  <div class="flex gap-3 p-2 bg-[#0F1724] overflow-x-auto no-scrollbar rounded-lg">
    <div v-for="stock in stocks" :key="stock.s" @click="select(stock.s)" :class="[
      'flex items-center gap-2 whitespace-nowrap px-2 py-1 cursor-pointer border rounded-md transition-all duration-500',
      stock.s === selectedSymbol ? 'bg-[#0D1728] border-[#00D4FF]' : 'bg-[#111827] border-[#1f3348]'
    ]">
      <span class="text-xs font-bold text-[#00D4FF]">{{ stock.s }}</span>

      <span class="text-xs font-semibold transition-colors duration-300"
        :class="stock.p >= stock.pc ? 'text-green-400' : 'text-red-400'">
        ${{ Number(stock.p)?.toFixed(2) }}
      </span>

      <span class="text-[10px] font-medium px-1.5 py-0.5 rounded"
        :class="stock.p >= stock.pc ? 'bg-green-400/10 text-green-400' : 'bg-red-400/10 text-red-400'">
        {{ stock.p >= stock.pc ? '▲' : '▼' }}
        {{ Math.abs(((stock.p - stock.pc) / (stock.pc || 1)) * 100).toFixed(2) }}%
      </span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, defineEmits, defineProps, watch } from "vue";
import api from "@/api";

const props = defineProps({
  selectedSymbol: { type: String, default: 'AAPL' },
  additionalTickers: { type: Array, default: () => [] }
});

const defaultStocks = [
  { s: 'AAPL', p: 0, pc: 0 },
  { s: 'TSLA', p: 0, pc: 0 },
  { s: 'MSFT', p: 0, pc: 0 }
];

const emit = defineEmits(['select-symbol']);

const stocks = ref([...defaultStocks]);

const normalizeTicker = (ticker) => {
  const symbol = ticker?.symbol || ticker?.s;
  const price = Number(ticker?.price ?? ticker?.p ?? 0);
  const prevClose = Number(ticker?.pc ?? price);
  if (!symbol) return null;
  return { s: symbol, p: price, pc: prevClose };
};

const fetchPricesForSymbols = async (symbols) => {
  if (!symbols || symbols.length === 0) return;
  
  try {
    const response = await api.get('/market/quotes', {
      params: { symbols: symbols.join(',') }
    });
    
    const quoteData = response.data?.data || response.data || [];
    quoteData.forEach(quote => {
      const sym = quote.symbol || quote.s;
      const price = Number(quote.price || quote.p || 0);
      const ticker = stocks.value.find(s => s.s === sym);
      if (ticker && price > 0) {
        ticker.p = price;
        ticker.pc = price;
      }
    });
  } catch (e) {
    console.error('Failed to fetch prices', e);
  }
};

watch(
  () => props.additionalTickers,
  (newTickers) => {
    if (!Array.isArray(newTickers)) return;

    if (newTickers.length > 0) {
      const normalized = newTickers.map(normalizeTicker).filter(Boolean);
      const combined = [...normalized, ...defaultStocks];
      stocks.value = combined.filter((v, i, a) => a.findIndex(t => t.s === v.s) === i);
      
      // Fetch real prices for all symbols
      const symbols = stocks.value.map(s => s.s);
      fetchPricesForSymbols(symbols);
    } else {
      stocks.value = [...defaultStocks];
      fetchPricesForSymbols(defaultStocks.map(s => s.s));
    }
  },
  { deep: true, immediate: true }
);

let channel = null;

const select = (symbol) => {
  emit('select-symbol', symbol);
};

onMounted(() => {
  // Initial price fetch
  const symbols = stocks.value.map(s => s.s);
  fetchPricesForSymbols(symbols);
  
  channel = window.Echo.channel("market-channel")
    .listen('MarketUpdated', (e) => {
      const updates = Array.isArray(e) ? e : (e.data || []);
      if (Array.isArray(updates)) {
        updates.forEach(update => {
          const existing = stocks.value.find(s => s.s === update.s);
          if (existing) {
            if (existing.p !== Number(update.p)) {
              existing.pc = existing.p;
              existing.p = Number(update.p);
            }
          } else if (update.s && update.p != null) {
            stocks.value.push({ 
              s: update.s, 
              p: Number(update.p), 
              pc: Number(update.p) 
            });
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