<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";

const router = useRouter()
const products = ref([])
const loading = ref(true)
const error = ref('')

const load = async () => {
  try {
    const response = await api.get('/admin/fixed-income/products')
    products.value = response.data.data?.data || response.data.data || []
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to load products.'
  } finally { loading.value = false }
}

const action = async (product, endpoint) => {
  await api.post(`/admin/fixed-income/products/${product.id}/${endpoint}`)
  await load()
}

onMounted(load)
</script>

<template>
  <MainLayout>
  <div class="space-y-6">
    <div class="flex items-end justify-between">
      <div>
        <p class="text-xs uppercase tracking-widest text-cyan-400">Administration</p>
        <h1 class="mt-2 text-3xl font-semibold text-white">Fixed Income products</h1>
      </div>
      <button class="rounded-lg bg-cyan-400 px-4 py-2 font-semibold text-[#0F1724]"
        @click="router.push('/admin/fixed-income/products/create')">
        Create product
      </button>
    </div>
    <p v-if="error" class="text-red-400">{{ error }}</p>
    <p v-if="loading" class="text-gray-400">Loading products...</p>
    <div v-else class="overflow-x-auto rounded-xl border border-[#1f3348] bg-[#0F1724]">
      <table class="min-w-full text-left text-sm">
        <thead class="text-gray-500">
          <tr>
            <th class="p-4">Name</th>
            <th class="p-4">Code</th>
            <th class="p-4">Currency</th>
            <th class="p-4">Rate</th>
            <th class="p-4">Status</th>
            <th class="p-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in products" :key="product.id" class="border-t border-[#1f3348] text-white">
            <td class="p-4">{{ product.name }}</td>
            <td class="p-4">{{ product.code }}</td>
            <td class="p-4">{{ product.currency }}</td>
            <td class="p-4">{{ product.interest_rate }}%</td>
            <td class="p-4 capitalize">{{ product.status }}</td>
            <td class="flex gap-2 p-4"><button class="text-cyan-400"
                @click="router.push(`/admin/fixed-income/products/${product.id}`)">View</button><button
                class="text-cyan-400"
                @click="router.push(`/admin/fixed-income/products/${product.id}/edit`)">Edit</button><button
                v-if="product.status === 'draft' || product.status === 'suspended'" class="text-emerald-400"
                @click="action(product, 'activate')">Activate</button><button v-if="product.status === 'active'"
                class="text-amber-400" @click="action(product, 'suspend')">Suspend</button><button
                v-if="product.status !== 'closed'" class="text-red-400" @click="action(product, 'close')">Close</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!products.length" class="p-8 text-center text-gray-500">No products found.</p>
    </div>
  </div>
  </MainLayout>
</template>
