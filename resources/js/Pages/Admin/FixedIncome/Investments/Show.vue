<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";
import SkeletonLoader from '@/Components/SkeletonLoader.vue'
import { fixedIncomeCurrency, fixedIncomeDate, fixedIncomeLabel, fixedIncomePercent } from '@/lib/fixedIncomeFormatters'

const route = useRoute();
const router = useRouter();
const investment = ref(null);
const error = ref('')

onMounted(
  async () => {
    try {
      investment.value = (await api.get(`/admin/fixed-income/investments/${route.params.id}`)).data.data
    }
    catch (exception) {
      error.value = exception.response?.data?.message || 'Unable to load investment.'

    }
  })

const action = async (name) => {
  try {
    investment.value = (
      await api.post(`/admin/fixed-income/investments/${route.params.id}/${name}`, name === 'reject' ? {
        reason: 'Rejected by administrator'
      } :
        {}
      )).data.data
  }
  catch (exception) {
    error.value = exception.response?.data?.message || 'Action failed.'
  }
}
</script>
<template>
  <MainLayout>
  <div class="space-y-6"><button class="text-cyan-400"
      @click="router.push('/admin/fixed-income/investments')">Back</button>
    <p v-if="error" class="text-red-400">{{ error }}</p>
    <SkeletonLoader v-if="!investment && !error" type="card" :count="2" class="opacity-40" />
    <section v-if="investment" class="rounded-xl border border-[#1f3348] bg-[#0F1724] p-6">
      <div class="flex justify-between">
        <div>
          <p class="text-xs text-cyan-400">{{ investment.reference }}</p>
          <h1 class="mt-2 text-3xl font-semibold text-white">{{ investment.product?.name }}</h1>
          <p class="mt-1 text-gray-400">{{ investment.user?.name }}</p>
        </div><span class="text-cyan-400">{{ fixedIncomeLabel(investment.status) }}</span>
      </div>
      <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <div
          v-for="key in ['principal_amount', 'expected_interest', 'expected_maturity_amount', 'actual_interest', 'maturity_date', 'provider_reference']"
          :key="key">
          <p class="text-sm text-gray-500">{{ key.replaceAll('_', ' ') }}</p>
          <p class="mt-1 text-white">{{ key === 'maturity_date' ? fixedIncomeDate(investment[key]) : key === 'provider_reference' ? (investment[key] || '-') : fixedIncomeCurrency(investment[key], investment.currency) }}</p>
        </div>
      </div>
      <div v-if="['pending_execution', 'failed'].includes(investment.status)" class="mt-8 flex gap-3">
        <button
          class="rounded-lg bg-emerald-500 px-4 py-2 text-white" @click="action('activate')">
          Activate
        </button>
        <button
          class="rounded-lg bg-red-500 px-4 py-2 text-white" @click="action('reject')">
          Reject
        </button>
        <button
          class="rounded-lg border border-red-500 px-4 py-2 text-red-400" @click="action('cancel')">
          Cancel
        </button>
      </div>
    </section>
  </div>
</MainLayout>
</template>
