<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div ref="receipt" id="receipt-content"
      class="bg-[#0F1724] border border-[#1f3348] w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">
      
      <div class="p-6 border-b border-[#1f3348] flex justify-between items-center">
        <h3 class="text-lg font-bold">Transaction Receipt</h3>
        <button @click="$emit('close')" class="text-2xl text-gray-400 hover:text-white">&times;</button>
      </div>

      <div class="p-6 space-y-4">
        <div class="flex flex-col items-center py-4 border-b border-[#1f3348]/50">
          <div :class="txn.type === 'deposit' ? 'text-green-400' : 'text-white'" class="text-3xl font-bold">
            {{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.amount).toLocaleString() }}
          </div>
          <div class="mt-1 text-xs tracking-widest text-gray-400 uppercase">{{ txn.type.replace('_', ' ') }}</div>
        </div>

        <div class="space-y-3">
          <div class="flex justify-between text-sm">
            <span class="text-gray-400">Status</span>
            <span class="text-green-400 capitalize">{{ txn.status }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-gray-400">Date</span>
            <span class="text-white">{{ formatDate(txn.created_at) }}</span>
          </div>

          <template v-if="txn.type === 'currency_change'">
            <div class="p-4 space-y-3 border bg-blue-500/5 rounded-xl border-blue-500/20">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold tracking-wider text-blue-400 uppercase">Exchange Details</span>
                <div class="px-2 py-0.5 rounded-full bg-blue-500/20 text-[10px] text-blue-300 border border-blue-500/30">
                  Live Rate
                </div>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-400">Sent</span>
                <span class="text-white">{{ txn.currency }} {{ Number(txn.amount).toLocaleString() }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-400">Received (Credited)</span>
                <span class="font-bold text-green-400">
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
            <div class="flex justify-between text-sm"><span class="text-gray-400">Bank</span><span class="text-white">{{ txn.meta.bank }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-400">Account</span><span class="text-white">{{ txn.meta.acc_no }}</span></div>
          </template>

          <div class="flex justify-between text-sm">
            <span class="text-gray-400">Reference</span>
            <span class="text-gray-500 font-mono text-[10px]">{{ txn.meta?.reference || txn.id }}</span>
          </div>
        </div>
      </div>

      <div class="flex gap-3 p-6 no-pdf">
        <button 
          @click="downloadPDF"
          :disabled="isDownloading"
          class="flex items-center justify-center flex-1 gap-2 py-3 font-bold text-white transition bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 rounded-xl">
          <svg v-if="!isDownloading" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          <span>{{ isDownloading ? 'Generating...' : 'Download PDF' }}</span>
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
import { ref } from 'vue';
import html2pdf from 'html2pdf.js';

const props = defineProps(['show', 'txn']);
defineEmits(['close']);

const receipt = ref(null);
const isDownloading = ref(false);

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleString('en-NG', { dateStyle: 'medium', timeStyle: 'short' });
};

const downloadPDF = async () => {
  isDownloading.value = true;
  
  const element = receipt.value;
  const options = {
    margin: 0,
    filename: `Receipt-${props.txn.id}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { 
      scale: 2, 
      useCORS: true, 
      backgroundColor: '#0F1724' 
    },
    jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
  };

  try {
    const actionButtons = element.querySelector('.no-pdf');
    if (actionButtons) actionButtons.style.display = 'none';

    await html2pdf().set(options).from(element).save();
    
    if (actionButtons) actionButtons.style.display = 'flex';
  } catch (error) {
    console.error("PDF Export failed", error);
  } finally {
    isDownloading.value = false;
  }
};
</script>