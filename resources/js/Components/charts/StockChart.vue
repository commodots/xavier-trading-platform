<template>
  <div class="bg-[#0F172A] p-5 rounded-xl border border-[#1F2A44] space-y-4">

    <!-- Header -->
    <div class="flex justify-between items-center">
      <div>
        <h3 class="font-semibold text-lg">{{ symbol }} Chart</h3>
        <p class="text-xs text-gray-400">Historical price data</p>
      </div>

      <div class="flex items-center gap-3">

        <!-- Chart type -->
        <div class="flex rounded bg-gray-800 overflow-hidden">
          <button
            v-for="t in ['line', 'candlestick']"
            :key="t"
            @click="chartType = t"
            class="px-3 py-1 text-xs"
            :class="chartType === t ? 'bg-blue-600 text-white' : 'text-gray-300'"
          >
            {{ t === 'line' ? 'Line' : 'Candle' }}
          </button>
        </div>

        <!-- Timeframe -->
        <div class="flex gap-1">
          <button
            v-for="tf in ['1D','1W','1M']"
            :key="tf"
            @click="timeframe = tf"
            class="px-3 py-1 text-xs rounded"
            :class="timeframe === tf ? 'bg-gray-700 text-white' : 'bg-gray-800 text-gray-400'"
          >
            {{ tf }}
          </button>
        </div>

      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-16 text-center text-gray-400">
      Loading chart…
    </div>

    <!-- Chart -->
    <apexchart
      v-else
      height="340"
      :type="chartType === 'line' ? 'line' : 'candlestick'"
      :options="chartOptions"
      :series="series"
    />
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from "vue";
import axios from "@/lib/axios";

/* ---------- PROPS ---------- */
const props = defineProps({
  symbol: { type: String, required: true },
  market: { type: String, default: "ngx" }
});

/* ---------- STATE ---------- */
const loading = ref(false);
const chartType = ref("line");
const timeframe = ref("1D");
const candles = ref([]);
const series = ref([]);

/* ---------- CHART OPTIONS ---------- */
const chartOptions = ref({
  chart: {
    toolbar: { show: false },
    zoom: { enabled: false },
    background: "transparent"
  },
  theme: { mode: "dark" },
  xaxis: { type: "category" },
  yaxis: { tooltip: { enabled: true } },
  tooltip: { theme: "dark" },
  stroke: { curve: "smooth", width: 2 },
  plotOptions: {
    candlestick: {
      wick: { useFillColor: true }
    }
  }
});

/* ---------- LOAD DATA ---------- */
const fetchCandles = async () => {
  loading.value = true;

  try {
    const res = await axios.get("/markets/stocks/history", {
      params: {
        symbol: props.symbol,
        market: props.market,
        timeframe: timeframe.value
      }
    });

    candles.value = res.data.data;
    buildSeries();

  } catch (e) {
    console.error("Chart error", e);
  } finally {
    loading.value = false;
  }
};

/* ---------- BUILD SERIES ---------- */
const buildSeries = () => {
  if (!candles.value.length) return;

  if (chartType.value === "line") {
    series.value = [
      {
        name: props.symbol,
        data: candles.value.map(c => c.close)
      }
    ];

    chartOptions.value.xaxis.categories =
      candles.value.map(c => c.date);
  }

  if (chartType.value === "candlestick") {
    series.value = [
      {
        data: candles.value.map(c => ({
          x: c.date,
          y: [c.open, c.high, c.low, c.close]
        }))
      }
    ];
  }
};

/* ---------- WATCHERS ---------- */
watch([chartType, timeframe, () => props.symbol], () => {
  fetchCandles();
});

/* ---------- INIT ---------- */
onMounted(fetchCandles);
</script
