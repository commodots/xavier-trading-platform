<template>
  <div class="w-full h-[400px] rounded-lg overflow-hidden">
    <div ref="chart" class="w-full h-full"></div>
  </div>
</template>

<script setup>
import { createChart, CandlestickSeries } from "lightweight-charts";
import { onMounted, ref, onUnmounted, watch } from "vue";

const props = defineProps({
  symbol: { type: String, default: 'AAPL' }
});

const chart = ref();
let chartInstance = null;
let series = null;
let channel = null;

const baseData = (symbol) => [
  { time: Math.floor(Date.now() / 1000) - 300, open: 220, high: 225, low: 218, close: 224 },
  { time: Math.floor(Date.now() / 1000) - 240, open: 224, high: 228, low: 222, close: 226 },
  { time: Math.floor(Date.now() / 1000) - 180, open: 226, high: 230, low: 224, close: 228 },
  { time: Math.floor(Date.now() / 1000) - 120, open: 228, high: 232, low: 226, close: 230 },
  { time: Math.floor(Date.now() / 1000) - 60, open: 230, high: 235, low: 228, close: 233 },
];

const resetSeries = () => {
  if (series) {
    series.setData(baseData(props.symbol));
  }
};

onMounted(() => {
  if (!chart.value) return;

  chartInstance = createChart(chart.value, {
    layout: {
      background: { color: '#0F1724' },
      textColor: '#D1D5DB',
    },
    grid: {
      vertLines: { color: '#1F2937' },
      horzLines: { color: '#1F2937' },
    },
    crosshair: {
      mode: 1,
    },
    rightPriceScale: {
      borderColor: '#374151',
    },
    timeScale: {
      borderColor: '#374151',
      timeVisible: true,
    },
  });

  series = chartInstance.addSeries(CandlestickSeries, {
    upColor: '#10B981',
    downColor: '#EF4444',
    borderVisible: false,
    wickUpColor: '#10B981',
    wickDownColor: '#EF4444',
  });

  resetSeries();

  channel = window.Echo.channel("market-channel")
    .listen('.MarketUpdated', (e) => {
      if (e.data && Array.isArray(e.data)) {
        e.data.forEach(trade => {
          if (trade.p && trade.s === props.symbol) {
            const newCandle = {
              time: Math.floor(Date.now() / 1000),
              open: trade.p,
              high: trade.p + 0.5,
              low: trade.p - 0.5,
              close: trade.p,
            };
            series.update(newCandle);
          }
        });
      }
    });

  const resizeObserver = new ResizeObserver(() => {
    if (chartInstance) {
      chartInstance.applyOptions({
        width: chart.value.clientWidth,
        height: chart.value.clientHeight,
      });
    }
  });

  resizeObserver.observe(chart.value);
});

watch(() => props.symbol, () => {
  resetSeries();
});

onUnmounted(() => {
  if (channel) {
    window.Echo.leave("market-channel");
  }
  if (chartInstance) {
    chartInstance.remove();
  }
});
</script>
