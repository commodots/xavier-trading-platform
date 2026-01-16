<template>
  <MainLayout>
  <div class="space-y-8">
    <div>
      <h2 class="text-xl font-semibold text-white">Help & Support</h2>
      <p class="text-gray-400 text-sm">Find answers to common questions or reach out to our team.</p>
    </div>

    <div class="grid gap-4">
      <h3 class="text-white font-medium text-sm uppercase tracking-wider">Frequently Asked Questions</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div 
          v-for="(faq, index) in faqs" 
          :key="index" 
          @click="toggleFaq(index)"
          class="p-4 bg-[#16213A] border border-gray-700 rounded-lg hover:border-blue-500 transition group cursor-pointer h-fit"
        >
          <div class="flex justify-between items-center">
            <span class="text-gray-200 group-hover:text-blue-400 transition">{{ faq.title }} </span>
            <span class="text-gray-500 transition-transform duration-200" :class="{ 'rotate-90': activeIndex === index }">→</span>
          </div>

          <div 
            v-if="activeIndex === index" 
            class="mt-4 text-sm text-gray-400 border-t border-gray-700 pt-4 leading-relaxed"
          >
            {{ faq.content }}
          </div>
        </div>
      </div>
    </div>

    <hr class="border-gray-800">

    <div class="bg-blue-900/10 border border-blue-500/20 p-6 rounded-xl flex flex-col md:flex-row items-center justify-between gap-6">
      <div class="space-y-1">
        <h3 class="text-white font-semibold">Still need help?</h3>
        <p class="text-gray-400 text-sm">Our support team is available 24/7 to assist you with any issues. </p>
      </div>
      
      <button 
        @click="contactSupport"
        class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-bold shadow-lg shadow-blue-900/20 transition whitespace-nowrap"
      >
        Contact Support 
      </button>
    </div>

  </div>
  </MainLayout>
</template>

<script setup>
import { ref } from 'vue';
import MainLayout from '@/Layouts/MainLayout.vue';

const activeIndex = ref(null);

const faqs = [
  { 
    title: "How do I verify my KYC?", 
    content: "To verify your identity, navigate to the Settings page and select 'KYC Verification'. You'll need to upload a clear image of a government-issued ID (NIN, Driver's License, or Passport) and complete a quick facial scan. Most verifications are processed within 24 hours." 
  },
  { 
    title: "Withdrawal processing times", 
    content: "Local bank withdrawals are typically processed instantly but can take up to 30 minutes during peak hours. International wire transfers or crypto withdrawals usually take between 1 to 3 business days to clear depending on network congestion." 
  },
  { 
    title: "Resetting Two-Factor Authentication", 
    content: "If you have lost access to your 2FA device, please use your recovery codes provided during setup. If you don't have those, click 'Contact Support' below. For security reasons, manual resets require a video call with our compliance team." 
  },
  { 
    title: "Transaction fees and limits", 
    content: "Deposits are free. Withdrawals carry a flat processing fee of ₦50 for local transfers. Transaction limits are based on your KYC level: Level 1 (Unverified) is restricted, while Level 3 (Full KYC) allows for unlimited monthly volume." 
  }
];

const toggleFaq = (index) => {
  activeIndex.value = activeIndex.value === index ? null : index;
};

const contactSupport = () => {
  window.location.href = "mailto:support@xavier.com";
};
</script>