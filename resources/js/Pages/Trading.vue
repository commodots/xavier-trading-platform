<template>
  <MainLayout>
    <div class="space-y-6">
      <EmailVerificationPrompt v-if="showPrompt" :user="user" />
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold">Trading Crypto</h1>
          <p class="mt-1 text-sm text-gray-400">Crypto spot trading with real-time prices</p>
        </div>
        <div class="flex gap-3">
          <button @click="handleAction(depositNav)"
            class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white font-semibold hover:bg-[#252a3d] transition">
            + Deposit
          </button>
          <button @click="handleAction(withdrawNav)"
            class="bg-[#1C1F2E] border border-[#2A314A] px-4 py-2 rounded-lg text-white font-semibold hover:bg-[#252a3d] transition">
            - Withdraw
          </button>
          <button @click="fetchData"
            class="bg-[#1f3348] text-gray-300 px-4 py-2 rounded-lg hover:bg-[#2d4a66] transition text-sm">
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
          <p class="mb-2 text-sm text-gray-400">Total Profit \ Loss</p>
          <p :class="totalPnL >= 0 ? 'text-green-400' : 'text-red-400'" class="text-3xl font-bold">
            {{ totalPnL >= 0 ? '+' : '-' }}${{ formatCurrency(totalPnL) }}
          </p>
        </div>
      </div>

      <!-- Open Trade Form -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <h2 class="mb-4 text-xl font-semibold">Enter Trade</h2>
        <form @submit.prevent="openTrade" class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div>
            <label class="block mb-2 text-sm text-gray-400">Trading Pair</label>
            <select v-model="form.pair"
              class="w-full px-4 py-2 bg-[#111827] border border-[#1f3348] rounded-lg text-white focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none">
              <option v-for="coin in market" :key="coin.symbol" :value="coin.symbol.toUpperCase() + '/USDT'">
                {{ coin.name }} ({{ coin.symbol.toUpperCase() }}/USDT)
              </option>
              <option v-if="market.length === 0" value="BTC/USDT">Loading assets...</option>
            </select>
          </div>

          <div>
            <label class="block mb-2 text-sm text-gray-400">Trade Type</label>
            <select v-model="form.type"
              class="w-full px-4 py-2 bg-[#111827] border border-[#1f3348] rounded-lg text-white focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none">
              <option value="buy">Buy</option>
              <option value="sell">Sell</option>
            </select>
          </div>

          <div>
            <label class="block mb-2 text-sm text-gray-400">Amount (USD)</label>
            <input v-model.number="form.amount" type="number" placeholder="1000" min="1" step="1"
              class="w-full px-4 py-2 bg-[#111827] border border-[#1f3348] rounded-lg text-white placeholder-gray-600 focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none" />
          </div>

          <div class="flex items-end">
            <button type="submit" :disabled="loading || !form.amount"
              class="w-full bg-[#00D4FF] text-black px-4 py-2 rounded-lg font-bold hover:bg-[#00b8e6] disabled:opacity-50 disabled:cursor-not-allowed transition">
              {{ loading ? 'Opening...' : '→ Open Trade' }}
            </button>
          </div>
        </form>
        <p v-if="errorMessage" class="mt-3 text-sm text-red-400">{{ errorMessage }}</p>
        <p v-if="successMessage" class="mt-3 text-sm text-green-400">{{ successMessage }}</p>
      </div>

      <!-- Live Market Prices -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
          <h2 class="text-xl font-semibold">Live Market Prices</h2>
          <div class="relative w-full md:w-64">
            <input v-model="searchQuery" type="text" placeholder="Search assets..."
              class="w-full bg-[#111827] border border-[#1f3348] rounded-lg py-2 px-4 text-sm text-white focus:border-[#00D4FF] outline-none transition" />
          </div>
        </div>

        <div v-if="market.length === 0" class="py-8 text-center text-gray-400">Loading prices...</div>
        <div v-else-if="filteredMarket.length === 0" class="py-8 text-center text-gray-400">No assets found matching "{{
          searchQuery }}"</div>
        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div v-for="coin in filteredMarket" :key="coin.symbol"
            class="bg-[#111827] border border-[#1f2a44] rounded-lg p-4">
            <div class="flex items-start justify-between mb-3">
              <div>
                <p class="font-bold text-white">{{ coin.name }}</p>
                <p class="text-sm text-gray-400">{{ coin.symbol }}</p>
              </div>
              <span class="font-mono text-[#00D4FF] font-bold">${{ formatCurrency(coin.price) }}</span>
            </div>
            <button @click="quickBuy(coin.symbol)" :disabled="loading"
              class="w-full px-3 py-1 text-sm text-green-400 transition rounded bg-green-600/20 hover:bg-green-600/30">
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
                  <span :class="trade.type === 'buy' ? 'bg-green-600/20 text-green-400' : 'bg-red-600/20 text-red-400'"
                    class="px-2 py-1 text-xs font-semibold rounded">
                    {{ trade.type.toUpperCase() }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">${{ formatCurrency(trade.amount) }}</td>
                <td class="px-4 py-3 font-mono text-right">${{ formatCurrency(trade.entry_price) }}</td>
                <td class="px-4 py-3 text-right text-[#00D4FF]">${{ formatCurrency(currentPrices[trade.pair] ||
                  trade.entry_price) }}</td>
                <td class="px-4 py-3 text-right">
                  <span :class="calculatePnL(trade) >= 0 ? 'text-green-400' : 'text-red-400'" class="font-bold">
                    {{ calculatePnL(trade) >= 0 ? '+' : '-' }}${{ formatCurrency(calculatePnL(trade)) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <button @click="closeTrade(trade)" :disabled="loading"
                    class="bg-[#1f3348] text-gray-300 px-3 py-1 rounded text-xs hover:bg-[#2d4a66] disabled:opacity-50 transition">
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
                    {{ trade.profit_loss >= 0 ? '+' : '-' }}${{ formatCurrency(trade.profit_loss || 0) }}
                  </span>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-right">
                  ${{ formatCurrency(trade.entry_price) }} → ${{ trade.exit_price ? formatCurrency(trade.exit_price) :
                    'N/A' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-400">{{ formatDate(trade.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div v-if="closeModal.show"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl w-full max-w-md p-6 shadow-2xl">

        <div v-if="closeModal.phase === 'confirm'" class="text-center">
          <div class="flex justify-center mb-4 text-orange-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-white">Close Trade?</h3>
          <p class="mt-2 text-gray-400">Are you sure you want to close trade #{{ closeModal.trade?.id }} for {{
            closeModal.trade?.pair }}?</p>
          <div class="flex gap-3 mt-6">
            <button @click="closeModal.show = false"
              class="flex-1 px-4 py-2 text-gray-300 transition bg-gray-800 rounded-lg hover:bg-gray-700">Cancel</button>
            <button @click="executeClose"
              class="flex-1 px-4 py-2 font-bold text-black transition bg-red-500 rounded-lg hover:bg-red-600">Confirm
              Close</button>
          </div>
        </div>

        <div v-if="closeModal.phase === 'processing'" class="py-8 text-center">
          <div
            class="inline-block w-12 h-12 border-4 border-[#00D4FF] border-t-transparent rounded-full animate-spin mb-4">
          </div>
          <h3 class="text-xl font-bold text-white">Processing...</h3>
          <p class="mt-2 text-gray-400">Finalizing your P&L and updating balance.</p>
        </div>

        <div v-if="closeModal.phase === 'success'" class="text-center">
          <div class="flex justify-center mb-4 text-green-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-white">Trade Closed!</h3>
          <p class="mt-2 text-gray-400">Your trade was closed successfully. Refreshing your dashboard...</p>
        </div>

      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router'

import MainLayout from '@/Layouts/MainLayout.vue';
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import api from '@/api';

const router = useRouter();

const user = ref(JSON.parse(localStorage.getItem('user') || '{}'));
const showPrompt = ref(false);

const form = ref({ pair: 'BTC/USDT', type: 'buy', amount: 1000 });
const loading = ref(false);
const market = ref([]);
const trades = ref([]);
const closedTrades = ref([]);
const wallet = ref({ cleared_balance_usd: 0 });
const tronAddress = ref(null);
const currentPrices = ref({});
const errorMessage = ref('');
const successMessage = ref('');
const searchQuery = ref('');
let updateInterval = null;

const closeModal = ref({
  show: false,
  phase: 'confirm', // 'confirm' | 'processing' | 'success'
  trade: null
});

const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  if (role.includes('admin')) return true;
  if (Array.isArray(u.roles)) {
    return u.roles.some((r) => {
      const candidate = (typeof r === 'string' ? r : r?.name || '').toString().toLowerCase();
      return candidate.includes('admin');
    });
  }
  return false;
};

const isUserVerified = computed(() => {
  const u = user.value;
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

const handleAction = (callback) => {
  if (!isUserVerified.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  callback();
};


const openTradesCount = computed(() => trades.value.length);

const filteredMarket = computed(() => {
  if (!searchQuery.value) return market.value;
  const query = searchQuery.value.toLowerCase();
  return market.value.filter(coin =>
    coin.name.toLowerCase().includes(query) ||
    coin.symbol.toLowerCase().includes(query)
  );
});

const totalPnL = computed(() => {
  // Ensure we are reducing numbers, defaulting to 0 if something is missing
  const openPnL = trades.value.reduce((sum, t) => sum + (calculatePnL(t) || 0), 0);
  const closedPnL = closedTrades.value.reduce((sum, t) => {
    // Force conversion to float and default to 0
    const val = parseFloat(t.profit_loss);
    return sum + (isNaN(val) ? 0 : val);
  }, 0);

  return openPnL + closedPnL;
});

const calculatePnL = (trade) => {
  if (!trade) return 0;

  // Ensure all inputs are treated as numbers
  const currentPrice = parseFloat(currentPrices.value[trade.pair] || trade.entry_price || 0);
  const entryPrice = parseFloat(trade.entry_price || 0);
  const amount = parseFloat(trade.amount || 0);

  // Prevent division by zero or math on 0
  if (entryPrice <= 0 || amount <= 0) return 0;

  let pnl = 0;
  if (trade.type === 'buy') {
    pnl = (currentPrice - entryPrice) * (amount / entryPrice);
  } else {
    pnl = (entryPrice - currentPrice) * (amount / entryPrice);
  }

  return isNaN(pnl) ? 0 : pnl;
};

const formatCurrency = (amount) => {
  // If amount is undefined, null, or NaN, default to 0
  const value = parseFloat(amount) || 0;
  return Math.abs(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString();
};

const fetchData = async () => {
  try {
    // This starts all requests at the SAME time
    const [walletRes, addressRes, marketRes, tradesRes] = await Promise.all([
      api.get('/wallet/balances'),
      api.get('/crypto/address'),
      api.get('/market/crypto'),
      api.get('/trades')
    ]);

    // Now assign the data
    wallet.value = walletRes.data.data || { cleared_balance_usd: 0 };
    tronAddress.value = addressRes.data;

    market.value = marketRes.data.data || [];

    currentPrices.value = {};

    market.value.forEach(coin => {
      const symbol = coin.symbol.toUpperCase();
      currentPrices.value[`${symbol}/USDT`] = coin.price;
    });

    const allTrades = tradesRes.data.data || [];
    trades.value = allTrades.filter(t => t.status === 'open');
    closedTrades.value = allTrades.filter(t => t.status === 'closed');

  } catch (e) {
    console.error("Data fetch failed:", e);
  }
};

const openTrade = async () => {
  if (!isUserVerified.value) {
    showPrompt.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

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

const closeTrade = (trade) => {
  closeModal.value = {
    show: true,
    phase: 'confirm',
    trade: trade
  };
};

const executeClose = async () => {
  const tradeId = closeModal.value.trade.id;

  // 1. Move to processing
  closeModal.value.phase = 'processing';

  try {
    await api.post(`/trade/close/${tradeId}`);

    // 2. Move to success
    closeModal.value.phase = 'success';

    // 3. Wait 2 seconds so they can see the success message, then reload
    setTimeout(() => {
      window.location.reload(); // Hard refresh as requested
      // Alternatively, use: await fetchData(); closeModal.value.show = false;
    }, 2000);

  } catch (e) {
    closeModal.value.show = false;
    errorMessage.value = e.response?.data?.message || 'Failed to close trade';
  }
};

// New function for "Live" data only
const fetchUpdates = async () => {
  try {
    const [walletRes, marketRes, tradesRes] = await Promise.all([
      api.get('/wallet/balances'),
      api.get('/market/crypto'),
      api.get('/trades') // Maybe create a specific /trades/open endpoint later
    ]);

    wallet.value = walletRes.data.data || { cleared_balance_usd: 0 };
    market.value = marketRes.data.data || [];
    const allTrades = tradesRes.data.data || [];
    trades.value = allTrades.filter(t => t.status === 'open');

    market.value.forEach(coin => {
      currentPrices.value[coin.symbol.toUpperCase() + '/USDT'] = coin.price;
    });
  } catch (e) { console.error(e); }
};

onMounted(() => {
  // Instant Load from cache
  const cached = localStorage.getItem('last_market_data');
  if (cached) {
    market.value = JSON.parse(cached);
  }

  fetchData(); // Initial full load
  updateInterval = setInterval(fetchUpdates, 10000); // Polling only the essentials
});

onUnmounted(() => {
  if (updateInterval) clearInterval(updateInterval);
});

const quickBuy = async (symbol) => {
  form.value.pair = symbol.toUpperCase() + '/USDT';
  form.value.type = 'buy';
  form.value.amount = 100;
  await openTrade();
};


const withdrawNav = () => {
  router.push('/crypto/withdraw')
}
const depositNav = () => {
  router.push('/crypto/deposit')
}
</script>
