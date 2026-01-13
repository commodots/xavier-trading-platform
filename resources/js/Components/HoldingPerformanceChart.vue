<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
      <div>
        <h2 class="text-sm font-semibold tracking-wider text-gray-400 uppercase">{{ title }} Portfolio Performance</h2>
        <div class="text-2xl font-bold text-white">
          {{ currencySymbol }}{{ totalValue.toLocaleString() }}
          <span :class="isPositive ? 'text-green-400' : 'text-red-400'" class="ml-2 text-sm font-medium">
            {{ isPositive ? '▲' : '▼' }} {{ percentageChange }}%
          </span>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-4">
          <span class="text-[10px] text-gray-500 uppercase">From</span>
          <div class="flex items-center gap-1 px-2 border-[#1f3348] border rounded py-2">
            <input v-model="startDate" type="date"
              class="bg-transparent text-[10px] text-gray-300 outline-none border-none focus:ring-0 p-0 inverted-scheme" />
          </div>
          <span class="text-[10px] text-gray-500 uppercase">To</span>
          <div class="flex items-center gap-1 px-2 border-[#1f3348] border rounded py-2">
            <input v-model="endDate" type="date"
              class="bg-transparent text-[10px] text-gray-300 outline-none border-none focus:ring-0 p-0 inverted-scheme" />
          </div>
          <button @click="applyCustomRange"
            class="text-[10px] bg-[#00D4FF]/20 text-[#00D4FF] px-3 py-2 rounded-md hover:bg-[#00D4FF]/30 transition font-bold">
            SEARCH
          </button>

        <div class="flex gap-2 p-1 bg-[#16213A] rounded-lg border border-[#1f3348]">
          <button v-for="range in ['1D', '1W', '1M', '1Y', 'ALL']" :key="range" @click="changeRange(range)"
            :class="selectedRange === range ? 'bg-[#00D4FF] text-black font-bold' : 'text-gray-400 hover:text-white'"
            class="text-[10px] px-3 py-1.5 rounded-md transition-all duration-200">
            {{ range }}
          </button>
        </div>
      </div>
    </div>

    <div class="h-[180px] -mx-4 relative">
      <apexchart type="line" height="100%" :options="chartOptions" :series="series" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import VueApexCharts from "vue3-apexcharts";

const apexchart = VueApexCharts;
const emit = defineEmits(['rangeChange']);

const props = defineProps({
  title: String,
  currencySymbol: String,
  seriesData: Array,
  totalValue: Number,
  percentageChange: Number,
  loading: Boolean
});

const selectedRange = ref('1W');
const startDate = ref("");
const endDate = ref("");
const liveSeriesData = ref([]);
const progressionInterval = ref(null);

const startProgression = () => {
  stopProgression(); // Clear any existing interval

  progressionInterval.value = setInterval(() => {
    // Add new data points to each series to show progression
    liveSeriesData.value = liveSeriesData.value.map(series => ({
      ...series,
      data: [...series.data, {
        x: Date.now(),
        y: series.data[series.data.length - 1]?.y * (1 + (Math.random() - 0.5) * 0.02) // Small random change
      }]
    }));

    // Keep only last 50 data points to prevent memory issues
    liveSeriesData.value = liveSeriesData.value.map(series => ({
      ...series,
      data: series.data.slice(-50)
    }));
  }, 10000); // Add new point every 10 seconds
};

const stopProgression = () => {
  if (progressionInterval.value) {
    clearInterval(progressionInterval.value);
    progressionInterval.value = null;
  }
};

const changeRange = (range) => {
  selectedRange.value = range;
  // Clear custom dates when a preset is selected
  startDate.value = "";
  endDate.value = "";
  stopProgression();
  emit('rangeChange', range);
};

const applyCustomRange = () => {
  if (startDate.value && endDate.value) {
    selectedRange.value = 'CUSTOM';
    stopProgression();
    emit('rangeChange', {
      start: startDate.value,
      end: endDate.value
    });
  }
};

const isPositive = computed(() => props.percentageChange >= 0);
const series = computed(() => props.seriesData);

const chartOptions = computed(() => ({
  chart: {
    type: 'line',
    toolbar: { show: false },
    zoom: { enabled: false }
  },
  stroke: { curve: 'smooth', width: 3 },
  xaxis: {
    type: 'datetime',
    labels: {
      style: { colors: '#9ca3af', fontSize: '10px' }
    }
  },
  yaxis: {
    labels: {
      style: { colors: '#9ca3af', fontSize: '10px' },
      formatter: (val) => props.currencySymbol + val.toLocaleString()
    }
  },
  legend: {
    show: true,
    position: 'top',
    horizontalAlign: 'right',
    labels: { colors: '#9ca3af' }
  },
  grid: {
    borderColor: '#1f3348',
    strokeDashArray: 4
  },
  tooltip: { theme: 'dark' },
  colors: ['#00D4FF', '#FFFFFF', '#10b981', '#f59e0b', '#ef4444']
}));
</script>

<style scoped>
/* Ensures the date picker icon and text are visible on dark backgrounds in some browsers */
.inverted-scheme::-webkit-calendar-picker-indicator {
  filter: invert(1);
  cursor: pointer;
}
</style>

<script>
import VueApexCharts from "vue3-apexcharts";
export default { components: { apexchart: VueApexCharts } };
</script>