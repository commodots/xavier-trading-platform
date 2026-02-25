<template>
  <MainLayout>
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">FX Dashboard</h1>

        <div class="text-sm text-gray-400 bg-[#1C1F2E] px-4 py-2 rounded-lg border border-[#2A314A] flex items-center gap-2">
          <span>Current Base Rate: <span class="text-white font-bold">₦{{ formatCurrency(metrics.currentRate) }} / $1</span></span>
          <span class="bg-[#0f2922] text-[#34D399] px-2 py-0.5 rounded text-xs font-bold ml-2 border border-[#1a4a3b]">
            +{{ metrics.currentMarkup }}% Markup
          </span>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-4 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <div class="text-sm text-gray-400">Today FX Profit</div>
          <div class="text-2xl font-bold text-green-400">${{ formatCurrency(metrics.todayProfit) }}</div>
          <div class="text-lg text-gray-500 mt-1">≈ ₦{{ formatCurrency(metrics.todayProfitNgn) }}</div>
        </div>
        
        <div class="p-4 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <div class="text-sm text-gray-400">Monthly FX Profit</div>
          <div class="text-2xl font-bold">${{ formatCurrency(metrics.monthlyProfit) }}</div>
          <div class="text-lg text-gray-500 mt-1">≈ ₦{{ formatCurrency(metrics.monthlyProfitNgn) }}</div>
        </div>
        
        <div class="p-4 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <div class="text-sm text-gray-400">Lifetime FX Profit</div>
          <div class="text-2xl font-bold">${{ formatCurrency(metrics.totalProfit) }}</div>
          <div class="text-lg text-gray-500 mt-1">≈ ₦{{ formatCurrency(metrics.totalProfitNgn) }}</div>
        </div>
        
        <div class="p-4 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <div class="text-sm text-gray-400">Total Volume Converted</div>
          <div class="text-2xl font-bold">${{ formatCurrency(metrics.totalVolume) }}</div>
          <div class="text-lg text-gray-500 mt-1">≈ ₦{{ formatCurrency(metrics.totalVolumeNgn) }}</div>
        </div>
      </div>

      <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A] mt-6 max-w-2xl">
        <h3 class="mb-3 font-semibold">FX Rates Configuration</h3>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-sm text-gray-300">From Currency</label>
            <input v-model="form.from_currency" class="mt-1 block w-full bg-[#151a27] border border-[#2A314A] text-white px-3 py-2 rounded" />
          </div>

          <div>
            <label class="block text-sm text-gray-300">To Currency</label>
            <input v-model="form.to_currency" class="mt-1 block w-full bg-[#151a27] border border-[#2A314A] text-white px-3 py-2 rounded" />
          </div>

          <div>
            <label class="block text-sm text-gray-300">Base Rate</label>
            <input v-model.number="form.base_rate" type="number" step="0.000001" class="mt-1 block w-full bg-[#151a27] border border-[#2A314A] text-white px-3 py-2 rounded" />
          </div>

          <div>
            <label class="block text-sm text-gray-300">Markup %</label>
            <input v-model.number="form.markup_percent" type="number" step="0.01" class="mt-1 block w-full bg-[#151a27] border border-[#2A314A] text-white px-3 py-2 rounded" />
          </div>

          <div>
            <button type="submit" :disabled="loading" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50 transition-colors">
              {{ loading ? 'Saving...' : 'Save Rate' }}
            </button>
          </div>
        </form>

        <div v-if="message" :class="messageType === 'success' ? 'text-green-400' : 'text-red-400'" class="mt-4">
          {{ message }}
        </div>
      </div>

      <div class="mt-6">
        <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A] mt-4">
          <h3 class="mb-3 font-semibold">Daily FX Profit (last 14 days)</h3>
          <div class="h-64">
            <apexchart v-if="chartSeries.length > 0" type="area" height="100%" :options="chartOptions" :series="chartSeries" />
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import api from '@/api';
import VueApexCharts from 'vue3-apexcharts';

const apexchart = VueApexCharts;

const metrics = ref({ 
  todayProfit: 0, todayProfitNgn: 0,
  monthlyProfit: 0, monthlyProfitNgn: 0,
  totalProfit: 0, totalProfitNgn: 0,
  totalVolume: 0, totalVolumeNgn: 0,
  currentRate: 0, currentMarkup: 0 
});

const chartOptions = ref({});
const chartSeries = ref([]);

// FX Rates form state
const form = ref({ from_currency: 'NGN', to_currency: 'USD', base_rate: 0, markup_percent: 0 });
const message = ref('');
const messageType = ref('success');
const loading = ref(false);

const formatCurrency = (val) => {
  return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const fetchMetrics = async () => {
  try {
    const res = await api.get('/admin/fx-dashboard');
    if (res.data && res.data.success) {
      metrics.value = res.data.data;
      
      // PREFILL THE FORM HERE
      form.value.base_rate = metrics.value.currentRate || 0;
      form.value.markup_percent = metrics.value.currentMarkup || 0;
      
      const daily = res.data.data.daily || { labels: [], data: [] };
      
      chartSeries.value = [{ name: 'USD Profit', data: daily.data }];
      
      chartOptions.value = {
        chart: { type: 'area', toolbar: { show: false }, zoom: { enabled: false } },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: {
          type: 'gradient',
          gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
        },
        grid: { strokeDashArray: 4, borderColor: '#2A314A' },
        colors: ['#34D399'],
        xaxis: { 
          categories: daily.labels.map(l => {
            const d = new Date(l);
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
          }), 
          labels: { style: { colors: '#9CA3AF' } },
          axisBorder: { show: false },
          axisTicks: { show: false }
        },
        yaxis: { 
          labels: { 
            formatter: v => '$' + v.toFixed(2), 
            style: { colors: '#9CA3AF' } 
          } 
        },
        tooltip: { theme: 'dark', y: { formatter: v => '$' + v.toFixed(2) } },
      };
    }
  } catch (e) {
    console.error('Failed to load FX metrics', e);
  }
};

onMounted(fetchMetrics);

async function submit() {
  loading.value = true;
  try {
    const res = await api.post('/admin/fx-rates', form.value);
    if (res.data && res.data.success) {
      message.value = 'FX rate saved successfully';
      messageType.value = 'success';
      
      // removed the manual reset to 0 here.
      // fetchMetrics will automatically pull the fresh, newly saved values into the form.
      await fetchMetrics(); 
    } else {
      message.value = 'Failed to save rate';
      messageType.value = 'error';
    }
  } catch (e) {
    message.value = 'Failed to save rate';
    messageType.value = 'error';
  } finally {
    loading.value = false;
    setTimeout(() => { message.value = '' }, 3000); 
  }
}
</script>