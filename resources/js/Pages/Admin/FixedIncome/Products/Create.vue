<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/api'

const router = useRouter()
const saving = ref(false)
const error = ref('')

const form = reactive({
    name: '',
    code: '',
    type: 'bond',
    description: '',
    currency: 'NGN',
    issuer: '',
    status: 'draft',

    minimum_amount: 0,
    maximum_amount: null,
    maximum_open_ended: false,
    maximum_user_capacity: null,

    start_date: null,
    end_date: null,
    open_ended: false,

    interest_rate: null,
    rate_type: 'fixed',
    interest_frequency: 'at_maturity',
    tenor_days: null,

    early_withdrawal_allowed: false,
    early_withdrawal_penalty: 0,

    subscription_fee: 0,
    subscription_fee_type: 'none',

    maximum_capacity: null,

    execution_mode: 'manual',
    provider: null,

    allow_reinvestment: false,

    calculation_method: 'simple_interest',
    day_count_basis: 'actual_365',
    capitalise_interest: false,
    maturity_payout: 'wallet',

    metadata: {}
})

const submit = async () => {
    saving.value = true
    error.value = ''

    try {
        await api.post(
            '/admin/fixed-income/products',
            form
        )

        router.push(
            '/admin/fixed-income/products'
        )
    } catch (e) {
        error.value =
            e.response?.data?.message ||
            'Unable to create product.'
    } finally {
        saving.value = false
    }
}
</script>
