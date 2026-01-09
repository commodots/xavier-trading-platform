<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import api from '@/api';

/* ================= STATE ================= */
const services = ref([])
const loading = ref(true)
const error = ref(null)

const showModal = ref(false)
const selectedService = ref(null)
const activeTab = ref('general')
const connection = ref(null)
const config = ref(null)
const showConfirmModal = ref(false)
const confirmAction = ref(null)
const confirmService = ref(null)
const confirming = ref(false)

/* ================= FORM ================= */
const emptyForm = {
  service: '',
  type: '',
  mode: 'dummy',
  base_url: '',
  headers: '{}',
  parameters: '{}',
  credentials: '{}',
  is_active: true,
}

const form = ref({ ...emptyForm })

const jsonErrors = ref({
  headers: null,
  parameters: null,
  credentials: null,
  params: null,
})

/* ================= JSON VALIDATION ================= */
const validateJson = (value, field) => {
  try {
    if (!value || value.trim() === '') return {}
    const parsed = JSON.parse(value)
    jsonErrors.value[field] = null
    return parsed
  } catch {
    jsonErrors.value[field] = 'Invalid JSON'
    return null
  }
}

const hasJsonErrors = computed(() =>
  Object.values(jsonErrors.value).some(Boolean)
)

/* ================= FETCH ================= */
const fetchServices = async () => {
  try {
    loading.value = true
    const res = await api.get('/admin/services')
    const servicesData = res.data.services || []

    
    const servicesWithConnections = await Promise.all(
      servicesData.map(async (service) => {
        try {
          const connRes = await api.get(`/admin/services/${service.id}/connections`)
          service.connection = connRes.data.connections[0] || null
        } catch (e) {
         
          service.connection = null
        }
        return service
      })
    )

    services.value = servicesWithConnections
  } catch (e) {
    console.error(e)
    error.value = 'Failed to load services'
  } finally {
    loading.value = false
  }
}

/* ================= ACTIONS ================= */
const openCreate = () => {
  selectedService.value = null
  connection.value = null
  config.value = null
  activeTab.value = 'general'
  form.value = { ...emptyForm }
  showModal.value = true
}

const openEdit = async (service) => {
  selectedService.value = service
  activeTab.value = 'general'
  
 
  try {
    const connRes = await api.get(`/admin/services/${service.id}/connections`)
    connection.value = connRes.data.connections[0] || null 
  } catch (e) {
    console.error('Failed to fetch connection', e)
  }

 
  try {
    const configRes = await api.get(`/admin/services/${service.id}/config`)
    config.value = configRes.data.config || null
  } catch (e) {
    console.error('Failed to fetch config', e)
  }
  
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const toggleService = (service) => {
  confirmService.value = service
  confirmAction.value = service.is_active ? 'disable' : 'enable'
  showConfirmModal.value = true
}

const confirmToggle = async () => {
  confirming.value = true
  try {
    await api.patch(`/admin/services/${confirmService.value.id}/toggle`)
    showConfirmModal.value = false
    fetchServices()
  } catch (e) {
    console.error(e)
    alert('Failed to update service status')
  } finally {
    confirming.value = false
  }
}

/* ================= SAVE ================= */
const saveService = async () => {
  const headers = validateJson(form.value.headers, 'headers')
  const parameters = validateJson(form.value.parameters, 'parameters')
  const credentials = validateJson(form.value.credentials, 'credentials')
  const params = validateJson(form.value.params, 'params')

  if (hasJsonErrors.value) return

  try {
    if (selectedService.value) {
      if (connection.value) {
        await api.put(`/admin/service-connections/${connection.value.id}`, {
          mode: form.value.mode,
          base_url: form.value.base_url,
          headers,
          parameters,
          credentials,
          is_active: form.value.is_active,
        })
      }

      if (config.value) {
        await api.put(`/admin/services/${selectedService.value.id}/config`, {
          params,
          is_active: form.value.is_active,
        })
      }
    } else {
      await api.post('/admin/services', {
        name: form.value.service,
        type: form.value.type,
        is_active: form.value.is_active,
      })
    }

    closeModal()
    fetchServices()
  } catch (e) {
    console.error(e)
    alert(e.response?.data?.message || 'Failed to save service')
  }
}

/* ================= WATCH ================= */
watch(showModal, (open) => {
  if (open && selectedService.value) {
    const s = selectedService.value
    form.value = {
      service: s.name,
      type: s.type,
      is_active: s.is_active,
      mode: connection.value?.mode ?? 'dummy',
      base_url: connection.value?.base_url ?? '',
      headers: JSON.stringify(connection.value?.headers ?? {}, null, 2),
      parameters: JSON.stringify(connection.value?.parameters ?? {}, null, 2),
      credentials: JSON.stringify(connection.value?.credentials ?? {}, null, 2),
      params: JSON.stringify(config.value?.params ?? {}, null, 2),
    }
  }
})

onMounted(fetchServices)
</script>

<template>
  <div class="p-6 bg-[#1C1F2E] rounded-xl border border-[#2A314A] text-white">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold">Service Management</h2>
      <button
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium"
        @click="openCreate"
      >
        + Add Service
      </button>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-400 mx-auto"></div>
      <p class="text-gray-400 mt-2">Loading services...</p>
    </div>

    <div v-else-if="error" class="bg-red-500/10 border border-red-500/20 rounded-lg p-4">
      <p class="text-red-400">{{ error }}</p>
    </div>

    <table v-else class="w-full text-sm text-left">
      <thead class="bg-[#151a27] text-gray-400 uppercase">
        <tr>
          <th class="px-4 py-3">Service Name</th>
          <th class="px-4 py-3">Type</th>
          <th class="px-4 py-3">Mode</th>
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in services" :key="s.id" class="border-t border-[#2A314A] hover:bg-[#151a27]/50">
          <td class="px-4 py-3 font-bold">{{ s.name }}</td>
          <td class="px-4 py-3 uppercase text-xs">{{ s.type }}</td>
          <td class="px-4 py-3">{{ s.connection?.mode ?? '—' }}</td>
          <td class="px-4 py-3">
            <span :class="s.is_active ? 'text-green-400' : 'text-red-400'">
              {{ s.is_active ? 'Active' : 'Disabled' }}
            </span>
          </td>
          <td class="px-4 py-3 text-right space-x-2">
            <button @click="openEdit(s)" class="px-3 py-1 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded">Edit</button>
            <button
              @click="toggleService(s)"
              :class="s.is_active
                ? 'px-3 py-1 text-sm bg-red-600 hover:bg-red-700 text-white rounded'
                : 'px-3 py-1 text-sm bg-green-600 hover:bg-green-700 text-white rounded'"
            >
              {{ s.is_active ? 'Disable' : 'Enable' }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="services.length === 0 && !loading" class="text-center py-8 text-gray-400">
      No services configured yet.
    </div>
  </div>

  <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm px-4">
    <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A] w-full max-w-2xl shadow-2xl overflow-y-auto max-h-[90vh]">
      <h3 class="mb-4 text-lg font-bold text-white">{{ selectedService ? 'Edit Service' : 'Add Service' }}</h3>

      <div class="space-y-4 text-white">
        <div>
          <label class="block mb-1 text-xs text-gray-400">Service Name</label>
          <input v-model="form.service" placeholder="e.g., NGX FIX Gateway" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-white" />
        </div>
        <div>
          <label class="block mb-1 text-xs text-gray-400">Type</label>
          <select v-model="form.type" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-white">
            <option disabled value="">Select type</option>
            <option value="ngx">NGX</option>
            <option value="crypto">Crypto</option>
            <option value="fx">FX</option>
            <option value="payment">Payment</option>
            <option value="cscs">CSCS</option>
          </select>
        </div>
        <div>
          <label class="block mb-1 text-xs text-gray-400">Mode</label>
          <select v-model="form.mode" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-white">
            <option value="dummy">Dummy</option>
            <option value="test">Test</option>
            <option value="live">Live</option>
          </select>
        </div>
        <div>
          <label class="block mb-1 text-xs text-gray-400">Base URL</label>
          <input v-model="form.base_url" placeholder="https://api.example.com" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-white" />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block mb-1 text-xs text-gray-400">Headers (JSON)</label>
            <textarea v-model="form.headers" rows="3" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-sm text-white"></textarea>
            <p v-if="jsonErrors.headers" class="text-red-400 text-xs mt-1">{{ jsonErrors.headers }}</p>
          </div>
          <div>
            <label class="block mb-1 text-xs text-gray-400">Parameters (JSON)</label>
            <textarea v-model="form.parameters" rows="3" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-sm text-white"></textarea>
            <p v-if="jsonErrors.parameters" class="text-red-400 text-xs mt-1">{{ jsonErrors.parameters }}</p>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block mb-1 text-xs text-gray-400">Business Config (JSON)</label>
            <textarea v-model="form.params" rows="3" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-sm text-white"></textarea>
            <p v-if="jsonErrors.params" class="text-red-400 text-xs mt-1">{{ jsonErrors.params }}</p>
          </div>
          <div>
            <label class="block mb-1 text-xs text-gray-400">Credentials (JSON)</label>
            <textarea v-model="form.credentials" rows="3" class="w-full bg-[#151a27] border border-[#2A314A] p-2 rounded text-sm text-white"></textarea>
            <p v-if="jsonErrors.credentials" class="text-red-400 text-xs mt-1">{{ jsonErrors.credentials }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" v-model="form.is_active" id="is_active_form" class="w-4 h-4">
          <label for="is_active_form" class="text-xs text-gray-400">Active</label>
        </div>
      </div>

      <div class="flex justify-end gap-3 mt-6">
        <button @click="closeModal" class="px-4 py-2 text-sm text-gray-400 hover:text-white transition">Cancel</button>
        <button @click="saveService" :disabled="hasJsonErrors" class="px-4 py-2 text-sm text-white bg-blue-600 rounded-lg disabled:opacity-50 hover:bg-blue-700 transition">
          {{ selectedService ? 'Update' : 'Create' }}
        </button>
      </div>
    </div>
  </div>

  <div v-if="showConfirmModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/70 backdrop-blur-sm px-4">
    <div class="bg-[#1C1F2E] p-6 rounded-xl border border-[#2A314A] w-full max-w-sm shadow-2xl text-center">
      <div class="w-16 h-16 bg-yellow-500/10 text-yellow-500 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
        ⚠️
      </div>
      <h3 class="text-lg font-bold text-white mb-2 capitalize">{{ confirming ? 'Confirming...' : `${confirmAction} Service?` }}</h3>
      <p v-if="!confirming" class="text-gray-400 text-sm mb-6">
        Are you sure you want to {{ confirmAction }} <strong>{{ confirmService?.name }}</strong>?
        This will affect environment connectivity.
      </p>
      <p v-else class="text-blue-400 text-sm mb-6 flex items-center justify-center">
        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Updating service status...
      </p>
      <div class="flex gap-3">
        <button @click="showConfirmModal = false" :disabled="confirming" class="flex-1 px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition disabled:opacity-50">
          Cancel
        </button>
        <button @click="confirmToggle" :disabled="confirming" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
          {{ confirming ? 'Confirming...' : 'Confirm' }}
        </button>
      </div>
    </div>
  </div>
</template>