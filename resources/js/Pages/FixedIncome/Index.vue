<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import MainLayout from '@/Layouts/MainLayout.vue'
import api from '@/api'
import SkeletonLoader from '@/Components/SkeletonLoader.vue'
import { fixedIncomeCurrency, fixedIncomeLabel, fixedIncomePercent } from '@/lib/fixedIncomeFormatters'

const router = useRouter()
const products = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const response = await api.get('/fixed-income/products')
    products.value = response.data.data || []
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to load products.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <MainLayout>
    <div class="space-y-6">
      <div class="flex items-end justify-between gap-4">
        <div>
          <p class="text-xs uppercase tracking-widest text-cyan-400">Investments</p>
          <h1 class="mt-2 text-3xl font-semibold text-white">Fixed Income</h1>
          <p class="mt-1 text-gray-400">Choose an available product and see the return before investing.</p>
        </div>
        <button class="rounded-lg border border-[#1f3348] px-4 py-2 text-sm text-gray-200"
          @click="router.push('/fixed-income/investments')">My investments</button>
      </div>
      <p v-if="error" class="text-red-400">{{ error }}</p>
      <SkeletonLoader v-if="loading" type="card" :count="6" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3" />
      <div v-else class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <article v-for="product in products" :key="product.id"
          class="rounded-xl border border-[#1f3348] bg-[#0F1724] p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-white">{{ product.name }}</h2>
              <p class="mt-1 text-sm text-gray-400">{{ product.issuer || fixedIncomeLabel(product.type) }}</p>
            </div>
            <span class="text-sm text-cyan-400">{{ product.currency }}</span>
          </div>
          <div class="mt-6 grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-gray-500">Rate</p>
              <p class="mt-1 text-xl font-semibold text-white">{{ fixedIncomePercent(product.interest_rate) }}</p>
            </div>
            <div>
              <p class="text-gray-500">Tenor</p>
              <p class="mt-1 text-white">{{ product.tenor_days ? `${product.tenor_days} days` : 'Open ended' }}</p>
            </div>
            <div>
              <p class="text-gray-500">Minimum</p>
              <p class="mt-1 text-white">{{ fixedIncomeCurrency(product.minimum_amount, product.currency) }}</p>
            </div>
            <div>
              <p class="text-gray-500">Frequency</p>
              <p class="mt-1 text-white">{{ fixedIncomeLabel(product.interest_frequency) }}</p>
            </div>
          </div>
          <button class="mt-6 w-full rounded-lg bg-cyan-400 px-4 py-3 font-semibold text-[#0F1724]"
            @click="router.push(`/fixed-income/${product.id}`)">View product</button>
        </article>
      </div>
      <p v-if="!loading && !products.length"
        class="rounded-xl border border-dashed border-[#1f3348] p-10 text-center text-gray-500">No active products are
        available.</p>
    </div>
  </MainLayout>
</template>
