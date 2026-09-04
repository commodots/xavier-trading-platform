<script setup>

import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";

const route = useRoute();
const router = useRouter();
const product = ref(null);
const error = ref('')

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
    <button class="text-cyan-400" @click="router.push('/admin/fixed-income/products')">
      Back
    </button>
    <p v-if="error" class="text-red-400">{{ error }}</p>
    <section v-if="product" class="rounded-xl border border-[#1f3348] bg-[#0F1724] p-6">
      <h1 class="text-3xl font-semibold text-white">{{ product.name }}</h1>
      <p class="mt-1 text-gray-400">{{ product.code }} · {{ product.status }}</p>
      <div class="mt-8 grid gap-5 sm:grid-cols-2">
        <div
          v-for="key in ['type', 'description', 'currency', 'issuer', 'minimum_amount', 'maximum_amount', 'interest_rate', 'rate_type', 'interest_frequency', 'tenor_days', 'execution_mode', 'provider', 'maximum_capacity', 'maximum_user_capacity', 'maturity_payout', 'allow_reinvestment']"
          :key="key">
          <p class="text-sm text-gray-500">{{ key.replaceAll('_', ' ') }}</p>
          <p class="mt-1 text-white">{{ product[key] ?? 'Not specified' }}</p>
        </div>
      </div>
    </section>
  </div>
</MainLayout>
</template>
