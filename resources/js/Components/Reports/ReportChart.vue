<template>
  <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
    <div v-if="title || subtitle" class="flex items-start justify-between gap-3 mb-4">
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
    default: () => ['#3B82F6', '#22C55E', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#F97316', '#84CC16'],
  },
  currencySymbol: {
    type: String,
    default: '₦',
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

  if (props.type === 'pie' || props.type === 'donut' || props.type === 'doughnut') {
    // For pie/donut charts, extract all numeric values from the series
    const values = [];
    
    for (const item of rawSeries) {
      if (typeof item === 'number' || typeof item === 'string') {
        const normalized = normalizeValue(item);
        if (normalized !== null) values.push(normalized);
      } else if (item && typeof item === 'object') {
        if (Array.isArray(item.data)) {
          // Extract all values from the data array
          item.data.forEach(val => {
            const normalized = normalizeValue(val);
            if (normalized !== null) values.push(normalized);
          });
        } else if (typeof item.y === 'number' || typeof item.y === 'string') {
          const normalized = normalizeValue(item.y);
          if (normalized !== null) values.push(normalized);
        } else if (typeof item.value === 'number' || typeof item.value === 'string') {
          const normalized = normalizeValue(item.value);
          if (normalized !== null) values.push(normalized);
        } else if (Array.isArray(item)) {
          item.forEach(val => {
            const normalized = normalizeValue(val);
            if (normalized !== null) values.push(normalized);
          });
        }
      }
    }
    
    return values;
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

    // Normalize chart type aliases (ApexCharts uses "donut", Chart.js uses "doughnut")
    const chartType = props.type === 'doughnut' ? 'donut' : props.type;

    const options = {
      chart: {
        type: chartType,
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
            if (val >= 1000000) return props.currencySymbol + (val / 1000000).toFixed(1) + 'M';
            if (val >= 1000) return props.currencySymbol + (val / 1000).toFixed(1) + 'K';
            return props.currencySymbol + val.toFixed(0);
          },
        },
      },
      grid: {
        borderColor: '#1f3348',
        strokeDashArray: 3,
      },
      tooltip: {
        theme: 'dark',
        x: { show: false },
        y: {
          formatter: (val) => props.currencySymbol + Number(val).toLocaleString('en-US', {
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
        width: 3,
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
          colors: {
            ranges: [
              { from: -Infinity, to: Infinity, color: props.colors[0] },
            ],
          },
        },
      };
    }

    if (props.type === 'pie' || props.type === 'donut' || props.type === 'doughnut') {
      options.labels = props.categories;
      options.xaxis = undefined;
      
      options.series = cleanSeries.value;
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