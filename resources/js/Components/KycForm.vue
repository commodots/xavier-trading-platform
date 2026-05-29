<template>
  <div>
    <!-- Success Message -->
    <div v-if="showSuccessMessage" class="mb-4 p-4 border border-green-700 rounded-lg bg-green-900/20 animate-fadeIn">
      <p class="text-sm text-green-300 font-semibold">✓ KYC submitted successfully!</p>
      <p class="text-xs text-green-400 mt-1">We're verifying your identity. This usually takes a few minutes.</p>
    </div>

    <!-- Error Message -->
    <div v-if="message && !showSuccessMessage" class="mb-4 p-4 border border-red-700 rounded-lg bg-red-900/20 animate-fadeIn">
      <p class="text-sm text-red-300 font-semibold">{{ message }}</p>
    </div>

    <!-- Form -->
    <form @submit.prevent="submit" v-if="!showSuccessMessage" class="space-y-4">
      <div>
        <label class="block mb-2 text-xs font-semibold text-gray-300 uppercase tracking-wider">Bank Verification Number (BVN)</label>
        <input 
          v-model="form.bvn" 
          type="text" 
          maxlength="11"
          placeholder="11-digit BVN"
          class="w-full px-3 py-2.5 text-sm text-white bg-transparent border rounded placeholder:text-gray-600 border-[#2A314A] focus:border-blue-500 focus:outline-none transition" 
        />
        <p class="text-xs text-gray-500 mt-1">Your unique banking identifier</p>
      </div>

      <div>
        <label class="block mb-2 text-xs font-semibold text-gray-300 uppercase tracking-wider">National Identity Number (NIN)</label>
        <input 
          v-model="form.nin" 
          type="text" 
          maxlength="11"
          placeholder="11-digit NIN"
          class="w-full px-3 py-2.5 text-sm text-white bg-transparent border rounded placeholder:text-gray-600 border-[#2A314A] focus:border-blue-500 focus:outline-none transition" 
        />
        <p class="text-xs text-gray-500 mt-1">Your national identity number</p>
      </div>

      <div>
        <label class="block mb-2 text-xs font-semibold text-gray-300 uppercase tracking-wider">Tax Identification Number (TIN)</label>
        <input 
          v-model="form.tin" 
          type="text" 
          maxlength="11"
          placeholder="Optional - 8-digit TIN"
          class="w-full px-3 py-2.5 text-sm text-white bg-transparent border rounded placeholder:text-gray-600 border-[#2A314A] focus:border-blue-500 focus:outline-none transition" 
        />
        <p class="text-xs text-gray-500 mt-1">Optional - used for tax compliance</p>
      </div>

      <div>
        <label class="block mb-2 text-xs font-semibold text-gray-300 uppercase tracking-wider">ID Document Type</label>
        <select 
          v-model="form.id_type" 
          class="w-full px-3 py-2.5 text-sm text-white bg-[#111827] border rounded border-[#2A314A] focus:border-blue-500 focus:outline-none transition"
        >
          <option value="" disabled>Choose an ID type</option>
          <option value="intl_passport">International Passport</option>
          <option value="national_id">National ID Card</option>
          <option value="drivers_license">Driver's License</option>
          <option value="voters_card">Voter's Card</option>
          <option value="nin_slip">NIN Slip</option>
          <option value="proof_of_address">Proof of Address</option>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Selfie/Passport Photo</label>
          <input 
            type="file" 
            @change="onPhoto" 
            accept="image/*"
            class="w-full px-3 py-2.5 text-xs bg-transparent border rounded border-[#2A314A] focus:border-blue-500 focus:outline-none transition cursor-pointer" 
          />
          <p class="text-xs text-gray-500 mt-1">JPG, PNG (Max 2MB)</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">ID Document</label>
          <input 
            type="file" 
            @change="onDoc" 
            accept="image/*,application/pdf"
            class="w-full px-3 py-2.5 text-xs bg-transparent border rounded border-[#2A314A] focus:border-blue-500 focus:outline-none transition cursor-pointer" 
          />
          <p class="text-xs text-gray-500 mt-1">JPG, PNG, PDF (Max 5MB)</p>
        </div>
      </div>

      <button 
        type="submit" 
        :disabled="loading"
        class="w-full px-4 py-2.5 font-bold text-white bg-blue-600 rounded hover:bg-blue-700 disabled:bg-gray-700 disabled:cursor-not-allowed transition"
      >
        <span v-if="loading" class="flex items-center justify-center gap-2">
          <span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          Submitting...
        </span>
        <span v-else>Submit KYC Verification</span>
      </button>

      <p class="text-xs text-gray-400 text-center">
        Your information is encrypted and secure. We comply with data protection regulations.
      </p>
    </form>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import api from '@/api';

const props = defineProps({ initial: Object });
const emit = defineEmits(['submitted', 'success']);

const form = ref({ 
  bvn: '', 
  nin: '',
  tin: '',
  id_type: ''
});

const photo = ref(null);
const doc = ref(null);
const loading = ref(false);
const message = ref('');
const showSuccessMessage = ref(false);

function onPhoto(e) {
  photo.value = e.target.files[0];
}

function onDoc(e) {
  doc.value = e.target.files[0];
}

async function submit() {
  // Validation
  if (!form.value.bvn && !form.value.nin) {
    message.value = "Please provide at least BVN or NIN to begin verification.";
    return;
  }

  if (form.value.bvn && form.value.bvn.length !== 11) {
    message.value = "BVN must be exactly 11 digits.";
    return;
  }

  if (form.value.nin && form.value.nin.length !== 11) {
    message.value = "NIN must be exactly 11 digits.";
    return;
  }

  if (doc.value && !form.value.id_type) {
    message.value = "Please select the type of ID document you're uploading.";
    return;
  }

  loading.value = true;
  message.value = '';
  showSuccessMessage.value = false;

  const fd = new FormData();
  
  if (form.value.bvn) fd.append('bvn', form.value.bvn);
  if (form.value.nin) fd.append('nin', form.value.nin);
  if (form.value.tin) fd.append('tin', form.value.tin);
  if (form.value.id_type) fd.append('id_type', form.value.id_type);
  
  if (photo.value) fd.append('photo', photo.value);
  if (doc.value) fd.append('document', doc.value);

  try {
    const response = await api.post('/profile/kyc', fd, { 
      headers: { 'Content-Type': 'multipart/form-data' }, 
      withCredentials: true 
    });
    
    if (response.data.success) {
      showSuccessMessage.value = true;
      
      // Auto-hide message and refresh after 3 seconds
      setTimeout(() => {
        emit('submitted');
        emit('success');
      }, 2000);
    }
  } catch (e) {
    message.value = e.response?.data?.message || 'Error submitting KYC. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.3s ease-in-out;
}
</style>