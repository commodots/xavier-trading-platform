<template>
  <MainLayout>
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">FX Management</h1>
        <div class="flex gap-2">
          <button @click="refreshData" class="px-4 py-2 text-sm text-white transition bg-gray-700 rounded-lg hover:bg-gray-600">
            ⟳ Refresh
          </button>
        </div>
      </div>

      <!-- Status Messages -->
      <div v-if="message" :class="messageType === 'success' ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30'" 
        class="px-4 py-3 mb-4 border rounded-lg">
        {{ message }}
      </div>

      <!-- Section 1: Provider Settings -->
      <div class="p-6 mb-6 bg-[#1C1F2E] rounded-xl border border-[#2A314A]">
        <h2 class="mb-4 text-lg font-semibold">⚙️ FX Provider</h2>
        
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div>
            <label class="block mb-2 text-sm text-gray-400">Current Provider</label>
            <select v-model="selectedProvider" @change="switchProvider" 
              class="w-full px-4 py-3 text-white bg-[#151a27] border border-[#2A314A] rounded-lg">
              <option value="manual">🔧 Manual</option>
              <option value="fincra">🏦 Fincra</option>
            </select>
            <p class="mt-2 text-xs text-gray-500">
              <span v-if="settings?.enabled" class="text-green-400">● Enabled</span>
              <span v-else class="text-red-400">● Disabled</span>
            </p>
          </div>

          <div class="p-4 rounded-lg bg-[#151a27] border border-[#2A314A]">
            <p class="mb-2 text-sm font-medium text-gray-300">Auto-Convert for Stocks</p>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="autoConvertStocks" @change="toggleAutoConvert" class="sr-only peer">
              <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:bg-blue-600 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
              <span class="ml-3 text-sm text-gray-400">{{ autoConvertStocks ? 'ON' : 'OFF' }}</span>
            </label>
            <p class="mt-1 text-xs text-gray-500">When ON, stock purchases auto-convert NGN to USD if needed.</p>
          </div>
        </div>
      </div>

      <!-- Section 2: Manual Rates (Visible when provider = manual) -->
      <div v-if="selectedProvider === 'manual'" class="p-6 mb-6 bg-[#1C1F2E] rounded-xl border border-[#2A314A]">
        <h2 class="mb-4 text-lg font-semibold">📊 Manual FX Rates</h2>
        <p class="mb-4 text-sm text-gray-400">Edit buy/sell rates for each currency pair.</p>

        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left text-gray-300">
            <thead class="text-xs uppercase bg-[#151a27] text-gray-400">
              <tr>
                <th class="px-4 py-3">Pair</th>
                <th class="px-4 py-3">Buy Rate (Platform Buys)</th>
                <th class="px-4 py-3">Sell Rate (Platform Sells)</th>
                <th class="px-4 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#2A314A]">
              <tr v-for="pair in pairs" :key="pair.id" class="hover:bg-[#252b3d]">
                <td class="px-4 py-3 font-medium">{{ pair.base_currency }}/{{ pair.quote_currency }}</td>
                <td class="px-4 py-3">
                  <input v-model.number="pair.edit_buy_rate" type="number" step="0.01" 
                    :disabled="!pair.editing"
                    class="w-32 px-3 py-1.5 text-white bg-[#151a27] border border-[#2A314A] rounded disabled:opacity-50 disabled:cursor-not-allowed" 
                    :placeholder="formatCurrency(pair.buy_rate, pair.quote_currency)" />
                </td>
                <td class="px-4 py-3">
                  <input v-model.number="pair.edit_sell_rate" type="number" step="0.01" 
                    :disabled="!pair.editing"
                    class="w-32 px-3 py-1.5 text-white bg-[#151a27] border border-[#2A314A] rounded disabled:opacity-50 disabled:cursor-not-allowed" 
                    :placeholder="formatCurrency(pair.sell_rate, pair.quote_currency)" />
                </td>
                <td class="px-4 py-3 text-right">
                  <button v-if="!pair.editing" @click="enableEdit(pair)" 
                    class="px-4 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                    Edit
                  </button>
                  <button v-else @click="updatePair(pair)" :disabled="saving === pair.id"
                    class="px-4 py-1.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 transition">
                    {{ saving === pair.id ? 'Saving...' : 'Save' }}
                  </button>
                </td>
              </tr>
              <tr v-if="pairs.length === 0">
                <td colspan="4" class="px-4 py-4 text-center text-gray-500">No FX pairs configured. Run seeder.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Section 3: Fincra Status (Visible when provider = fincra) -->
      <div v-if="selectedProvider === 'fincra'" class="p-6 mb-6 bg-[#1C1F2E] rounded-xl border border-[#2A314A]">
        <h2 class="mb-4 text-lg font-semibold">🏦 Fincra Status</h2>
        
          <div v-if="fincraStatus" class="space-y-4">
            <!-- Health Message (if any) -->
            <div v-if="fincraStatus.message" 
              class="p-3 rounded-lg text-sm" 
              :class="fincraStatus.status === 'connected' ? 'bg-green-500/10 text-green-400 border border-green-500/30' : 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/30'">
              ⓘ {{ fincraStatus.message }}
              <p v-if="fincraStatus.help" class="mt-1 text-xs text-gray-400">{{ fincraStatus.help }}</p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
              <div class="p-4 rounded-lg bg-[#151a27] border border-[#2A314A]">
                <p class="text-xs tracking-wider text-gray-500 uppercase">Connection Status</p>
                <p class="mt-1 text-lg font-bold" :class="fincraStatus.status === 'connected' ? 'text-green-400' : 'text-red-400'">
                  {{ fincraStatus.status === 'connected' ? '✅ Connected' : '❌ Disconnected' }}
                </p>
              </div>
              <div class="p-4 rounded-lg bg-[#151a27] border border-[#2A314A]">
                <p class="text-xs tracking-wider text-gray-500 uppercase">Mode</p>
                <p class="mt-1 text-lg font-bold text-white">{{ fincraStatus.mode || 'N/A' }}</p>
              </div>
              <div class="p-4 rounded-lg bg-[#151a27] border border-[#2A314A]">
                <p class="text-xs tracking-wider text-gray-500 uppercase">Last Check</p>
                <p class="mt-1 text-sm text-white">{{ fincraStatus.last_check ? new Date(fincraStatus.last_check).toLocaleString() : 'N/A' }}</p>
              </div>
              <div class="p-4 rounded-lg bg-[#151a27] border border-[#2A314A] md:col-span-2">
                <p class="text-xs tracking-wider text-gray-500 uppercase">Endpoint</p>
                <p class="mt-1 font-mono text-sm text-blue-400 break-all">{{ fincraStatus.endpoint || 'N/A' }}</p>
              </div>
            </div>
          </div>
        <div v-else class="p-4 text-center text-gray-500">
          <p>Loading Fincra status...</p>
          <button @click="checkFincraHealth" class="px-4 py-2 mt-2 text-sm text-white bg-blue-600 rounded-lg hover:bg-blue-700">
            Check Now
          </button>
        </div>
      </div>

      <!-- Conversion History Section -->
      <div class="p-6 bg-[#1C1F2E] rounded-xl border border-[#2A314A]">
        <h2 class="mb-4 text-lg font-semibold">📋 Recent FX Conversions</h2>
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left text-gray-300">
            <thead class="text-xs uppercase bg-[#151a27] text-gray-400">
              <tr>
                <th class="px-4 py-3">Reference</th>
                <th class="px-4 py-3">User</th>
                <th class="px-4 py-3">From</th>
                <th class="px-4 py-3">To</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Rate</th>
                <th class="px-4 py-3">Converted</th>
                <th class="px-4 py-3">Provider</th>
                <th class="px-4 py-3">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#2A314A]">
              <tr v-for="conv in conversions" :key="conv.id" class="hover:bg-[#252b3d]">
                <td class="px-4 py-3 font-mono text-xs">{{ conv.reference }}</td>
                <td class="px-4 py-3">{{ conv.user?.name || conv.user_id }}</td>
                <td class="px-4 py-3">{{ conv.from_currency }}</td>
                <td class="px-4 py-3">{{ conv.to_currency }}</td>
                <td class="px-4 py-3">{{ formatCurrency(conv.amount, conv.from_currency) }}</td>
                <td class="px-4 py-3">{{ formatCurrency(conv.rate, conv.to_currency, true) }}</td>
                <td class="px-4 py-3">{{ formatCurrency(conv.converted_amount, conv.to_currency) }}</td>
                <td class="px-4 py-3">
                  <span :class="conv.provider === 'fincra' ? 'text-purple-400' : 'text-green-400'">
                    {{ conv.provider }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs">{{ new Date(conv.created_at).toLocaleString() }}</td>
              </tr>
              <tr v-if="conversions.length === 0">
                <td colspan="9" class="px-4 py-4 text-center text-gray-500">No conversions yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import api from '@/api';

const settings = ref(null);
const selectedProvider = ref('manual');
const autoConvertStocks = ref(false);
const pairs = ref([]);
const fincraStatus = ref(null);
const conversions = ref([]);
const saving = ref(null);
const message = ref('');
const messageType = ref('success');


const formatCurrency = (amount, currency, showFullDecimals = false) => {
  if (amount === null || amount === undefined) return '---';
  const value = Number(amount);
  if (currency === 'USD') {
   
    if (showFullDecimals || value < 0.01) {
      return '$' + value.toLocaleString('en-US', { minimumFractionDigits: 6, maximumFractionDigits: 6 });
    }
    return '$' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  } else if (currency === 'NGN') {
    return '₦' + value.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  return value.toLocaleString();
};

const showMessage = (msg, type = 'success') => {
  message.value = msg;
  messageType.value = type;
  setTimeout(() => { message.value = ''; }, 3000);
};

const fetchData = async () => {
  try {
    const res = await api.get('/admin/fx/management');
    if (res.data?.success) {
      const data = res.data.data;
      settings.value = data.settings;
      selectedProvider.value = data.settings?.provider || 'manual';
      autoConvertStocks.value = data.settings?.auto_convert_stocks || false;
      
      // Initialize edit fields for pairs
      pairs.value = (data.pairs || []).map(p => ({
        ...p,
        edit_buy_rate: p.buy_rate,
        edit_sell_rate: p.sell_rate,
        editing: false, 
      }));
      
      fincraStatus.value = data.fincra_status;
    }
  } catch (e) {
    console.error('Failed to load FX management data', e);
    showMessage('Failed to load data', 'error');
  }
};

const fetchConversions = async () => {
  try {
    const res = await api.get('/admin/fx/conversions');
    if (res.data?.success) {
      conversions.value = res.data.data || [];
    }
  } catch (e) {
    conversions.value = [];
  }
};

const enableEdit = (pair) => {
  // Disable editing for all other pairs
  pairs.value.forEach(p => {
    if (p.id !== pair.id) {
      p.editing = false;
    }
  });
  // Enable editing for this pair
  pair.editing = true;
};

const switchProvider = async () => {
  try {
    const res = await api.post('/admin/fx/switch-provider', { provider: selectedProvider.value });
    if (res.data?.success) {
      showMessage(`Provider switched to ${selectedProvider.value}`);
      await fetchData();
    }
  } catch (e) {
    showMessage('Failed to switch provider', 'error');
    selectedProvider.value = settings.value?.provider || 'manual';
  }
};

const updatePair = async (pair) => {
  saving.value = pair.id;
  try {
    const res = await api.put(`/admin/fx/pairs/${pair.id}`, {
      buy_rate: pair.edit_buy_rate,
      sell_rate: pair.edit_sell_rate,
    });
    if (res.data?.success) {
      showMessage(`${pair.base_currency}/${pair.quote_currency} rates updated`);
      pair.buy_rate = pair.edit_buy_rate;
      pair.sell_rate = pair.edit_sell_rate;
      pair.editing = false; // Disable editing after save
    }
  } catch (e) {
    showMessage('Failed to update rates', 'error');
  } finally {
    saving.value = null;
  }
};

const toggleAutoConvert = async () => {
  try {
    const res = await api.post('/admin/fx/toggle-auto-convert', { auto_convert_stocks: autoConvertStocks.value });
    if (res.data?.success) {
      showMessage(`Auto-convert ${autoConvertStocks.value ? 'enabled' : 'disabled'}`);
    }
  } catch (e) {
    showMessage('Failed to toggle auto-convert', 'error');
    autoConvertStocks.value = !autoConvertStocks.value;
  }
};

const checkFincraHealth = async () => {
  try {
    const res = await api.get('/admin/fx/health');
    if (res.data?.success) {
      fincraStatus.value = res.data.data;
    }
  } catch (e) {
    fincraStatus.value = { status: 'disconnected', error: e.message };
  }
};

const refreshData = async () => {
  await Promise.all([fetchData(), fetchConversions()]);
};

onMounted(refreshData);
</script>