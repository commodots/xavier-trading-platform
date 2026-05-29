<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4">
      <div class="absolute inset-0 transition-opacity" @click="handleClose"></div>
      <div class="relative z-10 w-full max-w-3xl overflow-hidden rounded-3xl border border-[#2A314A] bg-[#1C1F2E] shadow-2xl">
        <button @click="handleClose" class="absolute right-4 top-4 text-xl text-gray-400 transition hover:text-white">×</button>

        <div class="border-b border-[#2A314A] bg-[#161925] p-6">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
              <h2 class="text-xl font-bold text-white">Crypto Trade</h2>
              <p class="text-sm text-gray-400">Trade crypto assets across buy and sell orders.</p>
            </div>
            <div class="text-right">
              <p class="text-[10px] uppercase tracking-wider text-gray-500">Selected Asset</p>
              <p class="font-semibold text-white">{{ selectedTicker?.symbol || 'None selected' }}</p>
            </div>
          </div>
        </div>

        <div class="p-6 space-y-6">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <p class="text-[10px] uppercase tracking-widest text-gray-400">Order Type</p>
              <div class="flex overflow-hidden rounded-xl border border-gray-800 bg-[#0F1724]">
                <button
                  type="button"
                  @click="tradeAction = 'buy'"
                  :class="tradeAction === 'buy' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'"
                  class="flex-1 px-4 py-3 text-xs font-black uppercase transition"
                >BUY</button>
                <button
                  type="button"
                  @click="tradeAction = 'sell'"
                  :class="tradeAction === 'sell' ? 'bg-red-600 text-white' : 'text-gray-400 hover:text-white'"
                  class="flex-1 px-4 py-3 text-xs font-black uppercase transition"
                >SELL</button>
              </div>
            </div>

            <div class="space-y-2">
              <p class="text-[10px] uppercase tracking-widest text-gray-400">Trading Pair</p>
              <select
                v-model="selectedSymbol"
                class="w-full rounded-xl border border-gray-700 bg-[#0F1724] px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500"
              >
                <option :value="null" disabled>Select asset</option>
                <option v-for="coin in coins" :key="coin.symbol" :value="coin.symbol">
                  {{ coin.symbol }}/USDT — {{ coin.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-2">
              <label class="text-[10px] uppercase tracking-widest text-gray-400">Amount (USD)</label>
              <input
                v-model.number="amount"
                type="number"
                min="1"
                step="1"
                placeholder="1000"
                class="w-full rounded-xl border border-gray-700 bg-[#0F1724] px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500"
              />
            </div>
            <div class="space-y-2">
              <label class="text-[10px] uppercase tracking-widest text-gray-400">Estimated Quantity</label>
              <div class="rounded-xl border border-gray-700 bg-[#0F1724] px-4 py-3 text-sm text-gray-200 font-mono">
                {{ estimatedQuantity }} {{ selectedTicker?.symbol || '' }}
              </div>
            </div>
            <div class="space-y-2">
              <label class="text-[10px] uppercase tracking-widest text-gray-400">Current Price</label>
              <div class="rounded-xl border border-gray-700 bg-[#0F1724] px-4 py-3 text-sm text-white">
                {{ selectedTicker ? formatCurrency(selectedTicker.price) : '-' }}
              </div>
            </div>
          </div>

          <div class="space-y-4">
            <button
              type="button"
              @click="handleTrade"
              :disabled="isProcessing || !selectedTicker || amount <= 0"
              :class="tradeAction === 'buy' ? 'from-blue-700 to-[#00D4FF]' : 'from-red-700 to-red-500'"
              class="w-full rounded-xl bg-gradient-to-r px-6 py-4 text-sm font-black uppercase text-white shadow-lg transition disabled:opacity-50"
            >
              {{ isProcessing ? 'Processing...' : tradeAction === 'buy' ? 'Confirm Buy' : 'Confirm Sell' }}
            </button>

            <div v-if="errorMessage" class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm text-rose-300">
              {{ errorMessage }}
            </div>
            <div v-if="successMessage" class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-300">
              {{ successMessage }}
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-800 bg-[#0F1724] p-4">
              <p class="text-[10px] uppercase tracking-widest text-gray-400">Wallet Balance</p>
              <p class="mt-2 text-sm text-white">{{ formatCurrency(walletBalance) }}</p>
            </div>
            <div class="rounded-xl border border-gray-800 bg-[#0F1724] p-4">
              <p class="text-[10px] uppercase tracking-widest text-gray-400">Selected Asset</p>
              <p class="mt-2 text-sm text-white">{{ selectedTicker?.name || 'No asset selected' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import api from '@/api';

const props = defineProps({
  show: Boolean,
  coins: {
    type: Array,
    default: () => []
  },
  selectedAsset: Object
});

const emit = defineEmits(['close', 'trade-success']);

const tradeAction = ref('buy');
const amount = ref(1000);
const selectedSymbol = ref(null);
const isProcessing = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const walletBalance = ref(0);

const selectedTicker = computed(() => {
  if (selectedSymbol.value) {
    return props.coins.find(c => c.symbol === selectedSymbol.value) || null;
  }
  return props.selectedAsset || null;
});

const estimatedQuantity = computed(() => {
  if (!selectedTicker.value || !selectedTicker.value.price || amount.value <= 0) {
    return '0.000000';
  }
  return (amount.value / selectedTicker.value.price).toFixed(6);
});

const formatCurrency = (value) => {
  return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const loadWalletBalance = async () => {
  try {
    const response = await api.get('/wallet/balances');
    walletBalance.value = response.data.data?.cleared_balance_usd || 0;
  } catch (e) {
    walletBalance.value = 0;
  }
};

const handleTrade = async () => {
  if (!selectedTicker.value) {
    errorMessage.value = 'Select an asset first.';
    return;
  }
  if (!amount.value || amount.value <= 0) {
    errorMessage.value = 'Enter a valid amount.';
    return;
  }

  isProcessing.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    await api.post('/trade/open', {
      pair: `${selectedTicker.value.symbol.toUpperCase()}/USDT`,
      amount: amount.value,
      type: tradeAction.value
    });

    successMessage.value = `Order ${tradeAction.value.toUpperCase()} submitted successfully.`;
    emit('trade-success');
    setTimeout(() => {
      handleClose();
    }, 1200);
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Failed to submit order.';
  } finally {
    isProcessing.value = false;
  }
};

const handleClose = () => {
  resetState();
  emit('close');
};

const resetState = () => {
  tradeAction.value = 'buy';
  amount.value = 1000;
  selectedSymbol.value = null;
  errorMessage.value = '';
  successMessage.value = '';
};

watch(() => props.show, (show) => {
  if (show) {
    if (props.selectedAsset?.symbol) {
      selectedSymbol.value = props.selectedAsset.symbol;
    }
    loadWalletBalance();
  } else {
    resetState();
  }
});

watch(() => props.selectedAsset, (asset) => {
  if (asset?.symbol) {
    selectedSymbol.value = asset.symbol;
  }
});
</script>
