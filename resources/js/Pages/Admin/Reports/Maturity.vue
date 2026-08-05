<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-white">Maturity Report</h1>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
      <DateFilter @filter-change="onFilterChange" />
      <ExportButton
        reportType="maturity"
        :startDate="filters.start_date"
        :endDate="filters.end_date"
      />
    </div>

    <div v-if="loading" class="space-y-6">
      <SkeletonLoader type="card" :count="4" class="opacity-40" />
      <SkeletonLoader type="table" :count="6" class="opacity-40" />
    </div>

    <template v-else>
      <SummaryCards :cards="summaryCards" />

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <ReportChart
          v-if="charts[0]"
          :title="charts[0].title"
          type="bar"
          :categories="charts[0].labels"
          :series="[{ name: charts[0].title, data: charts[0].values }]"
        />
        <ReportChart
          v-if="charts[1]"
          :title="charts[1].title"
          type="pie"
          :categories="charts[1].labels"
          :series="charts[1].values.map((v, i) => ({ name: charts[1].labels[i], data: v }))"
        />
      </div>

      <div class="flex items-center justify-between gap-4 mb-3">
        <h2 class="text-sm font-medium text-white">Maturity Schedule</h2>
        <div class="flex items-center gap-2">
          <select v-model="filters.status" @change="fetchData" class="bg-[#1C2541] text-white text-xs rounded-lg px-3 py-2 border border-[#1f3348] outline-none">
            <option value="">All Status</option>
            <option value="filled">Completed</option>
            <option value="pending">Pending</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </div>

      <ReportTable :columns="tableColumns" :rows="tableRows" searchable>
        <template #cell-status="{ row }">
          <span
            class="px-2 py-0.5 rounded-full text-xs font-medium"
            :class="{
              'bg-green-900/50 text-green-400': row.status === 'filled',
              'bg-yellow-900/50 text-yellow-400': row.status === 'pending',
              'bg-red-900/50 text-red-400': row.status === 'cancelled',
              'bg-gray-900/50 text-gray-400': row.status === 'open',
            }"
          >
            {{ row.status }}
          </span>
        </template>
      </ReportTable>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import SummaryCards from '@/Components/Reports/SummaryCards.vue';
import DateFilter from '@/Components/Reports/DateFilter.vue';
import ReportChart from '@/Components/Reports/ReportChart.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const loading = ref(true);
const summary = ref({});
const charts = ref([]);
const table = ref([]);
const filters = ref({ start_date: '', end_date: '', status: '' });

const summaryCards = computed(() => [
  { label: 'Due Today', value: summary.value.today || 0, icon: 'Calendar', color: '#F59E0B' },
  { label: 'Next 7 Days', value: summary.value.seven_days || 0, icon: 'Clock', color: '#0047AB' },
  { label: 'Next 30 Days', value: summary.value.thirty_days || 0, icon: 'TrendingUp', color: '#10B981' },
  { label: 'Overdue', value: summary.value.overdue || 0, icon: 'AlertTriangle', color: '#EF4444' },
  { label: 'Completed', value: summary.value.completed || 0, icon: 'CheckCircle', color: '#10B981' },
  { label: 'Pending', value: summary.value.pending || 0, icon: 'Clock', color: '#F59E0B' },
]);

const tableColumns = [
  { key: 'id', label: 'Reference' },
  { key: 'investor', label: 'Investor' },
  { key: 'plan', label: 'Plan' },
  { key: 'amount', label: 'Principal', type: 'currency' },
  { key: 'status', label: 'Status' },
  { key: 'start_date', label: 'Start Date', type: 'date' },
];

const tableRows = computed(() => {
  if (!table.value || !Array.isArray(table.value.data)) return [];
  return table.value.data.map((row) => ({
    id: row.id,
    investor: row.user?.name || 'N/A',
    plan: row.market || row.symbol || 'N/A',
    amount: row.amount || (row.price * row.quantity) || 0,
    status: row.status,
    start_date: row.created_at,
  }));
});

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.start_date) params.start_date = filters.value.start_date;
    if (filters.value.end_date) params.end_date = filters.value.end_date;
    if (filters.value.status) params.status = filters.value.status;

    const res = await api.get('/admin/reports/maturity', { params });
    summary.value = res.data.summary || {};
    charts.value = res.data.charts || [];
    table.value = res.data.table || [];
  } catch (e) {
    console.error('Failed to load maturity report:', e);
  } finally {
    loading.value = false;
  }
};

const onFilterChange = (newFilters) => {
  filters.value = { ...filters.value, ...newFilters };
  fetchData();
};

onMounted(fetchData);
</script>