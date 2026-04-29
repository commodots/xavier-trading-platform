<template>
  <div class="bg-[#1f2937] p-5 rounded-xl border border-[#374151]">
    
    <!-- Wallet Balance Display -->
    <div class="mb-6 space-y-1">
      <div class="flex justify-between text-[10px] font-black uppercase tracking-widest text-gray-500">
        <span>Buying Power (USD)</span>
        <span class="text-white">${{ walletBalances.cleared_balance_usd ? walletBalances.cleared_balance_usd.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00' }}</span>
      </div>
      <div class="w-full h-1 overflow-hidden bg-gray-800 rounded-full">
        <div class="h-full bg-blue-500" :style="{ width: '100%' }"></div>
      </div>
    </div>

    <h3 class="mb-4 text-lg font-semibold">Place Order</h3>

    <div class="grid grid-cols-1 gap-4">
      <!-- Order Type -->
      <div>
        <label class="block mb-2 text-sm font-medium text-gray-300">Order Type</label>
        <select v-model="type" class="w-full px-3 py-2 bg-[#1F2937] border border-[#374151] rounded-lg text-white focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none">
          <option value="market">Market Order</option>
          <option value="limit">Limit Order</option>
          <option value="stop">Stop Loss</option>
          <option value="bracket">Bracket Order</option>
        </select>
      </div>

      <!-- Symbol -->
      <div>
        <label class="block mb-2 text-sm font-medium text-gray-300">Symbol</label>
        <div class="flex gap-2">
          <input v-model="symbol" type="text" placeholder="AAPL" class="flex-1 px-3 py-2 bg-[#1F2937] border border-[#374151] rounded-lg text-white placeholder-gray-500 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none" />
          <button @click="addToWatchlist" :disabled="watchlistLoading || !symbol" 
            class="px-3 py-2 text-xs font-bold transition-all border rounded-lg whitespace-nowrap"
            :class="isInWatchlist ? 'bg-yellow-500/10 border-yellow-500/50 text-yellow-500' : 'bg-gray-800 border-gray-700 text-gray-400 hover:text-white'">
            <span v-if="watchlistLoading" class="inline-block w-3 h-3 mr-1 border-b-2 border-current rounded-full animate-spin"></span>
            {{ isInWatchlist ? '★ In Watchlist' : '☆ Add to Watchlist' }}
          </button>
        </div>
      </div>

      <!-- Quantity -->
      <div>
        <label class="block mb-2 text-sm font-medium text-gray-300">Quantity</label>
        <input v-model.number="qty" type="number" min="1" step="1" placeholder="1" class="w-full px-3 py-2 bg-[#1F2937] border border-[#374151] rounded-lg text-white placeholder-gray-500 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none" />
      </div>

      <!-- Limit Price (for limit orders) -->
      <div v-if="type === 'limit'">
        <label class="block mb-2 text-sm font-medium text-gray-300">Limit Price</label>
        <input v-model.number="limit_price" type="number" step="0.01" placeholder="170.00" class="w-full px-3 py-2 bg-[#1F2937] border border-[#374151] rounded-lg text-white placeholder-gray-500 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none" />
      </div>

      <!-- Stop Price (for stop orders) -->
      <div v-if="type === 'stop'">
        <label class="block mb-2 text-sm font-medium text-gray-300">Stop Price</label>
        <input v-model.number="stop_price" type="number" step="0.01" placeholder="160.00" class="w-full px-3 py-2 bg-[#1F2937] border border-[#374151] rounded-lg text-white placeholder-gray-500 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none" />
      </div>

      <!-- Bracket Order Fields -->
      <div v-if="type === 'bracket'" class="grid grid-cols-2 gap-4">
        <div>
          <label class="block mb-2 text-sm font-medium text-gray-300">Take Profit</label>
          <input v-model.number="take_profit" type="number" step="0.01" placeholder="180.00" class="w-full px-3 py-2 bg-[#1F2937] border border-[#374151] rounded-lg text-white placeholder-gray-500 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none" />
        </div>
        <div>
          <label class="block mb-2 text-sm font-medium text-gray-300">Stop Loss</label>
          <input v-model.number="stop_loss" type="number" step="0.01" placeholder="160.00" class="w-full px-3 py-2 bg-[#1F2937] border border-[#374151] rounded-lg text-white placeholder-gray-500 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none" />
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-3 mt-4">
        <button @click="submit('buy')" :disabled="!!sideLoading || !isValid"
         class="flex items-center justify-center flex-1 px-4 py-3 font-semibold text-white transition duration-200 bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
          <span v-if="sideLoading === 'buy'" class="w-4 h-4 mr-2 border-b-2 border-white rounded-full animate-spin"></span>
    {{ sideLoading === 'buy' ? 'Buying...' : 'Buy' }}
  </button>
        <button @click="submit('sell')" :disabled="!!sideLoading || !isValid"
         class="flex items-center justify-center flex-1 px-4 py-3 font-semibold text-white transition duration-200 bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
          <span v-if="sideLoading === 'sell'" class="w-4 h-4 mr-2 border-b-2 border-white rounded-full animate-spin"></span>
    {{ sideLoading === 'sell' ? 'Selling...' : 'Sell' }}
  </button>
      </div>

      <!-- Error Message -->
      <div v-if="error" class="p-3 mt-3 border rounded-lg bg-red-900/20 border-red-500/50">
        <p class="text-sm text-red-400">{{ error }}</p>
      </div>

      <!-- Success Message -->
      <div v-if="success" class="p-3 mt-3 border rounded-lg bg-green-900/20 border-green-500/50">
        <p class="text-sm text-green-400">{{ success }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import axios from "axios";
import { ref, computed, onMounted, onUnmounted } from "vue";

const props = defineProps({
  initialSymbol: {
    type: String,
    default: 'AAPL'
  }
});

const symbol = ref(props.initialSymbol);
const qty = ref(1);
const type = ref("market");
const limit_price = ref(null);
const stop_price = ref(null);
const take_profit = ref(null);
const stop_loss = ref(null);

const sideLoading = ref(null); // Tracks 'buy', 'sell', or null
const emit = defineEmits(['order-placed']); // Define the emit
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
    const response = await axios.post("/trade/place", {
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
    success.value = `Order placed successfully! ID: ${order.id.substring(0, 8)}`;

    // Notify parent for chart annotations and sound effects
    emit('order-placed', order);

    // Immediate Wallet Update
    await fetchWalletBalances();

    // Trigger refresh on the Positions Monitor globally
    window.dispatchEvent(new CustomEvent('order-placed', { detail: order }));

    // Clear form on success
    symbol.value = props.initialSymbol; // Reset to initial or default
    qty.value = 1;
    limit_price.value = null;
    stop_price.value = null;
    take_profit.value = null;
    stop_loss.value = null;

  } catch (err) {
    error.value = (err.response && err.response.data && err.response.data.message) ? err.response.data.message : "Failed to place order. Please try again.";
  } finally {
    sideLoading.value = null;
  }
};

const fetchWalletBalances = async () => {
  try {
    const response = await axios.get("/wallet/balances");
    walletBalances.value = response.data.data;
  } catch (err) {
    console.error("Failed to fetch wallet balances:", err);
  }
};

const fetchWatchlist = async () => {
  try {
    const res = await axios.get('/watchlist');
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
      await axios.delete(`/watchlist/${item.id}`);
    } else {
      await axios.post("/watchlist", {
        symbol: symbol.value.toUpperCase(),
        name: symbol.value.toUpperCase(),
        market: "stocks",
        currency: "USD",
        added_price: 0
      });
    }
    
    await fetchWatchlist();
    
  } catch (err) {
    error.value = (err.response && err.response.data && err.response.data.message) ? err.response.data.message : "Failed to update watchlist.";
  } finally {
    watchlistLoading.value = false;
  }
};

// Fetch wallet balances and watchlist on component mount
onMounted(() => {
  fetchWalletBalances();
  fetchWatchlist();

  // Listen for close events from the monitor to refresh balance
  window.addEventListener('wallet-refresh', fetchWalletBalances);
});

onUnmounted(() => {
  window.removeEventListener('wallet-refresh', fetchWalletBalances);
});
</script>
