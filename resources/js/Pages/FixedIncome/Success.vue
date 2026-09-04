<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import MainLayout from '@/Layouts/MainLayout.vue';
import api from '@/api'

const route = useRoute()
const router = useRouter()
const investment = ref(null)
const error = ref('')
onMounted(
  async () => {
    try {
      investment.value = (
        await api.get(`/fixed-income/investments/${route.params.id}`)).data.data
    } catch (exception) {
      error.value = exception.response?.data?.message || 'Unable to load investment.'
    }
  }
)
</script>

<template>
  <MainLayout>
    <div class="mx-auto max-w-2xl space-y-6">
      <div class="rounded-xl border border-emerald-500/30 bg-[#0F1724] p-8 text-center">
        <p class="text-sm uppercase tracking-widest text-emerald-400">Investment successful</p>
        <h1 class="mt-3 text-3xl font-semibold text-white">Your investment is ready</h1>
        <p v-if="error" class="mt-4 text-red-400">{{ error }}</p>
        <div v-if="investment" class="mt-8 grid gap-4 text-left sm:grid-cols-2">
          <div>
            <p class="text-sm text-gray-500">Reference</p>
            <p class="mt-1 text-white">{{ investment.reference }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Product</p>
            <p class="mt-1 text-white">{{ investment.product?.name }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Investment</p>
            <p class="mt-1 text-white">{{ investment.principal_amount }} {{ investment.currency }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Expected interest</p>
            <p class="mt-1 text-white">{{ investment.expected_interest }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Expected maturity</p>
            <p class="mt-1 text-white">{{ investment.expected_maturity_amount }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500">Status</p>
            <p class="mt-1 capitalize text-cyan-400">{{ investment.status?.replace('_', ' ') }}</p>
          </div>
        </div>
        <div class="mt-8 flex flex-wrap justify-center gap-3"><button
            class="rounded-lg bg-cyan-400 px-4 py-3 font-semibold text-[#0F1724]"
            @click="router.push(`/fixed-income/investments/${investment.id}`)">View investment</button><button
            class="rounded-lg border border-[#1f3348] px-4 py-3 text-white" @click="router.push('/fixed-income')">Back to Fixed Income</button></div>
      </div>
    </div>
  </MainLayout>
</template>
