<template>
  <div class="space-y-8">
    <div>
      <h2 class="text-xl font-semibold text-white">Help & Support</h2>
      <p class="text-gray-400 text-sm">Find answers to common questions or reach out to our team.</p>
    </div>

    <div class="grid gap-4">
      <h3 class="text-white font-medium text-sm uppercase tracking-wider">Frequently Asked Questions</h3>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a 
          v-for="faq in faqs" 
          :key="faq.title" 
          :href="faq.link"
          class="p-4 bg-[#16213A] border border-gray-700 rounded-lg hover:border-blue-500 transition group"
        >
          <div class="flex justify-between items-center">
            <span class="text-gray-200 group-hover:text-blue-400 transition">{{ faq.title }} </span>
            <span class="text-gray-500">→</span>
          </div>
        </a>
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
</template>

<script setup>
const faqs = [
  { title: "How do I verify my KYC?", link: "#" },
  { title: "Withdrawal processing times", link: "#" },
  { title: "Resetting Two-Factor Authentication", link: "#" },
  { title: "Transaction fees and limits", link: "#" }
];

const contactSupport = () => {
  // Logic for opening a chat widget or a mailto link
  window.location.href = "mailto:support@xavier.com";
};
</script>