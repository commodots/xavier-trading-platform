<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import MainLayout from '@/Layouts/MainLayout.vue'
import api from '@/api'

const router = useRouter()
const investments = ref([])
const loading = ref(true)
const error = ref('')

onMounted(
  async () => {
    try {
      investments.value =
        (
          await api.get('/fixed-income/investments')).data.data?.data || []

    } catch (exception) {
      error.value = exception.response?.data?.message || 'Unable to load investments.'
    }
    finally {
      loading.value = false
    }
  })
</script>

<template>
  <MainLayout>
    <div class="space-y-6">
      <div class="flex items-end justify-between">
        <div>
          <p class="text-xs uppercase tracking-widest text-cyan-400">Portfolio</p>
          <h1 class="mt-2 text-3xl font-semibold text-white">My investments</h1>
        </div><button class="text-sm text-cyan-400" @click="router.push('/fixed-income')">Browse products</button>
      </div>
      <p v-if="error" class="text-red-400">{{ error }}</p>
      <p v-if="loading" class="text-gray-400">Loading investments...</p>
      <div v-else class="overflow-x-auto rounded-xl border border-[#1f3348] bg-[#0F1724]">
        <table class="min-w-full text-left text-sm">
          <thead class="text-gray-500">
            <tr>
              <th class="p-4">Reference</th>
              <th class="p-4">Product</th>
              <th class="p-4">Principal</th>
              <th class="p-4">Interest</th>
              <th class="p-4">Maturity</th>
              <th class="p-4">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="investment in investments" :key="investment.id"
              class="cursor-pointer border-t border-[#1f3348] text-white hover:bg-[#16213A]"
              @click="router.push(`/fixed-income/investments/${investment.id}`)">
              <td class="p-4 text-cyan-400">{{ investment.reference }}</td>
              <td class="p-4">{{ investment.product?.name || '-' }}</td>
              <td class="p-4">{{ investment.principal_amount }} {{ investment.currency }}</td>
              <td class="p-4">{{ investment.expected_interest }}</td>
              <td class="p-4">{{ investment.maturity_date || '-' }}</td>
              <td class="p-4 capitalize">{{ investment.status?.replace('_', ' ') }}</td>
            </tr>
          </tbody>
        </table>
        <p v-if="!investments.length" class="p-8 text-center text-gray-500">No investments yet.</p>
      </div>
    </div>
  </MainLayout>
</template>
