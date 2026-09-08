<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";
import SkeletonLoader from '@/Components/SkeletonLoader.vue'
import SuccessModal from '@/Components/SuccessModal.vue'
import ErrorModal from '@/Components/ErrorModal.vue'
import { fixedIncomeCurrency, fixedIncomePercent, fixedIncomeLabel } from '@/lib/fixedIncomeFormatters'

const router = useRouter()
const products = ref([])
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const activeAction = ref(null)
const showSuccessModal = ref(false)
const successMessage = ref('')
const showErrorModal = ref(false)

const actionLabels = {
  activate: 'Activating product',
  suspend: 'Suspending product',
  close: 'Closing product',
}

const actionPastTense = {
  activate: 'activated',
  suspend: 'suspended',
  close: 'closed',
}

const load = async () => {
  try {
    const response = await api.get('/admin/fixed-income/products')
    products.value = response.data.data?.data || response.data.data || []
  } catch (exception) {
    error.value = exception.response?.data?.message || 'Unable to load products.'
  } finally { loading.value = false }
}

const action = async (product, endpoint) => {
  activeAction.value = {
    product,
    endpoint,
    label: actionLabels[endpoint] || 'Updating product',
  }
  actionError.value = ''

  try {
    await api.post(`/admin/fixed-income/products/${product.id}/${endpoint}`)
    await load()
    successMessage.value = `${product.name} was ${actionPastTense[endpoint] || 'updated'} successfully.`
    showSuccessModal.value = true
  } catch (exception) {
    actionError.value = exception.response?.data?.message || 'Unable to update product.'
    showErrorModal.value = true
  } finally {
    activeAction.value = null
  }
}

onMounted(load)
</script>

<template>
  <MainLayout>
  <div class="space-y-6">
    <div class="flex items-end justify-between">
      <div>
        <h1 class="mt-2 text-3xl font-semibold text-white">Fixed Income products</h1>
      </div>
      <button :disabled="activeAction" class="rounded-lg bg-cyan-400 px-4 py-2 font-semibold text-[#0F1724] disabled:cursor-not-allowed disabled:opacity-50"
        @click="router.push('/admin/fixed-income/products/create')">
        Create product
      </button>
    </div>
    <p v-if="error" class="text-red-400">{{ error }}</p>
    <SkeletonLoader v-if="loading" type="table" :count="6" class="opacity-40" />
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
            <td class="p-4">{{ fixedIncomePercent(product.interest_rate) }}</td>
            <td class="p-4">{{ fixedIncomeLabel(product.status) }}</td>
            <td class="flex gap-2 p-4"><button :disabled="activeAction" class="text-cyan-400 disabled:cursor-not-allowed disabled:opacity-50"
                @click="router.push(`/admin/fixed-income/products/${product.id}`)">View</button><button
              :disabled="activeAction" class="text-cyan-400 disabled:cursor-not-allowed disabled:opacity-50"
                @click="router.push(`/admin/fixed-income/products/${product.id}/edit`)">Edit</button><button
              v-if="product.status === 'draft' || product.status === 'suspended'" :disabled="activeAction" class="text-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
                @click="action(product, 'activate')">Activate</button><button v-if="product.status === 'active'"
              :disabled="activeAction" class="text-amber-400 disabled:cursor-not-allowed disabled:opacity-50" @click="action(product, 'suspend')">Suspend</button><button
              v-if="product.status !== 'closed'" :disabled="activeAction" class="text-red-400 disabled:cursor-not-allowed disabled:opacity-50" @click="action(product, 'close')">Close</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!products.length" class="p-8 text-center text-gray-500">No products found.</p>
    </div>

    <div v-if="activeAction" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
      role="dialog" aria-modal="true" aria-labelledby="product-action-title">
      <div class="w-full max-w-sm rounded-xl border border-[#1f3348] bg-[#0F1724] p-6 shadow-2xl">
        <div class="flex items-center gap-4">
          <div class="h-10 w-10 animate-spin rounded-full border-2 border-cyan-400/30 border-t-cyan-400" aria-hidden="true"></div>
          <div>
            <h2 id="product-action-title" class="font-semibold text-white">{{ activeAction.label }}</h2>
            <p class="mt-1 text-sm text-gray-400">Please wait while the product status is updated.</p>
          </div>
        </div>
      </div>
    </div>

    <SuccessModal
      :show="showSuccessModal"
      :message="successMessage"
      @close="showSuccessModal = false"
    />
    <ErrorModal
      :show="showErrorModal"
      :message="actionError"
      @close="showErrorModal = false"
    />
  </div>
  </MainLayout>
</template>
