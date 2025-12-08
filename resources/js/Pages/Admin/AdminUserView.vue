<!-- resources/js/Pages/Admin/UserView.vue -->
<template>
  <MainLayout>
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-semibold">User — {{ user.name || 'Loading...' }}</h1>
          <div class="text-sm text-gray-400 mt-1">{{ user.email }}</div>
        </div>

        <div class="flex items-center gap-3">
          <button @click="goBack" class="px-3 py-2 bg-[#1C2541] rounded">← Back</button>
          <button v-if="isSaving" class="px-3 py-2 bg-gray-600 rounded">Saving...</button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Personal card -->
        <div class="bg-[#0F1724] p-6 rounded-xl border border-[#1f3348]">
          <div class="flex items-center gap-4">
            <div class="w-20 h-20 rounded-full bg-[#182232] flex items-center justify-center text-xl">
              {{ userInitials }}
            </div>
            <div>
              <div class="font-semibold text-lg">{{ user.name }}</div>
              <div class="text-xs text-gray-400 mt-1">{{ user.role || 'user' }}</div>
            </div>
          </div>

          <div class="mt-6 text-sm space-y-2 text-gray-300">
            <div><span class="text-gray-400">Email:</span> {{ user.email }}</div>
            <div><span class="text-gray-400">Phone:</span> {{ user.phone || '—' }}</div>
            <div><span class="text-gray-400">DOB:</span> {{ user.dob || '—' }}</div>
            <div><span class="text-gray-400">Joined:</span> {{ formattedDate(user.created_at) }}</div>
          </div>

          <hr class="my-4 border-gray-700" />

          <div>
            <div class="text-xs text-gray-400 mb-2">Wallets</div>
            <div class="space-y-3">
              <div v-for="w in (wallets || [])" :key="w.currency" class="flex items-center justify-between">
                <div>
                  <div class="font-medium">{{ w.currency }}</div>
                  <div class="text-xs text-gray-400">Status: {{ w.status }}</div>
                </div>
                <div class="text-lg font-semibold">{{ formatMoney(w.balance, w.currency) }}</div>
              </div>
              <div v-if="!wallets || wallets.length === 0" class="text-xs text-gray-500">No wallets</div>
            </div>
          </div>
        </div>

        <!-- Middle: Tabs (Profile / KYC) -->
        <div class="lg:col-span-2 space-y-4">
          <div class="bg-[#0F1724] p-4 rounded-xl border border-[#1f3348]">
            <div class="flex items-center justify-between mb-3">
              <div class="flex gap-2">
                <button :class="tab === 'profile' ? activeTabClass : tabClass" @click="tab='profile'">Profile</button>
                <button :class="tab === 'kyc' ? activeTabClass : tabClass" @click="tab='kyc'">KYC</button>
                <button :class="tab === 'transactions' ? activeTabClass : tabClass" @click="tab='transactions'">Transactions</button>
              </div>

              <div class="text-sm text-gray-400">User ID: {{ user.id }}</div>
            </div>

            <!-- Profile Tab -->
            <div v-if="tab === 'profile'">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="text-xs text-gray-400">Full name</label>
                  <div class="mt-1 text-white">{{ user.name }}</div>
                </div>
                <div>
                  <label class="text-xs text-gray-400">Phone</label>
                  <div class="mt-1 text-white">{{ user.phone || '—' }}</div>
                </div>
                <div>
                  <label class="text-xs text-gray-400">Email</label>
                  <div class="mt-1 text-white">{{ user.email }}</div>
                </div>
                <div>
                  <label class="text-xs text-gray-400">Address</label>
                  <div class="mt-1 text-white">{{ user.address || '—' }}</div>
                </div>
              </div>

              <div class="mt-6">
                <h3 class="text-sm text-gray-400 mb-2">Admin Notes</h3>
                <textarea v-model="adminNotes" class="w-full bg-[#081025] border border-gray-700 rounded p-3 text-sm" rows="3"></textarea>
                <div class="mt-3">
                  <button @click="saveAdminNotes" class="px-4 py-2 rounded bg-gradient-to-r from-[#0047AB] to-[#00D4FF]">Save Notes</button>
                </div>
              </div>
            </div>

            <!-- KYC Tab -->
            <div v-if="tab === 'kyc'">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="text-xs text-gray-400">ID Type</label>
                  <div class="mt-1">{{ kyc?.id_type || '—' }}</div>
                </div>
                <div>
                  <label class="text-xs text-gray-400">ID Number</label>
                  <div class="mt-1">{{ kyc?.id_value || '—' }}</div>
                </div>

                <div>
                  <label class="text-xs text-gray-400">BVN / NIN</label>
                  <div class="mt-1">{{ kyc?.bvn ?? kyc?.nin ?? '—' }}</div>
                </div>

                <div>
                  <label class="text-xs text-gray-400">Status</label>
                  <div class="mt-1">
                    <span :class="kycStatusClass">{{ kyc?.status || 'none' }}</span>
                  </div>
                </div>

                <div class="md:col-span-2">
                  <label class="text-xs text-gray-400">Address</label>
                  <div class="mt-1">{{ kyc?.address || '—' }}</div>
                </div>
              </div>

              <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div v-if="kyc?.photo">
                  <label class="text-xs text-gray-400">Photo</label>
                  <img :src="kyc.photo" class="mt-2 rounded shadow" />
                </div>
                <div v-if="kyc?.id_front">
                  <label class="text-xs text-gray-400">ID Front</label>
                  <img :src="kyc.id_front" class="mt-2 rounded shadow" />
                </div>
                <div v-if="kyc?.id_back">
                  <label class="text-xs text-gray-400">ID Back</label>
                  <img :src="kyc.id_back" class="mt-2 rounded shadow" />
                </div>
              </div>

              <div class="mt-4 flex gap-3">
                <button @click="reviewKyc('verified')" class="px-4 py-2 rounded bg-green-600/80">Approve KYC</button>
                <button @click="reviewKyc('rejected')" class="px-4 py-2 rounded bg-red-600/80">Reject</button>
                <button @click="reviewKyc('pending')" class="px-4 py-2 rounded bg-yellow-600/80">Set Pending</button>
                <div v-if="kycMessage" class="text-sm text-yellow-300 ml-3">{{ kycMessage }}</div>
              </div>
            </div>

            <!-- Transactions Tab -->
            <div v-if="tab === 'transactions'">
              <div v-if="transactions && transactions.length">
                <table class="w-full text-sm mt-2">
                  <thead class="text-gray-400 border-b border-gray-700">
                    <tr>
                      <th class="py-2">Date</th>
                      <th>Type</th>
                      <th>Asset</th>
                      <th class="text-right">Amount</th>
                      <th class="text-right">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="t in transactions" :key="t.ref" class="border-b border-gray-800">
                      <td class="py-2">{{ t.date }}</td>
                      <td>{{ t.type }}</td>
                      <td>{{ t.asset }}</td>
                      <td class="text-right">{{ formatMoney(t.amount, t.currency || 'NGN') }}</td>
                      <td class="text-right">
                        <span :class="transactionStatusClass(t.status)">{{ t.status }}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div v-else class="text-sm text-gray-400">No transactions found.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import MainLayout from '@/Layouts/MainLayout.vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const id = route.params.id;

const user = ref({});
const wallets = ref([]);
const kyc = ref({});
const transactions = ref([]);
const adminNotes = ref('');
const tab = ref('profile');
const isSaving = ref(false);
const kycMessage = ref('');

// UI classes
const tabClass = 'px-3 py-1 text-sm rounded text-gray-300 hover:bg-[#0E1624]';
const activeTabClass = 'px-3 py-1 text-sm rounded bg-[#0E6EA4]/20 text-white';

// derived
const userInitials = computed(() => {
  if (!user.value.name) return 'U';
  return user.value.name.split(' ').map(n => n[0]).slice(0,2).join('').toUpperCase();
});

const kycStatusClass = computed(() => {
  const s = kyc.value?.status || 'none';
  return {
    'px-2 py-1 rounded text-xs font-semibold': true,
    'bg-green-600/30 text-green-300': s === 'verified',
    'bg-yellow-600/30 text-yellow-300': s === 'pending',
    'bg-red-600/30 text-red-300': s === 'rejected',
    'bg-gray-700/30 text-gray-300': s === 'none'
  };
});

const transactionStatusClass = (status) => {
  if (status === 'Completed') return 'text-green-400';
  if (status === 'Pending') return 'text-yellow-400';
  return 'text-red-400';
};

// helpers
const formattedDate = (d) => d ? new Date(d).toLocaleString() : '—';
const formatMoney = (n, currency='NGN') => {
  if (currency === 'NGN') return `₦${Number(n).toLocaleString()}`;
  if (currency === 'USD') return `$${Number(n).toLocaleString()}`;
  return `${Number(n).toLocaleString()} ${currency}`;
};

const goBack = () => router.push('/admin/users');

async function load() {
  try {
    // Primary: fetch user
    const resU = await axios.get(`/api/admin/users/${id}`);
    user.value = resU.data.data ?? resU.data ?? {};

    // wallets
    const resW = await axios.get(`/api/admin/users/${id}/wallets`).catch(()=>({data:{data:[]}}));
    wallets.value = resW.data.data ?? resW.data ?? [];

    // kyc
    const resK = await axios.get(`/api/admin/users/${id}/kyc`).catch(()=>({data:{data:{}}}));
    kyc.value = resK.data.data ?? resK.data ?? {};

    // transactions
    const resT = await axios.get(`/api/admin/users/${id}/transactions`).catch(()=>({data:{data:[]}}));
    transactions.value = resT.data.data ?? resT.data ?? [];

    // admin notes fallback
    adminNotes.value = user.value.admin_notes ?? '';
  } catch (e) {
    // fallback demo data when no backend
    user.value = { id: id || 999, name: 'Demo User', email: 'demo@example.com', role: 'user', created_at: new Date().toISOString(), phone: '+2348000000' };
    wallets.value = [{ currency:'NGN', balance:1240000, status:'active' }, { currency:'USD', balance:120.5, status:'active' }];
    kyc.value = { status: 'pending', id_type: 'bvn', id_value: '', photo: '', id_front: '', id_back: '' };
    transactions.value = [
      { date: '2025-11-04', type:'Deposit', asset:'NGN Wallet', amount: 500000, status:'Completed', ref:'DEP-001'},
      { date: '2025-11-05', type:'Buy', asset:'AAPL', amount: 200, status:'Pending', ref:'TRD-002' },
    ];
  }
}

onMounted(load);

async function saveAdminNotes() {
  isSaving.value = true;
  try {
    await axios.post(`/api/admin/users/${id}/notes`, { notes: adminNotes.value });
    // optimistic update
    isSaving.value = false;
    alert('Notes saved');
  } catch (e) {
    isSaving.value = false;
    alert('Error saving notes');
  }
}

async function reviewKyc(status) {
  kycMessage.value = '';
  try {
    const res = await axios.post(`/api/admin/kycs/${kyc.value.id || id}/review`, { status });
    kyc.value.status = status;
    kycMessage.value = res.data.message || 'KYC updated';
  } catch (e) {
    kycMessage.value = 'Could not update KYC.';
  }
}
</script>

<style scoped>
/* small niceties */
</style>
