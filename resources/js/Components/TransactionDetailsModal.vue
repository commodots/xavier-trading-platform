<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F172A] backdrop-blur-md h-screen w-full">
    <div ref="receipt" id="receipt-content"
      class="bg-[#0F172A] border border-slate-800 w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl max-h-[90vh] overflow-y-auto">

      <div class="p-4 border-b border-slate-800/50 flex justify-between items-center bg-slate-900/40 sticky top-0 z-10">
        <div class="flex items-center gap-3">
          <img src="/images/xavier-logo.png" alt="Xavier" class="w-auto h-5 opacity-90" />
          <div class="h-4 w-[1px] bg-neutral-700"></div>
          <h3 class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase">Transaction Summary</h3>
        </div>
        <button @click="$emit('close')"
          class="text-2xl text-slate-500 hover:text-white transition-colors">&times;</button>
      </div>

      <div class="p-6">
        <!-- Header Section -->
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

        <!-- Financial Breakdown Section -->
        <div class="space-y-4">
          <!-- Core Transaction Details -->
          <div class="grid grid-cols-1 gap-3 py-4 border-y border-slate-800/50">
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Affected Wallet</span>
              <span class="text-xs font-bold text-slate-200">{{ txn.currency }} Wallet</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Transaction Ref</span>
              <span class="font-mono text-[10px] text-blue-400 font-bold uppercase truncate max-w-[180px]">{{ txn.reference || txn.meta?.reference || txn.id }}</span>
            </div>
            <div v-if="txn.external_reference" class="flex justify-between items-center">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">External Ref</span>
              <span class="font-mono text-[10px] text-slate-400 font-bold uppercase truncate max-w-[180px]">{{ txn.external_reference }}</span>
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

          <!-- Financial Breakdown -->
          <div class="p-4 rounded-xl bg-slate-900/50 border border-slate-800/50 space-y-3">
            <h4 class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2">Financial Breakdown</h4>
            
            <div v-if="txn.gross_amount !== undefined" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Gross Amount</span>
              <span class="font-bold text-slate-300">{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.gross_amount).toLocaleString() }}</span>
            </div>
            
            <div v-if="txn.charge !== undefined && txn.charge !== null" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Transaction Fee</span>
              <span class="font-bold text-slate-300">-{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.charge).toLocaleString() }}</span>
            </div>
            
            <div v-if="txn.meta?.processing_fee" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Processing Fee</span>
              <span class="font-bold text-slate-300">-{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.meta.processing_fee).toLocaleString() }}</span>
            </div>
            
            <div v-if="txn.meta?.network_fee" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Network/Gas Fee</span>
              <span class="font-bold text-slate-300">-{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.meta.network_fee).toLocaleString() }}</span>
            </div>
            
            <div v-if="txn.meta?.tax_withheld" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Tax Withheld</span>
              <span class="font-bold text-slate-300">-{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.meta.tax_withheld).toLocaleString() }}</span>
            </div>
            
            <div class="flex justify-between text-[11px] pt-2 border-t border-slate-700/50">
              <span class="text-slate-400 font-bold">Net Amount</span>
              <span class="font-black text-white text-sm">{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.net_amount || txn.amount).toLocaleString() }}</span>
            </div>
          </div>

          <!-- Balance History -->
          <div class="p-4 rounded-xl bg-slate-900/50 border border-slate-800/50 space-y-3">
            <h4 class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2">Balance History</h4>
            
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-500">Previous Balance</span>
              <span class="font-bold text-slate-300">{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.meta?.previous_balance || 0).toLocaleString() }}</span>
            </div>
            
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-500">Change</span>
              <span :class="['deposit', 'sell_crypto', 'refund'].includes(txn.type?.toLowerCase()) ? 'text-green-500' : 'text-red-500'"
                class="font-bold">
                {{ (['deposit', 'sell_crypto', 'refund'].includes(txn.type?.toLowerCase())) ? '+' : '-' }}{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.net_amount || txn.amount).toLocaleString() }}
              </span>
            </div>
            
            <div v-if="txn.meta?.new_balance !== undefined" class="flex justify-between text-[11px] pt-2 border-t border-slate-700/50">
              <span class="text-slate-400 font-bold">Current Balance</span>
              <span class="font-black text-white text-sm">{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.meta.new_balance).toLocaleString() }}</span>
            </div>
          </div>

          <!-- Timestamp Breakdown -->
          <div class="p-4 rounded-xl bg-slate-900/50 border border-slate-800/50 space-y-2">
            <h4 class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2">Timeline</h4>
            
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-500">Initiated</span>
              <span class="font-bold text-slate-300">{{ formatDate(txn.created_at) }}, {{ formatTime(txn.created_at) }}</span>
            </div>
            
            <div v-if="txn.processed_at" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Processed</span>
              <span class="font-bold text-slate-300">{{ formatDate(txn.processed_at) }}, {{ formatTime(txn.processed_at) }}</span>
            </div>
            
            <div v-if="txn.completed_at" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Completed</span>
              <span class="font-bold text-slate-300">{{ formatDate(txn.completed_at) }}, {{ formatTime(txn.completed_at) }}</span>
            </div>
            
            <div v-if="txn.settled_at" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Settled</span>
              <span class="font-bold text-slate-300">{{ formatDate(txn.settled_at) }}, {{ formatTime(txn.settled_at) }}</span>
            </div>
          </div>

          <!-- Withdrawal Specific Details -->
          <div v-if="txn.type === 'withdrawal' && (txn.meta?.bank || txn.meta?.recipient_name)"
            class="p-4 rounded-xl bg-slate-900/50 border border-slate-800/50 space-y-3">
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
            
            <div v-if="txn.meta.source_account" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Source Account</span>
              <span class="font-mono text-slate-300">{{ txn.meta.source_account }}</span>
            </div>
            
            <!-- Delivery Status -->
            <div v-if="txn.delivery_status" class="mt-3 pt-3 border-t border-slate-700/50">
              <div class="flex justify-between text-[11px] mb-1">
                <span class="text-slate-500">Delivery Status</span>
                <span :class="txn.delivery_status === 'delivered' ? 'text-green-500' : txn.delivery_status === 'failed' ? 'text-red-500' : 'text-amber-500'"
                  class="font-bold uppercase text-[9px] tracking-wider">
                  {{ txn.delivery_status }}
                </span>
              </div>
              
              <div v-if="txn.delivered_at" class="flex justify-between text-[11px]">
                <span class="text-slate-500">Delivered At</span>
                <span class="font-bold text-slate-300">{{ formatDate(txn.delivered_at) }}, {{ formatTime(txn.delivered_at) }}</span>
              </div>
              
              <div v-if="txn.delivery_failure_reason" class="flex justify-between text-[11px]">
                <span class="text-slate-500">Failure Reason</span>
                <span class="font-bold text-red-400">{{ txn.delivery_failure_reason }}</span>
              </div>
            </div>
          </div>

          <!-- Deposit Specific Details -->
          <div v-if="txn.type === 'deposit'"
            class="p-4 rounded-xl bg-slate-900/50 border border-slate-800/50 space-y-3">
            <h4 class="text-[9px] font-black text-green-500 uppercase tracking-[0.2em] mb-2">Inflow Details</h4>
            
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-500">Funding Method</span>
              <span class="font-bold text-slate-300 capitalize">{{ txn.meta?.gateway || 'Manual' }}</span>
            </div>
            
            <div v-if="txn.meta.source_card" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Source Card</span>
              <span class="font-mono text-slate-300">**** **** **** {{ txn.meta.source_card }}</span>
            </div>
            
            <div v-if="txn.meta.source_account" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Source Account</span>
              <span class="font-mono text-slate-300">{{ txn.meta.source_account }}</span>
            </div>
            
            <div v-if="txn.tx_hash" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Network Hash</span>
              <span class="font-mono text-blue-400 truncate max-w-[120px]">{{ txn.tx_hash }}</span>
            </div>
            
            <div v-if="txn.confirmations !== undefined" class="flex justify-between text-[11px]">
              <span class="text-slate-500">Confirmations</span>
              <span class="font-bold text-green-500">{{ txn.confirmations }}</span>
            </div>
          </div>

          <!-- Currency Conversion Specific Details -->
          <div v-if="txn.type === 'currency_change'"
            class="p-4 rounded-xl bg-blue-500/5 rounded-xl border border-blue-500/20 space-y-3">
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

            <div v-if="txn.meta?.mid_rate" class="flex justify-between text-[11px]">
              <span class="text-slate-400">Mid-Rate</span>
              <span class="font-bold text-slate-300">{{ txn.currency === 'NGN' ? '₦' : '$' }}{{ Number(txn.meta.mid_rate).toLocaleString() }}</span>
            </div>

            <div v-if="txn.meta?.margin" class="flex justify-between text-[11px]">
              <span class="text-slate-400">Margin/Spread</span>
              <span class="font-bold text-amber-500">{{ Number(txn.meta.margin).toFixed(2) }}%</span>
            </div>

            <div v-if="txn.meta?.provider" class="flex justify-between text-[11px]">
              <span class="text-slate-400">Provider</span>
              <span class="font-bold text-slate-300">{{ txn.meta.provider }}</span>
            </div>

            <div v-if="txn.meta?.received_amount" class="flex justify-between items-end pt-2 border-t border-blue-500/10">
              <span class="text-[11px] text-slate-400">Amount Received ({{ txn.meta?.to_currency }})</span>
              <span class="text-xl font-black text-green-500">
                 {{ txn.meta?.to_currency === 'USD' ? '$' : '₦' }}{{ Number(txn.meta?.received_amount).toLocaleString() }}
              </span>
            </div>
          </div>

          <!-- Trade/Order Specific Details -->
          <div v-if="['buy', 'sell'].includes(txn.type?.toLowerCase())"
            class="p-4 rounded-xl bg-slate-900/50 border border-slate-800/50 space-y-3">
            <h4 class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] mb-2">Trade Details</h4>
            
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-500">Asset</span>
              <span class="font-bold text-slate-300">{{ txn.meta?.asset || 'N/A' }}</span>
            </div>
            
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-500">Order Type</span>
              <span class="font-bold text-slate-300 uppercase">{{ txn.meta?.order_type || 'Market' }}</span>
            </div>
            
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-500">Price per Unit</span>
              <span class="font-bold text-slate-300">{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.meta?.price_per_unit || 0).toLocaleString() }}</span>
            </div>
            
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-500">Quantity</span>
              <span class="font-bold text-slate-300">{{ Number(txn.meta?.quantity || 0).toLocaleString() }}</span>
            </div>
            
            <div class="flex justify-between text-[11px]">
              <span class="text-slate-500">Total Value</span>
              <span class="font-bold text-white">{{ txn.currency === 'USD' ? '$' : '₦' }}{{ Number(txn.meta?.total_value || txn.amount).toLocaleString() }}</span>
            </div>
          </div>

          <!-- Current Balance Display -->
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

      <div class="flex gap-3 p-6 pt-2 no-pdf sticky bottom-0 bg-[#0F172A] border-t border-slate-800/50">
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