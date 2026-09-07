<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";

const route = useRoute();
const router = useRouter();
const saving = ref(false);
const error = ref('');

const goBack = () => {
  if (window.history.state?.back) {
    router.back()
    return
  }

  router.push('/admin/fixed-income/products')
}
const form = reactive(
  {
    name: '',
    description: '',
    issuer: '',
    interest_rate: null,
    tenor_days: null,
    status: 'draft'
  })

onMounted(
  async () => {
    try {
      Object.assign(form,
        (await api.get(`/admin/fixed-income/products/${route.params.id}`)).data.data)
    }
    catch (exception) {
      error.value = exception.response?.data?.message || 'Unable to load product.'
    }
  })

const submit = async () => {
  saving.value = true;
  error.value = '';
  try {
    await api.put(`/admin/fixed-income/products/${route.params.id}`, form);
    router.push(`/admin/fixed-income/products/${route.params.id}`)
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to update product.'
  }
  finally {
    saving.value = false
  }
}
</script>
<template>
  <MainLayout>
  <div class="max-w-2xl space-y-6">
    <button
      type="button"
      class="text-sm text-cyan-400 transition hover:text-cyan-300"
      @click="goBack"
    >
      Back to product
    </button>
    <h1 class="text-3xl font-semibold text-white">Edit product</h1>
    <form class="grid gap-4 rounded-xl border border-[#1f3348] bg-[#0F1724] p-6" @submit.prevent="submit">

      <label v-for="field in ['name', 'description', 'issuer', 'interest_rate', 'tenor_days', 'minimum_amount', 'maximum_amount', 'maximum_user_capacity', 'early_withdrawal_penalty', 'subscription_fee', 'maximum_capacity', 'provider']" :key="field"
        class="text-sm capitalize text-gray-400">

        {{ field.replaceAll('_', ' ') }}

        <input v-model="form[field]" :type="['interest_rate', 'tenor_days', 'minimum_amount', 'maximum_amount', 'maximum_user_capacity', 'early_withdrawal_penalty', 'subscription_fee', 'maximum_capacity'].includes(field) ? 'number' : field.includes('date') ? 'date' : 'text'"
          class="mt-1 w-full rounded-lg border border-[#1f3348] bg-[#0B132B] p-3 text-white">

      </label>

      <label v-for="field in ['start_date', 'end_date']" :key="field" class="text-sm capitalize text-gray-400">
        {{ field.replaceAll('_', ' ') }}
        <input v-model="form[field]" type="date" class="mt-1 w-full rounded-lg border border-[#1f3348] bg-[#0B132B] p-3 text-white">
      </label>
      <label v-for="field in ['open_ended', 'maximum_open_ended', 'early_withdrawal_allowed', 'capitalise_interest', 'allow_reinvestment']" :key="field" class="flex items-center gap-2 text-sm text-gray-300 capitalize">
        <input v-model="form[field]" type="checkbox"> {{ field.replaceAll('_', ' ') }}
      </label>
      <label v-for="field in ['rate_type', 'interest_frequency', 'subscription_fee_type', 'execution_mode', 'calculation_method', 'day_count_basis', 'maturity_payout', 'status']" :key="field" class="text-sm capitalize text-gray-400">
        {{ field.replaceAll('_', ' ') }}
        <select v-model="form[field]" class="mt-1 w-full rounded-lg border border-[#1f3348] bg-[#0B132B] p-3 text-white capitalize">
          <option v-for="option in {
            rate_type: ['fixed', 'variable'],
            interest_frequency: ['monthly', 'quarterly', 'semi_annual', 'annual', 'at_maturity'],
            subscription_fee_type: ['none', 'fixed', 'percentage'],
            execution_mode: ['manual', 'automated'],
            calculation_method: ['simple_interest', 'compound_interest'],
            day_count_basis: ['actual_365', 'actual_360', 'actual_366'],
            maturity_payout: ['wallet', 'rollover'],
            status: ['draft', 'active', 'suspended', 'closed']
          }[field]" :key="option" :value="option">{{ option }}</option>
        </select>
      </label>

      <p v-if="error" class="text-red-400">{{ error }}</p>
      <button :disabled="saving" class="rounded-lg bg-cyan-400 px-4 py-3 font-semibold text-[#0F1724]">
        {{ saving ? 'Saving...' : 'Save changes'}}
      </button>
    </form>
  </div>
  </MainLayout>
</template>
