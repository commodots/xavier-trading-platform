<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from "@/Layouts/MainLayout.vue";

const router = useRouter()

const products = ref([])
const loading = ref(true)

const loadProducts = async () => {
    try {
        const response = await api.get(
            '/fixed-income/products'
        )

        products.value =
            response.data.data || []
    } finally {
        loading.value = false
    }
}

const openProduct = (product) => {
    router.push(
        `/fixed-income/${product.id}`
    )
}

onMounted(loadProducts)
</script>

<template>
  <MainLayout>
    <div class="space-y-6">

        <div>
            <h1 class="text-2xl font-semibold text-white">
                Fixed Income
            </h1>

            <p class="text-gray-400 mt-1">
                Invest in available fixed-income opportunities.
            </p>
        </div>

        <div
            v-if="loading"
            class="text-gray-400"
        >
            Loading products...
        </div>

        <div
            v-else
            class="grid md:grid-cols-2 xl:grid-cols-3 gap-5"
        >
            <div
                v-for="product in products"
                :key="product.id"
                class="rounded-xl border border-[#1F2A44] bg-[#111827] p-5"
            >
                <div class="flex justify-between">
                    <h2 class="text-lg font-semibold text-white">
                        {{ product.name }}
                    </h2>

                    <span class="text-sm text-gray-400">
                        {{ product.currency }}
                    </span>
                </div>

                <div class="mt-5 text-3xl font-semibold text-white">
                    {{ product.interest_rate }}%
                </div>

                <div class="text-gray-400 text-sm">
                    Interest rate
                </div>

                <div class="grid grid-cols-2 gap-4 mt-5 text-sm">
                    <div>
                        <div class="text-gray-500">
                            Minimum
                        </div>
                        <div class="text-white">
                            {{ product.minimum_amount }}
                        </div>
                    </div>

                    <div>
                        <div class="text-gray-500">
                            Tenor
                        </div>
                        <div class="text-white">
                            {{ product.tenor_days || 'Open' }}
                            {{ product.tenor_days ? 'days' : '' }}
                        </div>
                    </div>
                </div>

                <button
                    @click="openProduct(product)"
                    class="w-full mt-6 py-3 rounded-lg bg-indigo-600 text-white"
                >
                    View Investment
                </button>
            </div>
        </div>

    </div>
  </MainLayout>
</template>