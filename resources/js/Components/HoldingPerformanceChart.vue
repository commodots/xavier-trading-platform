<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-sm font-semibold tracking-wider text-gray-400 uppercase">{{ title }} Portfolio Performance</h2>
        <div class="text-2xl font-bold text-white">
          {{ currencySymbol }}{{ totalValue.toLocaleString() }}
          <span :class="isPositive ? 'text-green-400' : 'text-red-400'" class="ml-2 text-sm font-medium">
            {{ isPositive ? '▲' : '▼' }} {{ percentageChange }}%
          </span>
        </div>
      </div>

      <div class="flex gap-2 p-1 bg-[#16213A] rounded-lg border border-[#1f3348]">
        <button 
          v-for="range in ['1D', '1W', '1M', '1Y', 'ALL']" 
          :key="range" 
          @click="changeRange(range)"
          :class="selectedRange === range ? 'bg-[#00D4FF] text-black font-bold' : 'text-gray-400 hover:text-white'"
          class="text-[10px] px-3 py-1.5 rounded-md transition-all duration-200"
        >
          {{ range }}
        </button>
      </div>
    </div>

    <div class="h-[180px] -mx-4 relative">
      <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-[#0F1724]/50 z-10">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00D4FF]"></div>
      </div>
      <apexchart type="line" height="100%" :options="chartOptions" :series="series" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
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

const changeRange = (range) => {
  selectedRange.value = range;
  emit('rangeChange', range); 
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
  xaxis: { type: 'datetime' },
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

<script>
import VueApexCharts from "vue3-apexcharts";
export default { components: { apexchart: VueApexCharts } };
</script>