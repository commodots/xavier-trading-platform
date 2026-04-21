<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-md h-screen w-full
  ">
    <div ref="receipt" id="receipt-content"
      class="bg-[#0F172A] border border-slate-800 w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">

      <div class="p-4 border-b border-slate-800/50 flex justify-between items-center bg-slate-900/40">
        <div class="flex items-center gap-3">
          <img src="/images/xavier-logo.png" alt="Xavier" class="w-auto h-5 opacity-90" />
          <div class="h-4 w-[1px] bg-neutral-700"></div>
          <h3 class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase">Transaction Summary</h3>
        </div>
        <button @click="$emit('close')"
          class="text-2xl text-slate-500 hover:text-white transition-colors">&times;</button>
      </div>

      <div class="p-6">
        <div class="flex flex-col items-center text-center mb-6">
          <div :class="getStatusClass(txn.status)"
            class="mb-4 px-3 py-1 bg-current/10 text-[9px] uppercase font-bold tracking-[0.2em]">
            {{ txn.status }}
          </div>
          <div
            :class="['deposit', 'sell_crypto', 'refund'].includes(txn.type?.toLowerCase()) ? 'text-green-500' : 'text-red-500'"
            class="text-4xl font-black tracking-tighter mb-3">
            {{ (['deposit', 'sell_crypto', 'refund'].includes(txn.type?.toLowerCase())) ? '+' : '-' }}{{
              txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.net_amount || txn.amount).toLocaleString() }}
          </div>
          <div
            class="px-4 py-1.5 bg-slate-800/50 rounded-lg text-[10px] tracking-[0.15em] text-slate-300 uppercase font-black border border-slate-700/50">
            {{ formatType(txn.type) }}
          </div>
        </div>

        <div class="space-y-4">
          <div class="grid grid-cols-1 gap-3 py-4 border-y border-slate-800/50">
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Affected Wallet</span>
              <span class="text-xs font-bold text-slate-200">{{ txn.currency }} Wallet</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Transaction Ref</span>
              <span class="font-mono text-[10px] text-blue-400 font-bold uppercase truncate max-w-[180px]">{{ txn.reference || txn.meta?.reference || txn.id }}</span>
            </div>
            <div v-if="txn.charge !== undefined && txn.charge !== null" class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Transaction Fee</span>
              <span class="text-xs font-bold text-slate-300">{{ txn.currency === 'USD' ? '$' : '₦' }}{{
                Number(txn.charge).toLocaleString() }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Timestamp</span>
              <span class="text-xs font-bold text-slate-200">{{ formatDate(txn.created_at) }}, {{
                formatTime(txn.created_at) }}</span>
            </div>
          </div>

          <div v-if="txn.type === 'withdrawal' && (txn.meta?.bank || txn.meta?.recipient_name)"
            class="p-4 rounded-xl bg-slate-900/50 border border-slate-800/50 space-y-2">
            <h4 class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2">Outflow Details</h4>
            <div v-if="txn.meta.bank" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Destination Bank</span>
              <span class="font-bold text-slate-300">{{ txn.meta.bank }}</span>
            </div>
            <div v-if="txn.meta.acc_no" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Account Number</span>
              <span class="font-mono text-slate-300">{{ txn.meta.acc_no }}</span>
            </div>
            <div v-if="txn.meta.recipient_name" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Beneficiary</span>
              <span class="font-bold text-slate-300">{{ txn.meta.recipient_name }}</span>
            </div>
          </div>

          <div v-if="txn.type === 'deposit'"
            class="p-4 space-y-2">
            <h4 class="text-[9px] font-black text-green-500 uppercase tracking-[0.2em] mb-2">Inflow Details</h4>
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-500">Funding Method</span>
              <span class="font-bold text-slate-300 capitalize">{{ txn.meta?.gateway || 'Manual' }}</span>
            </div>
            <div v-if="txn.tx_hash" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Network Hash</span>
              <span class="font-mono text-blue-400 truncate max-w-[120px]">{{ txn.tx_hash }}</span>
            </div>
          </div>

          <div v-if="txn.type === 'currency_change'"
            class="p-4 space-y-3 bg-blue-500/5 rounded-xl border border-blue-500/20">
            <div class="flex items-center gap-2 mb-1">
              <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
              <span class="text-[9px] font-black tracking-[0.2em] text-blue-400 uppercase">Conversion Details</span>
            </div>
            
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-400">Amount Converted</span>
              <span class="font-bold text-slate-200">{{ txn.currency }} {{ Number(txn.amount).toLocaleString() }}</span>
            </div>

            <div class="flex justify-between pb-2 text-[11px] border-b border-blue-500/10">
              <span class="text-slate-400">Exchange Rate</span>
              <span class="font-bold text-white text-xs">1 {{ txn.meta?.to_currency }} = {{ txn.currency === 'NGN' ? '₦' : '$' }}{{ Number(txn.meta?.exchange_rate || txn.meta?.fx_rate_applied).toLocaleString() }}</span>
            </div>

            <div v-if="txn.meta?.received_amount" class="flex justify-between items-end pt-1">
              <span class="text-[11px] text-slate-400">Amount ({{ txn.meta?.to_currency }})</span>
              <span class="text-xl font-black text-green-500">
                 {{ txn.meta?.to_currency === 'USD' ? '$' : '₦' }}{{ Number(txn.meta?.received_amount).toLocaleString() }}
              </span>
            </div>
          </div>

          <div v-if="txn.meta?.new_balance !== undefined" class="mt-2">
            <div class="flex items-center justify-between p-3 bg-slate-900/30 rounded-xl border border-slate-800/30">
              <div>
                <p class="text-[8px] text-slate-500 uppercase font-black tracking-widest mb-0.5">Available Balance</p>
                <p class="text-base font-bold text-white">{{ txn.currency === 'USD' ? '$' : '₦' }}{{
                  Number(txn.meta.new_balance).toLocaleString() }}</p>
              </div>
              <div class="opacity-20">
                <img src="/images/xavier-logo.png" alt="Xavier" class="w-auto h-4 grayscale" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex gap-3 p-6 pt-2 no-pdf">
        <button 
          @click="downloadPDF"
          :disabled="isDownloading"
          class="flex items-center justify-center flex-1 gap-2 py-3 font-bold text-white transition bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800/50 rounded-xl shadow-lg shadow-blue-900/20">
          <svg v-if="!isDownloading" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          <span class="text-xs uppercase tracking-widest">{{ isDownloading ? 'Processing...' : 'Download PDF' }}</span>
        </button>
        <button @click="$emit('close')"
          class="flex-1 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-bold transition text-xs uppercase tracking-widest">
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
  return new Date(dateStr).toLocaleDateString('en-NG', { dateStyle: 'medium' });
};

const formatTime = (dateStr) => {
  return new Date(dateStr).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
};

const formatType = (type) => {
  return type ? type.replace(/_/g, ' ') : 'Transaction';
};

const getStatusClass = (status) => {
  switch (status?.toLowerCase()) {
    case 'completed':
    case 'filled':
    case 'success':
      return 'text-emerald-400';
    case 'pending':
    case 'processing':
      return 'text-amber-400';
    case 'failed':
    case 'cancelled':
    case 'rejected':
      return 'text-rose-400';
    default:
      return 'text-gray-400';
  }
};

const downloadPDF = async () => {
  isDownloading.value = true;

  const element = receipt.value;
  const options = {
    margin: 0,
    filename: `Xavier-Receipt-${props.txn.id.substring(0, 8)}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: {
      scale: 2,
      useCORS: true,
      backgroundColor: '#0F172A'
    },
    jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
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