<template>
  <div class="bg-[#0f172a] p-6 rounded-lg border border-gray-700 space-y-4">

    <h2 class="text-xl font-semibold mb-4">KYC Information</h2>

    <form @submit.prevent="submitKyc" class="space-y-4">

      <div>
        <label class="text-sm text-gray-400">BVN</label>
        <input v-model="form.bvn" class="input" />
      </div>

      <div>
        <label class="text-sm text-gray-400">ID Type</label>
        <select v-model="form.id_type" class="input">
          <option value="nin">NIN</option>
          <option value="passport">International Passport</option>
          <option value="voters">Voter's Card</option>
        </select>
      </div>

      <div>
        <label class="text-sm text-gray-400">ID Number</label>
        <input v-model="form.id_number" class="input" />
      </div>

      <div>
        <label class="text-sm text-gray-400">Upload ID Document</label>
        <input type="file" @change="handleFile" class="file-input" />
      </div>

      <button
        type="submit"
        class="bg-green-600 px-4 py-2 rounded text-white hover:bg-green-700"
      >
        Submit KYC
      </button>
    </form>
  </div>
</template>

<script setup>
import { reactive } from "vue";
import axios from "axios";

const props = defineProps({ kyc: Object });

const token = localStorage.getItem("xavier_token");

const form = reactive({
  bvn: props.kyc?.bvn ?? "",
  id_type: props.kyc?.id_type ?? "",
  id_number: props.kyc?.id_number ?? "",
  id_document: null,
});

const handleFile = (e) => {
  form.id_document = e.target.files[0];
};

const submitKyc = async () => {
  const fd = new FormData();
  Object.keys(form).forEach((key) => fd.append(key, form[key]));

  await axios.post("/api/profile/kyc", fd, {
    headers: {
      Authorization: `Bearer ${token}`,
      "Content-Type": "multipart/form-data",
    },
  });

  alert("KYC submitted");
};
</script>
