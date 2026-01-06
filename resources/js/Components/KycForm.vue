<template>
  <div>
    <form @submit.prevent="submit">
      <div class="mb-3">
        <label class="block mb-1 text-xs text-gray-400">Bank Verification Number</label>
        <input v-model="form.bvn" type="text" maxlength="11"
          class="w-full px-3 py-2 text-white bg-transparent border rounded placeholder:text-gray-600 border-[#2A314A]" 
          placeholder="Enter 11-digit BVN" />
      </div>

      <div class="mb-3">
        <label class="block mb-1 text-xs text-gray-400">National Identity Number</label>
        <input v-model="form.nin" type="text" maxlength="11"
          class="w-full px-3 py-2 text-white bg-transparent border rounded placeholder:text-gray-600 border-[#2A314A]" 
          placeholder="Enter 11-digit NIN" />
      </div>

      <div class="mb-3">
        <label class="block mb-1 text-xs text-gray-400">Taxpayer Identification Number</label>
        <input v-model="form.tin" type="text" maxlength="11"
          class="w-full px-3 py-2 text-white bg-transparent border rounded placeholder:text-gray-600 border-[#2A314A]" 
          placeholder="Enter 8-digit TIN" />
      </div>

      <div class="mb-3">
        <label class="block mb-1 text-xs text-gray-400">Select ID Document Type</label>
        <select v-model="form.id_type" 
          class="w-full px-3 py-2 text-white bg-[#111827] border rounded border-[#2A314A] text-sm">
          <option value="" disabled>Choose an ID type</option>
          <option value="intl_passport">International Passport</option>
          <option value="national_id">National ID Card</option>
          <option value="drivers_license">Driver's License</option>
          <option value="voters_card">Voter's Card</option>
          <option value="nin">NIN Slip</option>
          <option value="proof_of_address">Proof of Address</option>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-3 mb-3">
        <div>
          <label class="block text-[10px] text-gray-500 mb-1">Passport/Selfie</label>
          <input type="file" @change="onPhoto" class="w-full px-3 py-2 text-xs bg-transparent border rounded border-[#2A314A]" />
        </div>
        <div>
          <label class="block text-[10px] text-gray-500 mb-1">ID Document (Selected Above)</label>
          <input type="file" @change="onDoc" class="w-full px-3 py-2 text-xs bg-transparent border rounded border-[#2A314A]" />
        </div>
      </div>

        <button type="submit" :disabled="loading" class="px-3 py-2 font-bold text-white bg-blue-600 rounded hover:bg-blue-700">
          {{ loading ? 'Processing...' : 'Submit KYC' }}
        </button>
        
      <p v-if="message" class="mt-2 text-sm text-yellow-400">{{ message }}</p>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import api from '@/api';

const props = defineProps({ initial: Object });
const emit = defineEmits(['submitted', 'success']);

const form = ref({ 
  bvn: '', 
  nin: '',
  tin:'',
  id_type: '' // Track the selected document type
});

const photo = ref(null);
const doc = ref(null);
const loading = ref(false);
const message = ref('');

function onPhoto(e){ photo.value = e.target.files[0]; }
function onDoc(e){ doc.value = e.target.files[0]; }

async function submit(){
  
  if(!form.value.bvn && !form.value.nin) {
    message.value = "Please provide at least your BVN to begin verification.";
    return;
  }

  // If a document is uploaded, ensure a type is selected
  if(doc.value && !form.value.id_type) {
    message.value = "Please select the type of ID document you are uploading.";
    return;
  }

  loading.value = true;
  message.value = '';

  const fd = new FormData();
  
  if(form.value.bvn) fd.append('bvn', form.value.bvn);
  if(form.value.nin) fd.append('nin', form.value.nin);
  if(form.value.tin) fd.append('tin', form.value.tin);
  if(form.value.id_type) fd.append('id_type', form.value.id_type); // Append the type
  
  if (photo.value) fd.append('photo', photo.value);
  if (doc.value) fd.append('document', doc.value);

  try{
    const r = await api.post('/profile/kyc', fd, { 
      headers:{'Content-Type':'multipart/form-data'}, 
      withCredentials:true 
    });
    
    message.value = 'KYC submitted successfully';
    emit('submitted');
    emit('success'); 
  }catch(e){
    message.value = e.response?.data?.message || 'Error submitting KYC';
  }finally{ 
    loading.value = false; 
  }
}

</script>