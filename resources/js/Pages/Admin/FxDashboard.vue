<template>
  <MainLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-4">FX Dashboard</h1>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-4 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <div class="text-sm text-gray-400">Today FX Profit (USD)</div>
          <div class="text-2xl font-bold">${{ metrics.todayProfit.toFixed(2) }}</div>
        </div>
        <div class="p-4 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <div class="text-sm text-gray-400">Monthly FX Profit (USD)</div>
          <div class="text-2xl font-bold">${{ metrics.monthlyProfit.toFixed(2) }}</div>
        </div>
        <div class="p-4 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <div class="text-sm text-gray-400">Total Lifetime FX Profit (USD)</div>
          <div class="text-2xl font-bold">${{ metrics.totalProfit.toFixed(2) }}</div>
        </div>
        <div class="p-4 bg-[#111827] rounded-xl border border-[#1F2A44]">
          <div class="text-sm text-gray-400">Total NGN Converted</div>
          <div class="text-2xl font-bold">{{ metrics.totalVolume.toLocaleString() }}</div>
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
              <button type="submit" :disabled="loading" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
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
          <div class="h-44">
            <apexchart type="line" height="100%" :options="chartOptions" :series="chartSeries" />
          </div>
        </div>

        
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import MainLayout from '@/Layouts/MainLayout.vue';
import api from '@/api';
import VueApexCharts from 'vue3-apexcharts';

const apexchart = VueApexCharts;

const metrics = ref({ todayProfit: 0, monthlyProfit: 0, totalProfit: 0, totalVolume: 0 });
const chartOptions = ref({});
const chartSeries = ref([]);

const fetchMetrics = async () => {
  try {
    const res = await api.get('/admin/fx-dashboard');
    if (res.data && res.data.success) {
      metrics.value = res.data.data;
      const daily = res.data.data.daily || { labels: [], data: [] };
      chartSeries.value = [{ name: 'USD Profit', data: daily.data }];
      chartOptions.value = {
        chart: { toolbar: { show: false }, zoom: { enabled: false } },
        stroke: { curve: 'smooth', width: 2 },
        grid: { strokeDashArray: 4 },
        colors: ['#34D399'],
        xaxis: { categories: daily.labels.map(l => new Date(l).toLocaleDateString()), labels: { style: { colors: '#9CA3AF' } } },
        yaxis: { labels: { formatter: v => v.toFixed(2), style: { colors: '#9CA3AF' } } },
        tooltip: { theme: 'dark' },
      };
    }
  } catch (e) {
    console.error('Failed to load FX metrics', e);
  }
};

onMounted(fetchMetrics);

// FX Rates form state
const form = ref({ from_currency: 'NGN', to_currency: 'USD', base_rate: 0, markup_percent: 0 });
const message = ref('');
const messageType = ref('success');
const loading = ref(false);

async function submit() {
  loading.value = true;
  try {
    try {
      await api.get(`${window.location.origin}/sanctum/csrf-cookie`, { withCredentials: true });
    } catch (csrfErr) {
      console.warn('Failed to fetch CSRF cookie', csrfErr);
    }

    const res = await api.post('/admin/fx-rates', form.value);
    if (res.data && res.data.success) {
      message.value = 'FX rate saved successfully';
      messageType.value = 'success';
      form.value = { from_currency: 'NGN', to_currency: 'USD', base_rate: 0, markup_percent: 0 };
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
  }
}
</script>

<style scoped></style>
