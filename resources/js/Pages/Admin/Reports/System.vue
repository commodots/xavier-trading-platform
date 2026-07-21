<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-white">System Report</h1>

    <!-- Cards -->
    <div v-if="loading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
      <div v-for="i in 8" :key="i" class="p-4 bg-[#0F1724] border border-[#1f3348] rounded-lg space-y-3 animate-pulse">
        <div class="h-3 bg-gray-700 rounded w-20"></div>
        <div class="h-6 bg-gray-700 rounded w-16"></div>
      </div>
    </div>
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
      <StatCard v-for="c in cards" :key="c.label" v-bind="c" />
    </div>

    <!-- Integration Health -->
    <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
      <h3 class="text-lg font-semibold text-white mb-4">Integration Health</h3>
      <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div v-for="i in 5" :key="i" class="flex items-center gap-3 bg-[#16213A] rounded-lg p-4 animate-pulse">
          <div class="h-8 w-8 bg-gray-700 rounded-full"></div>
          <div class="flex-1 space-y-2">
            <div class="h-4 bg-gray-700 rounded w-20"></div>
            <div class="h-3 bg-gray-700 rounded w-16"></div>
          </div>
        </div>
      </div>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div v-for="item in integrations" :key="item.name" class="flex items-center gap-3 bg-[#16213A] rounded-lg p-4">
          <span class="text-2xl">{{ item.icon }}</span>
          <div>
            <p class="text-sm font-medium text-white">{{ item.name }}</p>
            <p class="text-xs capitalize" :class="item.status === 'connected' ? 'text-green-400' : item.status === 'degraded' ? 'text-yellow-400' : 'text-red-400'">{{ item.status }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Logs -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div v-for="(logEntries, logType) in logs" :key="logType" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
        <h3 class="text-lg font-semibold text-white mb-3 capitalize">{{ logType.replace('_', ' ') }}</h3>
        <div v-if="loading" class="space-y-2">
          <div v-for="i in 5" :key="i" class="h-8 bg-gray-700 rounded animate-pulse"></div>
        </div>
        <div v-else-if="logEntries.length === 0" class="text-gray-500 text-sm italic py-4 text-center">No entries</div>
        <div v-else v-for="(entry, i) in logEntries.slice(0, 5)" :key="i" class="text-xs text-gray-400 border-b border-[#1f3348] py-2 flex justify-between">
          <span>{{ entry.message }}</span>
          <span class="text-gray-500 ml-2">{{ entry.time }}</span>
        </div>
      </div>
    </div>

    <!-- Maintenance -->
    <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-5">
      <h3 class="text-lg font-semibold text-white mb-4">Maintenance Tools (Super Admin)</h3>
      <div class="flex flex-wrap gap-3">
        <button v-for="action in maintenanceActions" :key="action.key" @click="confirmAction(action.key, action.label)" class="px-4 py-2 bg-[#16213A] border border-gray-700 rounded-lg text-white text-sm hover:border-blue-500 transition">
          {{ action.label }}
        </button>
      </div>
      <div v-if="actionMessage" class="mt-4 text-sm" :class="actionMessage.includes('failed') ? 'text-red-400' : 'text-green-400'">{{ actionMessage }}</div>
    </div>

    <!-- Confirmation Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="showModal = false">
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-white mb-2">Confirm Action</h3>
        <p class="text-gray-400 text-sm mb-6">{{ modalMessage }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showModal = false" class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm hover:bg-gray-600 transition">Cancel</button>
          <button @click="executeConfirmedAction" class="px-4 py-2 bg-[#0047AB] text-white rounded-lg text-sm hover:bg-blue-600 transition">Confirm</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import StatCard from '@/Components/Reports/StatCard.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';
import api from '@/api';

const cards = ref([]);
const integrations = ref([]);
const logs = ref({ errors: [], warnings: [], payment_failures: [], login_attempts: [] });
const actionMessage = ref('');
const loading = ref(false);

const maintenanceActions = [
  { key: 'clear-cache', label: 'Clear Cache' },
  { key: 'optimize', label: 'Optimize' },
  { key: 'queue-restart', label: 'Queue Restart' },
  { key: 'run-scheduler', label: 'Run Scheduler' },
  { key: 'logs', label: 'View Logs' },
];

const fetchSystem = async () => {
  loading.value = true;
  try {
    const res = await api.get('/admin/reports/system');
    cards.value = res.data.cards || [];
    integrations.value = res.data.integrations || [];
    logs.value = res.data.logs || { errors: [], warnings: [], payment_failures: [], login_attempts: [] };
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
};

const showModal = ref(false);
const modalMessage = ref('');
const pendingActionKey = ref('');

const confirmAction = (key, label) => {
  pendingActionKey.value = key;
  const confirmMessages = {
    'clear-cache': 'Are you sure you want to clear all application caches? This may temporarily slow down the application while caches rebuild.',
    'optimize': 'Are you sure you want to optimize the application? This will cache routes, config, and views.',
    'queue-restart': 'Are you sure you want to restart the queue worker? Any currently processing jobs will be interrupted.',
    'run-scheduler': 'Are you sure you want to run the scheduler now? This will execute all scheduled tasks.',
    'logs': 'Are you sure you want to view the latest application logs?',
  };
  modalMessage.value = confirmMessages[key] || `Are you sure you want to run "${label}"?`;
  showModal.value = true;
};

const executeConfirmedAction = async () => {
  showModal.value = false;
  const key = pendingActionKey.value;
  try {
    if (key === 'logs') {
      const res = await api.get('/admin/reports/logs');
      actionMessage.value = `Loaded ${res.data.logs?.length || 0} log entries. Check storage/logs/laravel.log`;
      return;
    }
    const res = await api.post(`/admin/reports/${key}`);
    actionMessage.value = res.data.message || 'Action completed';
  } catch (e) {
    console.error(e);
    actionMessage.value = 'Action failed';
  }
};

onMounted(fetchSystem);
</script>