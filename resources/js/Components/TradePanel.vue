<template>
  <div class="bg-[#1f2937] p-5 rounded-xl border border-[#374151]">
  
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
        <input v-model="symbol" type="text" placeholder="AAPL" class="w-full px-3 py-2 bg-[#1F2937] border border-[#374151] rounded-lg text-white placeholder-gray-500 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none" :readonly="!!initialSymbol" />
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
import { ref, computed } from "vue";

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

const isValid = computed(() => {
  if (!symbol.value || !qty.value || qty.value < 1) return false;

  if (type.value === 'limit' && !limit_price.value) return false;
  if (type.value === 'stop' && !stop_price.value) return false;
  if (type.value === 'bracket' && (!take_profit.value || !stop_loss.value)) return false;

  return true;
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

    success.value = `Order placed successfully! Order ID: ${response.data.id}`;

    emit('order-placed');

    // Clear form on success
    symbol.value = props.initialSymbol; // Reset to initial or default
    qty.value = 1;
    limit_price.value = null;
    stop_price.value = null;
    take_profit.value = null;
    stop_loss.value = null;

  } catch (err) {
    error.value = err.response?.data?.message || "Failed to place order. Please try again.";
  } finally {
    sideLoading.value = null;
  }
};
</script>
