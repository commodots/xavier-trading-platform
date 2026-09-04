<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api'

const route = useRoute()
const router = useRouter()

const product = ref(null)
const amount = ref('')
const calculation = ref(null)
const loading = ref(true)
const calculating = ref(false)
const error = ref('')

const loadProduct = async () => {
    try {
        const response = await api.get(
            `/fixed-income/products/${route.params.id}`
        )

        product.value =
            response.data.data
    } finally {
        loading.value = false
    }
}

const calculate = async () => {
    if (!amount.value) return

    calculating.value = true
    error.value = ''

    try {
        const response = await api.post(
            `/fixed-income/products/${route.params.id}/calculate`,
            {
                amount: Number(amount.value)
            }
        )

        calculation.value =
            response.data.data
    } catch (e) {
        error.value =
            e.response?.data?.message ||
            'Unable to calculate return.'
    } finally {
        calculating.value = false
    }
}

const continueInvestment = () => {
    router.push({
        path:
            `/fixed-income/${route.params.id}/review`,
        query: {
            amount: amount.value
        }
    })
}

onMounted(loadProduct)
</script>

<template>
    <div class="max-w-3xl mx-auto space-y-6">

        <h1 class="text-2xl font-semibold text-white">
            Invest in {{ product?.name }}
        </h1>

        <div
            class="rounded-xl bg-[#111827] border border-[#1F2A44] p-6"
        >
            <label class="text-gray-400 text-sm">
                Investment Amount
            </label>

            <input
                v-model="amount"
                type="number"
                min="0"
                class="w-full mt-2 rounded-lg bg-[#0B132B] border border-[#1F2A44] p-4 text-white"
                placeholder="Enter amount"
                @input="calculate"
            />

            <div
                v-if="calculation"
                class="mt-6 grid md:grid-cols-3 gap-4"
            >
                <div>
                    <div class="text-gray-500 text-sm">
                        Investment
                    </div>
                    <div class="text-white font-semibold">
                        {{ calculation.amount }}
                    </div>
                </div>

                <div>
                    <div class="text-gray-500 text-sm">
                        Expected Interest
                    </div>
                    <div class="text-white font-semibold">
                        {{ calculation.expected_interest }}
                    </div>
                </div>

                <div>
                    <div class="text-gray-500 text-sm">
                        Maturity Value
                    </div>
                    <div class="text-white font-semibold">
                        {{ calculation.expected_maturity_amount }}
                    </div>
                </div>
            </div>

            <div
                v-if="error"
                class="mt-4 text-red-400"
            >
                {{ error }}
            </div>

            <button
                :disabled="!calculation"
                @click="continueInvestment"
                class="w-full mt-6 py-3 rounded-lg bg-indigo-600 text-white disabled:opacity-50"
            >
                Continue
            </button>
        </div>

    </div>
</template>