<template>
  <div v-if="user" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="$emit('close')">
    <div class="w-full max-w-2xl mx-4 bg-[#111827] rounded-xl border border-[#1F2A44] shadow-2xl max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-700">
        <div class="flex items-center gap-4">
          <div class="flex items-center justify-center w-12 h-12 text-lg font-bold bg-blue-600 rounded-full">
            {{ initials(user) }}
          </div>
          <div>
            <h2 class="text-xl font-bold">{{ user.first_name }} {{ user.last_name }}</h2>
            <p class="text-sm text-gray-400">{{ user.email }}</p>
          </div>
        </div>
        <button @click="$emit('close')" class="p-2 text-gray-400 hover:text-white transition rounded-lg hover:bg-gray-700">
          ✕
        </button>
      </div>

      <div class="p-6 space-y-6">
        <!-- Status Badges -->
        <div class="flex flex-wrap gap-3">
          <span class="px-3 py-1 text-xs rounded" :class="user.is_suspended ? 'bg-red-600' : 'bg-green-600'">
            {{ user.is_suspended ? 'Suspended' : 'Active' }}
          </span>
          <span v-if="user.on_trial" class="px-3 py-1 text-xs rounded bg-yellow-600/40 text-yellow-300">Trial</span>
          <span v-if="user.subscription_status === 'active'" class="px-3 py-1 text-xs rounded bg-green-600/40 text-green-300">Subscribed</span>
          <span v-if="user.wallet_debt > 0" class="px-3 py-1 text-xs rounded bg-red-600/40 text-red-300">Debt: ₦{{ formatCurrency(user.wallet_debt) }}</span>
        </div>

        <!-- User Details -->
        <div class="grid grid-cols-2 gap-4 p-4 rounded-lg bg-[#1E293B]">
          <div>
            <p class="text-xs text-gray-400">Phone</p>
            <p class="text-sm">{{ user.phone || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400">Role</p>
            <p class="text-sm capitalize">{{ user.role || 'user' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400">Member Since</p>
            <p class="text-sm">{{ formatDate(user.created_at) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400">2FA Enabled</p>
            <p class="text-sm">{{ user.google2fa_enabled ? 'Yes' : 'No' }}</p>
          </div>
        </div>

        <!-- KYC Status -->
        <div v-if="user.kyc" class="p-4 rounded-lg bg-[#1E293B]">
          <p class="mb-2 text-xs text-gray-400">KYC Status</p>
          <span class="px-2 py-1 text-xs rounded" :class="kycClass(user.kyc.status)">
            {{ user.kyc.status }}
          </span>
          <p v-if="user.kyc.rejection_reason" class="mt-2 text-xs text-red-400">
            Reason: {{ user.kyc.rejection_reason }}
          </p>
        </div>

        <!-- Wallet -->
        <div v-if="wallet" class="p-4 rounded-lg bg-[#1E293B]">
          <p class="mb-2 text-xs text-gray-400">Wallet Balances</p>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <p class="text-xs text-gray-400">NGN</p>
              <p class="text-sm font-semibold">₦{{ formatCurrency(wallet.ngn) }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400">USD</p>
              <p class="text-sm font-semibold">${{ formatCurrency(wallet.usd) }}</p>
            </div>
          </div>
        </div>

        <!-- Recent Transactions -->
        <div v-if="transactions?.length" class="p-4 rounded-lg bg-[#1E293B]">
          <p class="mb-2 text-xs text-gray-400">Recent Transactions</p>
          <div v-for="txn in transactions.slice(0, 5)" :key="txn.id" class="flex items-center justify-between py-1 text-xs border-b border-gray-700 last:border-0">
            <span class="text-gray-300">{{ txn.type }}</span>
            <span :class="txn.status === 'completed' ? 'text-green-400' : 'text-yellow-400'">
              {{ txn.status }}
            </span>
            <span>₦{{ formatCurrency(txn.amount) }}</span>
          </div>
        </div>

        <!-- Devices -->
        <div v-if="devices?.length" class="p-4 rounded-lg bg-[#1E293B]">
          <p class="mb-2 text-xs text-gray-400">Trusted Devices</p>
          <div v-for="device in devices" :key="device.device_name" class="flex items-center justify-between py-1 text-xs">
            <span class="text-gray-300">{{ device.device_name }}</span>
            <span class="text-gray-400">{{ device.ip_address }}</span>
            <span class="text-gray-500">{{ formatDate(device.last_active_at) }}</span>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-700">
          <!-- Suspend / Unsuspend -->
          <button
            v-if="!user.is_suspended"
            @click="showSuspend = true"
            class="px-4 py-2 text-xs text-white transition bg-red-600 rounded-lg hover:bg-red-700"
          >
            Suspend
          </button>
          <button
            v-else
            @click="handleUnsuspend"
            class="px-4 py-2 text-xs text-white transition bg-green-600 rounded-lg hover:bg-green-700"
          >
            Unsuspend
          </button>

          <button
            @click="handleForceLogout"
            class="px-4 py-2 text-xs text-white transition bg-orange-600 rounded-lg hover:bg-orange-700"
          >
            Force Logout
          </button>

          <button
            @click="handleReset2FA"
            class="px-4 py-2 text-xs text-white transition bg-purple-600 rounded-lg hover:bg-purple-700"
          >
            Reset 2FA
          </button>
        </div>

        <!-- Suspend Reason Input -->
        <div v-if="showSuspend" class="p-4 rounded-lg bg-[#1E293B]">
          <textarea
            v-model="suspendReason"
            placeholder="Enter suspension reason..."
            class="w-full px-3 py-2 mb-3 text-sm bg-[#0B132B] border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:ring focus:ring-blue-700/40"
            rows="2"
          ></textarea>
          <div class="flex gap-2">
            <button
              @click="handleSuspend"
              class="px-4 py-2 text-xs text-white transition bg-red-600 rounded-lg hover:bg-red-700"
            >
              Confirm Suspend
            </button>
            <button
              @click="showSuspend = false"
              class="px-4 py-2 text-xs text-gray-300 transition bg-gray-700 rounded-lg hover:bg-gray-600"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import api from '@/api';

const props = defineProps({
  user: { type: Object, default: null },
  wallet: { type: Object, default: null },
  transactions: { type: Array, default: () => [] },
  devices: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'updated']);

const showSuspend = ref(false);
const suspendReason = ref('');

const initials = (u) =>
  (u.first_name?.[0] || '').toUpperCase() +
  (u.last_name?.[0] || '').toUpperCase();

const formatDate = (d) => new Date(d).toLocaleDateString();
const formatCurrency = (n) => Number(n || 0).toLocaleString();

const kycClass = (status) => {
  const map = { approved: 'bg-green-600', pending: 'bg-yellow-600', rejected: 'bg-red-600' };
  return map[status] || 'bg-gray-600';
};

const handleSuspend = async () => {
  if (!suspendReason.value.trim()) return;
  try {
    await api.post(`/admin/users/${props.user.id}/suspend`, { reason: suspendReason.value });
    showSuspend.value = false;
    suspendReason.value = '';
    emit('updated');
  } catch (e) {
    console.error('Suspend error:', e);
  }
};

const handleUnsuspend = async () => {
  try {
    await api.post(`/admin/users/${props.user.id}/unsuspend`);
    emit('updated');
  } catch (e) {
    console.error('Unsuspend error:', e);
  }
};

const handleForceLogout = async () => {
  if (!confirm('Revoke all sessions for this user?')) return;
  try {
    await api.post(`/admin/users/${props.user.id}/force-logout`);
    emit('updated');
  } catch (e) {
    console.error('Force logout error:', e);
  }
};

const handleReset2FA = async () => {
  if (!confirm('Reset two-factor authentication for this user?')) return;
  try {
    await api.post(`/admin/users/${props.user.id}/reset-2fa`);
    emit('updated');
  } catch (e) {
    console.error('Reset 2FA error:', e);
  }
};
</script>