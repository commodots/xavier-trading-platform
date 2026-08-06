<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
    <div v-if="title || subtitle" class="mb-4 flex items-start justify-between gap-3">
      <div>
        <h3 v-if="title" class="text-sm font-semibold text-white">{{ title }}</h3>
        <p v-if="subtitle" class="mt-1 text-xs leading-5 text-gray-400">{{ subtitle }}</p>
      </div>
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
  subtitle: {
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

// Sanitize series data - filter out empty or invalid series
const normalizeSeries = (rawSeries) => {
  if (!Array.isArray(rawSeries)) {
    return [];
  }

  const normalizeValue = (value) => {
    if (typeof value === 'number') {
      return value;
    }
    if (typeof value === 'string' && value.trim() !== '') {
      const parsed = Number(value);
      return Number.isNaN(parsed) ? null : parsed;
    }
    return null;
  };

  if (props.type === 'pie' || props.type === 'donut') {
    return rawSeries
      .map((item) => {
        if (typeof item === 'number' || typeof item === 'string') {
          return normalizeValue(item);
        }

        if (item && typeof item === 'object') {
          if (Array.isArray(item.data)) {
            return normalizeValue(item.data[0]);
          }
          if (typeof item.y === 'number' || typeof item.y === 'string') {
            return normalizeValue(item.y);
          }
          if (typeof item.value === 'number' || typeof item.value === 'string') {
            return normalizeValue(item.value);
          }
          if (Array.isArray(item)) {
            return normalizeValue(item[0]);
          }
        }

        return null;
      })
      .filter((value) => value !== null);
  }

  return rawSeries
    .map((item) => {
      if (typeof item === 'number' || typeof item === 'string') {
        const normalized = normalizeValue(item);
        return { data: normalized === null ? [] : [normalized] };
      }
      if (Array.isArray(item)) {
        return { data: item };
      }
      if (item && typeof item === 'object') {
        if (Array.isArray(item.data)) {
          return item;
        }
        if (typeof item.data === 'number' || typeof item.data === 'string') {
          return { ...item, data: [normalizeValue(item.data)] };
        }
        if (typeof item.y === 'number' || typeof item.y === 'string') {
          return { name: item.name ?? 'Series', data: [normalizeValue(item.y)] };
        }
      }

      return null;
    })
    .filter((item) => item && Array.isArray(item.data));
};

const cleanSeries = computed(() => normalizeSeries(props.series));

const hasData = computed(() => {
  return props.categories.length > 0 && cleanSeries.value.length > 0;
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
      series: cleanSeries.value,
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
        series: cleanSeries.value,
        xaxis: { categories: props.categories },
      });
    } else if (hasData.value) {
      initChart();
    }
  },
  { deep: true }
);

watch(hasData, (newValue) => {
  if (newValue && !chartInstance) {
    initChart();
  }
});

onMounted(() => {
  if (hasData.value) {
    initChart();
  }
});
</script>