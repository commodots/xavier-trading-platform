<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router';
import MainLayout from '@/Layouts/MainLayout.vue';
import api from '@/api';

const route = useRoute();
const router = useRouter();
const investment = ref(null);
const error = ref('');
const loading = ref(true);
const reinvesting = ref(false)

onMounted(
  async () => {
    try {
      investment.value = (
        await api.get(`/fixed-income/investments/${route.params.id}`)).data.data
    }
    catch (exception) {
      error.value = exception.response?.data?.message || 'Unable to load investment.'
    }
    finally {
      loading.value = false
    }
  }
)

const reinvest = async () => {
  reinvesting.value = true;
  error.value = '';
  try {
    const response = await api.post(`/fixed-income/investments/${route.params.id}/reinvest`);
    router.push(`/fixed-income/investments/${response.data.data.id}`)
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to reinvest.'
  } finally {
    reinvesting.value = false
  }
}
</script>

<template>
  <MainLayout>
    <div class="mx-auto max-w-3xl space-y-6">
      <button class="text-sm text-cyan-400" @click="router.push('/fixed-income/investments')">
        Back to investments
      </button>
      <p v-if="loading" class="text-gray-400">Loading investment...</p>
      <p v-if="error" class="text-red-400">{{ error }}</p>
      <section v-if="investment" class="rounded-xl border border-[#1f3348] bg-[#0F1724] p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-xs uppercase tracking-widest text-cyan-400">{{ investment.reference }}</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">{{ investment.product?.name }}</h1>
          </div><span class="rounded-full bg-cyan-400/10 px-3 py-1 text-sm capitalize text-cyan-400">{{
            investment.status?.replace('_', ' ') }}</span>
        </div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2">
          <div v-for="item in [
            ['Principal', investment.principal_amount],
            ['Rate', `${investment.interest_rate}%`],
            ['Expected interest', investment.expected_interest],
            ['Expected maturity', investment.expected_maturity_amount],
            ['Actual interest', investment.actual_interest || '-'],
            ['Actual maturity', investment.actual_maturity_amount || '-'],
            ['Investment date', investment.investment_date],
            ['Execution date', investment.execution_date || '-'],
            ['Maturity date', investment.maturity_date || '-'],
            ['Redeemed date', investment.redeemed_at || '-'],
            ['Funding method', investment.funding_method],
            ['Provider', investment.provider || '-'],
            ['Provider reference', investment.provider_reference || '-']
          ]" :key="item[0]">
            <p class="text-sm text-gray-500">{{ item[0] }}</p>
            <p class="mt-1 text-white">{{ item[1] }}</p>
          </div>
        </div>
        <div class="mt-8">
          <h2 class="text-lg font-semibold text-white">Transactions</h2>
          <p v-if="!investment.transactions?.length" class="mt-3 text-gray-500">No transactions recorded.</p>
          <div v-for="transaction in investment.transactions" :key="transaction.id"
            class="mt-3 flex justify-between border-b border-[#1f3348] py-3 text-sm">
            <span class="text-gray-300">
              {{ transaction.type }}
            </span>
            <span class="text-white">
              {{ transaction.amount }} {{ transaction.currency }}
            </span>
          </div>
        </div>
        <button v-if="investment.status === 'redeemed' && investment.reinvestment_enabled" :disabled="reinvesting"
          class="mt-8 rounded-lg bg-cyan-400 px-4 py-3 font-semibold text-[#0F1724] disabled:opacity-40"
          @click="reinvest">
          {{ reinvesting ? 'Reinvesting...' : 'Reinvest' }}
        </button>
      </section>
    </div>
  </MainLayout>
</template>
