<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-white">ROI Report</h1>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
      <DateFilter @filter-change="onFilterChange" />
      <ExportButton
        reportType="roi"
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
        <h2 class="text-sm font-medium text-white">ROI Details</h2>
        <select v-model="filters.status" @change="fetchData" class="bg-[#1C2541] text-xs rounded-lg px-3 py-2 border border-[#1f3348] outline-none">
          <option value="" disabled selected class="text-gray-500">All Status</option>
          <option value="filled" class="text-white">Completed</option>
          <option value="pending">Pending</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <ReportTable :columns="tableColumns" :rows="tableRows" searchable />
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
  { label: 'Expected ROI', value: summary.value.expected_roi || 0, icon: 'TrendingUp', color: '#0047AB', prefix: '$' },
  { label: 'Paid ROI', value: summary.value.paid_roi || 0, icon: 'DollarSign', color: '#10B981', prefix: '$' },
  { label: 'Outstanding ROI', value: summary.value.outstanding_roi || 0, icon: 'AlertTriangle', color: '#F59E0B', prefix: '$' },
  { label: 'Upcoming ROI', value: summary.value.upcoming_roi || 0, icon: 'Calendar', color: '#8B5CF6', prefix: '$' },
  { label: 'Overdue ROI', value: summary.value.overdue_roi || 0, icon: 'AlertTriangle', color: '#EF4444', prefix: '$' },
  { label: 'Completed ROI', value: summary.value.completed_roi || 0, icon: 'CheckCircle', color: '#10B981' },
  { label: 'Active ROI', value: summary.value.active_roi || 0, icon: 'Activity', color: '#0047AB' },
]);

const tableColumns = [
  { key: 'id', label: 'Reference' },
  { key: 'investor', label: 'Investor' },
  { key: 'plan', label: 'Plan' },
  { key: 'amount', label: 'Principal', type: 'currency' },
  { key: 'roi', label: 'Expected ROI' },
  { key: 'status', label: 'Status', type: 'status' },
  { key: 'start_date', label: 'Start Date', type: 'date' },
  { key: 'created_at', label: 'Created', type: 'date' },
];

const tableRows = computed(() => {
  if (!table.value || !Array.isArray(table.value.data)) return [];
  return table.value.data.map((row) => ({
    id: row.id,
    investor: row.user?.name || 'N/A',
    plan: row.market || row.symbol || 'N/A',
    amount: row.amount || (row.price * row.quantity) || 0,
    roi: '0%',
    status: row.status,
    start_date: row.created_at,
    created_at: row.created_at,
  }));
});

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.start_date) params.start_date = filters.value.start_date;
    if (filters.value.end_date) params.end_date = filters.value.end_date;
    if (filters.value.status) params.status = filters.value.status;

    const res = await api.get('/admin/reports/roi', { params });
    summary.value = res.data.summary || {};
    charts.value = res.data.charts || [];
    table.value = res.data.table || [];
  } catch (e) {
    console.error('Failed to load ROI report:', e);
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