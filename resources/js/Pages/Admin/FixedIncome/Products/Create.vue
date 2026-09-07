<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";

const router = useRouter()
const saving = ref(false)
const error = ref('')

const goBack = () => {
  if (window.history.state?.back) {
    router.back()
    return
  }

  router.push('/admin/fixed-income/products')
}

const form = reactive({
  name: '',
  code: '',
  type: 'bond',
  description: '',
  currency: 'NGN',
  issuer: '',
  status: 'draft',

  minimum_amount: 0,
  maximum_amount: null,
  maximum_open_ended: false,
  maximum_user_capacity: null,

  start_date: null,
  end_date: null,
  open_ended: false,

  interest_rate: null,
  rate_type: 'fixed',
  interest_frequency: 'at_maturity',
  tenor_days: null,

  early_withdrawal_allowed: false,
  early_withdrawal_penalty: 0,

  subscription_fee: 0,
  subscription_fee_type: 'none',

  maximum_capacity: null,

  execution_mode: 'manual',
  provider: null,

  allow_reinvestment: false,

  calculation_method: 'simple_interest',
  day_count_basis: 'actual_365',
  capitalise_interest: false,
  maturity_payout: 'wallet',

  metadata: {}
})

const submit = async () => {
  saving.value = true
  error.value = ''

  try {
    await api.post(
      '/admin/fixed-income/products',
      form
    )

    router.push(
      '/admin/fixed-income/products'
    )
  } catch (e) {
    error.value =
      e.response?.data?.message ||
      'Unable to create product.'
  } finally {
    saving.value = false
  }
}
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
    <h1 class="text-3xl font-semibold text-white">
      Create product
    </h1>

    <form class="grid gap-4 rounded-xl border border-[#1f3348] bg-[#0F1724] p-6 sm:grid-cols-2"
      @submit.prevent="submit">
      <label
        v-for="field in ['name', 'code', 'type', 'currency', 'issuer', 'minimum_amount', 'maximum_amount', 'maximum_user_capacity', 'start_date', 'end_date', 'interest_rate', 'tenor_days', 'early_withdrawal_penalty', 'subscription_fee', 'maximum_capacity', 'provider']"
        :key="field" class="text-sm text-gray-400 capitalize">
        {{ field.replaceAll('_', ' ') }}

        <input v-model="form[field]"
          :type="['minimum_amount', 'maximum_amount', 'maximum_user_capacity', 'interest_rate', 'tenor_days', 'early_withdrawal_penalty', 'subscription_fee', 'maximum_capacity'].includes(field) ? 'number' : field.includes('date') ? 'date' : 'text'"
          class="mt-1 w-full rounded-lg border border-[#1f3348] bg-[#0B132B] p-3 text-white capitalize">
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
      <p v-if="error" class="sm:col-span-2 text-red-400">
        {{ error }}
      </p>
      <button :disabled="saving" class="sm:col-span-2 rounded-lg bg-cyan-400 px-4 py-3 font-semibold text-[#0F1724]">
        {{ saving ? 'Saving...' : 'Create product' }}
      </button>
    </form>
  </div>
  </MainLayout>
</template>
