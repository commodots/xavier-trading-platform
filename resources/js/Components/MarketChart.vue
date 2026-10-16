<template>
  <div class="w-full h-[400px] rounded-lg overflow-hidden bg-[#0F1724] relative">
    <div v-if="isLoading || chartError || isEmptyHistory"
      class="absolute inset-0 z-10 flex items-center justify-center bg-[#0F1724]/95 p-5 text-center">
      <div v-if="isLoading" class="space-y-2" role="status" aria-live="polite">
        <p class="text-xs font-bold uppercase tracking-widest text-[#00D4FF]">Loading market history...</p>
      </div>
      <div v-else-if="chartError" class="max-w-sm space-y-3" role="alert">
        <p class="font-semibold text-white">Market history could not be loaded</p>
        <p class="text-sm text-gray-400">{{ chartError }}</p>
        <button
          type="button"
          class="min-h-11 rounded-lg border border-[#1f3348] px-4 py-2 text-sm font-semibold text-[#00D4FF] transition hover:bg-white/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-300"
          @click="fetchHistory"
        >
          Try again
        </button>
      </div>
      <div v-else class="max-w-sm space-y-2" role="status" aria-live="polite">
        <p class="font-semibold text-white">Historical market data is unavailable</p>
        <p class="text-sm text-gray-400">
          No candles were returned for {{ props.symbol }}. Try again later.
        </p>
      </div>
    </div>
    <div class="absolute right-3 top-3 z-10 rounded bg-[#0B132B]/90 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-gray-300">
      {{ liveFeedActive ? 'Live updates' : isDemo ? 'Demo data' : hasHistory ? 'Historical data' : 'No market data' }}
    </div>
    <div ref="chartContainer" class="w-full h-full"></div>
  </div>
</template>

<script setup>
import { createChart, CandlestickSeries } from "lightweight-charts";
import { onMounted, ref, onUnmounted, watch } from "vue";
import api from "@/api";

const props = defineProps({
  symbol: { type: String, default: 'AAPL' }
});

const chartContainer = ref();
const isLoading = ref(true);
const chartError = ref('');
const isEmptyHistory = ref(false);
const hasHistory = ref(false);
const isDemo = ref(false);
let chartInstance = null;
let series = null;
let lastCandle = null;
let resizeObserver = null;
let abortController = null;
const liveFeedActive = ref(false);

const historyCache = new Map();

/**
 * Fetch Historical Data
 */
const fetchHistory = async () => {
  if (!series) return;

  if (abortController) abortController.abort();
  const requestController = new AbortController();
  abortController = requestController;
  const symbol = props.symbol;
  chartError.value = '';
  isEmptyHistory.value = false;

  if (historyCache.has(symbol)) {
    const cached = historyCache.get(symbol);
    series.setData(cached.candles);
    lastCandle = { ...cached.candles[cached.candles.length - 1] };
    hasHistory.value = cached.candles.length > 0;
    isDemo.value = cached.isDemo;
    chartInstance.timeScale().fitContent();
    isLoading.value = false;
  } else {
    isLoading.value = true;
  }

  try {
    const response = await api.get(`/market/candles`, {
      params: { symbol, interval: '1', limit: 150 },
      signal: requestController.signal
    });

    const candles = response.data?.candles;
    if (!Array.isArray(candles)) {
      throw new Error('Market history response did not include a candle list.');
    }

    const historyData = candles.map(d => ({
      time: d.time / 1000,
      open: d.open,
      high: d.high,
      low: d.low,
      close: d.close
    }));
    isDemo.value = Boolean(response.data?.is_demo);

    if (historyData.length > 0) {
      historyCache.set(symbol, { candles: historyData, isDemo: isDemo.value });
      series.setData(historyData);
      lastCandle = { ...historyData[historyData.length - 1] };
      hasHistory.value = true;
      chartInstance.timeScale().fitContent();
      chartInstance.priceScale('right').applyOptions({ autoScale: true });
    } else if (isDemo.value) {
      const demoData = createDemoCandles(symbol);
      historyCache.set(symbol, { candles: demoData, isDemo: true });
      series.setData(demoData);
      lastCandle = { ...demoData[demoData.length - 1] };
      hasHistory.value = true;
      chartInstance.timeScale().fitContent();
      chartInstance.priceScale('right').applyOptions({ autoScale: true });
    } else {
      series.setData([]);
      lastCandle = null;
      hasHistory.value = false;
      isDemo.value = false;
      isEmptyHistory.value = true;
    }
  } catch (e) {
    if (e.name !== 'CanceledError' && e.code !== 'ERR_CANCELED') {
      console.error("Failed to load chart history", e);
      isDemo.value = false;
      chartError.value = 'Check your connection and then try again.';
    }
  } finally {
    if (abortController === requestController) {
      isLoading.value = false;
    }
  }
};

const createDemoCandles = (symbol) => {
  let seed = [...symbol.toUpperCase()].reduce(
    (value, character) => (value * 31 + character.charCodeAt(0)) >>> 0,
    7
  );
  const random = () => {
    seed = (seed * 1664525 + 1013904223) >>> 0;
    return seed / 4294967296;
  };

  let previousClose = 40 + (seed % 360);
  const lastMinute = Math.floor(Date.now() / 60000) * 60;
  const candles = [];

  for (let index = 149; index >= 0; index -= 1) {
    const open = previousClose;
    const close = Math.max(1, open * (1 + (random() - 0.5) * 0.012));
    const wick = open * random() * 0.004;

    candles.push({
      time: lastMinute - index * 60,
      open,
      high: Math.max(open, close) + wick,
      low: Math.max(0.01, Math.min(open, close) - wick),
      close
    });

    previousClose = close;
  }

  return candles;
};

const setupEchoListener = () => {
  if (!window.Echo) {
    return;
  }

  window.Echo.channel("market-channel")
    .listen('MarketUpdated', (e) => {
      const updates = Array.isArray(e) ? e : [];

      updates.forEach(trade => {
        if (trade.s && trade.s.toUpperCase() === props.symbol.toUpperCase()) {
          const tradePrice = parseFloat(trade.p);
          if (!Number.isFinite(tradePrice) || tradePrice <= 0) return;

          const tradeTime = Math.floor((trade.t || Date.now()) / 1000);
          const candleTime = Math.floor(tradeTime / 60) * 60;

          if (isDemo.value) {
            series.setData([]);
            lastCandle = null;
            hasHistory.value = false;
            isDemo.value = false;
            chartInstance.timeScale().fitContent();
          }

          // Update existing candle or create new one
          if (lastCandle && lastCandle.time === candleTime) {
            lastCandle.close = tradePrice;
            lastCandle.high = Math.max(lastCandle.high, tradePrice);
            lastCandle.low = Math.min(lastCandle.low, tradePrice);
          } else {
            lastCandle = {
              time: candleTime,
              open: tradePrice,
              high: tradePrice,
              low: tradePrice,
              close: tradePrice
            };
          }

          if (series) {
            series.update(lastCandle);
            liveFeedActive.value = true;
          }
        }
      });
    });
};

onMounted(() => {
  if (!chartContainer.value) return;

  // Initialize Chart
  chartInstance = createChart(chartContainer.value, {
    layout: { background: { color: 'transparent' }, textColor: '#9CA3AF' },
    grid: { vertLines: { color: '#1f3348' }, horzLines: { color: '#1f3348' } },
    priceScale: {
      autoScale: true,          
      mode: 0,
      borderVisible: false,
    },
    timeScale: {
      borderColor: '#1f3348',
      timeVisible: true,
      rightOffset: 12,
      barSpacing: 6,
    },
  });

  series = chartInstance.addSeries(CandlestickSeries, {
    upColor: '#10B981', downColor: '#EF4444', borderVisible: false,
    wickUpColor: '#10B981', wickDownColor: '#EF4444',
    priceFormat: {
      type: 'price',
      precision: 4,
      minMove: 0.0001,
    },
  });

  fetchHistory();
  setupEchoListener();

  
  resizeObserver = new ResizeObserver(() => {
    if (chartInstance && chartContainer.value) {
      chartInstance.applyOptions({
        width: chartContainer.value.clientWidth,
        height: chartContainer.value.clientHeight,
      });
    }
  });
  resizeObserver.observe(chartContainer.value);
});

watch(() => props.symbol, () => {
  lastCandle = null;
  hasHistory.value = false;
  isEmptyHistory.value = false;
  isDemo.value = false;
  liveFeedActive.value = false;
  
  // Force price scale to reset
  if (chartInstance && series) {
    chartInstance.priceScale('right').applyOptions({
      autoScale: true,
      scaleMargins: { top: 0.1, bottom: 0.1 }
    });
  }
  
  fetchHistory();
});

onUnmounted(() => {
  window.Echo?.leave("market-channel");
  if (resizeObserver) resizeObserver.disconnect();
  if (chartInstance) chartInstance.remove();
  if (abortController) abortController.abort();
});
</script>