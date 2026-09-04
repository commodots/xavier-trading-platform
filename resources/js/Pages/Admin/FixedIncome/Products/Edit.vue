<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";

const route = useRoute();
const router = useRouter();
const saving = ref(false);
const error = ref('');
const form = reactive(
  {
    name: '',
    description: '',
    issuer: '',
    interest_rate: null,
    tenor_days: null,
    status: 'draft'
  })

onMounted(
  async () => {
    try {
      Object.assign(form,
        (await api.get(`/admin/fixed-income/products/${route.params.id}`)).data.data)
    }
    catch (exception) {
      error.value = exception.response?.data?.message || 'Unable to load product.'
    }
  })

const submit = async () => {
  saving.value = true;
  error.value = '';
  try {
    await api.put(`/admin/fixed-income/products/${route.params.id}`, form);
    router.push(`/admin/fixed-income/products/${route.params.id}`)
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to update product.'
  }
  finally {
    saving.value = false
  }
}
</script>
<template>
  <MainLayout>
  <div class="max-w-2xl space-y-6">
    <h1 class="text-3xl font-semibold text-white">Edit product</h1>
    <form class="grid gap-4 rounded-xl border border-[#1f3348] bg-[#0F1724] p-6" @submit.prevent="submit">

      <label v-for="field in ['name', 'description', 'issuer', 'interest_rate', 'tenor_days']" :key="field"
        class="text-sm capitalize text-gray-400">

        {{ field.replaceAll('_', ' ') }}

        <input v-model="form[field]" :type="['interest_rate', 'tenor_days'].includes(field) ? 'number' : 'text'"
          class="mt-1 w-full rounded-lg border border-[#1f3348] bg-[#0B132B] p-3 text-white">

      </label>

      <p v-if="error" class="text-red-400">{{ error }}</p>
      <button :disabled="saving" class="rounded-lg bg-cyan-400 px-4 py-3 font-semibold text-[#0F1724]">
        {{ saving ? 'Saving...' : 'Save changes'}}
      </button>
    </form>
  </div>
  </MainLayout>
</template>
