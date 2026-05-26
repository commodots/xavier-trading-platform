<template>
  <MainLayout>
    <div class="bg-[#0f172a] p-6 rounded-lg border border-gray-700">
      <div class="flex flex-col items-start justify-between gap-4 mb-6 lg:flex-row lg:items-center">
        <div>
          <h2 class="text-xl font-bold text-white">Security Audit Logs</h2>
          <p class="text-xs text-gray-500 mt-1">Financial & security events — withdrawals, 2FA, password changes</p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-[10px] text-gray-500 uppercase mb-1 font-bold">Search</label>
            <input v-model="filters.q" @input="debounceSearch" type="text" placeholder="Search action or email..."
              class="w-56 py-2 text-sm text-white bg-gray-800 border border-gray-700 rounded outline-none px-3 focus:border-blue-500" />
          </div>
          <div>
            <label class="block text-[10px] text-gray-500 uppercase mb-1 font-bold">Action</label>
            <select v-model="filters.action" @change="fetchLogs(1)"
              class="px-3 py-2 text-sm text-white bg-gray-800 border border-gray-700 rounded outline-none focus:border-blue-500">
              <option value="">All Actions</option>
              <option value="withdrawal_created">Withdrawal</option>
              <option value="password_changed">Password Changed</option>
              <option value="2fa_enabled">2FA Enabled</option>
              <option value="2fa_disabled">2FA Disabled</option>
              <option value="trade_executed">Trade Executed</option>
              <option value="system_event">System Event</option>
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
          <button @click="resetFilters" class="px-3 py-2 text-xs text-red-400 underline hover:text-red-300">Reset</button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-400">
          <thead class="bg-gray-800/50 text-gray-200 uppercase text-[11px] tracking-wider">
            <tr>
              <th class="px-4 py-3 border-b border-gray-700">User</th>
              <th class="px-4 py-3 border-b border-gray-700">Action</th>
              <th class="px-4 py-3 border-b border-gray-700">Entity</th>
              <th class="px-4 py-3 border-b border-gray-700">IP Address</th>
              <th class="px-4 py-3 border-b border-gray-700 text-right">Date/Time</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-800">
            <tr v-if="loading">
              <td colspan="5" class="py-4">
                <div class="space-y-2">
                  <SkeletonLoader v-for="i in 5" :key="i" class="h-12 w-full rounded bg-gray-800" />
                </div>
              </td>
            </tr>
            <tr v-else-if="logsData.data?.length === 0">
              <td colspan="5" class="py-10 text-center text-gray-600 italic">No audit events found.</td>
            </tr>
            <tr v-for="log in logsData.data" :key="log.id" @click="selected = log"
              class="hover:bg-gray-900/60 cursor-pointer transition">
              <td class="px-4 py-4">
                <div class="font-medium text-white">{{ log.user?.name || 'System' }}</div>
                <div class="text-[11px] text-gray-500">{{ log.user?.email || '—' }}</div>
              </td>
              <td class="px-4 py-4">
                <span class="px-2 py-1 rounded text-[10px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                  {{ log.action }}
                </span>
              </td>
              <td class="px-4 py-4 text-xs text-gray-400">
                {{ log.entity ? `${log.entity} #${log.entity_id}` : '—' }}
              </td>
              <td class="px-4 py-4 font-mono text-xs">{{ log.ip_address || '—' }}</td>
              <td class="px-4 py-4 text-xs text-right text-gray-500">{{ formatDate(log.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="logsData.last_page > 1" class="flex items-center justify-between pt-4 border-t border-gray-800 mt-4">
        <span class="text-xs text-gray-500">Page {{ logsData.current_page }} of {{ logsData.last_page }}</span>
        <div class="flex gap-2">
          <button @click="fetchLogs(logsData.current_page - 1)" :disabled="logsData.current_page === 1"
            class="px-4 py-2 text-xs bg-gray-800 border border-gray-700 rounded text-gray-300 hover:bg-gray-700 disabled:opacity-50">
            Previous
          </button>
          <button @click="fetchLogs(logsData.current_page + 1)" :disabled="logsData.current_page === logsData.last_page"
            class="px-4 py-2 text-xs bg-gray-800 border border-gray-700 rounded text-gray-300 hover:bg-gray-700 disabled:opacity-50">
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div v-if="selected" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
      <div class="bg-[#1C1F2E] border border-gray-700 w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
        <div class="flex items-center justify-between p-4 border-b border-gray-800">
          <h3 class="font-bold text-white">Audit Event Details</h3>
          <button @click="selected = null" class="text-gray-400 hover:text-white">✕</button>
        </div>
        <div class="p-6 space-y-4 text-sm">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-[10px] text-gray-500 uppercase font-bold">Action</p>
              <p class="text-white">{{ selected.action }}</p>
            </div>
            <div>
              <p class="text-[10px] text-gray-500 uppercase font-bold">IP Address</p>
              <p class="font-mono text-white">{{ selected.ip_address || '—' }}</p>
            </div>
            <div>
              <p class="text-[10px] text-gray-500 uppercase font-bold">User</p>
              <p class="text-white">{{ selected.user?.email || 'System' }}</p>
            </div>
            <div>
              <p class="text-[10px] text-gray-500 uppercase font-bold">Entity</p>
              <p class="text-white">{{ selected.entity ? `${selected.entity} #${selected.entity_id}` : '—' }}</p>
            </div>
          </div>
          <div v-if="selected.payload && Object.keys(selected.payload).length">
            <p class="text-[10px] text-gray-500 uppercase font-bold mb-1">Payload</p>
            <pre class="bg-black/30 border border-gray-800 rounded p-3 text-xs text-gray-300 overflow-auto max-h-40">{{ JSON.stringify(selected.payload, null, 2) }}</pre>
          </div>
          <div>
            <p class="text-[10px] text-gray-500 uppercase font-bold">Timestamp</p>
            <p class="text-gray-400">{{ formatDate(selected.created_at) }}</p>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '@/api'
import MainLayout from '@/Layouts/MainLayout.vue'
import SkeletonLoader from '@/Components/SkeletonLoader.vue'

const logsData = ref({ data: [], current_page: 1, last_page: 1 })
const loading = ref(false)
const selected = ref(null)
const filters = reactive({ q: '', action: '', start_date: '', end_date: '' })

let searchTimeout = null
const debounceSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchLogs(1), 500)
}

const fetchLogs = async (page = 1) => {
  loading.value = true
  try {
    const res = await api.get('/admin/audit-logs', { params: { ...filters, page } })
    logsData.value = res.data.data
  } catch (err) {
    console.error('Failed to load audit logs', err)
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  filters.q = ''
  filters.action = ''
  filters.start_date = ''
  filters.end_date = ''
  fetchLogs(1)
}

const formatDate = (d) => d ? new Date(d).toLocaleString('en-GB', {
  day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit'
}) : '—'

onMounted(() => fetchLogs(1))
</script>
