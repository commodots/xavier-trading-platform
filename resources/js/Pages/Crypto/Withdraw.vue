<template>
  <MainLayout>
    <div class="max-w-2xl mx-auto space-y-6">
      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      <!-- Header -->
       <button @click="router.push('/trading')" class="flex items-center text-gray-400 hover:text-white transition group">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back to Trading
      </button>

      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold">💸 Withdraw USDT</h1>
          <p class="text-gray-400 text-sm mt-1">Withdraw USDT from your trading account</p>
        </div>
      </div>

      <!-- Balance -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="text-xl font-semibold mb-4">Account Balance</h2>
        <div class="text-3xl font-bold text-[#00D4FF]">${{ balance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</div>
        <p class="text-gray-400 text-sm mt-1">Available for withdrawal</p>
      </div>

      <!-- Withdrawal Form -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="text-xl font-semibold mb-4">Withdrawal Request</h2>

        <form @submit.prevent="submitWithdrawal" class="space-y-4">
          <div>
            <label class="block text-sm text-gray-400 mb-2">TRON Address (TRC20)</label>
            <input
              v-model="form.address"
              type="text"
              placeholder="TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
              class="w-full px-4 py-3 bg-[#111827] border border-[#1f3348] rounded-lg text-white placeholder-gray-600 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none"
              required
            />
          </div>

          <div>
            <label class="block text-sm text-gray-400 mb-2">Amount (USDT)</label>
            <input
              v-model.number="form.amount"
              type="number"
              placeholder="100"
              min="1"
              step="0.01"
              :max="balance"
              class="w-full px-4 py-3 bg-[#111827] border border-[#1f3348] rounded-lg text-white placeholder-gray-600 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none"
              required
            />
          </div>

          <div class="bg-yellow-900/20 border border-yellow-600/30 rounded-lg p-4">
            <h3 class="font-semibold text-yellow-400 mb-2">⚠️ Security Check</h3>
            <p class="text-sm text-gray-300 mb-3">
              For security, withdrawals require email confirmation. Check your email after submitting.
            </p>
            <label class="flex items-center">
              <input
                v-model="form.confirmed"
                type="checkbox"
                class="mr-2"
                required
              />
              <span class="text-sm text-gray-300">
                I confirm this withdrawal and understand it cannot be reversed
              </span>
            </label>
          </div>

          <button
            type="submit"
            :disabled="loading || !form.address || !form.amount || !form.confirmed"
            class="w-full bg-red-600 text-white px-4 py-3 rounded-lg font-bold hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
          >
            {{ loading ? 'Processing...' : '🚀 Submit Withdrawal' }}
          </button>
        </form>

        <div v-if="errorMessage" class="mt-4 p-3 bg-red-900/20 border border-red-600/30 rounded-lg">
          <p class="text-red-400">{{ errorMessage }}</p>
        </div>

        <div v-if="successMessage" class="mt-4 p-3 bg-green-900/20 border border-green-600/30 rounded-lg">
          <p class="text-green-400">{{ successMessage }}</p>
        </div>
      </div>

      <!-- Recent Withdrawals -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="text-xl font-semibold mb-4">Recent Withdrawals</h2>
        <div v-if="withdrawals.length === 0" class="text-gray-400 text-center py-8">
          No recent withdrawals
        </div>
        <div v-else class="space-y-3">
          <div v-for="withdrawal in withdrawals.slice(0, 5)" :key="withdrawal.id"
               class="flex justify-between items-center p-3 bg-[#111827] rounded-lg">
            <div>
              <p class="font-semibold">{{ withdrawal.amount }} USDT</p>
              <p class="text-sm text-gray-400">{{ formatDate(withdrawal.created_at) }}</p>
            </div>
            <span :class="withdrawal.status === 'completed' ? 'bg-green-600/20 text-green-400' : 'bg-yellow-600/20 text-yellow-400'"
                  class="px-2 py-1 rounded text-sm">
              {{ withdrawal.status }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import MainLayout from '@/Layouts/MainLayout.vue'
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue'
import api from '@/api'

const getUser = () => JSON.parse(localStorage.getItem('user') || '{}');
const user = ref(getUser());
const showPrompt = ref(false);

const form = ref({
  address: '',
  amount: '',
  confirmed: false
})
const loading = ref(false)
const balance = ref(0)
const withdrawals = ref([])
const errorMessage = ref('')
const successMessage = ref('')

const router = useRouter()

const loadBalance = async () => {
  try {
    const res = await api.get('/wallet/balances')
    balance.value = res.data.data.cleared_balance_usd || 0
  } catch (e) {
    console.error(e)
  }
}

const loadWithdrawals = async () => {
  try {
    const res = await api.get('/transactions')
    withdrawals.value = res.data.filter(t => t.type === 'withdrawal' && t.currency === 'USDT')
  } catch (e) {
    console.error(e)
  }
}

const submitWithdrawal = async () => {
  if (!form.value.confirmed) {
    errorMessage.value = 'Please confirm the withdrawal'
    return
  }

  loading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    const res = await api.post('/crypto/withdraw', {
      address: form.value.address,
      amount: form.value.amount
    })

    successMessage.value = 'Withdrawal request submitted! Check your email for confirmation.'
    form.value = { address: '', amount: '', confirmed: false }
    await loadBalance()
    await loadWithdrawals()
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Withdrawal failed'
  } finally {
    loading.value = false
  }
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

onMounted(() => {
  loadBalance()
  loadWithdrawals()
})
</script>