<script setup>

import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";
import SkeletonLoader from '@/Components/SkeletonLoader.vue'
import { fixedIncomeCurrency, fixedIncomeLabel, fixedIncomePercent } from '@/lib/fixedIncomeFormatters'

const route = useRoute();
const router = useRouter();
const product = ref(null);
const error = ref('')

const goBack = () => {
  if (window.history.state?.back) {
    router.back()
    return
  }

  router.push('/admin/fixed-income/products')
}

onMounted(
  async () => {
    try {
      product.value = (await api.get(`/admin/fixed-income/products/${route.params.id}`)).data.data
    } catch (exception) {
      error.value = exception.response?.data?.message || 'Unable to load product.'
    }
  }
)
</script>

<template>
  <MainLayout>
  <div class="max-w-3xl space-y-6">
    <button
      type="button"
      class="text-sm text-cyan-400 transition hover:text-cyan-300"
      @click="goBack"
    >
      Back to products
    </button>
    <p v-if="error" class="text-red-400">{{ error }}</p>
    <SkeletonLoader v-if="!product && !error" type="card" :count="2" class="opacity-40" />
    <section v-if="product" class="rounded-xl border border-[#1f3348] bg-[#0F1724] p-6">
      <h1 class="text-3xl font-semibold text-white">{{ product.name }}</h1>
      <p class="mt-1 text-gray-400">{{ product.code }} · {{ fixedIncomeLabel(product.status) }}</p>
      <div class="mt-8 grid gap-5 sm:grid-cols-2 capitalize">
        <div
          v-for="key in ['type', 'description', 'currency', 'issuer', 'minimum_amount', 'maximum_amount', 'interest_rate', 'rate_type', 'interest_frequency', 'tenor_days', 'execution_mode', 'provider', 'maximum_capacity', 'maximum_user_capacity', 'maturity_payout', 'allow_reinvestment']"
          :key="key">
          <p class="text-sm text-gray-500">{{ key.replaceAll('_', ' ') }}</p>
          <p class="mt-1 text-white">{{ ['minimum_amount', 'maximum_amount', 'maximum_capacity', 'maximum_user_capacity'].includes(key) ? fixedIncomeCurrency(product[key], product.currency) : key === 'interest_rate' ? fixedIncomePercent(product[key]) : ['type', 'rate_type', 'interest_frequency', 'execution_mode', 'maturity_payout'].includes(key) ? fixedIncomeLabel(product[key]) : product[key] ?? 'Not specified' }}</p>
        </div>
      </div>
    </section>
  </div>
</MainLayout>
</template>
