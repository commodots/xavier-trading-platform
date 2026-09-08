<script setup>
import { onMounted, ref } from 'vue'
import api from '@/api'
import SkeletonLoader from '@/Components/SkeletonLoader.vue'

const loading = ref(true)
const action = ref('')
const error = ref('')
const message = ref('')
const cards = ref([])

const schedules = [
  { name: 'Settlement processing', cadence: 'Hourly / daily at 08:00', command: 'settlements:process' },
  { name: 'Quarterly billing', cadence: 'Quarterly', command: 'billing:process-quarterly' },
  { name: 'Inactivity checks', cadence: 'Daily at 02:00', command: 'user:check-inactivity 30' },
  { name: 'Trial and fee reminders', cadence: 'Daily at 09:00', command: 'Scheduled closures' },
  { name: 'Fixed Income lifecycle', cadence: 'Hourly', command: 'Maturity and provider jobs' },
]

const loadSystem = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/admin/reports/system')
    cards.value = response.data.cards || []
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to load runtime status.'
  } finally {
    loading.value = false
  }
}

const runAction = async (name, endpoint, successMessage) => {
  action.value = name
  error.value = ''
  message.value = ''
  try {
    await api.post(endpoint)
    message.value = successMessage
    await loadSystem()
  } catch (exception) {
    error.value = exception.response?.data?.message || `Unable to ${name}.`
  } finally {
    action.value = ''
  }
}

const cardValue = (label) => cards.value.find((card) => card.label === label)?.value ?? '-'

onMounted(loadSystem)
</script>

<template>
  <section class="space-y-6 rounded-xl border border-[#2A314A] bg-[#1C1F2E] p-6 text-white">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-cyan-400">Runtime operations</p>
        <h2 class="mt-2 text-xl font-bold">Queues, jobs and schedules</h2>
        <p class="mt-1 max-w-2xl text-sm text-gray-400">Monitor Laravel runtime work and manually signal operational
          workers when needed.</p>
      </div>
      <button class="rounded-lg border border-[#2A314A] px-3 py-2 text-sm text-gray-300 hover:bg-[#252A3D]"
        :disabled="loading" @click="loadSystem">Refresh status</button>
    </div>
    <p v-if="error" class="rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-300">{{ error }}</p>
    <p v-if="message" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-300">{{
      message }}</p>
    <SkeletonLoader v-if="loading" type="card" :count="4" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 opacity-40" />
    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="label in ['Queued Jobs', 'Failed Jobs', 'Cron Status', 'API Status']" :key="label"
        class="rounded-lg border border-[#343B54] bg-[#252A3D] p-4">
        <p class="text-xs uppercase tracking-wider text-gray-400">{{ label }}</p>
        <p class="mt-2 text-2xl font-semibold text-white">{{ loading ? '...' : cardValue(label) }}</p>
      </div>
    </div>
    <div class="grid gap-4 md:grid-cols-3">
      <button
        class="rounded-lg border border-cyan-500/30 bg-cyan-500/10 p-4 text-left hover:bg-cyan-500/20 disabled:opacity-50"
        :disabled="action !== ''"
        @click="runAction('restart queue workers', '/admin/reports/queue-restart', 'Queue restart signal sent to workers.')">
        <p class="font-semibold text-cyan-300">Restart queue workers</p>
        <p class="mt-1 text-xs text-gray-400">Signals supervised Laravel workers to reload.</p><span
          class="mt-3 block text-xs text-cyan-400">{{ action === 'restart queue workers' ? 'Sending...' : 'Run action'
          }}</span>
      </button>
      <button
        class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-4 text-left hover:bg-amber-500/20 disabled:opacity-50"
        :disabled="action !== ''"
        @click="runAction('run the scheduler', '/admin/reports/run-scheduler', 'Scheduler checked and due tasks were dispatched.')">
        <p class="font-semibold text-amber-300">Run scheduler now</p>
        <p class="mt-1 text-xs text-gray-400">Runs tasks due at this moment; it does not replace cron.</p><span
          class="mt-3 block text-xs text-amber-400">{{ action === 'run the scheduler' ? 'Running...' : 'Run action'
          }}</span>
      </button>
      <button
        class="rounded-lg border border-blue-500/30 bg-blue-500/10 p-4 text-left hover:bg-blue-500/20 disabled:opacity-50"
        :disabled="action !== ''"
        @click="runAction('clear the cache', '/admin/reports/clear-cache', 'Application cache cleared.')">
        <p class="font-semibold text-blue-300">Clear application cache</p>
        <p class="mt-1 text-xs text-gray-400">Clears cache, config and compiled views.</p><span
          class="mt-3 block text-xs text-blue-400">{{ action === 'clear the cache' ? 'Clearing...' : 'Run action'
          }}</span>
      </button>
    </div>
    <div>
      <div class="mb-3 flex items-center justify-between">
        <h3 class="font-semibold">Registered schedules</h3><span class="text-xs text-gray-500">Defined in
          routes/console.php</span>
      </div>
      <div class="overflow-x-auto rounded-lg border border-[#343B54]">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-[#252A3D] text-xs uppercase tracking-wider text-gray-400">
            <tr>
              <th class="p-3">Task</th>
              <th class="p-3">Cadence</th>
              <th class="p-3">Implementation</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="schedule in schedules" :key="schedule.name" class="border-t border-[#343B54]">
              <td class="p-3 text-gray-200">{{ schedule.name }}</td>
              <td class="p-3 text-gray-400">{{ schedule.cadence }}</td>
              <td class="p-3 font-mono text-xs text-cyan-300">{{ schedule.command }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </section>
</template>