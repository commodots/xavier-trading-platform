<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4">
      <div class="absolute inset-0 transition-opacity" @click="$emit('close')"></div>
      
      <div class="bg-[#1C1F2E] p-6 rounded-2xl shadow-xl w-full max-w-md relative border border-[#2A314A] transition-colors duration-300 z-10">
        
        <button @click="$emit('close')" class="absolute text-xl text-gray-400 transition-colors top-4 right-4 hover:text-white">×</button>

        <h2 class="flex items-center mb-4 text-xl font-semibold text-white">
          Trade {{ symbol?.toUpperCase() }}
        </h2>

        <div class="space-y-3">
          <div class="space-y-1">
            <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Asset Symbol</label>
            <div class="flex gap-2">
              <input v-model="symbol" type="text" placeholder="AAPL"
                class="flex-1 px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm font-bold uppercase placeholder-gray-600 focus:border-blue-500 outline-none transition-all" />
              
              <button @click="addToWatchlist" :disabled="watchlistLoading || !symbol"
                class="px-4 py-2 text-[10px] font-bold transition-all border rounded-xl whitespace-nowrap uppercase tracking-wider"
                :class="isInWatchlist ? 'bg-yellow-500/10 border-yellow-500/50 text-yellow-500' : 'bg-gray-800 border-gray-700 text-gray-400 hover:text-white'">
                <span v-if="watchlistLoading" class="inline-block w-3 h-3 mr-1 border-b-2 border-current rounded-full animate-spin"></span>
                {{ isInWatchlist ? '★ Saved' : '☆ Watchlist' }}
              </button>
            </div>
          </div>

          <div class="space-y-1">
            <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Order Type</label>
            <div class="relative">
              <select v-model="type"
                class="w-full px-3 py-2.5 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm outline-none focus:border-blue-500 transition-all appearance-none cursor-pointer font-bold">
                <option value="market">Market Order</option>
                <option value="limit">Limit Order</option>
                <option value="stop">Stop Loss</option>
                <option value="bracket">Bracket Order</option>
              </select>
              <div class="absolute inset-y-0 right-0 flex items-center px-3 text-xs text-gray-400 pointer-events-none">▼</div>
            </div>
          </div>

          <div class="flex items-center justify-between px-1">
            <span class="text-[10px] text-gray-400 uppercase font-bold">USD Wallet Balance</span>
            <span class="text-xs font-bold text-gray-300">
              ${{ walletBalances.cleared_balance_usd ? walletBalances.cleared_balance_usd.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00' }}
            </span>
          </div>

          <div class="p-3 space-y-3 border border-gray-800 rounded-xl bg-black/20">
            <div class="space-y-1">
              <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Shares Quantity</label>
              <div class="relative">
                <input v-model.number="qty" type="number" min="1" step="1" placeholder="1"
                  class="w-full px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm outline-none focus:border-blue-500 transition-all font-mono" />
                <span class="absolute text-[10px] font-bold text-gray-500 -translate-y-1/2 right-3 top-1/2 uppercase pointer-events-none">Units</span>
              </div>
            </div>

            <div v-if="type === 'limit'" class="pt-1 space-y-1">
              <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Target Limit Price ($)</label>
              <input v-model.number="limit_price" type="number" step="0.01" placeholder="170.00"
                class="w-full px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm outline-none focus:border-blue-500 transition-all font-mono" />
            </div>

            <div v-if="type === 'stop'" class="pt-1 space-y-1">
              <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Target Activation Stop Price ($)</label>
              <input v-model.number="stop_price" type="number" step="0.01" placeholder="160.00"
                class="w-full px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm outline-none focus:border-blue-500 transition-all font-mono" />
            </div>

            <div v-if="type === 'bracket'" class="grid grid-cols-2 gap-2 pt-1">
              <div class="space-y-1">
                <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Take Profit ($)</label>
                <input v-model.number="take_profit" type="number" step="0.01" placeholder="180.00"
                  class="w-full px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm outline-none focus:border-blue-500 transition-all font-mono" />
              </div>
              <div class="space-y-1">
                <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Stop Loss ($)</label>
                <input v-model.number="stop_loss" type="number" step="0.01" placeholder="160.00"
                  class="w-full px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm outline-none focus:border-blue-500 transition-all font-mono" />
              </div>
            </div>
          </div>

          <div v-if="error" class="flex items-center gap-2 p-3 text-xs border rounded-lg bg-rose-500/10 border-rose-500/20 text-rose-400">
            <span class="w-1.5 h-1.5 rounded-full bg-rose-400 shrink-0"></span>
            <p class="truncate">{{ error }}</p>
          </div>

          <div v-if="success" class="flex items-center gap-2 p-3 text-xs border rounded-lg bg-emerald-500/10 border-emerald-500/20 text-emerald-400">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
            <p class="truncate">{{ success }}</p>
          </div>

          <div class="flex gap-2 pt-1">
            <button @click="submit('buy')" :disabled="!!sideLoading || !isValid"
              class="flex items-center justify-center flex-1 py-3.5 font-black text-white transition-all rounded-xl bg-gradient-to-r from-[#0047AB] to-[#00D4FF] shadow-lg shadow-blue-900/20 text-sm uppercase tracking-wide disabled:opacity-40 disabled:grayscale">
              <span v-if="sideLoading === 'buy'" class="w-3 h-3 mr-2 border-b-2 border-white rounded-full animate-spin"></span>
              {{ sideLoading === 'buy' ? 'Sending...' : 'Buy' }}
            </button>
            <button @click="submit('sell')" :disabled="!!sideLoading || !isValid"
              class="flex items-center justify-center flex-1 py-3.5 font-black text-white transition-all rounded-xl bg-gradient-to-r from-red-700 to-red-500 shadow-lg shadow-red-900/20 text-sm uppercase tracking-wide disabled:opacity-40 disabled:grayscale">
              <span v-if="sideLoading === 'sell'" class="w-3 h-3 mr-2 border-b-2 border-white rounded-full animate-spin"></span>
              {{ sideLoading === 'sell' ? 'Sending...' : 'Sell' }}
            </button>
          </div>
        </div>
        
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import api from "@/api";
import { ref, computed, onMounted, onUnmounted } from "vue";

const props = defineProps({
  show: Boolean, 
  initialSymbol: {
    type: String,
    default: 'AAPL'
  }
});

const emit = defineEmits(['order-placed', 'close']);

const symbol = ref(props.initialSymbol);
const qty = ref(1);
const type = ref("market");
const limit_price = ref(null);
const stop_price = ref(null);
const take_profit = ref(null);
const stop_loss = ref(null);

const sideLoading = ref(null); 
const error = ref("");
const success = ref("");

const walletBalances = ref({
  cleared_balance_usd: 0,
  cleared_balance_ngn: 0
});
const watchlistLoading = ref(false);
const watchlist = ref([]);

const isValid = computed(() => {
  if (!symbol.value || !qty.value || qty.value < 1) return false;

  if (type.value === 'limit' && !limit_price.value) return false;
  if (type.value === 'stop' && !stop_price.value) return false;
  if (type.value === 'bracket' && (!take_profit.value || !stop_loss.value)) return false;

  return true;
});

const isInWatchlist = computed(() => {
  return Array.isArray(watchlist.value) && watchlist.value.some(item =>
    item.symbol === symbol.value.toUpperCase()
  );
});

const submit = async (side) => {
  if (!isValid.value) return;

  sideLoading.value = side;
  error.value = "";
  success.value = "";

  try {
    const response = await api.post("/trade/place", {
      symbol: symbol.value.toUpperCase(),
      qty: qty.value,
      side,
      type: type.value,
      limit_price: limit_price.value,
      stop_price: stop_price.value,
      take_profit: take_profit.value,
      stop_loss: stop_loss.value
    });

    const order = response.data.data;
    success.value = `Order configured. ID: ${String(order.id).substring(0, 8)}`;

    emit('order-placed', order);
    await fetchWalletBalances();

    window.dispatchEvent(new CustomEvent('order-placed', { detail: order }));

    qty.value = 1;
    limit_price.value = null;
    stop_price.value = null;
    take_profit.value = null;
    stop_loss.value = null;

  } catch (err) {
    error.value = (err.response?.data?.message) ? err.response.data.message : "Failed to place order. Please try again.";
  } finally {
    sideLoading.value = null;
  }
};

const fetchWalletBalances = async () => {
  try {
    const response = await api.get("/wallet/balances");
    walletBalances.value = response.data.data;
  } catch (err) {
    console.error("Failed to fetch wallet balances:", err);
  }
};

const fetchWatchlist = async () => {
  try {
    const res = await api.get('/watchlist');
    watchlist.value = res.data.data || res.data;
  } catch (error) {
    console.error("Failed to fetch watchlist", error);
  }
};

const addToWatchlist = async () => {
  if (!symbol.value) return;

  watchlistLoading.value = true;
  try {
    if (isInWatchlist.value) {
      const item = watchlist.value.find(i => i.symbol === symbol.value.toUpperCase());
      await api.delete(`/watchlist/${item.id}`);
    } else {
      await api.post("/watchlist", {
        symbol: symbol.value.toUpperCase(),
        name: symbol.value.toUpperCase(),
        market: "stocks",
        currency: "USD",
        added_price: 0
      });
    }
    await fetchWatchlist();
  } catch (err) {
    error.value = (err.response?.data?.message) ? err.response.data.message : "Failed to update watchlist.";
  } finally {
    watchlistLoading.value = false;
  }
};

onMounted(() => {
  fetchWalletBalances();
  fetchWatchlist();
  window.addEventListener('wallet-refresh', fetchWalletBalances);
});

onUnmounted(() => {
  window.removeEventListener('wallet-refresh', fetchWalletBalances);
});
</script>