<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-bold text-white">Subscription Report</h1>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
      <DateFilter @filter-change="onFilterChange" />
      <ExportButton
        reportType="subscriptions"
        :startDate="filters.start_date"
        :endDate="filters.end_date"
      />
    </div>

    <div v-if="loading" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4 h-24 animate-pulse"></div>
      </div>
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
        <h2 class="text-sm font-medium text-white">Subscription Records</h2>
        <select v-model="filters.status" @change="fetchData" class="bg-[#1C2541] text-white text-xs rounded-lg px-3 py-2 border border-[#1f3348] outline-none">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="trial">Trial</option>
          <option value="expired">Expired</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <ReportTable :columns="tableColumns" :rows="tableRows" searchable>
        <template #cell-status="{ row }">
          <span
            class="px-2 py-0.5 rounded-full text-xs font-medium"
            :class="{
              'bg-green-900/50 text-green-400': row.status === 'active' || row.status === 'trial',
              'bg-yellow-900/50 text-yellow-400': row.status === 'expired',
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
import api from '@/api';

const loading = ref(true);
const summary = ref({});
const charts = ref([]);
const table = ref([]);
const filters = ref({ start_date: '', end_date: '', status: '' });

const summaryCards = computed(() => {
  const cards = [];
  const summaryValue = summary.value || {};

  // Add plan-specific cards
  for (const [key, value] of Object.entries(summaryValue)) {
    if (['trial', 'expired', 'cancelled', 'renewals', 'revenue'].includes(key)) continue;
    cards.push({
      label: key.charAt(0).toUpperCase() + key.slice(1),
      value,
      icon: 'Users',
      color: '#0047AB',
    });
  }

  cards.push(
    { label: 'Trial', value: summaryValue.trial || 0, icon: 'Clock', color: '#8B5CF6' },
    { label: 'Expired', value: summaryValue.expired || 0, icon: 'AlertTriangle', color: '#F59E0B' },
    { label: 'Cancelled', value: summaryValue.cancelled || 0, icon: 'XCircle', color: '#EF4444' },
    { label: 'Renewals', value: summaryValue.renewals || 0, icon: 'RefreshCw', color: '#10B981' },
    { label: 'Revenue', value: summaryValue.revenue || 0, icon: 'DollarSign', color: '#10B981', prefix: '$' },
  );

  return cards;
});

const tableColumns = [
  { key: 'id', label: 'ID' },
  { key: 'user', label: 'User' },
  { key: 'plan', label: 'Plan' },
  { key: 'started', label: 'Started', type: 'date' },
  { key: 'expires', label: 'Ends', type: 'date' },
  { key: 'auto_renew', label: 'Auto Renew' },
  { key: 'status', label: 'Status' },
];

const tableRows = computed(() => {
  if (!table.value || !Array.isArray(table.value.data)) return [];
  return table.value.data.map((row) => ({
    id: row.id,
    user: row.user || 'N/A',
    plan: row.plan || 'N/A',
    started: row.started,
    expires: row.expires,
    auto_renew: row.auto_renew ? 'Yes' : 'No',
    status: row.status || 'N/A',
  }));
});

const fetchData = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.start_date) params.start_date = filters.value.start_date;
    if (filters.value.end_date) params.end_date = filters.value.end_date;
    if (filters.value.status) params.status = filters.value.status;

    const res = await api.get('/admin/reports/subscriptions', { params });
    summary.value = res.data.summary || {};
    charts.value = res.data.charts || [];
    table.value = res.data.table || [];
  } catch (e) {
    console.error('Failed to load subscription report:', e);
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