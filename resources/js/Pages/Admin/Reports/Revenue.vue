<template>
  <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-white">Revenue Report</h1>
        <ExportButton
          title="revenue-report"
          :rows="tableRows"
          :headers="['Revenue Source', 'Amount', 'Percentage']"
          type="revenue"
          :startDate="filters.start_date"
          :endDate="filters.end_date"
        />
      </div>

      <DateFilter @filter-change="onFilterChange" />

      <div v-if="loading" class="space-y-6">
        <SkeletonLoader type="card" :count="4" />
        <SkeletonLoader type="table" />
        <SkeletonLoader type="table" :count="6" />
      </div>

      <template v-else>
        <SummaryCards :cards="summaryCards" />

        <ReportChart
          title="Monthly Revenue"
          type="line"
          :categories="chart.categories"
          :series="chart.series"
        />

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
const summary = ref({ today: 0, month: 0, year: 0, total: 0 });
const chart = ref({ categories: [], series: [] });
const table = ref([]);
const filters = ref({ start_date: '', end_date: '', period: 'month' });

const summaryCards = computed(() => [
  { label: 'Today', value: summary.value.today, icon: 'DollarSign', color: '#10B981', prefix: '$' },
  { label: 'This Month', value: summary.value.month, icon: 'Calendar', color: '#0047AB', prefix: '$' },
  { label: 'This Year', value: summary.value.year, icon: 'TrendingUp', color: '#F59E0B', prefix: '$' },
  { label: 'Total Revenue', value: summary.value.total, icon: 'Wallet', color: '#8B5CF6', prefix: '$' },
]);

const tableColumns = [
  { key: 'source', label: 'Revenue Source' },
  { key: 'amount', label: 'Amount', type: 'currency' },
  { key: 'percentage', label: 'Percentage (%)' },
];

const tableRows = computed(() => table.value);

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.start_date) params.start_date = filters.value.start_date;
    if (filters.value.end_date) params.end_date = filters.value.end_date;
    if (filters.value.period) params.period = filters.value.period;

    const res = await api.get('/admin/reports/revenue', { params });
    summary.value = res.data.summary || { today: 0, month: 0, year: 0, total: 0 };
    chart.value = res.data.chart || { categories: [], series: [] };
    table.value = res.data.table || [];
  } catch (e) {
    console.error('Failed to load revenue:', e);
  } finally {
    loading.value = false;
  }
};

const onFilterChange = (newFilters) => {
  filters.value = newFilters;
  fetchData();
};

onMounted(fetchData);
</script>