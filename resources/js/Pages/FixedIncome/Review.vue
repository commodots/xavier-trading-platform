<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import MainLayout from '@/Layouts/MainLayout.vue';
import api from '@/api';

const route = useRoute()
const router = useRouter()
const product = ref(null)
const calculation = ref(null)
const error = ref('')
const submitting = ref(false)
const idempotencyKey = typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`

onMounted(
  async () => {
    try {
      product.value = (
        await api.get(`/fixed-income/products/${route.params.id}`)).data.data
      calculation.value = (
        await api.post(`/fixed-income/products/${route.params.id}/calculate`,
          { amount: Number(route.query.amount) }
        )).data.data
    } catch (exception) {
      error.value = exception.response?.data?.message || 'Unable to prepare investment.'
    }
  })

const submitInvestment = async () => {
  submitting.value = true
  error.value = ''
  try {
    const response = await api.post(`/fixed-income/products/${route.params.id}/invest`,
      {
        amount: Number(route.query.amount),
        funding_method: 'wallet', idempotency_key: idempotencyKey
      }
    )
    router.replace(`/fixed-income/success/${response.data.data.id}`)
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Investment could not be completed.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <MainLayout>
    <div class="mx-auto max-w-2xl space-y-6">
      <h1 class="text-3xl font-semibold text-white">Review investment</h1>
      <section class="space-y-4 rounded-xl border border-[#1f3348] bg-[#0F1724] p-6">
        <div v-for="item in [
          ['Product', product?.name],
          ['Amount', calculation?.amount],
          ['Currency', product?.currency],
          ['Interest rate', `${product?.interest_rate}%`],
          ['Estimated interest', calculation?.expected_interest],
          ['Estimated maturity', calculation?.expected_maturity_amount],
          ['Tenor', product?.tenor_days ? `${product.tenor_days} days` : 'Open ended'],
          ['Funding method', 'Wallet'],
          ['Fees', product?.subscription_fee || 0]
        ]" :key="item[0]" class="flex justify-between gap-4 border-b border-[#1f3348] pb-3 text-sm">
          <span class="text-gray-500">
            {{ item[0] }}
          </span>
          <span class="text-right text-white">
            {{ item[1] || 'Not specified' }}
          </span>
        </div>
        <p v-if="error" class="text-red-400">{{ error }}</p>
        <button :disabled="submitting || !calculation"
          class="w-full rounded-lg bg-cyan-400 px-4 py-3 font-semibold text-[#0F1724] disabled:opacity-40"
          @click="submitInvestment">
          {{ submitting ? 'Submitting...' : 'Confirm investment' }}
        </button>
      </section>
    </div>
  </MainLayout>
</template>
