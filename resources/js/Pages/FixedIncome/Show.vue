<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import MainLayout from '@/Layouts/MainLayout.vue';
import api from '@/api';
import SkeletonLoader from '@/Components/SkeletonLoader.vue'
import { fixedIncomeCurrency, fixedIncomeLabel, fixedIncomePercent } from '@/lib/fixedIncomeFormatters'

const route = useRoute()
const router = useRouter()
const product = ref(null)
const error = ref('')

onMounted(async () => {
  try {
    product.value = (await api.get(`/fixed-income/products/${route.params.id}`)).data.data
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to load product.'
  }
})
</script>

<template>
  <MainLayout>
    <div class="mx-auto max-w-3xl space-y-6">
      <button class="text-sm text-cyan-400" @click="router.back()">Back</button>
      <p v-if="error" class="text-red-400">{{ error }}</p>
      <SkeletonLoader v-if="!product && !error" type="card" :count="2" class="opacity-40" />
      <div v-if="product" class="space-y-6">
        <div>
          <p class="text-xs uppercase tracking-widest text-cyan-400">{{ product.type }}</p>
          <h1 class="mt-2 text-3xl font-semibold text-white">{{ product.name }}</h1>
          <p class="mt-2 text-gray-400">{{ product.description }}</p>
        </div>
        <section class="grid gap-4 rounded-xl border border-[#1f3348] bg-[#0F1724] p-6 sm:grid-cols-2">
          <div v-for="item in [
            ['Issuer', product.issuer],
            ['Currency', product.currency],
            ['Rate', `${fixedIncomePercent(product.interest_rate)} (${fixedIncomeLabel(product.rate_type)})`],
            ['Frequency', fixedIncomeLabel(product.interest_frequency)],
            ['Tenor', product.tenor_days ? `${product.tenor_days} days` : 'Open ended'],
            ['Minimum', fixedIncomeCurrency(product.minimum_amount, product.currency)],
            ['Maximum', product.maximum_amount ? fixedIncomeCurrency(product.maximum_amount, product.currency) : 'Unlimited'],
            ['Early withdrawal', product.early_withdrawal_allowed ? `Allowed (${fixedIncomePercent(product.early_withdrawal_penalty)})` : 'Not allowed'],
            ['Fees', `${fixedIncomeCurrency(product.subscription_fee || 0, product.currency)} (${fixedIncomeLabel(product.subscription_fee_type)})`],
            ['Maturity payout', fixedIncomeLabel(product.maturity_payout)],
            ['Reinvestment', product.allow_reinvestment ? 'Available' : 'Unavailable']
          ]"
           :key="item[0]">
            <p class="text-sm text-gray-500">{{ item[0] }}</p>
            <p class="mt-1 text-white">{{ item[1] || 'Not specified' }}</p>
          </div>
        </section>
        <button class="w-full rounded-lg bg-cyan-400 px-4 py-3 font-semibold text-[#0F1724]"
          @click="router.push(`/fixed-income/${product.id}/invest`)">Invest now</button>
      </div>
    </div>
  </MainLayout>
</template>
