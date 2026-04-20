<template>
  <MainLayout>
    <div class="space-y-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">₿ Crypto Settings</h1>
        <button
          @click="saveSettings"
          :disabled="!dirty || loading"
          class="bg-[#00D4FF] text-black px-6 py-2 rounded-lg font-semibold hover:bg-[#00b8e6] disabled:opacity-50"
        >
          {{ loading ? 'Saving...' : dirty ? 'Save Changes' : 'Saved ✓' }}
        </button>
      </div>

      <div class="flex gap-4">
        <!-- Spread Control -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 w-1/2">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h2 class="text-xl font-semibold mb-1">Spread</h2>
            <p class="text-gray-400 text-sm">Mark-up on buy prices, mark-down on sell prices (%)</p>
          </div>
          <div class="text-3xl font-bold text-[#00D4FF]">{{ form.crypto_spread }}%</div>
        </div>
        <input
          v-model.number="form.crypto_spread"
          type="number"
          min="0"
          max="50"
          step="0.1"
          @input="dirty = true"
          class="w-full px-4 py-3 bg-[#111827] border border-[#1f3348] rounded-lg text-white focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none"
          placeholder="0.0"
        />
        <div class="flex justify-between text-xs text-gray-500 mt-2">
          <span>0% - 50%</span>
        </div>
        <p class="text-gray-400 text-sm mt-3">
          For Example: BTC at $50,000 → User pays $50,000 × (1 + {{ form.crypto_spread }}%) on buy
        </p>
      </div>

        <!-- Fee Control -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6 w-1/2">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h2 class="text-xl font-semibold mb-1">Transaction Fee</h2>
            <p class="text-gray-400 text-sm">Percentage fee on all crypto trades</p>
          </div>
          <div class="text-3xl font-bold text-[#00D4FF]">{{ form.crypto_fee }}%</div>
        </div>
        <input
          v-model.number="form.crypto_fee"
          type="number"
          min="0"
          max="10"
          step="0.1"
          @input="dirty = true"
          class="w-full px-4 py-3 bg-[#111827] border border-[#1f3348] rounded-lg text-white focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none"
          placeholder="0.0"
        />
        <div class="flex justify-between text-xs text-gray-500 mt-2">
          <span>0% - 10%</span>
        </div>
        <p class="text-gray-400 text-sm mt-3">
          For Example: User opens $1,000 trade → Fee charged: $1,000 × {{ form.crypto_fee }}% = ${{ (1000 * form.crypto_fee / 100).toFixed(2) }}
        </p>
      </div>

      </div>

      <!-- Max Trade Amount -->
      <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-6">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h2 class="text-xl font-semibold mb-1">Max Trade Amount</h2>
            <p class="text-gray-400 text-sm">Maximum USD allowed per trade</p>
          </div>
          <div class="text-3xl font-bold text-[#00D4FF]">${{ form.max_trade_amount.toLocaleString() }}</div>
        </div>
        <input
          v-model.number="form.max_trade_amount"
          type="number"
          min="0"
          step="100"
          @input="dirty = true"
          class="w-full px-4 py-3 bg-[#111827] border border-[#1f3348] rounded-lg text-white focus:border-[#00D4FF] focus:ring-1 focus:ring-[#00D4FF] outline-none"
        />
        <p class="text-gray-400 text-sm mt-3">
          Set to 0 for unlimited trades
        </p>
      </div>

      <!-- Quick Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <p class="text-gray-400 text-sm mb-2">Total Spread Revenue (Est.)</p>
          <p class="text-2xl font-bold">{{ spreadRevenue }}</p>
        </div>
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <p class="text-gray-400 text-sm mb-2">Total Fee Revenue (Est.)</p>
          <p class="text-2xl font-bold">{{ feeRevenue }}</p>
        </div>
        <div class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
          <p class="text-gray-400 text-sm mb-2">Trade Limit</p>
          <p class="text-2xl font-bold">{{ form.max_trade_amount > 0 ? `$${form.max_trade_amount.toLocaleString()}` : 'Unlimited' }}</p>
        </div>
      </div>

      <!-- Status -->
      <div v-if="statusMessage" class="bg-[#0F1724] border border-[#1f3348] rounded-xl p-4">
        <p :class="statusMessage.includes('Success') ? 'text-green-400' : 'text-red-400'">
          {{ statusMessage }}
        </p>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import api from '@/api';

const form = ref({
  crypto_spread: 0,
  crypto_fee: 0,
  max_trade_amount: 10000,
});

const dirty = ref(false);
const loading = ref(false);
const statusMessage = ref('');

const spreadRevenue = computed(() => {
  // Rough estimate: assuming $100k in daily trades
  return `$${(100000 * form.value.crypto_spread / 100 / 30).toFixed(0)}/mo (est.)`;
});

const feeRevenue = computed(() => {
  return `$${(100000 * form.value.crypto_fee / 100 / 30).toFixed(0)}/mo (est.)`;
});

const fetchSettings = async () => {
  try {
    const res = await api.get('/admin/settings');
    if (res.data.success) {
      form.value.crypto_spread = res.data.data.crypto_spread || 0;
      form.value.crypto_fee = res.data.data.crypto_fee || 0;
      form.value.max_trade_amount = res.data.data.max_trade_amount || 10000;
      dirty.value = false;
    }
  } catch (e) {
    statusMessage.value = 'Error loading settings';
    console.error(e);
  }
};

const saveSettings = async () => {
  loading.value = true;
  statusMessage.value = '';
  try {
    const res = await api.post('/admin/settings/update', form.value);
    if (res.data.success) {
      statusMessage.value = 'Success! Settings saved.';
      dirty.value = false;
      setTimeout(() => {
        statusMessage.value = '';
      }, 3000);
    }
  } catch (e) {
    statusMessage.value = e.response?.data?.message || 'Failed to save settings';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchSettings();
});
</script>
