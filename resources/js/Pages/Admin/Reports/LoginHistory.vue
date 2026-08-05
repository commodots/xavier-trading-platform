<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-white">Login History Report</h1>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
      <DateFilter @filter-change="onFilterChange" />
      <ExportButton
        reportType="login-history"
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
          type="line"
          :categories="charts[0].labels"
          :series="[
            { name: 'Successful', data: charts[0].values },
            { name: 'Failed', data: charts[0].failed || [] },
          ]"
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
        <h2 class="text-sm font-medium text-white">Login History</h2>
        <select v-model="filters.status" @change="fetchData" class="bg-[#1C2541] text-white text-xs rounded-lg px-3 py-2 border border-[#1f3348] outline-none">
          <option value="">All</option>
          <option value="success">Successful</option>
          <option value="failed">Failed</option>
        </select>
      </div>

      <ReportTable :columns="tableColumns" :rows="tableRows" searchable>
        <template #cell-status="{ row }">
          <span
            class="px-2 py-0.5 rounded-full text-xs font-medium"
            :class="row.status === 'success' ? 'bg-green-900/50 text-green-400' : 'bg-red-900/50 text-red-400'"
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
  { label: 'Successful', value: summary.value.successful || 0, icon: 'CheckCircle', color: '#10B981' },
  { label: 'Failed', value: summary.value.failed || 0, icon: 'XCircle', color: '#EF4444' },
  { label: 'Locked', value: summary.value.locked || 0, icon: 'Lock', color: '#F59E0B' },
  { label: 'Suspicious', value: summary.value.suspicious || 0, icon: 'AlertTriangle', color: '#8B5CF6' },
  { label: 'Total', value: summary.value.total || 0, icon: 'Activity', color: '#0047AB' },
]);

const tableColumns = [
  { key: 'id', label: 'ID' },
  { key: 'user', label: 'User' },
  { key: 'ip_address', label: 'IP' },
  { key: 'device', label: 'Device' },
  { key: 'browser', label: 'Browser' },
  { key: 'location', label: 'Country' },
  { key: 'logged_in_at', label: 'Time', type: 'date' },
  { key: 'status', label: 'Status' },
];

const tableRows = computed(() => {
  if (!table.value || !Array.isArray(table.value.data)) return [];
  return table.value.data.map((row) => ({
    id: row.id,
    user: row.user?.name || 'N/A',
    ip_address: row.ip_address || 'N/A',
    device: row.device || 'N/A',
    browser: row.browser || 'N/A',
    location: row.location || 'N/A',
    logged_in_at: row.logged_in_at,
    status: row.successful ? 'success' : 'failed',
  }));
});

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.start_date) params.start_date = filters.value.start_date;
    if (filters.value.end_date) params.end_date = filters.value.end_date;
    if (filters.value.status) params.status = filters.value.status;

    const res = await api.get('/admin/reports/login-history', { params });
    summary.value = res.data.summary || {};
    charts.value = res.data.charts || [];
    table.value = res.data.table || [];
  } catch (e) {
    console.error('Failed to load login history report:', e);
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