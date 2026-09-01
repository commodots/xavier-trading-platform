<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">User Report</h1>
    </div>

    <!-- Summary Cards -->
    <SkeletonLoader v-if="loading" type="card" :count="6" class="opacity-40" />
    <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-3">
      <StatCard v-for="s in summary" :key="s.title" v-bind="s" />
    </div>

    <!-- Date range + Export -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <DateFilter @filter-change="handleFilterChange" />
      <ExportButton
        reportType="users"
        :startDate="filters.from"
        :endDate="filters.to"
      />
    </div>

    <!-- Table -->
    <SkeletonLoader v-if="loading" type="table" :count="8" class="opacity-40" />
    <ReportTable v-else :columns="columns" :data="users" :pagination="pagination" :sort-by="sortBy" :sort-dir="sortDir" @sort="handleSort" @page-change="handlePageChange" :filters="tableFilters" title="User activity" description="Users matching the selected filters and their account status.">
      <template #cell-avatar="{ row }">
        <div class="inline-flex items-center justify-center w-8 h-8 rounded-full overflow-hidden border border-[#1f3348]">
          <img v-if="row.avatar" :src="row.avatar" :alt="row.name" class="object-cover w-full h-full" />
          <div v-else class="flex items-center justify-center w-full h-full bg-[#0047AB] text-white text-xs font-medium">
            {{ row.name?.charAt(0)?.toUpperCase() || 'U' }}
          </div>
        </div>
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
          <button @click="viewUser(row.id)" class="text-xs text-blue-400 hover:text-blue-300">View</button>
          <button @click="editUser(row.id)" class="text-xs text-green-400 hover:text-green-300">Edit</button>
          <button v-if="row.status !== 'suspended'" @click="suspendUser(row.id)" class="text-xs text-yellow-400 hover:text-yellow-300">Suspend</button>
          <button v-else @click="activateUser(row.id)" class="text-xs text-green-400 hover:text-green-300">Activate</button>
        </div>
      </template>
    </ReportTable>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import ReportTable from '@/Components/Reports/ReportTable.vue';
import DateFilter from '@/Components/Reports/DateFilter.vue';
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
const perPage = ref(20);
const sortBy = ref('');
const sortDir = ref('desc');
const filters = reactive({ from: '', to: '', period: 'month', kyc_status: '', status: '', subscription: '', country: '' });
const pagination = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 20 });

const columns = [
  { key: 'avatar', label: '', width: '48px', cellClass: 'px-2 py-2' },
  { key: 'name', label: 'Name', sortable: true, width: '120px', cellClass: 'px-2 py-2' },
  { key: 'email', label: 'Email', width: '150px', cellClass: 'px-2 py-2' },
  { key: 'phone', label: 'Phone', width: '100px', cellClass: 'px-2 py-2' },
  { key: 'country', label: 'Country', width: '100px', cellClass: 'px-2 py-2' },
  { key: 'kyc_status', label: 'KYC', width: '70px', cellClass: 'px-2 py-2' },
  { key: 'subscription_status', label: 'Sub', width: '70px', cellClass: 'px-2 py-2' },
  { key: 'status', label: 'Status', width: '80px', cellClass: 'px-2 py-2' },
  { key: 'joined', label: 'Joined', sortable: true, width: '120px', cellClass: 'px-2 py-2' },
  { key: 'last_login', label: 'Last Login', width: '120px', cellClass: 'px-2 py-2' },
  { key: 'actions', label: 'Actions', width: '120px', cellClass: 'px-2 py-2' },
];

const handleSort = (key) => {
  if (sortBy.value === key) { sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'; }
  else { sortBy.value = key; sortDir.value = 'asc'; }
  fetchUsers();
};

const handlePageChange = (page) => {
  currentPage.value = page;
  pagination.current_page = page;
  fetchUsers();
};

const filterOptions = ref({
  countries: [],
  subscriptions: ['active', 'inactive', 'trial'],
  statuses: ['active', 'suspended', 'inactive'],
  kyc_statuses: ['verified', 'pending', 'rejected', 'none']
});

const handleFilterChange = (payload) => {
  filters.from = payload.start_date || '';
  filters.to = payload.end_date || '';
  filters.period = payload.period || 'month';
  fetchUsers();
};


const tableFilters = computed(() => [
  {
    key: 'kyc_status',
    label: 'KYC',
    allLabel: 'All KYC Statuses',
    options: [
      { label: 'Verified', value: 'verified' },
      { label: 'Pending', value: 'pending' },
      { label: 'Rejected', value: 'rejected' },
      { label: 'None', value: 'none' },
    ],
  },
  {
    key: 'status',
    label: 'User Status',
    allLabel: 'All User Statuses',
    options: [
      { label: 'Active', value: 'active' },
      { label: 'Pending', value: 'pending' },
      { label: 'Suspended', value: 'suspended' },
    ],
  },
  {
    key: 'subscription_status',
    label: 'Subscription',
    allLabel: 'All Subscriptions',
    options: [
      { label: 'Active', value: 'active' },
      { label: 'Trial', value: 'trial' },
      { label: 'Expired', value: 'expired' },
    ],
  },
  {
    key: 'country',
    label: 'Country',
    allLabel: 'All Countries',
    options: filterOptions.value.countries,
  },
]);

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
    pagination.current_page = listRes.data.current_page;
    pagination.last_page = listRes.data.last_page;
    pagination.total = listRes.data.total;
    pagination.per_page = listRes.data.per_page;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
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

onMounted(fetchUsers);
</script>