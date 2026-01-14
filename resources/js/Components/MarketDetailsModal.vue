<template>
  <div v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 pt-20 overflow-y-auto bg-black/80 backdrop-blur-sm">
    <div class="bg-[#0F1724] border border-[#1f3348] w-full max-w-4xl rounded-2xl overflow-hidden shadow-2xl">
      <div class="p-6 border-b border-[#1f3348] flex justify-between items-start">
        <div>
          <div class="flex items-center gap-3 mb-1">
            <h2 class="text-2xl font-bold text-white">{{ item?.name }}</h2>
            <span class="text-sm px-2 py-0.5 bg-[#16213A] text-gray-400 rounded border border-[#1f3348]">{{ item?.symbol
            }}</span>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-gray-400">Current Market Price:</span>
            <span class="text-3xl font-mono text-[#00D4FF]">
              {{ currencySymbol }}{{ item?.price?.toLocaleString() }}
            </span>
            <span :class="item?.change >= 0 ? 'text-green-400' : 'text-red-400'" class="font-medium">
              {{ item?.change >= 0 ? '+' : '' }}{{ item?.change }}% (24h)
            </span>
          </div>
        </div>
        <button @click="$emit('close')" class="text-2xl text-gray-500 transition hover:text-white">&times;</button>
      </div>

      <div class="p-6">
        <div class="grid grid-cols-2 gap-4 mb-8 md:grid-cols-4">
          <div class="bg-[#16213A] p-4 rounded-xl border border-[#1f3348]">
            <p class="text-xs text-gray-500 uppercase">Market Cap</p>
            <p class="font-semibold text-gray-200">{{ item?.marketcap ? currencySymbol + item.marketcap.toLocaleString()
              : 'N/A' }}</p>
          </div>
          <div class="bg-[#16213A] p-4 rounded-xl border border-[#1f3348]">
            <p class="text-xs text-gray-500 uppercase">Volume (24h)</p>
            <p class="font-semibold text-gray-200">{{ item?.volume ? item.volume.toLocaleString() : 'N/A' }}</p>
          </div>
          <div class="bg-[#16213A] p-4 rounded-xl border border-[#1f3348]">
            <p class="text-xs text-gray-500 uppercase">All Time High</p>
            <p class="font-semibold text-gray-200">{{ currencySymbol }}{{ item?.price ? (item.price *
              1.2).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00' }}</p>
          </div>
          <div class="bg-[#16213A] p-4 rounded-xl border border-[#1f3348]">
            <p class="text-xs text-gray-500 uppercase">All Time Low</p>
            <p class="font-semibold text-gray-200">{{ currencySymbol }}{{ item?.price ? (item.price *
              0.7).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00' }}</p>
          </div>
        </div>

        <div class="bg-[#16213A]/50 p-4 rounded-xl border border-[#1f3348] relative">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-gray-300">Market Performance</h3>

            <div class="flex flex-wrap items-center gap-4">
              <span class="text-[10px] text-gray-500 uppercase">From</span>
              <div class="flex items-center gap-1 px-2 border border-[#1f3348] rounded py-2">
                <input v-model="startDate" type="date"
                  class="bg-transparent text-[10px] text-gray-300 outline-none border-none focus:ring-0 p-0 inverted-scheme" />
              </div>
              <span class="text-[10px] text-gray-500 uppercase">To</span>
              <div class="flex items-center gap-1 px-2 border border-[#1f3348] rounded py-2">
                <input v-model="endDate" type="date"
                  class="bg-transparent text-[10px] text-gray-300 outline-none border-none focus:ring-0 p-0 inverted-scheme" />
              </div>
              <button @click="applyCustomRange"
                class="text-[10px] bg-[#00D4FF]/20 text-[#00D4FF] px-3 py-2 rounded-md hover:bg-[#00D4FF]/30 transition font-bold">
                SEARCH
              </button>

              <div class="flex gap-2 p-1 bg-[#16213A] rounded-lg border border-[#1f3348]">
                <button v-for="range in ['1D', '1W', '1M', '90D', '1Y', 'ALL']" :key="range" @click="changeRange(range)"
                  :class="selectedRange === range ? 'bg-[#00D4FF] text-black font-bold' : 'text-gray-400 hover:text-white'"
                  class="text-[10px] px-3 py-1.5 rounded-md transition-all duration-200">
                  {{ range }}
                </button>
              </div>
              <div v-if="selectedRange === '1D'" class="flex gap-2 p-1 bg-[#16213A] rounded-lg border border-[#1f3348]">
  <button @click="setViewMode('hourly')"
    :class="viewMode === 'hourly' ? 'bg-[#00D4FF] text-black font-bold' : 'text-gray-400 hover:text-white'"
    class="text-[10px] px-3 py-1.5 rounded-md transition-all duration-200">
    Hourly
  </button>
  <button @click="setViewMode('minute')"
    :class="viewMode === 'minute' ? 'bg-[#00D4FF] text-black font-bold' : 'text-gray-400 hover:text-white'"
    class="text-[10px] px-3 py-1.5 rounded-md transition-all duration-200">
    Minute
  </button>
</div>
            </div>
          </div>
          <div v-if="isLoading && historyData.length === 0"
            class="absolute inset-0 flex items-center justify-center bg-[#16213A]/80 rounded-xl z-10">
            <div class="flex flex-col items-center gap-2">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00D4FF]"></div>
              <p class="text-[#00D4FF] text-sm">Loading Market Data...</p>
            </div>
          </div>
          <apexchart type="area" height="300" :options="chartOptions" :series="series" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import VueApexCharts from "vue3-apexcharts";
import api from "@/api";

const props = defineProps({
  isOpen: Boolean,
  item: Object,
  currencySymbol: String
});

const emit = defineEmits(['close', 'timeRangeChange']);

const selectedRange = ref('1D');
const startDate = ref('');
const endDate = ref('');
const historyData = ref([]);
const isLoading = ref(false);
const chartLabels = ref([]);
const liveUpdateInterval = ref(null);
const viewMode = ref('hourly');

const fetchMarketHistory = async () => {
  if (!props.item?.symbol) return;

  isLoading.value = true;
  try {
    const params = {
      market: props.item.market?.toLowerCase() || 'ngx'
    };

    if (selectedRange.value === 'CUSTOM' && startDate.value && endDate.value) {
      params.start = startDate.value;
      params.end = endDate.value;
      params.range = 'custom';
    } else {
      params.range = selectedRange.value.toLowerCase();
    }

    if (selectedRange.value === '1D') {
      params.view = viewMode.value;
    }

    const res = await api.get(`/markets/stocks/${props.item.symbol}/history`, {
      params: { ...params, t: Date.now() }
    });

    if (res.data?.success && res.data.data?.data) {
      const chartData = res.data.data.data;
      historyData.value = chartData.map(candle => candle.close);
      chartLabels.value = chartData.map(candle => {
        const d = new Date(candle.date);
        if (selectedRange.value === '1D') {
          return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });
        } else if (selectedRange.value === '1Y') {
          return d.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
        } else if (selectedRange.value === 'ALL') {
          return d.toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
        } else if (selectedRange.value === 'CUSTOM') {
          return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        } else {
          return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
        }
      });
    }
  } catch (error) {
    console.error("Failed to fetch market history", error);
  } finally {
    isLoading.value = false;
  }
};

const fetchLiveData = async () => {
  // Logic Fix: Only fetch if range is 1D, minute view, and modal is open
  if (!props.item?.symbol || !props.isOpen || selectedRange.value !== '1D' || viewMode.value !== 'minute') {
    stopLiveUpdates();
    return;
  }

  try {
    const res = await api.get(`/dummy/ngx/market/${props.item.symbol}`);
    if (res.data) {
      const newPrice = res.data.bid;
      const currentTime = new Date();

      if (props.item) {
        props.item.price = newPrice;
        props.item.volume = res.data.volume;
      }

      historyData.value.push(newPrice);
      chartLabels.value.push(currentTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }));

      if (historyData.value.length > 24) {
        historyData.value.shift();
        chartLabels.value.shift();
      }
    }
  } catch (error) {
    console.error("Failed to fetch live data", error);
  }
};

const startLiveUpdates = () => {
  stopLiveUpdates();
  // Only start the interval if we are on the 1D minute view
  if (selectedRange.value === '1D' && viewMode.value === 'minute') {
    liveUpdateInterval.value = setInterval(fetchLiveData, 5000);
  }
};

const stopLiveUpdates = () => {
  if (liveUpdateInterval.value) {
    clearInterval(liveUpdateInterval.value);
    liveUpdateInterval.value = null;
  }
};

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    fetchMarketHistory();
    startLiveUpdates();
  } else {
    stopLiveUpdates();
  }
});

onMounted(() => {
  if (props.isOpen) {
    fetchMarketHistory();
    startLiveUpdates();
  }
});

onUnmounted(() => {
  stopLiveUpdates();
});

const changeRange = (range) => {
  selectedRange.value = range;
  if (range === '1D') {
    viewMode.value = 'hourly';
  }
  fetchMarketHistory();

  // Logic Fix: Toggle polling based on range selection
  if (range === '1D' && viewMode.value === 'minute') {
    startLiveUpdates();
  } else {
    stopLiveUpdates();
  }

  emit('timeRangeChange', { symbol: props.item?.symbol, range: range });
};

const applyCustomRange = () => {
  if (startDate.value && endDate.value) {
    selectedRange.value = 'CUSTOM';
    stopLiveUpdates(); // Custom range is never "live"
    fetchMarketHistory();
    emit('timeRangeChange', {
      symbol: props.item?.symbol,
      range: 'CUSTOM',
      start: startDate.value,
      end: endDate.value
    });
  }
};

const setViewMode = (mode) => {
  viewMode.value = mode;
  fetchMarketHistory();
  if (mode === 'minute') {
    startLiveUpdates();
  } else {
    stopLiveUpdates();
  }
};

const series = computed(() => [{
  name: props.item?.name || 'Asset',
  data: [...historyData.value]
}]);

const chartOptions = computed(() => ({
  chart: { 
    toolbar: { show: false }, 
    zoom: { enabled: false }, 
    background: 'transparent',
    animations: {
      enabled: true,
      easing: 'linear',
      dynamicAnimation: {
        speed: 800 
      }
    }
  },
  colors: ['#00D4FF'],
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
  },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 3 },
  grid: { borderColor: '#1f3348', strokeDashArray: 4 },
  xaxis: {
    categories: chartLabels.value,
    labels: { 
      style: { colors: '#64748b' },
      hideOverlappingLabels: true 
    },
    axisBorder: { show: false },
    axisTicks: { show: false }
  },
  yaxis: {
    labels: { 
      style: { colors: '#64748b' },
      formatter: (val) => props.currencySymbol + val.toLocaleString()
    },
    forceNiceScale: true,
    decimalsInFloat: 2
  },
  tooltip: { theme: 'dark' },
}));

const apexchart = VueApexCharts;
</script>

<style scoped>
.inverted-scheme::-webkit-calendar-picker-indicator {
  filter: invert(1);
  cursor: pointer;
}
</style>