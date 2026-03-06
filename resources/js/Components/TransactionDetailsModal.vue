<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div id="receipt-content"
      class="bg-[#0F1724] border border-[#1f3348] w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
      <div class="p-6 border-b border-[#1f3348] flex justify-between items-center">
        <h3 class="text-lg font-bold">Transaction Receipt</h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-white text-2xl">&times;</button>
      </div>

      <div class="p-6 space-y-4">
        <div class="flex flex-col items-center py-4 border-b border-[#1f3348]/50">
          <div :class="txn.type === 'deposit' ? 'text-green-400' : 'text-white'" class="text-3xl font-bold">
            {{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.amount).toLocaleString() }}
          </div>
          <div class="text-xs text-gray-400 uppercase tracking-widest mt-1">{{ txn.type }}</div>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between text-sm"><span class="text-gray-400">Status</span><span
              class="text-green-400 capitalize">{{ txn.status }}</span></div>
          <div class="flex justify-between text-sm"><span class="text-gray-400">Date</span><span class="text-white">{{
            formatDate(txn.created_at) }}</span></div>

          <template v-if="txn.type === 'currency_change'">
            <div class="p-4 bg-blue-500/5 rounded-xl border border-blue-500/20 space-y-3">
              <div class="flex justify-between items-center">
                <span class="text-xs font-bold text-blue-400 uppercase tracking-wider">Exchange Details</span>
                <div
                  class="px-2 py-0.5 rounded-full bg-blue-500/20 text-[10px] text-blue-300 border border-blue-500/30">
                  Live Rate
                </div>
              </div>

              <div class="flex justify-between text-sm">
                <span class="text-gray-400">Sent (Debited)</span>
                <span class="text-white font-medium">
                  {{ txn.currency }} {{ Number(txn.amount).toLocaleString() }}
                </span>
              </div>

              <div class="flex justify-center my-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
              </div>

              <div class="flex justify-between text-sm">
                <span class="text-gray-400">Received (Credited)</span>
                <span class="text-green-400 font-bold">
                  {{ txn.meta?.to_currency }} {{ Number(txn.meta?.received_amount || 0).toLocaleString() }}
                </span>
              </div>

              <div class="pt-2 border-t border-blue-500/10 flex justify-between text-[11px]">
                <span class="text-gray-500">Exchange Rate</span>
                <span class="text-gray-300">
                  1 {{ txn.meta?.to_currency === 'USD' ? 'USD' : 'NGN' }} =
                  {{ Number(txn.meta?.exchange_rate).toLocaleString() }}
                  {{ txn.currency }}
                </span>
              </div>
            </div>
          </template>

          <template v-if="txn.meta?.bank">
            <div class="flex justify-between text-sm"><span class="text-gray-400">Bank</span><span class="text-white">{{
              txn.meta.bank }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-400">Account</span><span
                class="text-white">{{ txn.meta.acc_no }}</span></div>
          </template>

          <div class="flex justify-between text-sm"><span class="text-gray-400">Ref</span><span
              class="text-gray-500 font-mono text-[10px]">{{ txn.id }}</span></div>
        </div>
      </div>

      <div class="p-6 flex gap-3">
        <button
          class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition flex items-center justify-center gap-2">
          <span>Share Receipt</span>
        </button>
        <button @click="$emit('close')"
          class="flex-1 py-3 bg-[#1f3348] hover:bg-[#2d4663] text-white rounded-xl font-semibold transition">
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps(['show', 'txn']);
defineEmits(['close']);

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
};


</script>