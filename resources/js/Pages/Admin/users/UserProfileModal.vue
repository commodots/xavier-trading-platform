<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="$emit('close')">
    <div class="w-full max-w-2xl mx-4 bg-[#111827] rounded-xl border border-[#1F2A44] shadow-2xl max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between p-6 border-b border-gray-700">
        <div class="flex items-center gap-4">
          <div v-if="loading" class="w-12 h-12 bg-gray-700 rounded-full animate-pulse"></div>
          <div v-else class="flex items-center justify-center w-12 h-12 text-lg font-bold bg-blue-600 rounded-full">
            {{ initials(user) }}
          </div>
          <div>
            <div v-if="loading" class="space-y-1">
              <div class="h-5 bg-gray-700 rounded w-32 animate-pulse"></div>
              <div class="h-3 bg-gray-700 rounded w-48 animate-pulse"></div>
            </div>
            <template v-else>
              <h2 class="text-xl font-bold">{{ user.first_name }} {{ user.last_name }}</h2>
              <p class="text-sm text-gray-400">{{ user.email }}</p>
            </template>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <template v-if="!loading && user">
            <button
              v-if="!user.is_suspended"
              @click="showSuspend = true"
              class="px-3 py-1.5 text-xs font-medium text-white transition bg-red-600 rounded-lg hover:bg-red-700"
            >
              Suspend
            </button>
            <button
              v-else
              @click="handleUnsuspend"
              class="px-3 py-1.5 text-xs font-medium text-white transition bg-green-600 rounded-lg hover:bg-green-700"
            >
              Unsuspend
            </button>
            <button
              @click="handleForceLogout"
              class="px-3 py-1.5 text-xs font-medium text-white transition bg-orange-600 rounded-lg hover:bg-orange-700"
            >
              Force Logout
            </button>
            <button
              @click="handleReset2FA"
              class="px-3 py-1.5 text-xs font-medium text-white transition bg-purple-600 rounded-lg hover:bg-purple-700"
            >
              Reset 2FA
            </button>
          </template>
          <button @click="$emit('close')" class="p-2 text-gray-400 hover:text-white transition rounded-lg hover:bg-gray-700">
            ✕
          </button>
        </div>
      </div>

      <div v-if="showSuspend" class="p-4 mx-6 mt-4 rounded-lg bg-[#1E293B] border border-red-500/30">
        <p class="text-xs text-gray-400 mb-2">Suspension Reason</p>
        <textarea
          v-model="suspendReason"
          placeholder="Enter suspension reason..."
          class="w-full px-3 py-2 mb-3 text-sm bg-[#0B132B] border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:ring focus:ring-red-700/40"
          rows="2"
        ></textarea>
        <div class="flex gap-2">
          <button
            @click="handleSuspend"
            class="px-4 py-2 text-xs font-medium text-white transition bg-red-600 rounded-lg hover:bg-red-700"
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

      <!-- Loading Skeleton -->
      <div v-if="loading" class="p-6 space-y-6">
        <div class="space-y-3">
          <div class="h-4 bg-gray-700 rounded w-1/3 animate-pulse"></div>
          <div class="grid grid-cols-2 gap-4">
            <div class="h-3 bg-gray-700 rounded animate-pulse"></div>
            <div class="h-3 bg-gray-700 rounded animate-pulse"></div>
            <div class="h-3 bg-gray-700 rounded animate-pulse"></div>
            <div class="h-3 bg-gray-700 rounded animate-pulse"></div>
          </div>
        </div>
        <div class="p-4 rounded-lg bg-[#1E293B] space-y-3">
          <div class="h-4 bg-gray-700 rounded w-1/4 animate-pulse"></div>
          <div class="h-3 bg-gray-700 rounded w-1/3 animate-pulse"></div>
        </div>
        <div class="p-4 rounded-lg bg-[#1E293B] space-y-2">
          <div class="h-4 bg-gray-700 rounded w-1/4 animate-pulse"></div>
          <div class="grid grid-cols-2 gap-2">
            <div class="h-3 bg-gray-700 rounded animate-pulse"></div>
            <div class="h-3 bg-gray-700 rounded animate-pulse"></div>
          </div>
        </div>
        <div class="p-4 rounded-lg bg-[#1E293B] space-y-2">
          <div class="h-4 bg-gray-700 rounded w-1/3 animate-pulse"></div>
          <div class="h-3 bg-gray-700 rounded w-1/2 animate-pulse"></div>
          <div class="h-3 bg-gray-700 rounded w-2/3 animate-pulse"></div>
        </div>
      </div>

      <!-- Content -->
      <div v-else class="p-6 space-y-6">
        <!-- Status Badges -->
        <div class="flex flex-wrap gap-2">
          <span class="px-3 py-1 text-xs font-medium rounded" :class="user.is_suspended ? 'bg-red-500/20 text-red-400' : 'bg-green-500/20 text-green-400'">
            {{ user.is_suspended ? 'Suspended' : 'Active' }}
          </span>
          <span class="px-3 py-1 text-xs font-medium rounded" :class="subscriptionStatusClass(user.subscription_status)">
            {{ subscriptionStatusLabel(user.subscription_status) }}
          </span>
          <span v-if="user.on_trial" class="px-3 py-1 text-xs rounded bg-yellow-500/20 text-yellow-400">On Trial</span>
          <span v-if="user.wallet_debt > 0" class="px-3 py-1 text-xs rounded bg-red-500/20 text-red-300">Debt: ₦{{ formatCurrency(user.wallet_debt) }}</span>
          <span v-for="role in userRoles" :key="role" class="px-2 py-1 text-xs rounded bg-purple-500/20 text-purple-300">
            {{ role }}
          </span>
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

        <!-- Subscription Info -->
        <div class="p-4 rounded-lg bg-[#1E293B]">
          <p class="mb-3 text-xs text-gray-400 uppercase tracking-wider">Subscription Details</p>
          <div class="flex items-center gap-3 mb-3">
            <span class="px-2 py-0.5 text-xs rounded" :class="subscriptionStatusClass(user.subscription_status)">
              {{ subscriptionStatusLabel(user.subscription_status) }}
            </span>
            <span v-if="user.subscription_status === 'active'" class="text-sm text-gray-300">
              Tier: <span class="font-medium text-white">{{ user.tier || 'Standard' }}</span>
            </span>
          </div>
          <div v-if="subscriptions.length" class="mt-2 space-y-2">
            <div
              v-for="sub in subscriptions"
              :key="sub.id"
              class="flex items-center justify-between p-2 rounded bg-[#111827] text-xs"
            >
              <div class="flex items-center gap-2">
                <span class="px-1.5 py-0.5 rounded" :class="sub.status === 'active' ? 'bg-green-500/20 text-green-400' : sub.status === 'trial' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-gray-500/20 text-gray-400'">
                  {{ sub.status }}
                </span>
                <span class="text-gray-300">{{ sub.plan?.name || 'Unknown Plan' }}</span>
              </div>
              <div class="text-gray-500">
                <span v-if="sub.expires_at">Expires {{ formatDate(sub.expires_at) }}</span>
              </div>
            </div>
          </div>
          <p v-else class="text-xs text-gray-500">No active subscription records.</p>
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
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import api from '@/api';

const props = defineProps({
  user: { type: Object, default: null },
  wallet: { type: Object, default: null },
  subscriptions: { type: Array, default: () => [] },
  transactions: { type: Array, default: () => [] },
  devices: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'updated']);

const showSuspend = ref(false);
const suspendReason = ref('');

const initials = (u) =>
  (u.first_name?.[0] || '').toUpperCase() +
  (u.last_name?.[0] || '').toUpperCase();

const formatDate = (d) => new Date(d).toLocaleDateString();
const formatCurrency = (n) => Number(n || 0).toLocaleString();

const userRoles = computed(() => {
  if (!props.user) return [];
  if (Array.isArray(props.user.roles)) return props.user.roles;
  if (props.user.role) return [props.user.role];
  return [];
});

const kycClass = (status) => {
  const map = { approved: 'bg-green-600', verified: 'bg-green-600', pending: 'bg-yellow-600', rejected: 'bg-red-600' };
  return map[status] || 'bg-gray-600';
};

const subscriptionStatusClass = (status) => {
  switch (status) {
    case 'active':    return 'bg-green-500/20 text-green-400';
    case 'trial':     return 'bg-yellow-500/20 text-yellow-400';
    case 'suspended': return 'bg-red-500/20 text-red-400';
    case 'inactive':  return 'bg-gray-500/20 text-gray-400';
    default:          return 'bg-gray-600/30 text-gray-500';
  }
};

const subscriptionStatusLabel = (status) => {
  switch (status) {
    case 'active':    return 'Active Subscription';
    case 'trial':     return 'Trial';
    case 'suspended': return 'Suspended';
    case 'inactive':  return 'Inactive';
    default:          return 'No Subscription';
  }
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