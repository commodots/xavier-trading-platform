<template>
  <MainLayout>
    <div class="p-6 space-y-6 text-white">
      <!-- HEADER -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">User Management</h1>
          <p class="text-sm text-gray-400">Manage all platform users, suspensions, and settings</p>
        </div>
        <div class="px-3 py-1.5 bg-[#111827] rounded-lg border border-[#1F2A44]">
          <span class="text-sm text-gray-300">{{ pagination.total }} <span class="text-gray-500">users</span></span>
        </div>
      </div>

      <!-- FILTERS -->
      <UserFilters
        v-model:search="filters.search"
        v-model:status="filters.status"
        v-model:subscription="filters.subscription"
        @search="onFilterChange"
      />

      <!-- TABLE -->
      <div class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
        <!-- Skeleton Loading -->
        <SkeletonLoader v-if="loading && !users.length" type="table" :count="8" />

        
        <div v-else class="relative">
          <SkeletonLoader v-if="loading" type="table" :count="users.length || 8" class="absolute inset-0 z-10" />
          <div :class="{ 'opacity-30 pointer-events-none': loading }">
            <UserTable
              :users="users"
              @view="openUserProfile"
            />
          </div>
        </div>

        <!-- PAGINATION -->
        <div v-if="pagination.last_page > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-gray-700">
          <span class="text-xs text-gray-500">
            Showing {{ (pagination.current_page - 1) * pagination.per_page + 1 }}–{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of {{ pagination.total }}
          </span>
          <div class="flex items-center gap-1">
            <button
              class="px-2 py-1 text-xs text-gray-400 bg-[#1E293B] rounded border border-gray-700 disabled:opacity-40 hover:bg-[#2a3a55] transition"
              :disabled="pagination.current_page <= 1"
              @click="changePage(1)"
            >
              «
            </button>
            <button
              class="px-2 py-1 text-xs text-gray-400 bg-[#1E293B] rounded border border-gray-700 disabled:opacity-40 hover:bg-[#2a3a55] transition"
              :disabled="pagination.current_page <= 1"
              @click="changePage(pagination.current_page - 1)"
            >
              ‹
            </button>

            <template v-for="page in visiblePages" :key="page">
              <span v-if="page === '...'" class="px-2 py-1 text-xs text-gray-500">...</span>
              <button
                v-else
                class="px-3 py-1 text-xs rounded border transition"
                :class="page === pagination.current_page
                  ? 'bg-blue-600 text-white border-blue-600'
                  : 'bg-[#1E293B] text-gray-400 border-gray-700 hover:bg-[#2a3a55]'"
                @click="changePage(page)"
              >
                {{ page }}
              </button>
            </template>

            <button
              class="px-2 py-1 text-xs text-gray-400 bg-[#1E293B] rounded border border-gray-700 disabled:opacity-40 hover:bg-[#2a3a55] transition"
              :disabled="pagination.current_page >= pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
            >
              ›
            </button>
            <button
              class="px-2 py-1 text-xs text-gray-400 bg-[#1E293B] rounded border border-gray-700 disabled:opacity-40 hover:bg-[#2a3a55] transition"
              :disabled="pagination.current_page >= pagination.last_page"
              @click="changePage(pagination.last_page)"
            >
              »
            </button>
          </div>
        </div>
      </div>

      <!-- USER PROFILE MODAL -->
      <UserProfileModal
        v-if="selectedUser"
        :user="selectedUser"
        :wallet="selectedWallet"
        :subscriptions="selectedSubscriptions"
        :transactions="selectedTransactions"
        :devices="selectedDevices"
        :loading="profileLoading"
        @close="closeProfile"
        @updated="onProfileUpdate"
      />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import UserFilters from './UserFilters.vue';
import UserTable from './UserTable.vue';
import UserProfileModal from './UserProfileModal.vue';

const users = ref([]);
const loading = ref(false);

const filters = reactive({
  search: '',
  status: '',
  subscription: '',
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 25,
});

let searchTimer = null;

const onFilterChange = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    fetchUsers(1);
  }, 300);
};

// Selected user for profile modal
const selectedUser = ref(null);
const selectedWallet = ref(null);
const selectedSubscriptions = ref([]);
const selectedTransactions = ref([]);
const selectedDevices = ref([]);
const profileLoading = ref(false);

const visiblePages = computed(() => {
  const total = pagination.last_page;
  const current = pagination.current_page;
  const pages = [];

  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i);
  } else {
    pages.push(1);
    if (current > 3) pages.push('...');
    const start = Math.max(2, current - 1);
    const end = Math.min(total - 1, current + 1);
    for (let i = start; i <= end; i++) pages.push(i);
    if (current < total - 2) pages.push('...');
    pages.push(total);
  }
  return pages;
});

const fetchUsers = async (page = 1) => {
  loading.value = true;
  try {
    const params = { page, per_page: pagination.per_page };
    if (filters.search) params.search = filters.search;
    if (filters.status) params.status = filters.status;
    if (filters.subscription) params.subscription = filters.subscription;

    const res = await api.get('/admin/users', { params });
    const data = res.data;
    users.value = data.users || [];
    pagination.current_page = data.pagination?.current_page || 1;
    pagination.last_page = data.pagination?.last_page || 1;
    pagination.total = data.pagination?.total || 0;
    pagination.per_page = data.pagination?.per_page || 25;
  } catch (err) {
    console.error('Fetch users error:', err);
  }
  loading.value = false;
};

const changePage = (page) => {
  if (page >= 1 && page <= pagination.last_page) {
    fetchUsers(page);
  }
};

const openUserProfile = async (user) => {
  // Show modal immediately with basic data from the table row
  selectedUser.value = { ...user };
  selectedWallet.value = null;
  selectedSubscriptions.value = [];
  selectedTransactions.value = [];
  selectedDevices.value = [];
  profileLoading.value = true;

  try {
    const res = await api.get(`/admin/users/${user.id}`);
    const data = res.data;
    selectedUser.value = { ...selectedUser.value, ...(data.user || data) };
    selectedWallet.value = data.wallet || null;
    selectedSubscriptions.value = data.subscriptions || [];
    selectedTransactions.value = data.transactions || [];
    selectedDevices.value = data.devices || [];
  } catch (err) {
    console.error('Fetch user detail error:', err);
  } finally {
    profileLoading.value = false;
  }
};

const closeProfile = () => {
  selectedUser.value = null;
  selectedWallet.value = null;
  selectedSubscriptions.value = [];
  selectedTransactions.value = [];
  selectedDevices.value = [];
};

const onProfileUpdate = () => {
  closeProfile();
  fetchUsers(pagination.current_page);
};

// Watch dropdown filters for immediate reactivity
watch(() => filters.status, () => { onFilterChange(); });
watch(() => filters.subscription, () => { onFilterChange(); });

onMounted(() => fetchUsers());
</script>