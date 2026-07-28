<template>
  <div class="bg-[#1C1F2E] rounded-xl overflow-hidden">
    <!-- Filters -->
    <div class="p-4 border-b border-[#2A314A]">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm text-gray-400 mb-2">Search</label>
          <input
            v-model="localFilters.search"
            type="text"
            placeholder="IP, device, or browser..."
            class="w-full px-4 py-2 bg-[#0F1219] border border-[#2A314A] rounded-lg text-white focus:outline-none focus:border-[#00D4FF]"
          />
        </div>

        <div>
          <label class="block text-sm text-gray-400 mb-2">User ID</label>
          <input
            v-model="localFilters.user_id"
            type="text"
            placeholder="User ID"
            class="w-full px-4 py-2 bg-[#0F1219] border border-[#2A314A] rounded-lg text-white focus:outline-none focus:border-[#00D4FF]"
          />
        </div>

        <div>
          <label class="block text-sm text-gray-400 mb-2">Status</label>
          <select
            v-model="localFilters.successful"
            class="w-full px-4 py-2 bg-[#0F1219] border border-[#2A314A] rounded-lg text-white focus:outline-none focus:border-[#00D4FF]"
          >
            <option value="">All</option>
            <option value="1">Successful</option>
            <option value="0">Failed</option>
          </select>
        </div>

        <div>
          <label class="block text-sm text-gray-400 mb-2">Date From</label>
          <input
            v-model="localFilters.date_from"
            type="date"
            class="w-full px-4 py-2 bg-[#0F1219] border border-[#2A314A] rounded-lg text-white focus:outline-none focus:border-[#00D4FF]"
          />
        </div>
      </div>

      <div class="mt-4 flex gap-2">
        <button
          @click="applyFilters"
          class="px-4 py-2 bg-[#00D4FF] text-black rounded-lg hover:bg-[#00D4FF]/90 transition"
        >
          Apply Filters
        </button>
        <button
          @click="clearFilters"
          class="px-4 py-2 bg-[#2A314A] text-white rounded-lg hover:bg-[#2A314A]/80 transition"
        >
          Clear
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="text-gray-400 bg-[#0F1219]">
          <tr>
            <th class="px-6 py-3">Date</th>
            <th class="px-6 py-3">User</th>
            <th class="px-6 py-3">IP Address</th>
            <th class="px-6 py-3">Device</th>
            <th class="px-6 py-3">Browser</th>
            <th class="px-6 py-3">Platform</th>
            <th class="px-6 py-3">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in loginHistory" :key="log.id" class="border-t border-[#2A314A] hover:bg-[#0F1219]/50">
            <td class="px-6 py-4">
              {{ formatDate(log.logged_in_at) }}
            </td>
            <td class="px-6 py-4">
              <div>
                <p class="text-white font-medium">{{ log.user?.name || 'Unknown' }}</p>
                <p class="text-xs text-gray-400">{{ log.user?.email || '' }}</p>
              </div>
            </td>
            <td class="px-6 py-4">
              <span class="font-mono text-xs">{{ log.ip_address }}</span>
            </td>
            <td class="px-6 py-4">
              {{ log.device || 'N/A' }}
            </td>
            <td class="px-6 py-4">
              {{ log.browser || 'N/A' }}
            </td>
            <td class="px-6 py-4">
              {{ log.platform || 'N/A' }}
            </td>
            <td class="px-6 py-4">
              <span
                :class="log.successful ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'"
                class="px-2 py-1 rounded text-xs"
              >
                {{ log.successful ? 'Success' : 'Failed' }}
              </span>
            </td>
          </tr>
          <tr v-if="loginHistory.length === 0">
            <td colspan="7" class="px-6 py-8 text-center text-gray-500">
              No login history found
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="total > 0" class="px-6 py-4 border-t border-[#2A314A] flex items-center justify-between">
      <p class="text-sm text-gray-400">
        Showing {{ loginHistory.length }} of {{ total }} entries
      </p>
      <div class="flex gap-2">
        <button
          @click="prevPage"
          :disabled="currentPage === 1"
          class="px-3 py-1 bg-[#2A314A] text-white rounded hover:bg-[#2A314A]/80 disabled:opacity-50"
        >
          Previous
        </button>
        <button
          @click="nextPage"
          :disabled="currentPage === lastPage"
          class="px-3 py-1 bg-[#2A314A] text-white rounded hover:bg-[#2A314A]/80 disabled:opacity-50"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue';
import api from '@/api';

const props = defineProps({
  initialFilters: {
    type: Object,
    default: () => ({
      search: '',
      user_id: '',
      successful: '',
      date_from: '',
    })
  }
});

const loginHistory = ref([]);
const total = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);

const localFilters = reactive({ ...props.initialFilters });

async function fetchLoginHistory() {
  try {
    const params = {
      page: currentPage.value,
      ...localFilters,
    };

    const response = await api.get('/admin/security/login-history', { params });
    
    if (response.data?.success) {
      loginHistory.value = response.data.data?.data || [];
      total.value = response.data.data?.total || 0;
      lastPage.value = response.data.data?.last_page || 1;
    }
  } catch (error) {
    console.error('Failed to fetch login history:', error);
  }
}

function applyFilters() {
  currentPage.value = 1;
  fetchLoginHistory();
}

function clearFilters() {
  localFilters.search = '';
  localFilters.user_id = '';
  localFilters.successful = '';
  localFilters.date_from = '';
  currentPage.value = 1;
  fetchLoginHistory();
}

function prevPage() {
  if (currentPage.value > 1) {
    currentPage.value--;
    fetchLoginHistory();
  }
}

function nextPage() {
  if (currentPage.value < lastPage.value) {
    currentPage.value++;
    fetchLoginHistory();
  }
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

// Watch for filter changes from parent
watch(() => props.initialFilters, (newFilters) => {
  Object.assign(localFilters, newFilters);
  currentPage.value = 1;
  fetchLoginHistory();
}, { deep: true });

// Expose methods for parent component
defineExpose({
  refresh: fetchLoginHistory,
  clearFilters
});

onMounted(() => {
  fetchLoginHistory();
});
</script>