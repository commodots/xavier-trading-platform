<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-white">Investment Plan Report</h1>
      <p class="text-sm text-gray-400">Track performance and metrics across all investment markets</p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
      <DateFilter @filter-change="onFilterChange" />
      <ExportButton
        reportType="investment-plans"
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
          type="bar"
          :categories="charts[1].labels"
          :series="[{ name: charts[1].title, data: charts[1].values }]"
        />
      </div>

      <h2 class="text-sm font-medium text-white">Investment Plan Performance</h2>
      <ReportTable :columns="tableColumns" :rows="tableRows" searchable>
        <template #cell-status="{ row }">
          <span
            class="px-2 py-0.5 rounded-full text-xs font-medium"
            :class="{
              'bg-green-900/50 text-green-400': row.status === 'active' || row.status === 'filled',
              'bg-yellow-900/50 text-yellow-400': row.status === 'pending',
              'bg-red-900/50 text-red-400': row.status === 'cancelled',
              'bg-gray-900/50 text-gray-400': !row.status,
            }"
          >
            {{ row.status || 'N/A' }}
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
const filters = ref({ start_date: '', end_date: '' });

const summaryCards = computed(() => [
  { label: 'Total Plans', value: summary.value.plans || 0, icon: 'Layers', color: '#0047AB' },
  { label: 'Active Plans', value: summary.value.active_plans || 0, icon: 'CheckCircle', color: '#10B981' },
  { label: 'Inactive Plans', value: summary.value.inactive_plans || 0, icon: 'XCircle', color: '#EF4444' },
  { label: 'Total Investments', value: summary.value.investments || 0, icon: 'BarChart', color: '#8B5CF6' },
  { label: 'Investment Value', value: summary.value.principal || 0, icon: 'DollarSign', color: '#F59E0B', prefix: '$' },
  { label: 'Expected ROI', value: summary.value.expected_roi || 0, icon: 'TrendingUp', color: '#10B981', prefix: '$' },
]);

const tableColumns = [
  { key: 'market', label: 'Plan Name' },
  { key: 'total_investments', label: 'Investors' },
  { key: 'total_amount', label: 'Total Amount', type: 'currency' },
  { key: 'average_investment', label: 'Average Investment', type: 'currency' },
  { key: 'status', label: 'Status' },
];

const tableRows = computed(() => {
  if (!table.value || !Array.isArray(table.value.data)) return [];
  return table.value.data.map((row) => ({
    market: row.market || 'N/A',
    total_investments: row.total_investments || 0,
    total_amount: row.total_amount || row.amount || 0,
    average_investment: row.average_investment || (row.total_amount && row.total_investments ? row.total_amount / row.total_investments : 0),
    status: row.status || 'N/A',
  }));
});

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.start_date) params.start_date = filters.value.start_date;
    if (filters.value.end_date) params.end_date = filters.value.end_date;

    const res = await api.get('/admin/reports/investment-plans', { params });
    summary.value = res.data.summary || {};
    charts.value = res.data.charts || [];
    table.value = res.data.table || [];
  } catch (e) {
    console.error('Failed to load investment plans report:', e);
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