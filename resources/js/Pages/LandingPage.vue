<template>
  <div class="bg-[#0F1724] text-white min-h-screen font-sans">
    <header class="relative px-6 py-20 overflow-hidden border-b border-gray-800 md:py-32">
      <div class="absolute rounded-full -top-40 -left-40 w-80 h-80 bg-blue-600/10 blur-3xl opacity-60"></div>
      <div class="absolute rounded-full -bottom-40 -right-40 w-96 h-96 bg-blue-600/10 blur-3xl opacity-60"></div>

      <div class="relative z-10 mx-auto text-center max-w-7xl">
        <img src="/images/xavier-logo.png" alt="Logo" class="h-[100px] m-auto" />
        <h1 class="mb-8 text-5xl font-black leading-tight tracking-tight md:text-7xl animate-fade-in-up"
          style="animation-delay: 100ms;">
          Smart Trading, <br />
          <span class="text-blue-500">Secure & Simplified.</span>
        </h1>
      </div>
      <p class="max-w-3xl mx-auto mb-12 text-xl leading-relaxed text-gray-400 animate-fade-in-up"
        style="animation-delay: 200ms;">
        Access global markets instantly. Manage NGN & USD wallets, execute trades in real-time, and grow your portfolio
        with Xavier's robust trading platform.
      </p>
      <div class="flex flex-col items-center justify-center gap-6 sm:flex-row animate-fade-in-up"
        style="animation-delay: 300ms;">
        <button @click="navigateToRegister"
          class="w-full px-12 py-4 text-lg font-bold text-white transition bg-blue-600 shadow-lg rounded-xl hover:bg-blue-700 shadow-blue-500/30 sm:w-auto">
          Get Started
        </button>
        <button @click="navigateToLogin"
          class="w-full px-12 py-4 text-lg font-bold text-white transition bg-blue-600 shadow-lg rounded-xl hover:bg-blue-700 shadow-blue-500/30 sm:w-auto">
          Log In
        </button>
      </div>

      <div
        class="mt-8 flex flex-wrap justify-center items-center gap-x-6 gap-y-2 text-[13px] uppercase tracking-[0.2em] text-gray-500 font-bold animate-fade-in-up"
        style="animation-delay: 400ms;">
        <span class="flex items-center gap-2">🔒 NDPR Data Compliant</span>
        <span class="hidden text-gray-800 sm:block">•</span>
        <span class="flex items-center gap-2">💳 PCI-DSS Secure Payments</span>
        <span class="hidden text-gray-800 sm:block">•</span>
        <span class="flex items-center gap-2">🛡️ 256-Bit SSL Encrypted</span>
      </div>

      <div class="mt-12">
        <button @click="scrollToFeatures"
          class="flex items-center gap-2 px-8 py-4 m-auto font-semibold text-gray-300 transition hover:text-white group">
          Learn How It Works
          <span class="transition-transform group-hover:translate-x-1">→</span>
        </button>
      </div>
    </header>

    <section id="features" class="py-20 px-6 bg-[#0B111D] border-b border-gray-800">
      <div class="mx-auto max-w-7xl">
        <div class="mb-16 text-center">
          <h2 class="mb-4 text-4xl font-black md:text-5xl">Why Choose Xavier?</h2>
          <p class="max-w-2xl mx-auto text-xl text-gray-400">Built for speed, security, and scalability. Everything you
            need to trade effectively.</p>
        </div>

        <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
          <div
            class="bg-[#111827] p-8 rounded-3xl border border-gray-700/50 hover:border-blue-500/50 transition-all group">
            <div
              class="flex items-center justify-center w-16 h-16 mb-8 text-blue-500 border rounded-2xl bg-blue-500/10 border-blue-500/20">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2-2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <h3 class="mb-4 text-2xl font-bold transition group-hover:text-blue-400">Multi-Currency Wallet</h3>
            <p class="mb-6 leading-relaxed text-gray-400">Seamlessly manage NGN and USD balances. Deposit instantly via
              bank transfer and enjoy competitive exchange rates.</p>
            <span class="font-semibold text-blue-500">Flexible Funding</span>
          </div>

          <div
            class="bg-[#111827] p-8 rounded-3xl border border-gray-700/50 hover:border-blue-500/50 transition-all group relative">
            <div
              class="flex items-center justify-center w-16 h-16 mb-8 border rounded-2xl bg-amber-500/10 text-amber-500 border-amber-500/20">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <h3 class="mb-4 text-2xl font-bold transition group-hover:text-amber-400">Instant Execution</h3>
            <p class="mb-6 leading-relaxed text-gray-400">Experience lightning-fast trade settlements. Our robust
              matching engine ensures your orders are filled at the best prices.</p>
            <span class="font-semibold text-amber-500">High Performance</span>
          </div>

          <div
            class="bg-[#111827] p-8 rounded-3xl border border-gray-700/50 hover:border-blue-500/50 transition-all group">
            <div
              class="flex items-center justify-center w-16 h-16 mb-8 text-green-500 border rounded-2xl bg-green-500/10 border-green-500/20">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
            </div>
            <h3 class="mb-4 text-2xl font-bold transition group-hover:text-green-400">Secure Withdrawals</h3>
            <p class="mb-6 leading-relaxed text-gray-400">Your funds are yours. Withdraw to your local bank account
              instantly with minimal processing fees.</p>
            <span class="font-semibold text-green-500">Reliable Liquidity</span>
          </div>
        </div>
      </div>
    </section>

    <section class="py-20 px-6 bg-[#0F1724]">
      <div class="flex flex-col items-center gap-16 mx-auto max-w-7xl md:flex-row">
        <div class="space-y-6 md:w-1/2">
          <h2 class="mb-6 text-4xl font-black leading-tight md:text-5xl">
            Built for You. <br />
            <span class="text-gray-500">Focused on Privacy.</span> <br />
            Safe & Reliable.
          </h2>
          <p class="text-lg leading-relaxed text-gray-400">
            We believe that professional investment advice should be both accessible and secure.
            Xavier is built with a "privacy-first" mindset, ensuring that your data stays yours
            and your financial journey is protected every step of the way. No complications,
            just a platform you can depend on.
          </p>
        </div>

        <div class="md:w-1/2 bg-[#111827] p-10 rounded-3xl border border-gray-700 relative">
          <div class="absolute w-20 h-20 rounded-full -top-6 -right-6 bg-blue-600/10 blur-2xl opacity-80"></div>
          <ul class="space-y-6 text-gray-300">
            <li class="flex items-center gap-4 text-lg">
              <span class="flex items-center justify-center w-8 h-8 text-xl text-white bg-blue-600 rounded-lg">🔒</span>
              End-to-End Privacy Protection
            </li>
            <li class="flex items-center gap-4 text-lg">
              <span
                class="flex items-center justify-center w-8 h-8 text-xl text-white bg-blue-600 rounded-lg">🛡️</span>
              Verified User Verification
            </li>
            <li class="flex items-center gap-4 text-lg">
              <span class="flex items-center justify-center w-8 h-8 text-xl text-white bg-blue-600 rounded-lg">✨</span>
              Clean, Secure Transactions
            </li>
            <li class="flex items-center gap-4 text-lg">
              <span class="flex items-center justify-center w-8 h-8 text-xl text-white bg-blue-600 rounded-lg">🤝</span>
              Reliable Support Always
            </li>
          </ul>
        </div>
      </div>
    </section>


    <section class="py-20 px-6 bg-[#0B111D] border-t border-gray-800">
      <div class="mx-auto text-center max-w-7xl">
        <h2 class="mb-4 text-4xl font-black md:text-5xl">Market Advisory Plans</h2>
        <p class="max-w-3xl mx-auto mb-6 text-xl text-gray-400">
          Navigate financial markets with confidence using Xavier's dual-engine approach. We combine seasoned human
          expertise with cutting-edge AI algorithms to deliver actionable trading signals, comprehensive market
          analysis, and risk-managed model portfolios.
        </p>
        <p class="max-w-2xl mx-auto mb-16 text-lg text-gray-500">Choose the level of intelligence that fits your trading
          style to maximize your returns.</p>

        <div class="flex flex-col items-stretch justify-center gap-8 lg:flex-row">
          <div
            class="bg-[#111827] p-10 rounded-3xl border border-gray-700/50 w-full lg:w-96 flex flex-col hover:border-indigo-500/30 transition group">
            <h3 class="mb-2 text-xl font-bold text-indigo-400">Regular Tier</h3>
            <p class="mb-6 text-sm text-gray-500">Stay informed with daily market updates.</p>
            <ul class="flex-1 mb-12 space-y-4 text-sm text-left text-gray-400">
              <li class="flex items-center gap-3"> <span class="text-indigo-500">✓</span> Regular Market Posts </li>
              <li class="flex items-center gap-3"> <span class="text-indigo-500">✓</span> General News & Analysis </li>
              <li class="flex items-center gap-3"> <span class="text-indigo-500">✓</span> Basic Watchlists </li>
              <li class="flex items-center gap-3 text-gray-600"> ✕ AI Prediction Models </li>
              <li class="flex items-center gap-3 text-gray-600"> ✕ Model Portfolios </li>
            </ul>
            <button @click="navigateToLogin"
              class="w-full py-4 font-bold text-white transition bg-gray-700 hover:bg-indigo-600 rounded-xl">
              Subscribe
            </button>
          </div>

          <div
            class="bg-[#111827] p-10 rounded-3xl border-2 border-indigo-500 w-full lg:w-[400px] flex flex-col relative hover:border-indigo-400 transition scale-105 shadow-2xl shadow-indigo-900/20">
            <span
              class="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1.5 bg-indigo-500 text-white font-black text-xs rounded-full uppercase tracking-wider">Recommended</span>
            <h3 class="mb-2 text-xl font-bold text-white">Premium Tier</h3>
            <p class="mb-6 text-sm text-indigo-200">Full access to Xavier's AI intelligence.</p>
            <ul class="flex-1 mb-12 space-y-4 text-sm text-left text-gray-300">
              <li class="flex items-center gap-3"> <span class="text-indigo-400">✓</span> Everything in Regular </li>
              <li class="flex items-center gap-3"> <span class="text-indigo-400">✓</span> AI Top Picks & Signals </li>
              <li class="flex items-center gap-3"> <span class="text-indigo-400">✓</span> Copy Model Portfolios </li>
              <li class="flex items-center gap-3"> <span class="text-indigo-400">✓</span> Priority Advisory Access </li>
            </ul>
            <button @click="navigateToLogin"
              class="w-full py-4 font-bold text-white transition bg-indigo-600 hover:bg-indigo-500 rounded-xl">
              Subscribe
            </button>
          </div>
        </div>
      </div>
    </section>

    <section class="py-24 px-6 bg-[#0F1724]">
      <div class="max-w-4xl mx-auto text-center">
        <h2 class="mb-8 text-5xl font-black leading-tight md:text-6xl"> Ready to start <br /> <span
            class="text-blue-500">trading?</span> </h2>
        <p class="max-w-2xl mx-auto mb-12 text-xl leading-relaxed text-gray-400"> Join thousands of Nigerian investors
          making smarter financial moves. Create your account in minutes, verify your identity, and fund your wallet to
          begin. </p>
        <div class="flex items-center justify-center gap-4">
          <button @click="navigateToRegister"
            class="w-full px-16 py-5 text-xl font-bold text-white transition bg-blue-600 shadow-2xl rounded-xl hover:bg-blue-700 shadow-blue-500/30 sm:w-auto">
            Create Your Account
          </button>
          <button @click="navigateToLogin"
            class="w-full px-16 py-5 text-xl font-bold text-white transition bg-blue-600 shadow-2xl rounded-xl hover:bg-blue-700 shadow-blue-500/30 sm:w-auto">
            Log In
          </button>
        </div>

      </div>
    </section>

    <footer class="py-10 px-6 border-t border-gray-800 text-center text-gray-600 text-sm bg-[#0B111D]">
      <p>&copy; 2026 Xavier Advisory Limited. All rights reserved.</p>
    </footer>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();

const scrollToFeatures = () => {
  document.getElementById('features')?.scrollIntoView({ behavior: 'smooth' });
};

const navigateToRegister = () => {
  router.push('/register');
};

const navigateToLogin = () => {
  router.push('/login');
};
</script>

<style scoped>
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in-up {
  opacity: 0;
  animation: fadeInUp 0.6s ease-out forwards;
}
</style>