<template>
  <main id="main-content" tabindex="-1" class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0B132B] to-[#1C2541] text-white">
    <div class="w-full max-w-md bg-[#1C1F2E]/80 backdrop-blur-md rounded-2xl shadow-xl p-8">

      <div class="mb-6 text-center">
        <img src="/images/xavier-logo.png" alt="Xavier" class="h-20 mx-auto mb-4 drop-shadow-lg" />
        <h1 id="register-title" class="text-3xl font-bold">Create Account</h1>
        <p class="text-sm text-gray-400 mt-2">Get started with your Xavier trading account</p>
      </div>

      <!-- Step indicator -->
      <div class="mb-6 flex items-center justify-between text-sm font-medium text-gray-400">
        <div
          v-for="step in steps"
          :key="step.id"
          class="flex items-center gap-2"
        >
          <span
            :class="[
              currentStep === step.id ? 'text-[#00D4FF] font-bold' : currentStep > step.id ? 'text-[#00D4FF]' : 'text-gray-500',
            ]"
          >
            {{ step.label }}
          </span>
          <span v-if="step.id < steps.length" class="text-gray-600">›</span>
        </div>
        <span class="text-xs">
          Step {{ currentStep }} of {{ steps.length }}
        </span>
      </div>

      <form aria-labelledby="register-title" @submit.prevent="submit" class="space-y-4">
        <!-- ===== STEP 1: PERSONAL DETAILS ===== -->
        <div v-if="currentStep === 1" class="space-y-4">
          <div>
            <label for="register-name" class="block mb-1 text-gray-300">Full Name</label>
            <input id="register-name" v-model="name" type="text" autocomplete="name"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.name" class="mt-1 text-sm text-red-400">{{ localErrors.name[0] }}</p>
          </div>

          <div>
            <label for="register-email" class="block mb-1 text-gray-300">Email</label>
            <input id="register-email" v-model="email" type="email" autocomplete="email"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.email" class="mt-1 text-sm text-red-400">{{ localErrors.email[0] }}</p>
          </div>

          <div>
            <label for="register-dob" class="block mb-1 text-gray-300">Date of Birth</label>
            <input id="register-dob" v-model="dob" type="date" autocomplete="bday"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.dob" class="mt-1 text-sm text-red-400">{{ localErrors.dob[0] }}</p>
          </div>

          <div>
            <label for="register-phone" class="block mb-1 text-gray-300">Mobile Phone</label>
            <input id="register-phone" v-model="mobile_phone_no" type="tel" autocomplete="tel"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.phone || localErrors.mobile_phone_no" class="mt-1 text-sm text-red-400">
              {{ (localErrors.mobile_phone_no || localErrors.phone)[0] }}
            </p>
          </div>

          <div>
            <label for="register-gender" class="block mb-1 text-gray-300">Gender</label>
            <select id="register-gender" v-model="gender_cd"
              class="w-full px-4 py-2 bg-[#1C1F2E] border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required>
              <option disabled value="">Select gender</option>
              <option value="M">Male</option>
              <option value="F">Female</option>
            </select>
            <p v-if="localErrors.gender_cd" class="mt-1 text-sm text-red-400">{{ localErrors.gender_cd[0] }}</p>
          </div>

          <div class="pt-4">
            <button type="button" @click="nextStep"
              :disabled="loading || !step1Valid"
              class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2.5 rounded-lg font-bold hover:opacity-90 disabled:opacity-40 transition flex items-center justify-center gap-2">
              <span class="mr-1">Next</span>
              <span>›</span>
            </button>
          </div>
        </div>

        <!-- ===== STEP 2: ACCOUNT & BANK DETAILS ===== -->
        <div v-else-if="currentStep === 2" class="space-y-4">
          <div>
            <label for="register-address-street" class="block mb-1 text-gray-300">Street Address</label>
            <input id="register-address-street" v-model="address_street" type="text" autocomplete="street-address"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.address_street" class="mt-1 text-sm text-red-400">{{ localErrors.address_street[0] }}</p>
          </div>

          <div>
            <label for="register-address-city" class="block mb-1 text-gray-300">City</label>
            <input id="register-address-city" v-model="address_city" type="text" autocomplete="address-level2"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.address_city" class="mt-1 text-sm text-red-400">{{ localErrors.address_city[0] }}</p>
          </div>

          <div>
            <label for="register-address-country" class="block mb-1 text-gray-300">Country</label>
            <select id="register-address-country" v-model="address_country_cd"
              class="w-full px-4 py-2 bg-[#1C1F2E] border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required>
              <option disabled value="">Select country</option>
              <option v-for="country in countries" :key="country.code" :value="country.code">
                {{ country.code }} — {{ country.description }}
              </option>
            </select>
            <p v-if="localErrors.address_country_cd" class="mt-1 text-sm text-red-400">{{ localErrors.address_country_cd[0] }}</p>
            <p v-if="lookupError && !countries.length" class="mt-1 text-sm text-red-400">{{ lookupError }}</p>
          </div>

          <div>
            <label for="register-bank-code" class="block mb-1 text-gray-300">Bank</label>
            <select id="register-bank-code" v-model="bank_cd"
              :disabled="lookupLoading || !banks.length"
              class="w-full px-4 py-2 bg-[#1C1F2E] border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required>
              <option disabled value="">Select bank</option>
              <option v-for="bank in banks" :key="bank.code" :value="bank.code">
                {{ bank.code }} — {{ bank.description }}
              </option>
            </select>
            <p v-if="localErrors.bank_cd" class="mt-1 text-sm text-red-400">{{ localErrors.bank_cd[0] }}</p>
            <p v-if="lookupError && !banks.length" class="mt-1 text-sm text-red-400">{{ lookupError }}</p>
          </div>

          <div>
            <label for="register-bank-account-name" class="block mb-1 text-gray-300">Bank Account Name</label>
            <input id="register-bank-account-name" v-model="bank_account_name" type="text" autocomplete="off"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.bank_account_name" class="mt-1 text-sm text-red-400">{{ localErrors.bank_account_name[0] }}</p>
          </div>

          <div>
            <label for="register-bank-account-no" class="block mb-1 text-gray-300">Bank Account Number</label>
            <input id="register-bank-account-no" v-model="bank_account_no" type="text" inputmode="numeric" autocomplete="off"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.bank_account_no" class="mt-1 text-sm text-red-400">{{ localErrors.bank_account_no[0] }}</p>
          </div>

          <div>
            <label for="register-bank-branch" class="block mb-1 text-gray-300">Bank Branch Name</label>
            <input id="register-bank-branch" v-model="bank_branch_name" type="text" autocomplete="off"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.bank_branch_name" class="mt-1 text-sm text-red-400">{{ localErrors.bank_branch_name[0] }}</p>
          </div>

          <div>
            <label for="register-bank-address" class="block mb-1 text-gray-300">Bank Address Details</label>
            <input id="register-bank-address" v-model="bank_address_details" type="text" autocomplete="off"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.bank_address_details" class="mt-1 text-sm text-red-400">{{ localErrors.bank_address_details[0] }}</p>
          </div>

          <div>
            <label for="register-bank-bvn" class="block mb-1 text-gray-300">Bank Verification Number (BVN)</label>
            <input id="register-bank-bvn" v-model="bank_bvn_no" type="text" inputmode="numeric" autocomplete="off"
              maxlength="11"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
              required />
            <p v-if="localErrors.bank_bvn_no" class="mt-1 text-sm text-red-400">{{ localErrors.bank_bvn_no[0] }}</p>
          </div>

          <div>
            <label for="register-password" class="block mb-1 text-gray-300">Password</label>
           <input id="register-password" v-model="password" type="password" autocomplete="new-password"
             class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
             required />
           <div v-if="password.length > 0" class="flex gap-1 mt-2">
             <div v-for="i in 3" :key="i" :class="['h-1 w-full rounded-full',
               (passwordStrength.level === 'weak' && i === 1) ? passwordStrength.color :
                 (passwordStrength.level === 'strong' && i <= 2) ? passwordStrength.color :
                   (passwordStrength.level === 'extremely-strong') ? passwordStrength.color :
                     'bg-gray-700'
           ]"></div>
           </div>
           <p v-if="password.length > 0" class="mt-1 text-xs text-gray-500">Strength: {{ passwordStrength.text }}</p>
         </div>

         <div>
           <label for="register-password-confirmation" class="block mb-1 text-gray-300">Confirm Password</label>
           <input id="register-password-confirmation" v-model="password_confirmation" type="password" autocomplete="new-password"
             class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
             required />
           <p v-if="password !== password_confirmation && password_confirmation" class="mt-1 text-xs text-red-400">
             Passwords do not match
           </p>
         </div>

         <div class="flex items-start gap-2 py-2">
           <input type="checkbox" id="terms" required class="mt-1 accent-[#00D4FF]" />
           <label for="terms" class="text-xs text-gray-400">I agree to the Terms of Service and Privacy Policy.</label>
         </div>

          <div class="pt-4 flex items-center justify-between">
            <button type="button" @click="prevStep"
              :disabled="loading"
              class="px-4 py-2 text-gray-400 hover:text-white transition disabled:opacity-40">
              ‹ Back
            </button>
            <button type="submit" :disabled="loading || password !== password_confirmation || password.length < MIN_PASSWORD_LENGTH || !step2Valid"
              class="w-auto bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white px-6 py-2.5 rounded-lg font-bold hover:opacity-90 disabled:opacity-40 transition flex items-center justify-center gap-2">
              <span v-if="loading" class="w-4 h-4 border-2 rounded-full border-white/30 border-t-white animate-spin mr-1"></span>
              {{ loading ? 'Creating Account...' : 'Get Started' }}
            </button>
          </div>
        </div>
      </form>

      <p class="mt-6 text-sm text-center text-gray-400">
        Already have an account?
        <a href="/login" class="text-[#00D4FF] hover:underline">Sign In</a>
      </p>

    </div>

    <WarningModal :show="showWarningModal" :message="warningMessage" @close="showWarningModal = false" />
    <ErrorModal :show="showErrorModal" :message="errorMessage" @close="showErrorModal = false" />
  </main>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/api";
import { useRouter } from "vue-router";
import WarningModal from "@/Components/WarningModal.vue";
import ErrorModal from "@/Components/ErrorModal.vue";

const router = useRouter();

// ── Step state ──
const currentStep = ref(1);
const steps = [
  { id: 1, label: 'Personal Details' },
  { id: 2, label: 'Account & Bank' },
];

const loading = ref(false);
const name = ref("");
const email = ref("");
const dob = ref("");
const mobile_phone_no = ref("");
const gender_cd = ref("");
const address_street = ref("");
const address_city = ref("");
const address_country_cd = ref("");
const bank_cd = ref("");
const bank_account_name = ref("");
const bank_account_no = ref("");
const bank_branch_name = ref("");
const bank_address_details = ref("");
const bank_bvn_no = ref("");
const password = ref("");
const password_confirmation = ref("");
const localErrors = ref({});
const showWarningModal = ref(false);
const showErrorModal = ref(false);
const warningMessage = ref('');
const errorMessage = ref('');
const countries = ref([]);
const banks = ref([]);
const lookupLoading = ref(false);
const lookupError = ref('');

const MIN_PASSWORD_LENGTH = 8;

const passwordStrength = computed(() => {
  const len = password.value.length;
  if (len < 5) return { level: 'weak', color: 'bg-amber-500', text: 'Weak' };
  if (len < 8) return { level: 'strong', color: 'bg-green-200', text: 'Strong' };
  return { level: 'extremely-strong', color: 'bg-green-600', text: 'Extremely Strong' };
});

// ── Step validity ──
const step1Valid = computed(() => {
  return Boolean(
    name.value.trim() &&
    email.value.trim() &&
    dob.value &&
    mobile_phone_no.value.trim() &&
    gender_cd.value
  );
});

const step2Valid = computed(() => {
  return Boolean(
    address_street.value.trim() &&
    address_city.value.trim() &&
    address_country_cd.value &&
    bank_cd.value &&
    bank_account_name.value.trim() &&
    bank_account_no.value.trim() &&
    bank_branch_name.value.trim() &&
    bank_address_details.value.trim() &&
    bank_bvn_no.value.trim() &&
    password.value.length >= MIN_PASSWORD_LENGTH &&
    password.value === password_confirmation.value
  );
});

const nextStep = () => {
  if (currentStep.value === 1 && !step1Valid.value) return;
  if (currentStep.value < steps.length) currentStep.value += 1;
};

const prevStep = () => {
  if (currentStep.value > 1) currentStep.value -= 1;
};

const loadCslLookups = async () => {
  lookupLoading.value = true;
  lookupError.value = '';

  try {
    const [countryResponse, bankResponse] = await Promise.all([
      api.get('/csl/countries'),
      api.get('/csl/banks'),
    ]);

    if (!Array.isArray(countryResponse.data.countries) || !Array.isArray(bankResponse.data.banks)) {
      throw new Error('CSL lookup response was malformed.');
    }

    countries.value = countryResponse.data.countries;
    banks.value = bankResponse.data.banks;
  } catch (err) {
    console.error('Unable to load CSL country and bank codes:', err);
    countries.value = [];
    banks.value = [];
    lookupError.value = 'Unable to load country and bank lists. Please retry.';
  } finally {
    lookupLoading.value = false;
  }
};

onMounted(loadCslLookups);

const submit = async () => {
  localErrors.value = {};

  if (currentStep.value === 2) {
    if (password.value.length < MIN_PASSWORD_LENGTH) {
      warningMessage.value = `Password must contain at least ${MIN_PASSWORD_LENGTH} characters.`;
      showWarningModal.value = true;
      return;
    }

    if (password.value !== password_confirmation.value) {
      localErrors.value.password_confirmation = ["Passwords do not match."];
      return;
    }
  }

  if (!step2Valid.value) {
    localErrors.value.general = ["Please complete all required fields."];
    return;
  }

  loading.value = true;

  try {
    const payload = {
      name: name.value,
      email: email.value,
      dob: dob.value,
      mobile_phone_no: mobile_phone_no.value,
      gender_cd: gender_cd.value,
      address_street: address_street.value,
      address_city: address_city.value,
      address_country_cd: address_country_cd.value,
      bank_cd: bank_cd.value,
      bank_account_name: bank_account_name.value,
      bank_account_no: bank_account_no.value,
      bank_branch_name: bank_branch_name.value,
      bank_address_details: bank_address_details.value,
      bank_bvn_no: bank_bvn_no.value,
      password: password.value,
      password_confirmation: password_confirmation.value,
    };

    const res = await api.post("/register", payload);

    if (res.data.token || res.data.access_token) {
      localStorage.setItem("xavier_token", res.data.token || res.data.access_token);
    }
    localStorage.setItem("user", JSON.stringify(res.data.user || res.data.data));

    // Redirect cleanly to email verification (Level 0 -> Level 1 transition path)
    router.push("/verify-email");
  } catch (err) {
    console.error("Registration pipeline submission exception:", err);
    if (err.response?.data?.errors) {
      localErrors.value = err.response.data.errors;
    } else {
      errorMessage.value = "Registration failed. Please try again.";
      showErrorModal.value = true;
    }
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>