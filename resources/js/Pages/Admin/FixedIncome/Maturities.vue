<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/api'
import MainLayout from '@/Layouts/MainLayout.vue'
import SkeletonLoader from '@/Components/SkeletonLoader.vue'
import { fixedIncomeCurrency, fixedIncomeDate } from '@/lib/fixedIncomeFormatters'

const router = useRouter()
const investments = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    investments.value = (await api.get('/admin/fixed-income/investments', {
      params: { status: 'maturing', per_page: 100 },
    })).data.data?.data || []
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to load maturities.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <MainLayout>
    <div class="space-y-6">
      <div>
        <p class="text-xs uppercase tracking-widest text-cyan-400">Operations</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Maturity queue</h1>
        <p class="mt-1 text-gray-400">Investments approaching their maturity date.</p>
      </div>
      <p v-if="error" class="text-red-400">{{ error }}</p>
      <SkeletonLoader v-if="loading" type="table" :count="6" class="opacity-40" />
      <div v-else class="overflow-x-auto rounded-xl border border-[#1f3348] bg-[#0F1724]">
        <table class="min-w-full text-left text-sm">
          <thead class="text-gray-500">
            <tr>
              <th class="p-4">Reference</th>
              <th class="p-4">Investor</th>
              <th class="p-4">Product</th>
              <th class="p-4">Principal</th>
              <th class="p-4">Maturity date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="investment in investments" :key="investment.id"
              class="cursor-pointer border-t border-[#1f3348] text-white hover:bg-[#16213A]"
              @click="router.push(`/admin/fixed-income/investments/${investment.id}`)">
              <td class="p-4 text-cyan-400">{{ investment.reference }}</td>
              <td class="p-4">{{ investment.user?.name || '-' }}</td>
              <td class="p-4">{{ investment.product?.name || '-' }}</td>
              <td class="p-4">{{ fixedIncomeCurrency(investment.principal_amount, investment.currency) }}</td>
              <td class="p-4">{{ fixedIncomeDate(investment.maturity_date) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-if="!investments.length" class="p-8 text-center text-gray-500">No investments are currently maturing.</p>
      </div>
    </div>
  </MainLayout>
</template>