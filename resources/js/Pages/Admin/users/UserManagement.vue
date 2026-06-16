<template>
  <MainLayout>
    <div class="p-6 space-y-6 text-white">
      <!-- HEADER -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">User Management</h1>
          <p class="text-sm text-gray-400">Manage all platform users, suspensions, and settings</p>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs text-gray-400">{{ totalUsers }} total users</span>
        </div>
      </div>

      <!-- FILTERS -->
      <UserFilters
        v-model:search="filters.search"
        v-model:status="filters.status"
        v-model:subscription="filters.subscription"
        @search="fetchUsers"
      />

      <!-- TABLE -->
      <div class="bg-[#111827] rounded-xl border border-[#1F2A44] overflow-hidden">
        <div class="p-4">
          <UserTable
            :users="users"
            @view="openUserProfile"
          />
        </div>

        <!-- PAGINATION -->
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-700">
          <button
            class="px-3 py-1 text-xs bg-gray-700 rounded disabled:opacity-40"
            :disabled="pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)"
          >
            Prev
          </button>
          <span class="text-xs text-gray-400">
            Page {{ pagination.current_page }} of {{ pagination.last_page }}
            ({{ pagination.total }} results)
          </span>
          <button
            class="px-3 py-1 text-xs bg-gray-700 rounded disabled:opacity-40"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >
            Next
          </button>
        </div>
      </div>

      <!-- LOADING -->
      <div v-if="loading" class="flex justify-center py-12">
        <div class="w-8 h-8 border-4 border-blue-600 rounded-full border-t-transparent animate-spin"></div>
      </div>

      <!-- USER PROFILE MODAL -->
      <UserProfileModal
        v-if="selectedUser"
        :user="selectedUser"
        :wallet="selectedWallet"
        :transactions="selectedTransactions"
        :devices="selectedDevices"
        @close="closeProfile"
        @updated="onProfileUpdate"
      />
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';
import UserFilters from './UserFilters.vue';
import UserTable from './UserTable.vue';
import UserProfileModal from './UserProfileModal.vue';

const users = ref([]);
const loading = ref(false);
const totalUsers = ref(0);

const filters = reactive({
  search: '',
  status: '',
  subscription: '',
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  total: 0,
});

// Selected user for profile modal
const selectedUser = ref(null);
const selectedWallet = ref(null);
const selectedTransactions = ref([]);
const selectedDevices = ref([]);

const fetchUsers = async (page = 1) => {
  loading.value = true;
  try {
    const params = { page };
    if (filters.search) params.search = filters.search;
    if (filters.status) params.status = filters.status;
    if (filters.subscription) params.subscription = filters.subscription;

    const res = await api.get('/admin/users', { params });
    const data = res.data;
    users.value = data.users || [];
    pagination.current_page = data.pagination?.current_page || 1;
    pagination.last_page = data.pagination?.last_page || 1;
    pagination.total = data.pagination?.total || 0;
    totalUsers.value = data.pagination?.total || 0;
  } catch (err) {
    console.error('Fetch users error:', err);
  }
  loading.value = false;
};

const changePage = (page) => {
  fetchUsers(page);
};

const openUserProfile = async (user) => {
  try {
    const res = await api.get(`/admin/users/${user.id}`);
    const data = res.data;
    selectedUser.value = data.user || data;
    selectedWallet.value = data.wallet || null;
    selectedTransactions.value = data.transactions || [];
    selectedDevices.value = data.devices || [];
  } catch (err) {
    console.error('Fetch user detail error:', err);
  }
};

const closeProfile = () => {
  selectedUser.value = null;
  selectedWallet.value = null;
  selectedTransactions.value = [];
  selectedDevices.value = [];
};

const onProfileUpdate = () => {
  closeProfile();
  fetchUsers(pagination.current_page);
};

onMounted(() => fetchUsers());
</script>