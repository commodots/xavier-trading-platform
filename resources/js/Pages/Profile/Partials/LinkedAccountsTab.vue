<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h2 class="text-xl font-semibold text-white">Linked Accounts</h2>
        <p class="text-gray-400 text-sm">Manage your withdrawal destinations.</p>
      </div>
      <button 
        @click="showAddForm = !showAddForm"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition"
      >
        {{ showAddForm ? 'Cancel' : '+ Add Account' }}
      </button>
    </div>

    <div v-if="showAddForm" class="bg-[#16213A] border border-blue-500/30 p-6 rounded-xl space-y-4">
      <h3 class="text-white font-medium">Link New Account</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-gray-400 text-xs mb-1">Account Type</label>
          <select v-model="form.type" class="w-full bg-[#0F1724] border border-gray-700 rounded p-2 text-white">
            <option value="bank">Bank Account</option>
            <option value="crypto_wallet">Crypto Wallet</option>
          </select>
        </div>
        <div>
          <label class="block text-gray-400 text-xs mb-1">Provider (Bank Name / Network)</label>
          <input v-model="form.provider" type="text" placeholder="e.g. Kuda Bank" class="w-full bg-[#0F1724] border border-gray-700 rounded p-2 text-white">
        </div>
        <div>
          <label class="block text-gray-400 text-xs mb-1">Account Number / Address</label>
          <input v-model="form.account_number" type="text" class="w-full bg-[#0F1724] border border-gray-700 rounded p-2 text-white">
        </div>
        <div>
          <label class="block text-gray-400 text-xs mb-1">Account Name</label>
          <input v-model="form.account_name" type="text" placeholder="Your Full Name" class="w-full bg-[#0F1724] border border-gray-700 rounded p-2 text-white">
        </div>
      </div>
      <button @click="addAccount" class="bg-blue-600 text-white px-6 py-2 rounded-lg w-full md:w-auto">
        Verify & Link Account
      </button>
    </div>

    <div class="space-y-3">
      <div v-if="accounts.length === 0" class="text-center py-10 border border-dashed border-gray-700 rounded-xl">
        <p class="text-gray-500">No linked accounts found.</p>
      </div>
      
      <div 
        v-for="account in accounts" 
        :key="account.id"
        class="flex items-center justify-between p-4 bg-[#16213A] border border-gray-700 rounded-lg"
      >
        <div class="flex items-center space-x-4">
          <div class="p-2 bg-blue-900/20 rounded-lg text-blue-400">
            <span v-if="account.type === 'bank'">🏦</span>
            <span v-else>🪙</span>
          </div>
          <div>
            <h4 class="text-white font-medium">{{ account.provider }}</h4>
            <p class="text-gray-400 text-xs">{{ account.account_number }} ({{ account.account_name }})</p>
          </div>
        </div>
        <span 
          :class="account.is_verified ? 'text-green-400' : 'text-yellow-400'"
          class="text-xs font-medium px-2 py-1 bg-black/20 rounded"
        >
          {{ account.is_verified ? 'Verified' : 'Pending Verification' }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import api from '@/lib/axios';

const accounts = ref([]);
 
const showAddForm = ref(false);


const form = reactive({
  type: 'bank',
  provider: '',
  account_number: '',
  account_name: ''
});

const fetchAccounts = async () => {
  try {
    const res = await api.get('/user/linked-accounts/index');
    accounts.value = res.data.data;
  } catch (err) {
    console.error("Failed to load accounts", err);
  }
};

const addAccount = async () => {
  try {
   
    await api.post('/user/linked-accounts/store', form); 
    alert("Account linked successfully!");
    showAddForm.value = false;
    
    
    location.reload(); 

    // Reset form
    form.provider = '';
    form.account_number = '';
    form.account_name = '';
  } catch (err) {
    alert("Failed to link account. Please check your details.");
  }
};

onMounted(() => {
  fetchAccounts();
});
</script>