<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import axios from '@/lib/axios'

/* ================= STATE ================= */
const services = ref([])
const loading = ref(true)
const error = ref(null)

const showModal = ref(false)
const selectedService = ref(null)

/* ================= FORM ================= */
const emptyForm = {
  service: '',
  type: '',
  mode: 'dummy',
  base_url: '',
  headers: '{}',
  params: '{}',
  credentials: '{}',
  is_active: true,
}

const form = ref({ ...emptyForm })

const jsonErrors = ref({
  headers: null,
  params: null,
  credentials: null,
})

/* ================= JSON VALIDATION ================= */
const validateJson = (value, field) => {
  try {
    if (!value) return {}
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
    const res = await axios.get('/admin/services')
    services.value = res.data.data || []
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
  form.value = { ...emptyForm }
  showModal.value = true
}

const openEdit = (service) => {
  selectedService.value = service
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const toggleService = async (service) => {
  try {
    await axios.patch('/admin/services/${service.id}/toggle')
    fetchServices()
  } catch (e) {
    console.error(e)
    alert('Failed to update service status')
  }
}

/* ================= SAVE ================= */
const saveService = async () => {
  const headers = validateJson(form.value.headers, 'headers')
  const params = validateJson(form.value.params, 'params')
  const credentials = validateJson(form.value.credentials, 'credentials')

  if (hasJsonErrors.value) return

  const payload = {
    service: form.value.service,
    type: form.value.type,
    mode: form.value.mode,
    base_url: form.value.base_url || null,
    headers,
    params,
    credentials,
    is_active: form.value.is_active,
  }

  try {
    if (selectedService.value) {
      await axios.put(
        `/admin/services/${selectedService.value.id}`,
        payload
      )
    } else {
      await axios.post('/admin/services', payload)
    }

    closeModal()
    fetchServices()
  } catch (e) {
    console.error(e)
    alert(
      e.response?.data?.message ||
      'Failed to save service'
    )
  }
}

/* ================= WATCH ================= */
watch(showModal, (open) => {
  if (open && selectedService.value) {
    const s = selectedService.value
    form.value = {
      service: s.name,
      type: s.type,
      mode: s.config?.mode ?? 'dummy',
      base_url: s.base_url ?? '',
      headers: JSON.stringify(s.headers ?? {}, null, 2),
      params: JSON.stringify(s.params ?? {}, null, 2),
      credentials: JSON.stringify(s.credentials ?? {}, null, 2),
      is_active: s.is_active,
    }
  }
})

onMounted(fetchServices)
</script>

<template>
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Service Management</h1>
        <button
          class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded"
          @click="openCreate"
        >
          + Add Service
        </button>
      </div>

      <div v-if="loading">Loading services…</div>
      <div v-else-if="error" class="text-red-600">{{ error }}</div>

      <table v-else class="w-full border rounded">
        <thead class="bg-gray-800 text-white">
          <tr>
            <th class="p-3">Service</th>
            <th class="p-3">Type</th>
            <th class="p-3">Mode</th>
            <th class="p-3">Status</th>
            <th class="p-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="s in services"
            :key="s.id"
            class="border-t hover:bg-gray-50"
          >
            <td class="p-3">{{ s.name }}</td>
            <td class="p-3">{{ s.type }}</td>
            <td class="p-3"> {{ s.config?.mode ?? '—' }} </td>
            <td class="p-3">
              <span :class="s.is_active ? 'text-green-600' : 'text-red-600'">
                {{ s.is_active ? 'Active' : 'Disabled' }}
              </span>
            </td>
            <td class="p-3 flex gap-3">
              <button class="text-indigo-600" @click="openEdit(s)">Edit</button>
              <button
                :class="s.is_active ? 'text-red-600' : 'text-green-600'"
                @click="toggleService(s)"
              >
                {{ s.is_active ? 'Disable' : 'Enable' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL -->
    <div
      v-if="showModal"
      class="fixed inset-0 bg-blue/50 flex items-center justify-center z-50"
      @click.self="closeModal"
    >
      <div class="bg-[#0F1724] border-[#1f3348] border w-full max-w-xl rounded-2xl p-6 relative">
        <button
          class="absolute top-3 right-3 text-gray-500 hover:text-black"
          @click="closeModal"
        >
          ✕
        </button>

        <h2 class="text-lg font-semibold mb-4">
          {{ selectedService ? 'Edit Service' : 'Add Service' }}
        </h2>

        <div class="space-y-3">
          <input v-model="form.service" placeholder="Service Name" class="input" />
          <select v-model="form.type" class="input">
            <option disabled value="">Select type</option>
            <option value="ngx">NGX</option>
            <option value="crypto">Crypto</option>
            <option value="fx">FX</option>
            <option value="payment">Payment</option>
            <option value="cscs">CSCS</option>
          </select>

          <select v-model="form.mode" class="input">
            <option value="dummy">Dummy</option>
            <option value="test">Test</option>
            <option value="live">Live</option>
          </select>

          <input v-model="form.base_url" placeholder="Base URL" class="input" />

          <textarea v-model="form.headers" placeholder="Headers (JSON)" class="input" />
          <p v-if="jsonErrors.headers" class="text-red-500 text-xs">{{ jsonErrors.headers }}</p>

          <textarea v-model="form.params" placeholder="Params (JSON)" class="input" />
          <textarea v-model="form.credentials" placeholder="Credentials (JSON)" class="input" />

          <label class="flex items-center gap-2">
            <input type="checkbox" v-model="form.is_active" />
            Active
          </label>
        </div>

        <div class="flex justify-end gap-3 mt-6">
          <button @click="closeModal">Cancel</button>
          <button
            class="bg-indigo-600 text-white px-4 py-2 rounded"
            @click="saveService"
          >
            Save
          </button>
        </div>
      </div>
    </div>

</template>

<style scoped>
.input {
  width: 100%;
  border: 1px solid #d1d5db;
  padding: 0.5rem;
  border-radius: 0.375rem;
  color: gray;
}
</style>
