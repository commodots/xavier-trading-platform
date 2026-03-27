<template>
  <MainLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold">📈 Trading Crypto</h1>
          <p class="mt-1 text-sm text-gray-400">Crypto spot trading with real-time prices</p>
        </div>
        <div class="flex gap-3">
          <button
            @click="depositNav"
           class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white font-semibold hover:bg-[#252a3d] transition">
            + Deposit
          </button>
          <button
            @click="withdrawNav"
            class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white font-semibold hover:bg-[#252a3d] transition">
            - Withdraw
          </button>
          <button
            @click="fetchData"
            class="bg-[#1f3348] text-gray-300 px-4 py-2 rounded-lg hover:bg-[#2d4a66] transition text-sm"
          >
            Refresh
          </button>
        </div>
      </div>

      <!-- Portfolio Overview -->
      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="bg-gradient-to-br from-[#0F1724] to-[#1a2332] border border-[#1f3348] rounded-xl p-5">
          <p class="mb-2 text-sm text-gray-400">Available Balance</p>
          <p class="text-3xl font-bold text-[#00D4FF]">${{ formatCurrency(wallet.cleared_balance_usd || 0) }}</p>
        </div>
        <div class="bg-gradient-to-br from-[#0F1724] to-[#1a2332] border border-[#1f3348] rounded-xl p-5">
          <p class="mb-2 text-sm text-gray-400">Open Trades</p>
          <p class="text-3xl font-bold text-orange-400">{{ openTradesCount }}</p>
        </div>
        <div class="bg-gradient-to-br from-[#0F1724] to-[#1a2332] border border-[#1f3348] rounded-xl p-5">
          <p class="mb-2 text-sm text-gray-400">Total Profit & Loss</p>
          <p :class="totalPnL >= 0 ? 'text-green-400' : 'text-red-400'" class="text-3xl font-bold">
            {{ totalPnL >= 0 ? '+' : '' }}${{ formatCurrency(Math.abs(totalPnL)) }}
          </p>
        </div>
      </div>

      <!-- TRON Deposit Address -->
      <div v-if="tronAddress" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="mb-4 text-xl font-semibold">+ Deposit USDT (TRON)</h2>
        <div class="flex items-center space-x-4">
          <div class="flex-1">
            <p class="mb-2 text-sm text-gray-400">Your TRON Address</p>
            <p class="font-mono text-[#00D4FF] bg-[#111827] p-3 rounded-lg break-all">{{ tronAddress.address }}</p>
          </div>
          <div class="flex-shrink-0">
            <img :src="tronAddress.qr_code_url" alt="QR Code" class="w-20 h-20 p-1 bg-white rounded-lg" />
          </div>
        </div>
        <p class="mt-3 text-xs text-gray-400">Send only USDT-TRC20 to this address. Deposits are processed automatically.</p>
      </div>

      <!-- Open Trade Form -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="mb-4 text-xl font-semibold">Enter Trade</h2>
        <form @submit.prevent="openTrade" class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div>
            <label class="block mb-2 text-sm text-gray-400">Trading Pair</label>
            <select
              v-model="form.pair"
              class="w-full px-4 py-2 bg-[#111827] border border-[#1f3348] rounded-lg text-white focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none"
            >
              <option value="BTC/USDT">₿ BTC/USDT</option>
              <option value="ETH/USDT">Ξ ETH/USDT</option>
              <option value="USDT/USD">💵 USDT/USD</option>
            </select>
          </div>

          <div>
            <label class="block mb-2 text-sm text-gray-400">Trade Type</label>
            <select
              v-model="form.type"
              class="w-full px-4 py-2 bg-[#111827] border border-[#1f3348] rounded-lg text-white focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none"
            >
              <option value="buy">Buy</option>
              <option value="sell">Sell</option>
            </select>
          </div>

          <div>
            <label class="block mb-2 text-sm text-gray-400">Amount (USD)</label>
            <input
              v-model.number="form.amount"
              type="number"
              placeholder="1000"
              min="1"
              step="1"
              class="w-full px-4 py-2 bg-[#111827] border border-[#1f3348] rounded-lg text-white placeholder-gray-600 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none"
            />
          </div>

          <div class="flex items-end">
            <button
              type="submit"
              :disabled="loading || !form.amount"
              class="w-full bg-[#00D4FF] text-black px-4 py-2 rounded-lg font-bold hover:bg-[#00b8e6] disabled:opacity-50 disabled:cursor-not-allowed transition"
            >
              {{ loading ? 'Opening...' : '→ Open Trade' }}
            </button>
          </div>
        </form>
        <p v-if="errorMessage" class="mt-3 text-sm text-red-400">{{ errorMessage }}</p>
        <p v-if="successMessage" class="mt-3 text-sm text-green-400">{{ successMessage }}</p>
      </div>

      <!-- Live Market Prices -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="mb-4 text-xl font-semibold">Live Market Prices</h2>
        <div v-if="market.length === 0" class="py-8 text-center text-gray-400">Loading prices...</div>
        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div v-for="coin in market" :key="coin.symbol" class="bg-[#111827] border border-[#1f2a44] rounded-lg p-4">
            <div class="flex items-start justify-between mb-3">
              <div>
                <p class="font-bold text-white">{{ coin.name }}</p>
                <p class="text-sm text-gray-400">{{ coin.symbol }}</p>
              </div>
              <span class="font-mono text-[#00D4FF] font-bold">${{ formatCurrency(coin.price) }}</span>
            </div>
            <button
              @click="quickBuy(coin.symbol)"
              :disabled="loading"
              class="w-full px-3 py-1 text-sm text-green-400 transition rounded bg-green-600/20 hover:bg-green-600/30"
            >
              Buy $100
            </button>
          </div>
        </div>
      </div>

      <!-- Open Trades -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="mb-4 text-xl font-semibold">Open Trades ({{ openTradesCount }})</h2>
        <div v-if="trades.length === 0" class="py-8 text-center text-gray-400">No open trades</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 border-b border-[#1f3348] bg-[#1a2332]">
              <tr>
                <th class="px-4 py-3 font-medium text-left">ID</th>
                <th class="px-4 py-3 font-medium text-left">Pair</th>
                <th class="px-4 py-3 font-medium text-left">Type</th>
                <th class="px-4 py-3 font-medium text-right">Amount</th>
                <th class="px-4 py-3 font-medium text-right">Entry Price</th>
                <th class="px-4 py-3 font-medium text-right">Current</th>
                <th class="px-4 py-3 font-medium text-right">P&L</th>
                <th class="px-4 py-3 font-medium text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#1f3348]">
              <tr v-for="trade in trades" :key="trade.id" class="hover:bg-[#16213A] transition">
                <td class="px-4 py-3 font-mono text-gray-300">#{{ trade.id }}</td>
                <td class="px-4 py-3 font-semibold">{{ trade.pair }}</td>
                <td class="px-4 py-3">
                  <span :class="trade.type === 'buy' ? 'bg-green-600/20 text-green-400' : 'bg-red-600/20 text-red-400'" class="px-2 py-1 text-xs font-semibold rounded">
                    {{ trade.type.toUpperCase() }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">${{ formatCurrency(trade.amount) }}</td>
                <td class="px-4 py-3 font-mono text-right">${{ formatCurrency(trade.entry_price) }}</td>
                <td class="px-4 py-3 text-right text-[#00D4FF]">${{ formatCurrency(currentPrices[trade.pair] || trade.entry_price) }}</td>
                <td class="px-4 py-3 text-right">
                  <span :class="calculatePnL(trade) >= 0 ? 'text-green-400' : 'text-red-400'" class="font-bold">
                    {{ calculatePnL(trade) >= 0 ? '+' : '' }}${{ formatCurrency(Math.abs(calculatePnL(trade))) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <button
                    @click="closeTrade(trade)"
                    :disabled="loading"
                    class="bg-[#1f3348] text-gray-300 px-3 py-1 rounded text-xs hover:bg-[#2d4a66] disabled:opacity-50 transition"
                  >
                    Close
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Closed Trades History -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="mb-4 text-xl font-semibold">Trade History</h2>
        <div v-if="closedTrades.length === 0" class="py-8 text-center text-gray-400">No closed trades</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-gray-400 border-b border-[#1f3348] bg-[#1a2332]">
              <tr>
                <th class="px-4 py-3 font-medium text-left">ID</th>
                <th class="px-4 py-3 font-medium text-left">Pair</th>
                <th class="px-4 py-3 font-medium text-right">Profit/Loss</th>
                <th class="px-4 py-3 font-medium text-right">Entry → Exit</th>
                <th class="px-4 py-3 font-medium text-left">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#1f3348]">
              <tr v-for="trade in closedTrades.slice(0, 10)" :key="trade.id" class="hover:bg-[#16213A] transition">
                <td class="px-4 py-3 font-mono text-gray-300">#{{ trade.id }}</td>
                <td class="px-4 py-3 font-semibold">{{ trade.pair }}</td>
                <td class="px-4 py-3 text-right">
                  <span :class="trade.profit_loss >= 0 ? 'text-green-400' : 'text-red-400'" class="font-bold">
                    {{ trade.profit_loss >= 0 ? '+' : '' }}${{ formatCurrency(Math.abs(trade.profit_loss || 0)) }}
                  </span>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-right">
                  ${{ formatCurrency(trade.entry_price) }} → ${{ trade.exit_price ? formatCurrency(trade.exit_price) : 'N/A' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-400">{{ formatDate(trade.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import api from '@/api';

const form = ref({ pair: 'BTC/USDT', type: 'buy', amount: 1000 });
const loading = ref(false);
const market = ref([]);
const trades = ref([]);
const closedTrades = ref([]);
const wallet = ref({ usd_cleared: 0 });
const tronAddress = ref(null);
const currentPrices = ref({});
const errorMessage = ref('');
const successMessage = ref('');

const openTradesCount = computed(() => trades.value.length);

const totalPnL = computed(() => {
  return trades.value.reduce((sum, t) => sum + calculatePnL(t), 0) +
    closedTrades.value.reduce((sum, t) => sum + (t.profit_loss || 0), 0);
});

const calculatePnL = (trade) => {
  const currentPrice = currentPrices.value[trade.pair] || trade.entry_price;
  if (trade.type === 'buy') {
    return (currentPrice - trade.entry_price) * (trade.amount / trade.entry_price);
  }
  return (trade.entry_price - currentPrice) * (trade.amount / trade.entry_price);
};

const formatCurrency = (amount) => {
  return Math.abs(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString();
};

const fetchData = async () => {
  try {

    // Fetch wallet balance
    const walletRes = await api.get('/wallet/balances');
    wallet.value = walletRes.data.data || { cleared_balance_usd: 0 };

    // Fetch tron address
    const addressRes = await api.get('/crypto/address');
    tronAddress.value = addressRes.data;

    // Fetch market prices
    const marketRes = await api.get('/market/crypto');
    market.value = marketRes.data.data || [];
    market.value.forEach(coin => {
      currentPrices.value[coin.symbol + '/USDT'] = coin.price;
    });

    // Fetch trades
    const tradesRes = await api.get('/trades');
    trades.value = tradesRes.data.data.filter(t => t.status === 'open') || [];
    closedTrades.value = tradesRes.data.data.filter(t => t.status === 'closed') || [];
  } catch (e) {
    console.error(e);
  }
};

const openTrade = async () => {
  if (!form.value.amount || form.value.amount <= 0) {
    errorMessage.value = 'Please enter a valid amount';
    return;
  }

  loading.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    await api.post('/trade/open', {
      pair: form.value.pair,
      amount: form.value.amount,
      type: form.value.type,
    });
    successMessage.value = `Trade opened successfully! ${form.value.type.toUpperCase()} ${form.value.amount} ${form.value.pair}`;
    form.value.amount = 1000;
    await fetchData();
    setTimeout(() => { successMessage.value = ''; }, 3000);
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Failed to open trade';
  } finally {
    loading.value = false;
  }
};

const closeTrade = async (trade) => {
  if (!confirm(`Close trade #${trade.id}?`)) return;
  
  loading.value = true;
  try {
    await api.post(`/trade/close/${trade.id}`);
    successMessage.value = 'Trade closed successfully!';
    await fetchData();
    setTimeout(() => { successMessage.value = ''; }, 3000);
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Failed to close trade';
  } finally {
    loading.value = false;
  }
};

const quickBuy = async (symbol) => {
  form.value.pair = symbol + '/USDT';
  form.value.type = 'buy';
  form.value.amount = 100;
  await openTrade();
};

onMounted(() => {
  fetchData();
  setInterval(fetchData, 10000); // Refresh every 10 seconds
});

const withdrawNav = () => {
  router.push('/crypto/withdraw')
}
const depositNav =() => {
  router.push('/crypro/deposit')
}
</script>
