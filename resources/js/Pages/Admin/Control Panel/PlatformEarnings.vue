<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-xl font-bold text-white">Platform Earnings</h1>
      <div class="bg-[#1F2A44] px-4 py-2 rounded-lg border border-[#00D4FF]/30">
        <p class="text-xs text-gray-400 uppercase tracking-wider">Period Total (NGN)</p>
        <p class="text-xl font-mono font-bold text-[#00D4FF]">₦ {{ totalNg }}</p>
      </div>
    </div>

    <div class="bg-[#111827] rounded-xl p-6 border border-[#1F2A44]">
      <div class="flex flex-wrap items-center gap-4 mb-6">
        <input v-model="q" @keyup.enter="fetchData" placeholder="Search email or source..." class="bg-[#0B132B] text-white px-4 py-2 rounded border border-[#1F2A44] focus:border-[#00D4FF] outline-none transition-all" />
        <div class="flex items-center bg-[#0B132B] rounded border border-[#1F2A44]">
          <input type="date" v-model="startDate" class="bg-transparent text-white px-3 py-2 outline-none" />
          <span class="text-gray-500">to</span>
          <input type="date" v-model="endDate" class="bg-transparent text-white px-3 py-2 outline-none" />
        </div>
        <button @click="fetchData" class="px-6 py-2 bg-[#00D4FF] hover:bg-[#00b8dd] text-black font-bold rounded transition-colors">
          Filter Report
        </button>
      </div>

      <div class="mb-8 bg-[#0B132B]/50 p-4 rounded-lg border border-[#1F2A44]">
        <h3 class="text-sm text-gray-400 mb-4 flex items-center">
          <span class="w-2 h-2 bg-[#00D4FF] rounded-full mr-2"></span>
          Earnings Performance (NGN)
        </h3>
        <apexchart type="area" height="280" :options="chartOptions" :series="chartSeries" />
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left border-collapse">
          <thead class="text-gray-400 text-xs uppercase bg-[#1F2A44]/30">
            <tr>
              <th class="p-4 border-b border-[#1F2A44]">Date</th>
              <th class="p-4 border-b border-[#1F2A44]">Source</th>
              <th class="p-4 border-b border-[#1F2A44]">Original Amount</th>
              <th class="p-4 border-b border-[#1F2A44]">NGN Equivalent</th>
              <th class="p-4 border-b border-[#1F2A44]">User</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in earnings.data" :key="item.id" class="border-b border-[#1F2A44] hover:bg-[#1F2A44]/20 transition-colors">
              <td class="p-4 text-gray-300">{{ formatDate(item.created_at) }}</td>
              <td class="p-4">
                <span class="px-2 py-1 rounded-md bg-blue-500/10 text-blue-400 text-xs font-medium">
                  {{ item.source.replace('_', ' ') }}
                </span>
              </td>
              <td class="p-4 font-mono text-white">
                {{ item.amount }} <span class="text-xs text-gray-500">{{ item.currency }}</span>
              </td>
              <td class="p-4 font-mono text-[#00D4FF] font-bold">
                ₦ {{ Number(item.amount_ngn).toLocaleString() }}
              </td>
              <td class="p-4 text-gray-400">{{ item.transaction?.user?.email || 'System' }}</td>
            </tr>
            <tr v-if="!earnings.data?.length">
                <td colspan="5" class="p-10 text-center text-gray-500">No earnings found for this period.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-6 flex items-center justify-between">
        <p class="text-xs text-gray-500">Showing page {{ earnings.current_page }} of {{ earnings.last_page }}</p>
        <div class="flex space-x-2">
          <button @click="changePage(earnings.current_page - 1)" :disabled="earnings.current_page === 1" 
                  class="px-4 py-2 bg-[#1F2A44] text-white rounded disabled:opacity-30 disabled:cursor-not-allowed hover:bg-[#2d3d61]">
            Previous
          </button>
          <button @click="changePage(earnings.current_page + 1)" :disabled="earnings.current_page === earnings.last_page" 
                  class="px-4 py-2 bg-[#1F2A44] text-white rounded disabled:opacity-30 disabled:cursor-not-allowed hover:bg-[#2d3d61]">
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/api';
import ApexChart from 'vue3-apexcharts';

const earnings = ref({ data: [], current_page: 1, last_page: 1 });
const q = ref('');
const startDate = ref('');
const endDate = ref('');
const totalNg = ref('0.00');

const chartSeries = ref([{ name: 'Daily Revenue', data: [] }]);
const chartOptions = ref({
  chart: { toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
  theme: { mode: 'dark' },
  xaxis: { categories: [], axisBorder: { show: false } },
  stroke: { curve: 'smooth', width: 3 },
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.0, stops: [0, 90, 100] }
  },
  colors: ['#00D4FF'],
  grid: { borderColor: '#1F2A44', strokeDashArray: 4 },
  dataLabels: { enabled: false }
});

async function fetchData(page = 1) {
  try {
    const res = await api.get('/admin/earnings/report', { 
        params: { q: q.value, start_date: startDate.value, end_date: endDate.value, page: page } 
    });
    
    earnings.value = res.data.data;
    totalNg.value = res.data.total_ngn;

    // Map Timeseries to Chart
    const times = res.data.timeseries || [];
    chartSeries.value = [{ name: 'Earnings (NGN)', data: times.map(t => Number(t.total)) }];
    
    // Update X-Axis labels
    chartOptions.value = {
      ...chartOptions.value,
      xaxis: { ...chartOptions.value.xaxis, categories: times.map(t => t.day) }
    };
  } catch (error) {
    console.error("Failed to load earnings report", error);
  }
}

function changePage(p) {
  if (p < 1 || p > earnings.value.last_page) return;
  fetchData(p);
}

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
}

onMounted(fetchData);
</script>

<script>
export default {
  components: { apexchart: ApexChart }
}
</script>