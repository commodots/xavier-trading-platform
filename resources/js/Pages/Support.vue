<template>
  <MainLayout>
  <div class="space-y-8">
    <div>
      <h2 class="text-xl font-semibold text-white">Help & Support</h2>
      <p class="text-sm text-gray-400">Find answers to common questions or reach out to our team.</p>
    </div>

    <div class="grid gap-4">
      <h3 class="text-sm font-medium tracking-wider text-white uppercase">Frequently Asked Questions</h3>
      
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div 
          v-for="(faq, index) in faqs" 
          :key="index" 
          @click="toggleFaq(index)"
          class="p-4 bg-[#16213A] border border-gray-700 rounded-lg hover:border-blue-500 transition group cursor-pointer h-fit"
        >
          <div class="flex items-center justify-between">
            <span class="text-gray-200 transition group-hover:text-blue-400">{{ faq.title }} </span>
            <span class="text-gray-500 transition-transform duration-200" :class="{ 'rotate-90': activeIndex === index }">→</span>
          </div>

          <div 
            v-if="activeIndex === index" 
            class="pt-4 mt-4 text-sm leading-relaxed text-gray-400 border-t border-gray-700"
          >
            {{ faq.content }}
          </div>
        </div>
      </div>
    </div>

    <hr class="border-gray-800">

    <div class="flex flex-col items-center justify-between gap-6 p-6 border bg-blue-900/10 border-blue-500/20 rounded-xl md:flex-row">
      <div class="space-y-1">
        <h3 class="font-semibold text-white">Still need help?</h3>
        <p class="text-sm text-gray-400">Our support team is available 24/7 to assist you with any issues. </p>
      </div>
      
      <button 
        @click="contactSupport"
        class="px-8 py-3 font-bold text-white transition bg-blue-600 rounded-lg shadow-lg hover:bg-blue-700 shadow-blue-900/20 whitespace-nowrap"
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
    content: "Withdrawals are processed via our secure Unified Withdrawal Flow. Local bank transfers are typically processed within 30 minutes after security approval. International transfers may take 1-3 business days. All withdrawals require 2FA and KYC Level 2+." 
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