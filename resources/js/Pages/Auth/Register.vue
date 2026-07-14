<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0B132B] to-[#1C2541] text-white">
    <div class="w-full max-w-md bg-[#1C1F2E]/80 backdrop-blur-md rounded-2xl shadow-xl p-8">

      <div class="mb-10 text-center">
        <img src="/images/xavier-logo.png" class="h-20 mx-auto mb-4 drop-shadow-lg" />
        <h1 class="text-3xl font-bold">Create Account</h1>
        <p class="text-sm text-gray-400 mt-2">Get started with your Xavier trading account</p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        
        <div>
          <label class="block mb-1 text-gray-300">Full Name</label>
          <input v-model="name" type="text"
            class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
            required />
          <p v-if="localErrors.name" class="mt-1 text-sm text-red-400">{{ localErrors.name[0] }}</p>
        </div>

        <div>
          <label class="block mb-1 text-gray-300">Email</label>
          <input v-model="email" type="email"
            class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
            required />
          <p v-if="localErrors.email" class="mt-1 text-sm text-red-400">{{ localErrors.email[0] }}</p>
        </div>

         <div>
          <label class="block mb-1 text-gray-300">Date of Birth</label>
          <input v-model="dob" type="date"
            class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none"
            required />
          <p v-if="localErrors.dob" class="mt-1 text-sm text-red-400">{{ localErrors.dob[0] }}</p>
        </div>

        <div>
          <label class="block mb-1 text-gray-300">Password</label>
          <input v-model="password" type="password"
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
          <label class="block mb-1 text-gray-300">Confirm Password</label>
          <input v-model="password_confirmation" type="password"
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

        <button type="submit" :disabled="loading || password !== password_confirmation || password.length < MIN_PASSWORD_LENGTH"
          class="w-full bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2.5 rounded-lg font-bold hover:opacity-90 disabled:opacity-40 transition flex items-center justify-center gap-2">
          <span v-if="loading" class="w-4 h-4 border-2 rounded-full border-white/30 border-t-white animate-spin"></span>
          {{ loading ? 'Creating Account...' : 'Get Started' }}
        </button>

      </form>

      <p class="mt-6 text-sm text-center text-gray-400">
        Already have an account?
        <a href="/login" class="text-[#00D4FF] hover:underline">Sign In</a>
      </p>

    </div>

    <WarningModal :show="showWarningModal" :message="warningMessage" @close="showWarningModal = false" />
    <ErrorModal :show="showErrorModal" :message="errorMessage" @close="showErrorModal = false" />
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import api from "@/api";
import { useRouter } from "vue-router";
import WarningModal from "@/Components/WarningModal.vue";
import ErrorModal from "@/Components/ErrorModal.vue";

const router = useRouter();

const loading = ref(false);
const name = ref("");
const email = ref("");
const dob = ref("");
const password = ref("");
const password_confirmation = ref("");
const localErrors = ref({});
const showWarningModal = ref(false);
const showErrorModal = ref(false);
const warningMessage = ref('');
const errorMessage = ref('');

const MIN_PASSWORD_LENGTH = 8;

const passwordStrength = computed(() => {
  const len = password.value.length;
  if (len < 5) return { level: 'weak', color: 'bg-amber-500', text: 'Weak' };
  if (len < 8) return { level: 'strong', color: 'bg-green-200', text: 'Strong' };
  return { level: 'extremely-strong', color: 'bg-green-600', text: 'Extremely Strong' };
});

const submit = async () => {
  localErrors.value = {};

  if (password.value.length < MIN_PASSWORD_LENGTH) {
    warningMessage.value = `Password must contain at least ${MIN_PASSWORD_LENGTH} characters.`;
    showWarningModal.value = true;
    return;
  }

  if (password.value !== password_confirmation.value) {
    localErrors.value.password_confirmation = ["Passwords do not match."];
    return;
  }

  loading.value = true;

  try {
    const payload = {
      name: name.value,
      email: email.value,
      dob: dob.value,
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
      errorMessage.value = err.response?.data?.message || "Registration failure. Please check inputs.";
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