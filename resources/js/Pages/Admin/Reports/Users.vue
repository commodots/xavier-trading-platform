<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">User Report</h1>
      <ExportButton @export-csv="exportReport('csv')" @export-excel="exportReport('excel')" />
    </div>

    <!-- Summary Cards -->
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-4">
      <div v-for="i in 7" :key="i" class="p-4 bg-[#0F1724] border border-[#1f3348] rounded-lg space-y-3 animate-pulse">
        <div class="h-3 bg-gray-700 rounded w-20"></div>
        <div class="h-6 bg-gray-700 rounded w-16"></div>
      </div>
    </div>
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-4">
      <StatCard v-for="s in summary" :key="s.title" v-bind="s" />
    </div>

    <!-- Search & Filters -->
    <div v-if="!loading" class="flex items-center gap-4">
      <SearchBar v-model="filters.search" placeholder="Search users..." />
      <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />
      <select v-model="filters.kyc_status" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
        <option value="">All KYC</option>
        <option value="verified">Verified</option>
        <option value="pending">Pending</option>
        <option value="none">None</option>
      </select>
      <button @click="fetchUsers" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Search</button>
    </div>

    <!-- Table -->
    <div v-if="loading" class="bg-[#0F1724] border border-[#1f3348] rounded-lg overflow-hidden">
      <div class="p-4 space-y-3">
        <div v-for="i in 8" :key="i" class="h-12 bg-gray-700/50 rounded animate-pulse"></div>
      </div>
    </div>
    <ReportTable v-else :columns="columns" :data="users" :sort-by="sortBy" :sort-dir="sortDir" @sort="handleSort">
      <template #cell-status="{ row }">
        <span :class="row.status === 'active' ? 'text-green-400' : row.status === 'suspended' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium">{{ row.status }}</span>
      </template>
    </ReportTable>

    <Pagination v-if="!loading && pagination.total > 0" v-bind="pagination" @update:perPage="perPage = $event; fetchUsers()" @page="currentPage = $event; fetchUsers()" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import SearchBar from '@/Components/Reports/SearchBar.vue';
import DateRangeFilter from '@/Components/Reports/DateRangeFilter.vue';
import ExportButton from '@/Components/Reports/ExportButton.vue';
import Pagination from '@/Components/Reports/Pagination.vue';
import api from '@/api';

const summary = ref([]);
const users = ref([]);
const loading = ref(false);
const currentPage = ref(1);
const perPage = ref(50);
const sortBy = ref('');
const sortDir = ref('desc');
const filters = reactive({ search: '', from: '', to: '', kyc_status: '' });
const pagination = reactive({ currentPage: 1, lastPage: 1, total: 0, perPage: 50 });

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Phone' },
  { key: 'country', label: 'Country' },
  { key: 'status', label: 'Status' },
  { key: 'joined', label: 'Joined' },
  { key: 'last_login', label: 'Last Login' },
];

const handleSort = (key) => {
  if (sortBy.value === key) { sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'; }
  else { sortBy.value = key; sortDir.value = 'asc'; }
  fetchUsers();
};

const fetchUsers = async () => {
  loading.value = true;
  try {
    const params = { ...filters, page: currentPage.value, per_page: perPage.value, sort: sortBy.value, dir: sortDir.value };
    const [sumRes, listRes] = await Promise.all([
      api.get('/admin/reports/users/summary'),
      api.get('/admin/reports/users', { params }),
    ]);
    summary.value = sumRes.data;
    users.value = listRes.data.data || [];
    pagination.currentPage = listRes.data.current_page;
    pagination.lastPage = listRes.data.last_page;
    pagination.total = listRes.data.total;
    pagination.perPage = listRes.data.per_page;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

const exportReport = async (format) => {
  try {
    const params = { ...filters, export: format };
    const res = await api.get('/admin/reports/users', { params, responseType: 'blob' });
    const blob = new Blob([res.data]);
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `users-report.${format === 'csv' ? 'csv' : 'xlsx'}`;
    a.click();
    window.URL.revokeObjectURL(url);
  } catch (e) {
    console.error(e);
  }
};

onMounted(fetchUsers);
</script>