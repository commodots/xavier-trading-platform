<template>
  <div>
    <form @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-3 mb-3">
        <select v-model="form.id_type" class="bg-transparent border rounded px-3 py-2">
          <option value="bvn">BVN</option>
          <option value="nin">NIN</option>
          <option value="vnin">vNIN</option>
        </select>
        <input v-model="form.id_value" class="bg-transparent border rounded px-3 py-2" placeholder="ID value" />
      </div>

      <div class="grid grid-cols-2 gap-3 mb-3">
        <input type="file" @change="onPhoto" class="bg-transparent border rounded px-3 py-2" />
        <input type="file" @change="onDoc" class="bg-transparent border rounded px-3 py-2" />
      </div>

      <div class="flex gap-2">
        <button type="submit" :disabled="loading" class="bg-[#00D4FF] px-4 py-2 rounded">Submit KYC</button>
        <button v-if="initial" @click.prevent="downloadInitial" class="bg-[#14314f] px-4 py-2 rounded">Download data</button>
      </div>
      <p v-if="message" class="text-sm text-yellow-400 mt-2">{{ message }}</p>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
const props = defineProps({ initial:Object });
const emit = defineEmits(['submitted']);

const form = ref({ id_type:'bvn', id_value:'' });
const photo = ref(null);
const doc = ref(null);
const loading = ref(false);
const message = ref('');

function onPhoto(e){ photo.value = e.target.files[0]; }
function onDoc(e){ doc.value = e.target.files[0]; }

async function submit(){
  loading.value = true;
  const fd = new FormData();
  fd.append('id_type', form.value.id_type);
  fd.append('id_value', form.value.id_value);
  if(photo.value) fd.append('photo', photo.value);
  if(doc.value) fd.append('document', doc.value);
  try{
    const r = await axios.post('/api/profile/kyc', fd, { headers:{'Content-Type':'multipart/form-data'}, withCredentials:true });
    message.value = 'KYC submitted';
    emit('submitted');
  }catch(e){
    message.value = e.response?.data?.message || 'Error';
  }finally{ loading.value=false; }
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
