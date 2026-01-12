<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#0B132B] to-[#1C2541] text-white">
    <div class="w-full max-w-md bg-[#1C1F2E]/80 backdrop-blur-md rounded-2xl shadow-xl p-8">

      <!-- Logo + Title -->
      <div class="mb-10 text-center">
        <img src="/images/xavier-logo.png" class="h-20 mx-auto mb-4 drop-shadow-lg" />
        <h1 class="text-3xl font-bold">Create Account</h1>

        <div class="flex justify-center gap-2 mt-4">
          <div v-for="step in 3" :key="step" :class="['h-1.5 rounded-full transition-all duration-300',
            currentStep >= step ? 'w-8 bg-[#00D4FF]' : 'w-4 bg-gray-600']">
          </div>
        </div>
      </div>

      <form @submit.prevent="submit">

        <div v-if="currentStep === 1" class="space-y-4 animate-fadeIn">
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
              Passwords do not match</p>
          </div>

          <button type="button" @click="nextStep"
            class="w-full bg-[#00D4FF] text-[#0B132B] py-2 rounded-lg font-bold hover:opacity-90">
            Continue
          </button>
        </div>

        <div v-if="currentStep === 2" class="space-y-4 animate-fadeIn">
          <div class="p-3 mb-4 border rounded-lg bg-blue-500/10 border-blue-500/30">
            <p class="text-xs leading-tight text-blue-300">
              ⓘ Adding these now speeds up your verification and enables instant transactions. You can skip this for
              now.
            </p>
          </div>

          <div>
            <label class="block mb-1 text-gray-300">BVN (Optional)</label>
            <input v-model="kyc.bvn" type="text" maxlength="11" placeholder="11-digit BVN"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none" />
          </div>

          <div>
            <label class="block mb-1 text-gray-300">NIN (Optional)</label>
            <input v-model="kyc.nin" type="text" maxlength="11" placeholder="11-digit NIN"
              class="w-full px-4 py-2 bg-transparent border border-gray-600 rounded-lg focus:border-[#00D4FF] outline-none" />
          </div>

          <div class="flex gap-3 pt-2">
            <button type="button" @click="currentStep--"
              class="w-1/3 py-2 font-semibold border border-gray-600 rounded-lg">Back</button>
            <button type="button" @click="nextStep" class="w-2/3 bg-[#00D4FF] text-[#0B132B] py-2 rounded-lg font-bold">
              {{ (!kyc.bvn && !kyc.nin) ? 'Skip for now' : 'Continue' }}
            </button>
          </div>
        </div>

        <div v-if="currentStep === 3" class="space-y-4 animate-fadeIn">
          <div class="bg-[#151a27] p-4 rounded-xl border border-gray-700">
            <h3 class="text-sm font-bold text-[#00D4FF] mb-2 uppercase tracking-widest">Summary</h3>
            <p class="text-sm">Name: <span class="text-white">{{ name }}</span></p>
            <p class="text-sm">Email: <span class="text-white">{{ email }}</span></p>
            <p class="text-sm">Verification: <span class="text-white">{{ kyc.bvn || kyc.nin ? 'Provided' : 'Pending'
            }}</span></p>
          </div>

          <div class="flex items-start gap-2 py-2">
            <input type="checkbox" id="terms" required class="mt-1" />
            <label for="terms" class="text-xs text-gray-400">I agree to the Terms of Service and Privacy Policy.</label>
          </div>

          <div class="flex gap-3">
            <button type="button" :disabled="loading" @click="currentStep--"
              class="w-1/3 py-2 font-semibold border border-gray-600 rounded-lg disabled:opacity-50">Back</button>
            <button type="submit" :disabled="loading"
              class="w-2/3 bg-gradient-to-r from-[#0047AB] to-[#00D4FF] text-white py-2 rounded-lg font-bold hover:opacity-90 disabled:opacity-70 flex items-center justify-center gap-2">
              <span v-if="loading"
                class="w-4 h-4 border-2 rounded-full border-white/30 border-t-white animate-spin"></span>
              {{ loading ? 'Creating...' : 'Create Account' }}
            </button>
          </div>
        </div>

      </form>

      <p v-if="currentStep === 1" class="mt-6 text-sm text-center text-gray-400">
        Already have an account?
        <a href="/login" class="text-[#00D4FF] hover:underline">Sign In</a>
      </p>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";

const router = useRouter();
const currentStep = ref(1);
const loading = ref(false);

const name = ref("");
const email = ref("");
const password = ref("");
const password_confirmation = ref("");

const kyc = ref({
  bvn: "",
  nin: "",
  passport: ""
});

const localErrors = ref({});

const passwordStrength = computed(() => {
  const len = password.value.length;
  if (len < 5) return { level: 'weak', color: 'bg-amber-500', text: 'Weak' };
  if (len < 8) return { level: 'strong', color: 'bg-green-200', text: 'Strong' };
  return { level: 'extremely-strong', color: 'bg-green-600', text: 'Extremely Strong' };
});

const MIN_PASSWORD_LENGTH = 8;

const nextStep = () => {
  if (currentStep.value === 1) {
    if (!name.value || !email.value || password.value.length < MIN_PASSWORD_LENGTH) {
      alert("Please complete all required fields correctly.");
      return;
    }
  }
  currentStep.value++;
};

const submit = async () => {
  localErrors.value = {};

  if (password.value !== password_confirmation.value) {
    localErrors.value.password_confirmation = ["Password does not match."];
    return;
  }

  loading.value = true;

  try {
    const res = await axios.post("/register", {
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: password_confirmation.value,
      bvn: kyc.value.bvn,
      nin: kyc.value.nin
    });

    localStorage.setItem("xavier_token", res.data.token);
    localStorage.setItem("user", JSON.stringify(res.data.user));
    router.push("/dashboard");
  } catch (err) {
    console.error(err);
    alert(err.response?.data?.message || "Registration failed");
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.animate-fadeIn {
  animation: fadeIn 0.4s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Custom Spinner Animation */
.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }

  to {
    transform: rotate(360deg);
  }
}
</style>