<template>
  <div>
    <form @submit.prevent="submit">
      <div class="mb-3">
        <label class="block mb-1 text-xs text-gray-400">Bank Verification Number (Tier 1)</label>
        <input v-model="form.bvn" type="text" maxlength="11"
          class="w-full px-3 py-2 text-white bg-transparent border rounded placeholder:text-gray-600" 
          placeholder="Enter 11-digit BVN" />
      </div>

      <div class="mb-3">
        <label class="block mb-1 text-xs text-gray-400">National Identity Number (Tier 2)</label>
        <input v-model="form.nin" type="text" maxlength="11"
          class="w-full px-3 py-2 text-white bg-transparent border rounded placeholder:text-gray-600" 
          placeholder="Enter 11-digit NIN" />
      </div>

      <div class="grid grid-cols-2 gap-3 mb-3">
        <div>
          <label class="block text-[10px] text-gray-500 mb-1">Passport/Selfie</label>
          <input type="file" @change="onPhoto" class="w-full px-3 py-2 text-xs bg-transparent border rounded" />
        </div>
        <div>
          <label class="block text-[10px] text-gray-500 mb-1">ID Document</label>
          <input type="file" @change="onDoc" class="w-full px-3 py-2 text-xs bg-transparent border rounded" />
        </div>
      </div>

      <div class="flex gap-2">
        <button type="submit" :disabled="loading" class="px-3 py-2 font-bold text-white bg-blue-600 rounded hover:bg-blue-700px-4">
          {{ loading ? 'Processing...' : 'Submit KYC' }}
        </button>
        <button v-if="initial" @click.prevent="downloadInitial" class="bg-[#14314f] px-4 py-2 rounded">Download data</button>
      </div>
      
      <p v-if="message" class="mt-2 text-sm text-yellow-400">{{ message }}</p>
      <p class="text-[10px] text-gray-500 mt-2 italic">* Provide BVN for Basic Tier or both for Full Verification.</p>
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
  nin: '' 
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

  loading.value = true;
  message.value = '';

  const fd = new FormData();
  

  if(form.value.bvn) fd.append('bvn', form.value.bvn);
  if(form.value.nin) fd.append('nin', form.value.nin);
  
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

function downloadInitial(){
  if(!props.initial) return;
  const data = JSON.stringify(props.initial, null, 2);
  const blob = new Blob([data],{type:'application/json'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a'); a.href=url; a.download='kyc.json'; a.click();
  URL.revokeObjectURL(url);
}
</script>