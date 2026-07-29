<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">User Report</h1>
      <ExportButton @export-csv="exportReport('csv')" @export-excel="exportReport('excel')" />
    </div>

    <!-- Summary Cards -->
    <SkeletonLoader v-if="loading" type="card" :count="6" class="opacity-40" />
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 gap-4">
      <StatCard v-for="s in summary" :key="s.title" v-bind="s" />
    </div>

    <!-- Search & Filters -->
    <div v-if="!loading" class="flex flex-wrap items-center gap-4">
      <SearchBar v-model="filters.search" placeholder="Search users..." />
      <DateRangeFilter v-model:from="filters.from" v-model:to="filters.to" />
      <select v-model="filters.kyc_status" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
        <option value="">All KYC</option>
        <option value="verified">Verified</option>
        <option value="pending">Pending</option>
        <option value="rejected">Rejected</option>
        <option value="none">None</option>
      </select>
      <select v-model="filters.status" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="suspended">Suspended</option>
        <option value="inactive">Inactive</option>
      </select>
      <select v-model="filters.subscription" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
        <option value="">All Subscriptions</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="trial">Trial</option>
      </select>
      <select v-model="filters.country" class="bg-[#16213A] border border-gray-700 rounded-lg p-2 text-white text-sm outline-none">
        <option value="">All Countries</option>
        <option v-for="country in filterOptions.countries" :key="country" :value="country">{{ country }}</option>
      </select>
      <button @click="fetchUsers" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm">Search</button>
      <button @click="resetFilters" class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm">Reset</button>
    </div>

    <!-- Table -->
    <SkeletonLoader v-if="loading" type="table" :count="8" class="opacity-40" />
    <ReportTable v-else :columns="columns" :data="users" :sort-by="sortBy" :sort-dir="sortDir" @sort="handleSort">
      <template #cell-avatar="{ row }">
        <img v-if="row.avatar" :src="row.avatar" :alt="row.name" class="w-8 h-8 rounded-full object-cover" />
        <div v-else class="w-3 h-8 rounded-full bg-[#0047AB] flex items-center justify-center text-white text-xs font-medium">
          {{ row.name?.charAt(0)?.toUpperCase() || 'U' }}
        </div>
      </template>
      <template #cell-wallet_balance="{ row }">
        <span class="font-mono text-sm">${{ formatNumber(Number(row.wallet_balance || 0)) }}</span>
      </template>
      <template #cell-subscription_status="{ row }">
        <span :class="row.subscription_status === 'active' ? 'text-green-400' : row.subscription_status === 'trial' ? 'text-blue-400' : 'text-gray-400'" class="text-xs font-medium capitalize">
          {{ row.subscription_status || 'none' }}
        </span>
      </template>
      <template #cell-kyc_status="{ row }">
        <span :class="row.kyc_status === 'verified' ? 'text-green-400' : row.kyc_status === 'pending' ? 'text-yellow-400' : 'text-red-400'" class="text-xs font-medium capitalize">
          {{ row.kyc_status }}
        </span>
      </template>
      <template #cell-status="{ row }">
        <span :class="row.status === 'active' ? 'text-green-400' : row.status === 'suspended' ? 'text-red-400' : 'text-yellow-400'" class="text-xs font-medium capitalize">{{ row.status }}</span>
      </template>
      <template #cell-actions="{ row }">
        <div class="flex flex-col gap-2">
          <button @click="viewUser(row.id)" class="text-blue-400 hover:text-blue-300 text-xs">View</button>
          <button @click="editUser(row.id)" class="text-green-400 hover:text-green-300 text-xs">Edit</button>
          <button v-if="row.status !== 'suspended'" @click="suspendUser(row.id)" class="text-yellow-400 hover:text-yellow-300 text-xs">Suspend</button>
          <button v-else @click="activateUser(row.id)" class="text-green-400 hover:text-green-300 text-xs">Activate</button>
        </div>
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
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const formatNumber = (num) => {
  const parts = num.toLocaleString('en-US', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 20,
  }).split('.');
  
  if (parts[1]) {
    parts[1] = parts[1].replace(/0+$/, '');
    if (parts[1] === '') {
      return parts[0];
    }
    return parts.join('.');
  }
  
  return parts[0];
};

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
  { key: 'avatar', label: '', width: '50px' },
  { key: 'name', label: 'Name', sortable: true, width: '140px' },
  { key: 'email', label: 'Email', width: '160px' },
  { key: 'phone', label: 'Phone', width: '100px' },
  { key: 'wallet_balance', label: 'Wallet', align: 'right', width: '100px' },
  { key: 'kyc_status', label: 'KYC', width: '80px' },
  { key: 'subscription_status', label: 'Sub', width: '70px' },
  { key: 'status', label: 'Status', width: '80px' },
  { key: 'joined', label: 'Joined', sortable: true, width: '110px' },
  { key: 'last_login', label: 'Last Login', width: '110px' },
  { key: 'actions', label: 'Actions', width: '130px' },
];

const handleSort = (key) => {
  if (sortBy.value === key) { sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'; }
  else { sortBy.value = key; sortDir.value = 'asc'; }
  fetchUsers();
};

const filterOptions = ref({
  countries: [],
  subscriptions: ['active', 'inactive', 'trial'],
  statuses: ['active', 'suspended', 'inactive'],
  kyc_statuses: ['verified', 'pending', 'rejected', 'none']
});

const fetchUsers = async () => {
  loading.value = true;
  try {
    const params = { ...filters, page: currentPage.value, per_page: perPage.value, sort: sortBy.value, dir: sortDir.value };
    const [sumRes, listRes, filtersRes] = await Promise.all([
      api.get('/admin/reports/users/summary'),
      api.get('/admin/reports/users', { params }),
      api.get('/admin/reports/users/filters').catch(() => ({ data: { countries: [] } })),
    ]);
    summary.value = sumRes.data;
    users.value = listRes.data.data || [];
    filterOptions.value = filtersRes.data;
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

const resetFilters = () => {
  filters.search = '';
  filters.from = '';
  filters.to = '';
  filters.kyc_status = '';
  filters.status = '';
  filters.subscription = '';
  filters.country = '';
  currentPage.value = 1;
  fetchUsers();
};

const viewUser = (id) => {
  window.open(`/admin/users/${id}`, '_blank');
};

const editUser = (id) => {
  window.open(`/admin/users/${id}/edit`, '_blank');
};

const suspendUser = async (id) => {
  if (!confirm('Are you sure you want to suspend this user?')) return;
  try {
    await api.post(`/admin/users/${id}/suspend`);
    alert('User suspended successfully');
    fetchUsers();
  } catch (e) {
    console.error(e);
    alert('Failed to suspend user');
  }
};

const activateUser = async (id) => {
  if (!confirm('Are you sure you want to activate this user?')) return;
  try {
    await api.post(`/admin/users/${id}/unsuspend`);
    alert('User activated successfully');
    fetchUsers();
  } catch (e) {
    console.error(e);
    alert('Failed to activate user');
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