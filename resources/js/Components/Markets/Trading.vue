<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm px-4">
      <div class="absolute inset-0 transition-opacity" @click="$emit('close')"></div>
      
      <div class="bg-[#1C1F2E] rounded-2xl shadow-xl w-full max-w-4xl relative border border-[#2A314A] transition-colors duration-300 z-10 overflow-hidden flex flex-col max-h-[90vh]">
        
        <button @click="$emit('close')" class="absolute z-20 text-xl text-gray-400 transition-colors top-4 right-4 hover:text-white">×</button>

        <div class="p-6 border-b border-[#2A314A] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shrink-0 bg-[#161925]">
          <div class="flex items-center gap-3">
            <h2 class="flex items-center gap-2 text-xl font-bold text-white">
              <span v-if="activeTab === 'market'">🪙 Crypto Exchange</span>
              <span v-else-if="activeTab === 'deposit'">💰 Deposit USDT</span>
              <span v-else>💸 Withdraw USDT</span>
            </h2>
            
            <div class="flex bg-[#0F1724] p-1 border border-gray-800 rounded-lg text-xs font-bold">
              <button @click="activeTab = 'market'" 
                class="px-3 py-1 rounded-md transition-all uppercase text-[10px] tracking-wider"
                :class="activeTab === 'market' ? 'bg-[#00D4FF] text-black' : 'text-gray-400 hover:text-white'">
                Market
              </button>
              <button @click="handleAction(() => activeTab = 'deposit')" 
                class="px-3 py-1 rounded-md transition-all uppercase text-[10px] tracking-wider"
                :class="activeTab === 'deposit' ? 'bg-[#00D4FF] text-black' : 'text-gray-400 hover:text-white'">
                Deposit
              </button>
              <button @click="handleAction(() => activeTab = 'withdraw')" 
                class="px-3 py-1 rounded-md transition-all uppercase text-[10px] tracking-wider"
                :class="activeTab === 'withdraw' ? 'bg-red-500 text-white' : 'text-gray-400 hover:text-white'">
                Withdraw
              </button>
            </div>
          </div>

          <div class="flex items-center gap-4 text-xs">
            <div class="text-right">
              <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider">USD Balance</p>
              <p class="text-sm font-bold text-[#00D4FF]">{{ formatCurrency(wallet.cleared_balance_usd || 0) }}</p>
            </div>
          </div>
        </div>

        <div class="p-6 overflow-y-auto space-y-6 flex-1 bg-[#1C1F2E]">
          <EmailVerificationPrompt v-if="showPrompt" :user="user" />

          <div v-if="activeTab === 'market'" class="space-y-6">
            
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div class="p-4 bg-[#0F1724] border border-gray-800 rounded-xl flex justify-between items-center">
                <div>
                  <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Active Open Trades</p>
                  <p class="mt-1 font-mono text-2xl font-bold text-orange-400">{{ openTradesCount }}</p>
                </div>
              </div>
              <div class="p-4 bg-[#0F1724] border border-gray-800 rounded-xl flex justify-between items-center">
                <div>
                  <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Gain/Loss</p>
                  <p :class="totalPnL >= 0 ? 'text-green-400' : 'text-red-400'" class="mt-1 font-mono text-2xl font-bold">
                    {{ totalPnL >= 0 ? '+' : '-' }}{{ formatCurrency(Math.abs(totalPnL)) }}
                  </p>
                </div>
                
              </div>
            </div>

            <div class="p-5 space-y-4 border border-gray-800 rounded-xl bg-black/20">
              <h3 class="text-xs font-bold tracking-widest text-gray-400 uppercase">Buy/Sell</h3>
              
              <form @submit.prevent="openTrade" class="grid items-end grid-cols-1 gap-3 sm:grid-cols-4">
                <div class="space-y-1">
                  <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Trading Pair</label>
                  <select v-model="form.pair" class="w-full px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm focus:border-blue-500 outline-none transition-all font-bold">
                    <option v-for="coin in market" :key="coin.symbol" :value="coin.symbol.toUpperCase() + '/USDT'">
                      {{ coin.name }} ({{ coin.symbol.toUpperCase() }}/USDT)
                    </option>
                    <option v-if="market.length === 0" value="BTC/USDT">Loading operational indices...</option>
                  </select>
                </div>

                <div class="space-y-1">
                  <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Action</label>
                  <select v-model="form.type" class="w-full px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm focus:border-blue-500 outline-none transition-all font-bold">
                    <option value="buy">BUY</option>
                    <option value="sell">SELL</option>
                  </select>
                </div>

                <div class="space-y-1">
                  <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Value ($)</label>
                  <input v-model.number="form.amount" type="number" placeholder="1000" min="1" step="1"
                    class="w-full px-3 py-2 bg-[#0F1724] border border-gray-700 rounded-lg text-white text-sm focus:border-blue-500 outline-none transition-all font-mono" />
                </div>

                <button type="submit" :disabled="loading || !form.amount"
                  class="w-full py-2 text-sm font-black tracking-wider text-white uppercase transition-all rounded-lg disabled:opacity-40"
                  :class="form.type === 'buy' ? 'bg-gradient-to-r from-blue-700 to-[#00D4FF]' : 'bg-gradient-to-r from-red-700 to-red-500'">
                  {{ loading ? 'Processing...' : form.type === 'buy' ? 'Buy' : 'Sell' }}
                </button>
              </form>

              <div v-if="errorMessage" class="flex items-center gap-2 p-3 text-xs border rounded-lg bg-rose-500/10 border-rose-500/20 text-rose-400">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-400 shrink-0"></span>
                <p>{{ errorMessage }}</p>
              </div>
              <div v-if="successMessage" class="flex items-center gap-2 p-3 text-xs border rounded-lg bg-emerald-500/10 border-emerald-500/20 text-emerald-400">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                <p>{{ successMessage }}</p>
              </div>
            </div>

            <div class="space-y-3">
              <h3 class="text-xs font-bold tracking-widest text-gray-400 uppercase">Active Open Positions ({{ openTradesCount }})</h3>
              <div class="border border-gray-800 rounded-xl overflow-hidden bg-[#0F1724]">
                <div v-if="trades.length === 0" class="py-8 text-sm text-center text-gray-500">No open positions reported in this node.</div>
                <div v-else class="overflow-x-auto">
                  <table class="w-full text-sm">
                    <thead class="text-[10px] font-bold uppercase text-gray-400 border-b border-gray-800 bg-black/20">
                      <tr>
                        <th class="px-4 py-3 tracking-wider text-left">No.</th>
                        <th class="px-4 py-3 tracking-wider text-left">Trading Pair</th>
                        <th class="px-4 py-3 tracking-wider text-right">Price (USD)</th>
                        <th class="px-4 py-3 tracking-wider text-right">Entry Price</th>
                        <th class="px-4 py-3 tracking-wider text-right">Live Market Price</th>
                        <th class="px-4 py-3 tracking-wider text-right">Gain/Loss</th>
                        <th class="px-4 py-3 tracking-wider text-center">Action</th>
                      </tr>
                    </thead>
                    <tbody class="font-medium divide-y divide-gray-800/60">
                      <tr v-for="trade in trades" :key="trade.id" class="transition-colors hover:bg-white/5">
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">#{{ String(trade.id).substring(0,6) }}</td>
                        <td class="px-4 py-3 font-bold text-white">{{ trade.pair }}</td>
                        <td class="px-4 py-3 font-mono text-right">{{ formatCurrency(trade.amount) }}</td>
                        <td class="px-4 py-3 font-mono text-right text-gray-400">{{ formatCurrency(trade.entry_price) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-[#00D4FF]">{{ formatCurrency(currentPrices[trade.pair] || trade.entry_price) }}</td>
                        <td class="px-4 py-3 font-mono text-right">
                          <span :class="calculatePnL(trade) >= 0 ? 'text-green-400' : 'text-red-400'">
                            {{ calculatePnL(trade) >= 0 ? '+' : '-' }}{{ formatCurrency(Math.abs(calculatePnL(trade))) }}
                          </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                          <button @click="closeTrade(trade)" :disabled="loading" class="px-3 py-1 text-xs text-gray-300 transition-all bg-gray-800 border border-gray-700 rounded hover:bg-red-950/40 hover:text-red-400 hover:border-red-950">
                          Sell
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'deposit'" class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div class="space-y-4 md:col-span-2">
              <div class="p-5 border border-gray-800 rounded-xl bg-[#0F1724] space-y-4">
                <h3 class="text-xs font-bold tracking-widest text-gray-400 uppercase">Deposit USDT (TRC20) to your trading account</h3>
                
                <div v-if="addressLoading" class="py-8 space-y-2 text-center">
                  <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-[#00D4FF] mx-auto"></div>
                  <p class="text-xs font-bold tracking-wider text-gray-500 uppercase">Generating TRON Address</p>
                </div>

                <div v-else-if="address" class="space-y-4">
                  <div class="p-4 space-y-1 border border-gray-800 bg-black/20 rounded-xl">
                    <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider">Your TRON Deposit Address</span>
                    <p class="font-mono text-[#00D4FF] break-all text-base select-all tracking-tight pt-1 font-bold">{{ address }}</p>
                  </div>

                  <div class="grid grid-cols-2 gap-2">
                    <button @click="copyAddress" class="bg-[#00D4FF] text-black py-2.5 rounded-lg font-bold text-xs uppercase tracking-wider hover:bg-[#00b8e6] transition-all">
                      📋 Copy Address
                    </button>
                    <button @click="showQR = !showQR" class="bg-gray-800 text-gray-300 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider hover:bg-gray-700 transition-all border border-gray-700">
                      📱 {{ showQR ? 'Hide' : 'Show' }} QR Code
                    </button>
                  </div>
                  
                  <div v-if="showQR" class="p-4 bg-white rounded-xl max-w-[200px] mx-auto transition-all animate-fadeIn">
                    <img :src="qrCodeUrl" alt="System QR Node" class="w-full aspect-square" />
                  </div>
                </div>

                <div v-else class="py-6 space-y-2 text-center">
                  <p class="text-xs font-bold uppercase text-rose-400">Failed to load deposit address</p>
                  <button @click="loadAddress" class="px-4 py-2 text-xs font-bold tracking-wider text-white uppercase bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700">Retry</button>
                </div>
              </div>

              <div class="p-4 space-y-2 border bg-yellow-500/5 border-yellow-500/20 rounded-xl">
                <h4 class="text-xs font-bold tracking-wider text-yellow-500 uppercase">⚠️ Important</h4>
                <ul class="text-[11px] text-gray-400 space-y-1 font-medium">
                  <li>• Send only USDT (TRC20) to this address</li>
                  <li>• Deposits are processed automatically</li>
                  <li>• Minimum deposit: 1 USDT</li>
                  <li>• Network: TRON (not ETH or BSC)</li>
                </ul>
              </div>
            </div>

            <div class="space-y-3">
              <h3 class="text-xs font-bold tracking-widest text-gray-400 uppercase">Recent Deposits</h3>
              <div class="bg-[#0F1724] border border-gray-800 rounded-xl p-4 divide-y divide-gray-800/60 max-h-[300px] overflow-y-auto">
                <div v-if="deposits.length === 0" class="py-6 text-xs font-bold tracking-wider text-center text-gray-500 uppercase">No recent deposits</div>
                <div v-for="deposit in deposits.slice(0, 5)" :key="deposit.id" class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                  <div>
                    <p class="font-mono text-sm font-bold text-white">{{ deposit.amount }} USDT</p>
                    <p class="text-[10px] text-gray-500 font-semibold">{{ formatDate(deposit.created_at) }}</p>
                  </div>
                  <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded text-[9px] uppercase font-black tracking-wider border border-emerald-500/20"> Completed </span>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'withdraw'" class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <div class="md:col-span-2">
              <form @submit.prevent="submitWithdrawal" class="p-5 border border-gray-800 rounded-xl bg-[#0F1724] space-y-4">
                <h3 class="text-xs font-bold tracking-widest text-gray-400 uppercase">Withdrawal Request</h3>
                
                <div class="space-y-1">
                  <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Destination TRON Address (TRC20)</label>
                  <input v-model="withdrawForm.address" type="text" placeholder="TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                    class="w-full px-3 py-2 font-mono text-sm text-white transition-all border border-gray-700 rounded-lg outline-none bg-black/20 focus:border-red-500" required />
                </div>

                <div class="space-y-1">
                  <label class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Withdrawal Amount (USDT)</label>
                  <input v-model.number="withdrawForm.amount" type="number" placeholder="100" min="1" step="0.01" :max="wallet.cleared_balance_usd"
                    class="w-full px-3 py-2 font-mono text-sm text-white transition-all border border-gray-700 rounded-lg outline-none bg-black/20 focus:border-red-500" required />
                </div>

                <div class="p-3 space-y-2 border bg-red-950/20 border-red-900/30 rounded-xl">
                  <h4 class="text-[11px] font-bold text-red-400 uppercase tracking-wider">⚠️ Security Check</h4>
                  <p class="text-[11px] text-gray-400 leading-relaxed font-medium">
                    For security, withdrawals require email confirmation. Check your email after submitting.
                  </p>
                  <label class="flex items-center gap-2 pt-1 cursor-pointer select-none">
                    <input v-model="withdrawForm.confirmed" type="checkbox" class="text-red-500 border-gray-700 rounded focus:ring-0 bg-black/40" required />
                    <span class="text-[10px] text-gray-300 font-bold uppercase tracking-tight">I confirm this withdrawal and understand it cannot be reversed</span>
                  </label>
                </div>

                <button type="submit" :disabled="loading || !withdrawForm.address || !withdrawForm.amount || !withdrawForm.confirmed"
                  class="w-full py-3 text-xs font-black tracking-wider text-white uppercase transition-all rounded-lg bg-gradient-to-r from-red-700 to-red-500 disabled:opacity-40">
                   {{ loading ? 'Processing...' : '🚀 Submit Withdrawal' }}
                </button>
              </form>

              <div v-if="errorMessage" class="flex items-center gap-2 p-3 mt-3 text-xs border rounded-lg bg-rose-500/10 border-rose-500/20 text-rose-400">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-400 shrink-0"></span>
                <p>{{ errorMessage }}</p>
              </div>
              <div v-if="successMessage" class="flex items-center gap-2 p-3 mt-3 text-xs border rounded-lg bg-emerald-500/10 border-emerald-500/20 text-emerald-400">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                <p>{{ successMessage }}</p>
              </div>
            </div>

            <div class="space-y-3">
              <h3 class="text-xs font-bold tracking-widest text-gray-400 uppercase">Recent Withdrawals</h3>
              <div class="bg-[#0F1724] border border-gray-800 rounded-xl p-4 divide-y divide-gray-800/60 max-h-[300px] overflow-y-auto">
                <div v-if="withdrawals.length === 0" class="py-6 text-xs font-bold tracking-wider text-center text-gray-500 uppercase">No recent withdrawals</div>
                <div v-for="withdrawal in withdrawals.slice(0, 5)" :key="withdrawal.id" class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                  <div>
                    <p class="font-mono text-sm font-bold text-white">{{ withdrawal.amount }} USDT</p>
                    <p class="text-[10px] text-gray-500 font-semibold">{{ formatDate(withdrawal.created_at) }}</p>
                  </div>
                  <span :class="withdrawal.status === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20'"
                    class="px-2 py-0.5 rounded text-[9px] uppercase font-black tracking-wider border">
                    {{ withdrawal.status }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="closeModal.show" class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
      <div class="bg-[#1C1F2E] border border-[#2A314A] rounded-2xl w-full max-w-sm p-6 text-center shadow-2xl space-y-4">
        <div v-if="closeModal.phase === 'confirm'" class="space-y-4">
          <div class="flex items-center justify-center w-12 h-12 mx-auto text-xl border rounded-full bg-amber-500/10 border-amber-500/30 text-amber-500">⚠️</div>
          <div>
            <h3 class="text-lg font-bold text-white">Confirm Contract Sale?</h3>
            <p class="mt-1 text-xs text-gray-400">Are you sure you want to terminate your current position in <span class="font-bold text-white">{{ closeModal.trade?.pair }}</span>?</p>
          </div>
          <div class="flex gap-2">
            <button @click="closeModal.show = false" class="flex-1 py-2 text-xs font-bold tracking-wider text-gray-300 uppercase transition bg-gray-800 border border-gray-700 rounded-lg hover:bg-gray-700">Abstain</button>
            <button @click="executeClose" class="flex-1 py-2 text-xs font-black tracking-wider text-white uppercase transition rounded-lg shadow-lg bg-gradient-to-r from-red-700 to-red-500 shadow-red-900/20">Execute Sale</button>
          </div>
        </div>

        <div v-if="closeModal.phase === 'processing'" class="py-4 space-y-3">
          <div class="inline-block w-8 h-8 border-2 border-[#00D4FF] border-t-transparent rounded-full animate-spin"></div>
          <h3 class="text-sm font-bold tracking-widest text-white uppercase">Finalizing Settlement...</h3>
        </div>

        <div v-if="closeModal.phase === 'success'" class="py-4 space-y-3">
          <div class="flex items-center justify-center w-10 h-10 mx-auto text-lg rounded-full bg-emerald-500/10 text-emerald-400">✓</div>
          <h3 class="text-sm font-bold tracking-widest text-white uppercase">Asset Liquidated</h3>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import api from '@/api';
import { formatCurrency, formatDate } from '@/lib/formatters';

const props = defineProps({
  show: Boolean
});

const emit = defineEmits(['close']);

const activeTab = ref('market'); 

const user = ref(JSON.parse(localStorage.getItem('user') || '{}'));
const showPrompt = ref(false);
const loading = ref(false);
const addressLoading = ref(false);

const market = ref([]);
const trades = ref([]);
const closedTrades = ref([]);
const wallet = ref({ cleared_balance_usd: 0 });
const currentPrices = ref({});

// Deposit Form Structures
const address = ref('');
const showQR = ref(false);
const qrCodeUrl = ref('');
const deposits = ref([]);

const withdrawForm = ref({
  address: '',
  amount: '',
  confirmed: false
});
const withdrawals = ref([]);

const form = ref({ pair: 'BTC/USDT', type: 'buy', amount: 1000 });
const errorMessage = ref('');
const successMessage = ref('');
let updateInterval = null;

const closeModal = ref({
  show: false,
  phase: 'confirm', // 'confirm' | 'processing' | 'success'
  trade: null
});

// Guardrail Computation Checks
const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  if (role.includes('admin')) return true;
  return Array.isArray(u.roles) && u.roles.some(r => (typeof r === 'string' ? r : r?.name || '').toString().toLowerCase().includes('admin'));
};

const isUserVerified = computed(() => {
  const u = user.value;
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

const handleAction = (callback) => {
  if (!isUserVerified.value) {
    showPrompt.value = true;
    return;
  }
  callback();
};

const openTradesCount = computed(() => trades.value.length);

const totalPnL = computed(() => {
  const openPnL = trades.value.reduce((sum, t) => sum + (calculatePnL(t) || 0), 0);
  const closedPnL = closedTrades.value.reduce((sum, t) => {
    const val = parseFloat(t.profit_loss);
    return sum + (isNaN(val) ? 0 : val);
  }, 0);
  return openPnL + closedPnL;
});

const calculatePnL = (trade) => {
  if (!trade) return 0;
  const currentPrice = parseFloat(currentPrices.value[trade.pair] || trade.entry_price || 0);
  const entryPrice = parseFloat(trade.entry_price || 0);
  const amount = parseFloat(trade.amount || 0);
  if (entryPrice <= 0 || amount <= 0) return 0;

  return trade.type === 'buy' 
    ? (currentPrice - entryPrice) * (amount / entryPrice) 
    : (entryPrice - currentPrice) * (amount / entryPrice);
};

const fetchData = async () => {
  try {
    const [walletRes, marketRes, positionsRes, txRes] = await Promise.all([
      api.get('/wallet/balances'),
      api.get('/market/crypto'),
      api.get('/trade/positions', { params: { category: 'crypto' } }),
      api.get('/transactions').catch(() => ({ data: [] }))
    ]);

    wallet.value = walletRes.data.data || { cleared_balance_usd: 0 };
    market.value = marketRes.data.data || [];

    market.value.forEach(coin => {
      currentPrices.value[`${coin.symbol.toUpperCase()}/USDT`] = coin.price;
    });

    const allTrades = positionsRes.data.data || [];
    trades.value = allTrades.filter(t => t.status === 'open');
    closedTrades.value = allTrades.filter(t => t.status === 'closed');

    if (Array.isArray(txRes.data)) {
      deposits.value = txRes.data.filter(t => t.type === 'deposit' && t.currency === 'USDT');
      withdrawals.value = txRes.data.filter(t => t.type === 'withdrawal' && t.currency === 'USDT');
    }
  } catch (e) {
    console.error("Core operational sync failed:", e);
  }
};

const fetchUpdates = async () => {
  try {
    const [walletRes, marketRes, positionsRes] = await Promise.all([
      api.get('/wallet/balances'),
      api.get('/market/crypto'),
      api.get('/trade/positions', { params: { category: 'crypto' } })
    ]);
    wallet.value = walletRes.data.data || { cleared_balance_usd: 0 };
    market.value = marketRes.data.data || [];
    trades.value = (positionsRes.data.data || []).filter(t => t.status === 'open');
    market.value.forEach(coin => {
      currentPrices.value[coin.symbol.toUpperCase() + '/USDT'] = coin.price;
    });
  } catch (e) { console.error("Poll failure:", e); }
};

// Inbound Deposit Logic
const loadAddress = async () => {
  addressLoading.value = true;
  try {
    const res = await api.get('/crypto/address');
    address.value = res.data.address;
    qrCodeUrl.value = res.data.qr_code_url || `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${address.value}`;
  } catch (e) {
    console.error(e);
  } finally {
    addressLoading.value = false;
  }
};

const copyAddress = async () => {
  try {
    await navigator.clipboard.writeText(address.value);
    alert('Address successfully mapped to runtime clipboard.');
  } catch (e) {
    alert('Failed to execute canvas clip.');
  }
};

// Outbound Settlement Request
const submitWithdrawal = async () => {
  if (!withdrawForm.value.confirmed) return;
  loading.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    await api.post('/crypto/withdraw', {
      address: withdrawForm.value.address,
      amount: withdrawForm.value.amount
    });
    successMessage.value = 'Settlement pipeline dispatched. Verify structural secure email framework linked confirmation node.';
    withdrawForm.value = { address: '', amount: '', confirmed: false };
    await fetchData();
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Outbound structural routing failure.';
  } finally {
    loading.value = false;
  }
};

// Position Controls
const openTrade = async () => {
  if (!isUserVerified.value) { showPrompt.value = true; return; }
  if (!form.value.amount || form.value.amount <= 0) return;

  loading.value = true;
  errorMessage.value = '';
  successMessage.value = '';

  try {
    await api.post('/trade/open', {
      pair: form.value.pair,
      amount: form.value.amount,
      type: form.value.type,
    });
    successMessage.value = `Contract ledger position opened successfully.`;
    form.value.amount = 1000;
    await fetchData();
    setTimeout(() => { successMessage.value = ''; }, 3000);
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Failed to open market contract.';
  } finally {
    loading.value = false;
  }
};

const closeTrade = (trade) => {
  closeModal.value = { show: true, phase: 'confirm', trade };
};

const executeClose = async () => {
  const tradeId = closeModal.value.trade.id;
  closeModal.value.phase = 'processing';
  try {
    await api.post(`/trade/close/${tradeId}`);
    closeModal.value.phase = 'success';
    await fetchData();
    setTimeout(() => { closeModal.value.show = false; }, 1200);
  } catch (e) {
    closeModal.value.show = false;
    errorMessage.value = e.response?.data?.message || 'Termination error.';
  }
};

onMounted(() => {
  const cached = localStorage.getItem('last_market_data');
  if (cached) market.value = JSON.parse(cached);

  fetchData();
  loadAddress();
  updateInterval = setInterval(fetchUpdates, 10000);
});

onUnmounted(() => {
  if (updateInterval) clearInterval(updateInterval);
});
</script>