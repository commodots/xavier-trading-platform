<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api'

const investments = ref([])
const loading = ref(false)

const filters = ref({
  status: '',
  currency: '',
  execution_mode: '',
  provider: ''
})

const loadInvestments = async () => {
  loading.value = true

  try {
    const response = await api.get(
      '/admin/fixed-income/investments',
      {
        params: filters.value
      }
    )

    investments.value =
      response.data.data?.data || []
  } finally {
    loading.value = false
  }
}

onMounted(loadInvestments)
</script>

<template>
  <div class="space-y-6">

    <div>
      <h1 class="text-2xl font-semibold text-white">
        Fixed Income Investments
      </h1>
    </div>

    <div class="grid md:grid-cols-4 gap-3">

      <select v-model="filters.status" @change="loadInvestments"
        class="bg-[#111827] border border-[#1F2A44] rounded-lg p-3 text-white">
        <option value="">All Statuses</option>
        <option value="pending_execution">
          Pending Execution
        </option>
        <option value="active">
          Active
        </option>
        <option value="maturing">
          Maturing
        </option>
        <option value="redeemed">
          Redeemed
        </option>
        <option value="rejected">
          Rejected
        </option>
        <option value="failed">
          Failed
        </option>
      </select>

      <select v-model="filters.currency" @change="loadInvestments"
        class="bg-[#111827] border border-[#1F2A44] rounded-lg p-3 text-white">
        <option value="">All Currencies</option>
        <option value="NGN">NGN</option>
        <option value="USD">USD</option>
      </select>

      <select v-model="filters.execution_mode" @change="loadInvestments"
        class="bg-[#111827] border border-[#1F2A44] rounded-lg p-3 text-white">
        <option value="">All Execution</option>
        <option value="manual">Manual</option>
        <option value="automated">Automated</option>
      </select>

      <button @click="loadInvestments" class="rounded-lg px-4 py-3 bg-indigo-600 text-white">
        Refresh
      </button>

    </div>

    <div class="overflow-x-auto rounded-xl border border-[#1F2A44]">
      <table class="min-w-full text-sm">

        <thead class="bg-[#111827] text-gray-400">
          <tr>
            <th class="p-4 text-left">Reference</th>
            <th class="p-4 text-left">Investor</th>
            <th class="p-4 text-left">Product</th>
            <th class="p-4 text-left">Principal</th>
            <th class="p-4 text-left">Interest</th>
            <th class="p-4 text-left">Status</th>
            <th class="p-4 text-left">Maturity</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="investment in investments" :key="investment.id" class="border-t border-[#1F2A44] text-gray-200">
            <td class="p-4">
              {{ investment.reference }}
            </td>

            <td class="p-4">
              {{ investment.user?.name || '—' }}
            </td>

            <td class="p-4">
              {{ investment.product?.name || '—' }}
            </td>

            <td class="p-4">
              {{ investment.principal_amount }}
              {{ investment.currency }}
            </td>

            <td class="p-4">
              {{ investment.expected_interest }}
            </td>

            <td class="p-4">
              {{ investment.status }}
            </td>

            <td class="p-4">
              {{ investment.maturity_date || '—' }}
            </td>
          </tr>
        </tbody>

      </table>
    </div>

  </div>
</template>