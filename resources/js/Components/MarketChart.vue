<template>
  <div class="w-full h-[400px] rounded-lg overflow-hidden bg-[#0F1724]">
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
let lastCandle = null;
let resizeObserver = null;

// Helper to get historical-looking data so the chart isn't empty
const baseData = () => {
  const data = [];
  const now = Math.floor(Date.now() / 1000);
  for (let i = 20; i > 0; i--) {
    data.push({
      time: (now - (i * 60)) - (now % 60), // aligned to minutes
      open: 220 + i,
      high: 225 + i,
      low: 218 + i,
      close: 224 + i
    });
  }
  return data;
};

const resetSeries = () => {
  if (series) {
    const data = baseData();
    series.setData(data);
    lastCandle = { ...data[data.length - 1] };
  }
};

onMounted(() => {
  if (!chart.value) return;

  chartInstance = createChart(chart.value, {
    layout: {
      background: { color: 'transparent' },
      textColor: '#9CA3AF',
    },
    grid: {
      vertLines: { color: '#1f3348' },
      horzLines: { color: '#1f3348' },
    },
    timeScale: {
      borderColor: '#1f3348',
      timeVisible: true,
      secondsVisible: false,
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

  // Subscribing to the channel
  channel = window.Echo.channel("market-channel")
    .listen('MarketUpdated', (e) => { // Removed the extra dot
      const updates = Array.isArray(e) ? e : (e.data || []);
      
      updates.forEach(trade => {
        // Ensure symbols match regardless of casing
        if (trade.s && trade.s.toUpperCase() === props.symbol.toUpperCase()) {
          
          const tradePrice = parseFloat(trade.p);
          const tradeTime = Math.floor((trade.t || Date.now()) / 1000);
          const candleTime = Math.floor(tradeTime / 60) * 60;

          if (lastCandle && lastCandle.time === candleTime) {
            // Update the current minute's candle
            lastCandle.close = tradePrice;
            lastCandle.high = Math.max(lastCandle.high, tradePrice);
            lastCandle.low = Math.min(lastCandle.low, tradePrice);
          } else {
            // It's a new minute! Start a new candle
            lastCandle = {
              time: candleTime,
              open: tradePrice,
              high: tradePrice,
              low: tradePrice,
              close: tradePrice,
            };
          }

          // Directly update the series - lightweight charts handles the "motion"
          series.update(lastCandle);
        }
      });
    });

  // Handle resizing
  resizeObserver = new ResizeObserver(() => {
    if (chartInstance && chart.value) {
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
  if (channel) window.Echo.leave("market-channel");
  if (resizeObserver) resizeObserver.disconnect();
  if (chartInstance) chartInstance.remove();
});
</script>