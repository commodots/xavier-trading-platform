<template>
  <MainLayout>
    <div class="max-w-2xl mx-auto space-y-6">
      <!-- Header -->
       <button @click="router.push('/trading')" class="flex items-center text-gray-400 hover:text-white transition group">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Back to Trading
      </button>

      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold">💰 Deposit USDT</h1>
          <p class="text-gray-400 text-sm mt-1">Deposit USDT (TRC20) to your trading account</p>
        </div>
      </div>

      <!-- Deposit Address -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="text-xl font-semibold mb-4">Your TRON Deposit Address</h2>

        <div v-if="loading" class="text-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#00D4FF] mx-auto"></div>
          <p class="text-gray-400 mt-2">Generating address...</p>
        </div>

        <div v-else-if="address" class="space-y-4">
          <div class="bg-[#111827] p-4 rounded-lg border border-[#1f3348]">
            <p class="text-sm text-gray-400 mb-2">TRON Address (TRC20)</p>
            <p class="font-mono text-[#00D4FF] break-all text-lg">{{ address }}</p>
          </div>

          <div class="flex gap-3">
            <button
              @click="copyAddress"
              class="flex-1 bg-[#00D4FF] text-black px-4 py-3 rounded-lg font-bold hover:bg-[#00b8e6] transition"
            >
              📋 Copy Address
            </button>
            <button
              @click="showQR = !showQR"
              class="flex-1 bg-[#1f3348] text-gray-300 px-4 py-3 rounded-lg hover:bg-[#2d4a66] transition"
            >
              📱 {{ showQR ? 'Hide' : 'Show' }} QR Code
            </button>
          </div>

          <div v-if="showQR" class="text-center">
            <img :src="qrCodeUrl" alt="QR Code" class="mx-auto w-48 h-48 bg-white p-2 rounded-lg" />
          </div>

          <div class="bg-yellow-900/20 border border-yellow-600/30 rounded-lg p-4">
            <h3 class="font-semibold text-yellow-400 mb-2">⚠️ Important</h3>
            <ul class="text-sm text-gray-300 space-y-1">
              <li>• Send only USDT (TRC20) to this address</li>
              <li>• Deposits are processed automatically</li>
              <li>• Minimum deposit: 1 USDT</li>
              <li>• Network: TRON (not ETH or BSC)</li>
            </ul>
          </div>
        </div>

        <div v-else class="text-center py-8">
          <p class="text-red-400">Failed to load deposit address</p>
          <button
            @click="loadAddress"
            class="mt-4 bg-[#00D4FF] text-black px-4 py-2 rounded-lg font-bold hover:bg-[#00b8e6] transition"
          >
            Retry
          </button>
        </div>
      </div>

      <!-- Recent Deposits -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="text-xl font-semibold mb-4">Recent Deposits</h2>
        <div v-if="deposits.length === 0" class="text-gray-400 text-center py-8">
          No recent deposits
        </div>
        <div v-else class="space-y-3">
          <div v-for="deposit in deposits.slice(0, 5)" :key="deposit.id"
               class="flex justify-between items-center p-3 bg-[#111827] rounded-lg">
            <div>
              <p class="font-semibold">{{ deposit.amount }} USDT</p>
              <p class="text-sm text-gray-400">{{ formatDate(deposit.created_at) }}</p>
            </div>
            <span class="px-2 py-1 bg-green-600/20 text-green-400 rounded text-sm">
              Completed
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
import api from '@/api'

const address = ref('')
const loading = ref(true)
const showQR = ref(false)
const deposits = ref([])

const qrCodeUrl = ref('')

const router = useRouter()

const loadAddress = async () => {
  loading.value = true
  try {
    const res = await api.get('/crypto/address')
    address.value = res.data.address
    qrCodeUrl.value = res.data.qr_code_url || `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${address.value}`
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const copyAddress = async () => {
  try {
    await navigator.clipboard.writeText(address.value)
    alert('Address copied to clipboard!')
  } catch (e) {
    alert('Failed to copy address')
  }
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const loadDeposits = async () => {
  try {
    const res = await api.get('/transactions')
    deposits.value = res.data.filter(t => t.type === 'deposit' && t.currency === 'USDT')
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => {
  loadAddress()
  loadDeposits()
})
</script>