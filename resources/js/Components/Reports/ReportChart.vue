<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
    <div v-if="title" class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-medium text-white">{{ title }}</h3>
    </div>
    <div v-if="!hasData" class="flex items-center justify-center h-64 text-gray-500">
      <EmptyState message="No chart data available" />
    </div>
    <div v-else ref="chartContainer" class="w-full" :style="{ height: height + 'px' }"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick, computed } from 'vue';
import EmptyState from './EmptyState.vue';

const props = defineProps({
  title: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'line',
  },
  categories: {
    type: Array,
    default: () => [],
  },
  series: {
    type: Array,
    default: () => [],
  },
  height: {
    type: Number,
    default: 300,
  },
  colors: {
    type: Array,
    default: () => ['#0047AB', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'],
  },
});

const chartContainer = ref(null);
let chartInstance = null;

const hasData = computed(() => {
  return props.categories.length > 0 && props.series.length > 0;
});

const initChart = async () => {
  if (!chartContainer.value || !hasData.value) return;

  await nextTick();

  try {
    const ApexCharts = (await import('apexcharts')).default;

    const options = {
      chart: {
        type: props.type,
        height: props.height,
        toolbar: { show: false },
        foreColor: '#9CA3AF',
        background: 'transparent',
      },
      colors: props.colors,
      series: props.series,
      xaxis: {
        categories: props.categories,
        labels: { style: { colors: '#9CA3AF' } },
        axisBorder: { color: '#1f3348' },
        axisTicks: { color: '#1f3348' },
      },
      yaxis: {
        labels: {
          style: { colors: '#9CA3AF' },
          formatter: (val) => {
            if (val >= 1000000) return '$' + (val / 1000000).toFixed(1) + 'M';
            if (val >= 1000) return '$' + (val / 1000).toFixed(1) + 'K';
            return '$' + val.toFixed(0);
          },
        },
      },
      grid: {
        borderColor: '#1f3348',
        strokeDashArray: 3,
      },
      tooltip: {
        theme: 'dark',
        y: {
          formatter: (val) => '$' + Number(val).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }),
        },
      },
      legend: {
        labels: { colors: '#9CA3AF' },
      },
      stroke: {
        curve: 'smooth',
        width: 2,
      },
      fill: {
        type: 'gradient',
        gradient: {
          shade: 'dark',
          type: 'vertical',
          shadeIntensity: 0.3,
          opacityFrom: 0.4,
          opacityTo: 0.1,
        },
      },
      dataLabels: {
        enabled: false,
      },
    };

    if (props.type === 'bar') {
      options.plotOptions = {
        bar: {
          borderRadius: 4,
          columnWidth: '60%',
        },
      };
    }

    if (props.type === 'pie' || props.type === 'donut') {
      options.labels = props.categories;
      options.xaxis = undefined;
    }

    if (chartInstance) {
      chartInstance.destroy();
    }

    chartInstance = new ApexCharts(chartContainer.value, options);
    await chartInstance.render();
  } catch (e) {
    console.error('Failed to load chart:', e);
  }
};

watch(
  () => [props.categories, props.series],
  () => {
    if (chartInstance) {
      chartInstance.updateOptions({
        series: props.series,
        xaxis: { categories: props.categories },
      });
    } else {
      initChart();
    }
  },
  { deep: true }
);

onMounted(initChart);
</script>