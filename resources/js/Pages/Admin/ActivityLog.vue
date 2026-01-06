<template>
  <MainLayout>
    <div class="bg-[#0f172a] p-6 rounded-lg border border-gray-700">
      <div class="flex flex-col items-start justify-between gap-4 mb-6 lg:flex-row lg:items-center">
        <h2 class="text-xl font-bold text-white">System Activity Logs</h2>
        <div class="flex flex-wrap items-end gap-3">
          <div class="w-full lg:w-64">
            <label class="block text-[10px] text-gray-500 uppercase mb-1 font-bold">Search</label>
            <div class="relative">
              <input v-model="filters.q" @input="debounceSearch" type="text"
                placeholder="Search name, email, or info..."
                class="w-full py-2 text-sm text-white bg-gray-800 border border-gray-700 rounded outline-none px-9 focus:border-blue-500" />
              <svg xmlns="http://www.w3.org/2000/svg" class="absolute w-4 h-4 text-gray-500 left-3 top-2.5" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>
          <div>
            <label class="block text-[10px] text-gray-500 uppercase mb-1 font-bold">Activity</label>
            <select v-model="filters.type" @change="fetchLogs(1)"
              class="px-3 py-2 text-sm text-white bg-gray-800 border border-gray-700 rounded outline-none focus:border-blue-500">
              <option value="">All Types</option>
              <option value="Registration">Registration</option>
<option value="Login">Login</option>
<option value="Logout">Logout</option>
<option value="Failed Login">Failed Login Attempts</option>
              <option value="Profile Update">Profile Update</option>
              <option value="Deposit">Deposit</option>
              <option value="Withdrawal">Withdrawal</option>
              <option value="Role Update">Role Update</option>
              <option value="Toggle Status">Toggle Status</option>
              <option value="KYC Submission">KYC Submission</option>
              <option value="Charge Update">Charge Update</option>
              <option value="KYC Review">KYC Review</option>
              <option value="Export Transactions">Export Transactions</option>
              <option value="Export Activity Logs">Export Activity Logs</option>
              <option value="Service Created">Service Created</option>
              <option value="Service Connection Update">Service Connection Update</option>
              <option value="Service Mode Update">Service Mode Update</option>
              <option value="Toggle Service">Toggle Service</option>
              <option value="Linked Account Added">Linked Account Added</option>
            </select>
          </div>

          <div class="flex gap-2">
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
          </div>

          <button @click="resetFilters" class="px-3 py-2 text-xs text-red-400 underline transition hover:text-red-300">
            Reset
          </button>
          <button @click="exportData"
            class="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white transition rounded bg-emerald-600 hover:bg-emerald-700">
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
              <th class="px-4 py-3 border-b border-gray-700">Details</th>
              <th class="px-4 py-3 border-b border-gray-700">IP Address</th>
              <th class="px-4 py-3 text-right border-b border-gray-700">Date/Time</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-800">
            <tr v-for="log in logsData.data" :key="log.id" @click="selectedLog = log"
              class="transition cursor-pointer hover:bg-gray-900/60">
              <td class="px-4 py-4">
                <div class="font-medium text-white">{{ log.user?.name || 'Unknown' }}</div>
                <div class="text-[11px] text-gray-500">{{ log.user?.email }}</div>
              </td>
              <td class="px-4 py-4">
                <span :class="getStatusClass(log.activity)" class="px-2 py-1 rounded text-[10px] font-bold">
                  {{ log.activity }}
                </span>
              </td>
              <td class="max-w-xs px-4 py-4 text-xs text-gray-300 truncate">
                {{ formatDetails(log.details || log.description) || '---' }}
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

    </div>

    <div v-if="selectedLog"
      class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
      <div class="bg-[#1C1F2E] border border-gray-700 w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center justify-between p-4 border-b border-gray-800">
          <h3 class="font-bold text-white">Activity Details</h3>
          <button @click="selectedLog = null" class="text-gray-400 hover:text-white">✕</button>
        </div>

        <div class="p-6 space-y-4">
          <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-900/50">
            <div
              class="w-10 h-10 rounded-full bg-[#00D4FF]/10 flex items-center justify-center text-[#00D4FF] font-bold">
              {{ selectedLog.user?.name?.charAt(0) || 'U' }}
            </div>
            <div>
              <p class="text-sm font-bold text-white">{{ selectedLog.user?.name || 'Unknown User' }}</p>
              <p class="text-xs text-gray-500">{{ selectedLog.user?.email || 'N/A' }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-[10px] text-gray-500 uppercase font-bold">Action</p>
              <p class="text-sm text-white">{{ selectedLog.activity }}</p>
            </div>
            <div>
              <p class="text-[10px] text-gray-500 uppercase font-bold">IP Address</p>
              <p class="font-mono text-sm text-white">{{ selectedLog.ip_address }}</p>
            </div>
          </div>

          <div>
            <p class="text-[10px] text-gray-500 uppercase font-bold mb-1">Description</p>
            <div class="p-3 text-xs leading-relaxed text-gray-300 border border-gray-800 rounded-lg bg-black/30">
              {{ formatDetails(selectedLog.details || selectedLog.description) || 'No additional details provided for this activity.' }}
            </div>
          </div>

          <div>
            <p class="text-[10px] text-gray-500 uppercase font-bold">Timestamp</p>
            <p class="text-sm text-gray-400">{{ formatDate(selectedLog.created_at) }}</p>
          </div>
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
const selectedLog = ref(null);
const filters = reactive({
  q: '',
  type: '',
  start_date: '',
  end_date: ''
});

let searchTimeout = null;

const debounceSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchLogs(1);
  }, 500);
};

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
  filters.q = '';
  filters.type = '';
  filters.start_date = '';
  filters.end_date = '';
  fetchLogs(1);
};

const getStatusClass = (activity) => {
  switch (activity) {
    case 'Registration': return 'bg-purple-500/10 text-purple-400 border border-purple-500/20';
    case 'Login': return 'bg-green-500/10 text-green-400 border border-green-500/20';
    case 'Logout': return 'bg-slate-500/10 text-slate-400 border border-slate-500/20';
    case 'Failed Login': return 'bg-orange-500/10 text-orange-400 border border-orange-500/20';
    case 'KYC Submission': return 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20';
    case 'KYC Review': return 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20';
    case 'Role Update': return 'bg-purple-500/10 text-purple-400 border border-purple-500/20';
    case 'Toggle Status': return 'bg-orange-500/10 text-orange-400 border border-orange-500/20';
    case 'Export Transactions': return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
    case 'Export Activity Logs': return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
    case 'Charge Update': return 'bg-orange-500/10 text-orange-400 border border-orange-500/20';
    case 'Service Created': return 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
    case 'Service Connection Update': return 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20';
    case 'Service Mode Update': return 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20';
    case 'Toggle Service': return 'bg-pink-500/10 text-pink-400 border border-pink-500/20';
    case 'Deposit': return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
    case 'Withdrawal': return 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
    case 'Linked Account Added': return 'bg-violet-500/10 text-violet-400 border border-violet-500/20';
    default: return 'bg-blue-500/10 text-blue-400 border border-blue-500/20';
  }

};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit'
  });
};

const exportData = async () => {
  try {
    const params = new URLSearchParams(filters).toString();
    const token = localStorage.getItem('xavier_token');
    const url = `${import.meta.env.VITE_API_URL}/admin/activities/export?${params}&token=${token}`;
    window.open(url, '_blank');
  } catch (err) {
    console.error("Export failed", err);
  }
};

onMounted(() => fetchLogs(1));

const formatDetails = (details) => {
  if (!details) return 'No details available';


  if (typeof details === 'string') {

    if (details.startsWith('{') || details.startsWith('[')) {
      try {
        const parsed = JSON.parse(details);
        return JSON.stringify(parsed, null, 2);
      } catch (e) {
        return details;
      }
    }
    return details;
  }


  try {
    return JSON.stringify(details, null, 2);
  } catch (e) {
    return String(details);
  }
};
</script>