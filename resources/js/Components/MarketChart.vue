<template>
  <div class="w-full h-[400px] rounded-lg overflow-hidden bg-[#0F1724] relative">
    <div v-if="isLoading" class="absolute inset-0 z-10 flex items-center justify-center bg-[#0F1724]/80 text-[#00D4FF] text-xs font-bold uppercase tracking-widest">
      Syncing Live Market...
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
let chartInstance = null;
let series = null;
let lastCandle = null;
let resizeObserver = null;
let abortController = null;

// In-memory cache for instant symbol switching
const historyCache = new Map();

/**
 Fetch REAL historical candles so the live "tick" 
 * matches the previous price action.
 */
const fetchHistory = async () => {
  if (!series) {
    return;
  }

  if (abortController) {
    abortController.abort();
  }
  abortController = new AbortController();

  // Check cache for instant visual feedback
  if (historyCache.has(props.symbol)) {
    const cachedData = historyCache.get(props.symbol);
    series.setData(cachedData);
    lastCandle = { ...cachedData[cachedData.length - 1] };
    chartInstance.timeScale().fitContent();
    isLoading.value = false;
  } else {
    isLoading.value = true;
  }

  try {
    const response = await api.get(`/market/candles`, {
      params: {
        symbol: props.symbol,
        interval: '1',
        limit: 150 // Optimize payload size for faster transfer
      },
      signal: abortController.signal
    });

    const historyData = response.data.candles.map(d => ({
      time: d.time / 1000,
      open: d.open,
      high: d.high,
      low: d.low,
      close: d.close
    }));

    if (historyData.length > 0) {
      historyCache.set(props.symbol, historyData);
      series.setData(historyData);
      lastCandle = { ...historyData[historyData.length - 1] };
      chartInstance.timeScale().fitContent();
    }
  } catch (e) {
    if (e.name !== 'CanceledError') {
      console.error("Failed to load chart history", e);
    }
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  if (!chartContainer.value) {
    return;
  }

  chartInstance = createChart(chartContainer.value, {
    layout: {
      background: { color: 'transparent' },
      textColor: '#9CA3AF',
    },
    grid: {
      vertLines: { color: '#1f3348' },
      horzLines: { color: '#1f3348' },
    },
    crosshair: {
      mode: 0, // Normal mode for tracking
    },
    timeScale: {
      borderColor: '#1f3348',
      timeVisible: true,
      secondsVisible: false,
      tickMarkFormatter: (time) => {
        const date = new Date(time * 1000);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      },
    },
  });

  series = chartInstance.addSeries(CandlestickSeries, {
    upColor: '#10B981',
    downColor: '#EF4444',
    borderVisible: false,
    wickUpColor: '#10B981',
    wickDownColor: '#EF4444',
  });

  fetchHistory();

  // The Live "Motion" Listener
  window.Echo.channel("market-channel")
    .listen('MarketUpdated', (e) => {
      const updates = Array.isArray(e) ? e : (e.data || []);

      updates.forEach(trade => {
        if (trade.s && trade.s.toUpperCase() === props.symbol.toUpperCase()) {

          const tradePrice = parseFloat(trade.p);
          const tradeTime = Math.floor((trade.t || Date.now()) / 1000);
          const candleTime = Math.floor(tradeTime / 60) * 60;

          if (lastCandle && lastCandle.time === candleTime) {
            // "Tick" logic: Update the CURRENT candle
            lastCandle.close = tradePrice;
            lastCandle.high = Math.max(lastCandle.high, tradePrice);
            lastCandle.low = Math.min(lastCandle.low, tradePrice);
          } else {
            // "New Bar" logic: Start a fresh minute
            lastCandle = {
              time: candleTime,
              open: tradePrice,
              high: tradePrice,
              low: tradePrice,
              close: tradePrice,
            };
          }

          
          series.update(lastCandle);
        }
      });
    });

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

// Refresh chart when user changes the stock symbol
watch(() => props.symbol, () => {
  lastCandle = null; // Clear state for new symbol
  fetchHistory();
});

onUnmounted(() => {
  window.Echo.leave("market-channel");
  if (resizeObserver) {
    resizeObserver.disconnect();
  }
  if (chartInstance) {
    chartInstance.remove();
  }
  if (abortController) {
    abortController.abort();
  }
});
</script>