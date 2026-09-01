<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-white">KYC Report</h1>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
      <DateFilter @filter-change="onFilterChange" />
      <ExportButton
        reportType="kyc"
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

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <ReportChart
          v-if="charts[0]"
          :title="charts[0].title"
          type="pie"
          :categories="charts[0].labels"
          :series="charts[0].values.map((v, i) => ({ name: charts[0].labels[i], data: v }))"
        />
        <ReportChart
          v-if="charts[1]"
          :title="charts[1].title"
          type="bar"
          :categories="charts[1].labels"
          :series="[{ name: charts[1].title, data: charts[1].values }]"
        />
      </div>

      <div class="flex items-center justify-between gap-4 mb-3">
        <h2 class="text-sm font-medium text-white">KYC Records</h2>
      </div>

      <ReportTable :columns="tableColumns" :rows="tableRows" :filters="tableFilters">
        <template #cell-status="{ row }">
          <span
            class="px-2 py-0.5 rounded-full text-xs font-medium"
            :class="{
              'bg-green-900/50 text-green-400': ['approved', 'verified'].includes(row.status),
              'bg-yellow-900/50 text-yellow-400': row.status === 'pending',
              'bg-red-900/50 text-red-400': row.status === 'rejected',
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
const filters = ref({ start_date: '', end_date: '', status: '' });

const summaryCards = computed(() => [
  { label: 'Pending', value: summary.value.pending || 0, icon: 'Clock', color: '#F59E0B' },
  { label: 'Approved', value: summary.value.approved || 0, icon: 'CheckCircle', color: '#10B981' },
  { label: 'Rejected', value: summary.value.rejected || 0, icon: 'XCircle', color: '#EF4444' },
  { label: 'Expired', value: summary.value.expired || 0, icon: 'AlertTriangle', color: '#8B5CF6' },
  { label: 'Total', value: summary.value.total || 0, icon: 'Users', color: '#0047AB' },
  { label: 'Approval Rate', value: summary.value.approval_rate || 0, icon: 'TrendingUp', color: '#10B981', suffix: '%' },
]);

const tableColumns = [
  { key: 'id', label: 'ID' },
  { key: 'user', label: 'User' },
  { key: 'document', label: 'Document' },
  { key: 'created_at', label: 'Submitted', type: 'date' },
  { key: 'verified_at', label: 'Approved', type: 'date' },
  { key: 'status', label: 'Status' },
];

const tableRows = computed(() => {
  if (!table.value || !Array.isArray(table.value.data)) return [];
  return table.value.data.map((row) => ({
    id: row.id,
    user: row.user?.name || 'N/A',
    document: row.id_type || row.document || 'N/A',
    created_at: row.created_at,
    verified_at: row.verified_at,
    status: row.status,
  }));
});


const tableFilters = computed(() => [
  {
    key: 'status',
    label: 'Status',
    allLabel: 'All Statuses',
    options: [
      { label: 'Pending', value: 'pending' },
      { label: 'Approved', value: 'approved' },
      { label: 'Verified', value: 'verified' },
      { label: 'Rejected', value: 'rejected' },
    ],
  },
]);

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.start_date) params.start_date = filters.value.start_date;
    if (filters.value.end_date) params.end_date = filters.value.end_date;
    if (filters.value.status) params.status = filters.value.status;

    const res = await api.get('/admin/reports/kyc', { params });
    summary.value = res.data.summary || {};
    charts.value = res.data.charts || [];
    table.value = res.data.table || [];
  } catch (e) {
    console.error('Failed to load KYC report:', e);
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