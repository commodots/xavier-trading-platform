<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MainLayout from '@/Layouts/MainLayout.vue'
import api from '@/api'
import SkeletonLoader from '@/Components/SkeletonLoader.vue'
import { fixedIncomeCurrency } from '@/lib/fixedIncomeFormatters'

const route = useRoute()
const router = useRouter()
const product = ref(null)
const amount = ref('')
const calculation = ref(null)
const error = ref('')
const calculating = ref(false)
const canContinue = computed(() => Number(amount.value) > 0 && calculation.value)

onMounted(async () => {
  try {
    product.value = (
      await api.get(`/fixed-income/products/${route.params.id}`)).data.data
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to load product.'
  }
})

const calculate = async () => {
  calculation.value = null
  if (!amount.value) return
  calculating.value = true
  error.value = ''
  try {
    calculation.value = (
      await api.post(`/fixed-income/products/${route.params.id}/calculate`,
        {
          amount: Number(amount.value)
        })).data.data
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to calculate return.'
  }
  finally {
    calculating.value = false
  }
}
</script>

<template>
  <MainLayout>
    <div class="mx-auto max-w-2xl space-y-6">
      <div>
        <button class="text-sm text-cyan-400" @click="router.back()">
          Back
        </button>
        <h1 class="mt-3 text-3xl font-semibold text-white">Invest in {{ product?.name }}</h1>
      </div>
      <SkeletonLoader v-if="!product && !error" type="card" :count="2" class="opacity-40" />
      <section class="rounded-xl border border-[#1f3348] bg-[#0F1724] p-6">
        <label class="text-sm text-gray-400">Investment amount ({{ product?.currency }})</label>
        <input v-model="amount" type="number" min="0"
          class="mt-2 w-full rounded-lg border border-[#1f3348] bg-[#0B132B] p-3 text-white" @input="calculate">
        <div v-if="calculating" class="mt-6 grid gap-4 sm:grid-cols-3 animate-pulse">
          <div v-for="item in 3" :key="item" class="h-16 rounded-lg bg-[#16213A]" />
        </div>
        <div v-if="calculation" class="mt-6 grid gap-4 sm:grid-cols-3">
          <div>
            <p class="text-sm text-gray-500">Investment</p>
            <p class="mt-1 text-white">{{ fixedIncomeCurrency(calculation.amount, product?.currency) }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Expected interest</p>
            <p class="mt-1 text-white">{{ fixedIncomeCurrency(calculation.expected_interest, product?.currency) }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Maturity value</p>
            <p class="mt-1 text-white">{{ fixedIncomeCurrency(calculation.expected_maturity_amount, product?.currency) }}</p>
          </div>
        </div>
        <p v-if="error" class="mt-4 text-red-400">{{ error }}</p>
        <button :disabled="!canContinue"
          class="mt-6 w-full rounded-lg bg-cyan-400 px-4 py-3 font-semibold text-[#0F1724] disabled:opacity-40"
          @click="router.push({ path: `/fixed-income/${route.params.id}/review`, query: { amount: amount } })">
          Continue
        </button>
      </section>
    </div>
  </MainLayout>
</template>
