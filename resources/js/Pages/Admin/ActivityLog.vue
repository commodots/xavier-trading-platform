<template>
  <MainLayout>
    <div class="bg-[#0f172a] p-6 rounded-lg border border-gray-700">
      <div class="flex flex-col items-start justify-between gap-4 mb-6 lg:flex-row lg:items-center">
        <h2 class="text-xl font-bold text-white">System Activity Logs</h2>

        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-[10px] text-gray-500 uppercase mb-1 font-bold">Activity</label>
            <select v-model="filters.type" @change="fetchLogs(1)"
              class="px-3 py-2 text-sm text-white bg-gray-800 border border-gray-700 rounded outline-none focus:border-blue-500">
              <option value="">All Types</option>
              <option value="Login">Login</option>
              <option value="Logout">Logout</option>
              <option value="Profile Update">Profile Update</option>
              <option value="KYC Submission">KYC Submission</option>
            </select>
          </div>

          <div>
            <label class="block text-[10px] text-gray-500 uppercase mb-1 font-bold">From</label>
            <input type="date" v-model="filters.start_date" @change="fetchLogs(1)"
              class="px-3 py-2 text-sm text-white bg-gray-800 border border-gray-700 rounded outline-none focus:border-blue-500" />
          </div>

          <div>
            <label class="block text-[10px] text-gray-500 uppercase mb-1 font-bold">To</label>
            <input type="date" v-model="filters.end_date" @change="fetchLogs(1)"
              class="px-3 py-2 text-sm text-white bg-gray-800 border border-gray-700 rounded outline-none focus:border-blue-500" />
          </div>

          <button @click="resetFilters" class="px-3 py-2 text-xs text-red-400 underline transition hover:text-red-300">
            Reset
          </button>

          <button @click="exportData"
            class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white transition rounded bg-emerald-600 hover:bg-emerald-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Export CSV
          </button>
        </div>
      </div>

      <div class="mb-4 overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-400">
          <thead class="bg-gray-800/50 text-gray-200 uppercase text-[11px] tracking-wider">
            <tr>
              <th class="px-4 py-3 border-b border-gray-700">User</th>
              <th class="px-4 py-3 border-b border-gray-700">Activity</th>
              <th class="px-4 py-3 border-b border-gray-700">IP Address</th>
              <th class="px-4 py-3 text-right border-b border-gray-700">Date/Time</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-800">
            <tr v-for="log in logsData.data" :key="log.id" class="transition hover:bg-gray-900/40">
              <td class="px-4 py-4">
                <div class="font-medium text-white">{{ log.user?.name || 'Unknown' }}</div>
                <div class="text-[11px] text-gray-500">{{ log.user?.email }}</div>
              </td>
              <td class="px-4 py-4">
                <span :class="getStatusClass(log.activity)" class="px-2 py-1 rounded text-[10px] font-bold">
                  {{ log.activity }}
                </span>
              </td>
              <td class="px-4 py-4 font-mono text-xs">{{ log.ip_address }}</td>
              <td class="px-4 py-4 text-xs text-right text-gray-500">
                {{ formatDate(log.created_at) }}
              </td>
            </tr>
            <tr v-if="logsData.data?.length === 0">
              <td colspan="4" class="py-10 italic text-center text-gray-600">No activities found matching criteria</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="logsData.total > 0" class="flex items-center justify-between pt-4 border-t border-gray-700">
        <p class="text-xs text-gray-500">
          Showing {{ logsData.from }} to {{ logsData.to }} of {{ logsData.total }} results
        </p>

        <div class="flex gap-1">
          <button @click="fetchLogs(logsData.current_page - 1)" :disabled="logsData.current_page === 1"
            class="px-3 py-1 text-xs text-white bg-gray-800 border border-gray-700 rounded disabled:opacity-30 disabled:cursor-not-allowed hover:bg-gray-700">
            Previous
          </button>

          <button v-for="page in logsData.last_page" :key="page" @click="fetchLogs(page)"
            :class="logsData.current_page === page ? 'bg-blue-600 border-blue-500 text-white' : 'bg-gray-800 border-gray-700 text-gray-400 hover:bg-gray-700'"
            class="px-3 py-1 text-xs transition border rounded">
            {{ page }}
          </button>

          <button @click="fetchLogs(logsData.current_page + 1)" :disabled="logsData.current_page === logsData.last_page"
            class="px-3 py-1 text-xs text-white bg-gray-800 border border-gray-700 rounded disabled:opacity-30 disabled:cursor-not-allowed hover:bg-gray-700">
            Next
          </button>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';

const logsData = ref({ data: [], total: 0 });
const filters = reactive({
  type: '',
  start_date: '',
  end_date: ''
});

const fetchLogs = async (page = 1) => {
  try {
    const res = await api.get('/admin/activities', {
      params: { ...filters, page }
    });
    logsData.value = res.data.data;
  } catch (err) {
    console.error("Fetch error", err);
  }
};

const resetFilters = () => {
  filters.type = '';
  filters.start_date = '';
  filters.end_date = '';
  fetchLogs(1);
};

const getStatusClass = (activity) => {
  switch (activity) {
    case 'Login': return 'bg-green-500/10 text-green-400 border border-green-500/20';
    case 'Logout': return 'bg-red-500/10 text-red-400 border border-red-500/20';
    case 'KYC Submission': return 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20';
    default: return 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
  }
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
  });
};

const exportData = async () => {
  try {
    const params = new URLSearchParams(filters).toString();
    const token = localStorage.getItem('xavier_token');

    // Construct the full URL with filters and auth token
    const url = `${import.meta.env.VITE_API_URL}/admin/activities/export?${params}&token=${token}`;

    window.open(url, '_blank');
  } catch (err) {
    console.error("Export failed", err);
  }
};

onMounted(() => fetchLogs(1));
</script>