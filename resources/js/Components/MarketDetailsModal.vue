<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 pt-20 overflow-y-auto bg-black/80 backdrop-blur-sm">
    <div class="bg-[#0F1724] border border-[#1f3348] w-full max-w-4xl rounded-2xl overflow-hidden shadow-2xl">
      <div class="p-6 border-b border-[#1f3348] flex justify-between items-start">
        <div>
          <div class="flex items-center gap-3 mb-1">
            <h2 class="text-2xl font-bold text-white">{{ item.name }}</h2>
            <span class="text-sm px-2 py-0.5 bg-[#16213A] text-gray-400 rounded border border-[#1f3348]">{{ item.symbol }}</span>
          </div>
          <div class="flex items-center gap-4">
            Current Market Price:
            <span class="text-3xl font-mono text-[#00D4FF]">
               {{ currencySymbol }}{{ item.price.toLocaleString() }}
            </span>
            <span :class="item.change >= 0 ? 'text-green-400' : 'text-red-400'" class="font-medium">
              {{ item.change >= 0 ? '+' : '' }}{{ item.change }}% (24h)
            </span>
          </div>
        </div>
        <button @click="$emit('close')" class="text-2xl text-gray-500 transition hover:text-white">&times;</button>
      </div>

      <div class="p-6">
        <div class="grid grid-cols-2 gap-4 mb-8 md:grid-cols-4">
          <div class="bg-[#16213A] p-4 rounded-xl border border-[#1f3348]">
            <p class="text-xs text-gray-500 uppercase">Market Cap</p>
            <p class="font-semibold text-gray-200">{{ item.marketcap ? currencySymbol + item.marketcap.toLocaleString() : 'N/A' }}</p>
          </div>
          <div class="bg-[#16213A] p-4 rounded-xl border border-[#1f3348]">
            <p class="text-xs text-gray-500 uppercase">Volume (24h)</p>
            <p class="font-semibold text-gray-200">{{ item.volume ? item.volume.toLocaleString() : 'N/A' }}</p>
          </div>
          <div class="bg-[#16213A] p-4 rounded-xl border border-[#1f3348]">
            <p class="text-xs text-gray-500 uppercase">All Time High</p>
            <p class="font-semibold text-gray-200">{{ currencySymbol }}{{ (item.price * 1.2).toLocaleString() }}</p>
          </div>
          <div class="bg-[#16213A] p-4 rounded-xl border border-[#1f3348]">
            <p class="text-xs text-gray-500 uppercase">All Time Low</p>
            <p class="font-semibold text-gray-200">{{ currencySymbol }}{{ (item.price * 0.7).toLocaleString() }}</p>
          </div>
        </div>

        <div class="bg-[#16213A]/50 p-4 rounded-xl border border-[#1f3348]">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-gray-300">Market Performance</h3>
            <div class="flex gap-2">
              <button 
                v-for="t in ['1D', '1W', '1M', '1Y']" 
                :key="t" 
                @click="changeTimeRange(t)"
                :class="selectedTime === t ? 'bg-[#00D4FF] text-black border-[#00D4FF]' : 'bg-[#0F1724] border-[#1f3348] text-gray-400'"
                class="text-[10px] px-2 py-1 rounded border hover:text-[#00D4FF] hover:border-[#00D4FF] transition"
              >
                {{ t }}
              </button>
            </div>
          </div>
          <apexchart
            type="area"
            height="300"
            :options="chartOptions"
            :series="series"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import VueApexCharts from "vue3-apexcharts";

const props = defineProps({
  isOpen: Boolean,
  item: Object,
  currencySymbol: String
});

const emit = defineEmits(['close', 'timeRangeChange']);

const selectedTime = ref('1M');

const changeTimeRange = (range) => {
  selectedTime.value = range;
  emit('timeRangeChange', { symbol: props.item.symbol, range: range });
};

// Placeholder for historical data 
const series = computed(() => [{
  name: props.item?.name,
  data: [31, 40, 28, 51, 42, 109, 100, 120, 80, 95, 110, props.item?.price || 0]
}]);

const chartOptions = {
  chart: { toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
  colors: ['#00D4FF'],
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
  },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 3 },
  grid: { borderColor: '#1f3348', strokeDashArray: 4 },
  xaxis: {
    categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    labels: { style: { colors: '#64748b' } },
    axisBorder: { show: false },
    axisTicks: { show: false }
  },
  yaxis: { labels: { style: { colors: '#64748b' } } },
  tooltip: { theme: 'dark' }
};

const apexchart = VueApexCharts;
</script>