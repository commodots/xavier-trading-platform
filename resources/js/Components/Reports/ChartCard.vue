<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
    <h3 class="text-sm font-medium text-gray-300 mb-4">{{ title }}</h3>
    <div :id="chartId" style="min-height: 300px;"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  title: { type: String, required: true },
  chartId: { type: String, required: true },
  options: { type: Object, default: () => ({}) },
  series: { type: Array, default: () => [] },
  type: { type: String, default: 'line' },
  height: { type: Number, default: 300 },
});

import { computed } from 'vue';

const chartOptions = computed(() => ({
  chart: {
    id: props.chartId,
    toolbar: { show: false },
    foreColor: '#9CA3AF',
    parentHeightOffset: 0,
  },
  grid: {
    borderColor: '#1f3348',
    strokeDashArray: 3,
  },
  stroke: {
    curve: 'smooth',
    width: 2,
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.5,
      opacityTo: 0,
    },
  },
  dataLabels: { enabled: false },
  xaxis: {
    labels: { style: { colors: '#6B7280', fontSize: '11px' } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    labels: { style: { colors: '#6B7280', fontSize: '11px' } },
  },
  legend: {
    labels: { colors: '#D1D5DB' },
  },
  tooltip: {
    theme: 'dark',
  },
  ...props.options,
}));
</script>

<style scoped>
:deep(.apexcharts-text) {
  fill: #9CA3AF !important;
}
:deep(.apexcharts-legend-text) {
  color: #D1D5DB !important;
}
</style>