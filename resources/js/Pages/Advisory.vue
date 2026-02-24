<template>
  <MainLayout>
    <div class="relative p-6 mx-auto max-w-7xl">

      <div v-if="feedback.show" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
        <div class="w-full max-w-sm p-6 text-center bg-white shadow-2xl rounded-2xl">
          <div :class="['flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full', feedback.type === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600']">
            <svg v-if="feedback.type === 'success'" xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </div>
          <h3 class="mb-2 text-xl font-bold text-gray-900">{{ feedback.title }}</h3>
          <p class="mb-6 text-gray-600">{{ feedback.message }}</p>
          <button @click="feedback.show = false" class="w-full py-3 font-bold text-white transition bg-gray-900 rounded-xl hover:bg-gray-800">Got it</button>
        </div>
      </div>

      <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm">
        <div class="w-full max-w-md p-6 text-center bg-white shadow-2xl rounded-2xl">
          <h3 class="mb-2 text-2xl font-bold text-gray-900">Cancel Subscription?</h3>
          <p class="mb-8 text-gray-600">You will immediately lose access to premium insights and AI picks.</p>
          <div class="flex justify-center gap-3">
            <button @click="showCancelModal = false" class="px-6 py-2.5 font-bold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">Keep VIP</button>
            <button @click="confirmCancelSubscription" :disabled="isCancelling" class="px-6 py-2.5 font-bold text-white bg-red-600 rounded-lg hover:bg-red-700 transition disabled:opacity-50 flex justify-center w-40">
              <span v-if="isCancelling">Canceling...</span>
              <span v-else>Yes, Cancel Plan</span>
            </button>
          </div>
        </div>
      </div>

      <div v-if="activePost" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col relative">
          <button @click="activePost = null" class="absolute z-10 p-1 text-gray-400 bg-white rounded-full top-4 right-4 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
          <div class="relative p-8 overflow-y-auto">
            <span class="block mb-2 text-xs font-bold tracking-wider text-blue-600 uppercase">{{ activePost.market_type }}</span>
            <h2 class="mb-4 text-3xl font-bold text-gray-900">{{ activePost.title }}</h2>
            <div :class="['text-gray-700 leading-relaxed text-lg', !user.has_active_subscription ? 'max-h-48 overflow-hidden' : '']">
              {{ activePost.content }}
            </div>
            <div v-if="!user.has_active_subscription" class="absolute bottom-0 left-0 right-0 flex flex-col items-center justify-end h-48 pb-8 bg-gradient-to-t from-white via-white/90 to-transparent">
              <button @click="scrollToPricing" class="px-8 py-3 font-bold text-white transition bg-blue-600 rounded-full shadow-lg hover:bg-blue-700">View Plans</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="isVerifying" class="fixed inset-0 z-50 flex flex-col items-center justify-center text-white bg-gray-900/90 backdrop-blur-sm">
        <div class="w-16 h-16 mb-4 border-t-4 border-blue-500 rounded-full animate-spin"></div>
        <h2 class="text-2xl font-bold">Verifying Payment...</h2>
      </div>

      <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold">Xavier Advisory</h1>
        <div v-if="user.has_active_subscription" class="flex items-center gap-4">
          <span class="flex items-center gap-2 px-4 py-1.5 text-sm font-bold text-blue-800 bg-blue-100 rounded-full shadow-sm">👑 VIP Active</span>
          <button @click="showCancelModal = true" class="text-sm font-semibold text-gray-400 underline transition hover:text-red-500">Cancel Plan</button>
        </div>
      </div>

      <div v-if="user.has_active_subscription" class="space-y-8">
        
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
          <div class="space-y-6 md:col-span-2">
            <section class="p-6 border shadow-sm bg-gray-50 rounded-xl">
              <h2 class="pb-2 mb-4 text-lg font-bold text-gray-700 border-b border-gray-200">Free Market Outlook</h2>
              <div v-for="post in freePosts" :key="post.id" @click="activePost = post" class="pb-4 mb-4 border-b border-gray-200 cursor-pointer last:border-0 group">
                <h3 class="text-lg font-semibold text-gray-800 transition group-hover:text-blue-600">{{ post.title }}</h3>
                <p class="mt-1 text-sm text-gray-500 line-clamp-2">{{ post.content }}</p>
              </div>
            </section>

            <section class="p-6 bg-white border shadow-sm rounded-xl">
              <h2 class="pb-2 mb-4 text-xl font-bold text-blue-900 border-b">Premium Insights</h2>
              <div v-for="post in premiumPosts" :key="post.id" @click="activePost = post" class="pb-4 mb-6 border-b cursor-pointer last:border-0 group">
                <h3 class="text-xl font-bold text-gray-900 transition group-hover:text-blue-600">{{ post.title }}</h3>
                <p class="text-gray-600 line-clamp-2">{{ post.content }}</p>
              </div>
            </section>
          </div>

          <div class="space-y-6">
            <section class="p-6 text-white bg-gray-900 shadow-2xl rounded-2xl">
              <h2 class="pb-4 mb-4 text-xl font-black border-b border-gray-800">🤖 AI Top Picks</h2>
              <ul class="space-y-3">
                <li v-for="pick in aiPicks" :key="pick.symbol" class="flex items-center justify-between p-4 bg-gray-800 rounded-xl">
                  <span class="text-xl font-black">{{ pick.symbol }}</span>
                  <span class="px-2 py-1 text-xs font-black text-green-400 rounded-md bg-green-400/10">{{ pick.confidence }}%</span>
                </li>
              </ul>
            </section>
          </div>
        </div>

        <div class="space-y-6">
          <section class="p-6 text-white bg-blue-600 border shadow-md rounded-2xl">
            <div class="flex items-start gap-4">
              <div class="p-3 bg-white/20 rounded-xl">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div>
                <h2 class="text-lg font-bold">What are Model Portfolios?</h2>
                <p class="mt-1 text-sm text-blue-50 text-balance">
                  These are "Investment Blueprints" curated by our team. Instead of picking stocks one-by-one, you can <strong>Copy Trade</strong> a whole basket. Our engine automatically buys each stock in the blueprint based on your budget.
                </p>
              </div>
            </div>
          </section>

          <section>
            <h2 class="pb-2 mb-4 text-2xl font-black text-gray-600">Model Portfolios</h2>
            <div class="grid grid-cols-1 gap-6 text-gray-600 md:grid-cols-3">
              <div v-for="portfolio in portfolios" :key="portfolio.id" class="relative flex flex-col p-6 transition bg-white border border-gray-100 shadow-sm rounded-2xl hover:shadow-lg">
                <h3 class="mb-1 text-xl font-black text-gray-900">{{ portfolio.name }}</h3>
                <p class="mb-4 text-xs font-bold tracking-widest text-gray-400 uppercase">{{ portfolio.risk_profile }} Strategy</p>
                <div class="mb-6 space-y-3">
                  <div v-for="stock in portfolio.stocks" :key="stock.id" class="space-y-1">
                    <div class="flex justify-between text-xs font-bold text-gray-700">
                      <span>{{ stock.symbol }}</span><span>{{ stock.allocation_percentage }}%</span>
                    </div>
                    <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden"><div class="h-full bg-blue-500" :style="{ width: stock.allocation_percentage + '%' }"></div></div>
                  </div>
                </div>
                <div class="flex items-center gap-2 pt-4 mt-auto border-t">
                  <input type="number" v-model="copyAmounts[portfolio.id]" placeholder="Budget (₦)" class="w-full px-4 py-2 text-sm border rounded-lg outline-none focus:border-blue-500" />
                  <button @click="copyPortfolio(portfolio.id)" :disabled="processingPortfolioId === portfolio.id" class="px-5 py-2 text-sm font-bold text-white bg-gray-900 rounded-lg hover:bg-blue-600 disabled:opacity-50">
                    <span v-if="processingPortfolioId === portfolio.id">Copying...</span>
                    <span v-else>Copy</span>
                  </button>
                </div>
              </div>
            </div>
          </section>
        </div>

      </div>

      <div v-else class="space-y-8">
        <section>
          <h2 class="pb-2 mb-4 text-xl font-bold text-gray-800 border-b">Free Market Outlook</h2>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div v-for="post in freePosts" :key="post.id" @click="activePost = post" class="p-5 transition bg-white border rounded-lg shadow-sm cursor-pointer hover:shadow-md group">
              <span class="block mb-1 text-xs font-bold text-gray-400 uppercase">{{ post.market_type }}</span>
              <h3 class="text-lg font-bold text-gray-800 group-hover:text-blue-600">{{ post.title }}</h3>
              <p class="mt-2 text-sm text-gray-600 line-clamp-3">{{ post.content }}</p>
              <p class="mt-4 text-xs font-bold text-blue-500 opacity-0 group-hover:opacity-100">Read Article &rarr;</p>
            </div>
          </div>
        </section>

        <div id="pricing-section" class="relative overflow-hidden bg-gray-900 shadow-xl rounded-xl min-h-[500px] flex items-center justify-center">
          <div class="absolute inset-0 p-8 grid grid-cols-1 md:grid-cols-2 gap-8 opacity-20 blur-[3px] pointer-events-none select-none">
            <div v-for="i in 2" :key="i"><div class="w-32 h-8 mb-4 bg-gray-600 rounded"></div><div v-for="j in 3" :key="j" class="h-16 mb-2 bg-gray-700 rounded"></div></div>
          </div>
          <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-[2px]"></div>
          <div class="relative z-10 w-full max-w-4xl p-6 py-12 mx-auto text-center">
            <div class="p-8 bg-white shadow-2xl rounded-2xl">
              <h2 class="text-2xl font-bold text-gray-900">Unlock VIP Advisory</h2>
              <p class="max-w-lg mx-auto mt-2 mb-8 text-gray-600">Get instant access to AI Stock Scoring and Model Portfolios.</p>
              <div class="flex flex-wrap justify-center gap-4">
                <div v-for="plan in plans" :key="plan.id" class="flex flex-col w-64 p-6 text-left border border-gray-200 rounded-xl bg-gray-50 hover:border-blue-400">
                  <h3 class="font-bold text-gray-800">{{ plan.name }}</h3>
                  <p class="my-3 text-3xl font-black text-blue-600">₦{{ plan.price.toLocaleString() }}</p>
                  <button v-if="showSubscribeBtn" @click="subscribe(plan.id)" :disabled="processingPlanId === plan.id" class="w-full py-2 mt-auto font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                    {{ processingPlanId === plan.id ? 'Processing...' : 'Subscribe Now' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';

const route = useRoute();
const router = useRouter();

// State
const user = ref({ has_active_subscription: false });
const plans = ref([]);
const freePosts = ref([]);
const premiumPosts = ref([]);
const portfolios = ref([]);
const aiPicks = ref([]);
const copyAmounts = ref({});

// Feedback State
const feedback = ref({ show: false, title: '', message: '', type: 'success' });
const showFeedback = (title, message, type = 'success') => {
  feedback.value = { show: true, title, message, type };
};

// UI State
const showSubscribeBtn = ref(false);
const activePost = ref(null);
const isVerifying = ref(false);
const showCancelModal = ref(false);
const isCancelling = ref(false);
const processingPlanId = ref(null);
const processingPortfolioId = ref(null); 

onMounted(async () => {
  setTimeout(() => { showSubscribeBtn.value = true; }, 3500);
  await checkStatus();
  await fetchDashboardData();
  if (route.query.reference && route.query.plan_id) handlePaymentVerification(route.query.reference, route.query.plan_id);
});

const checkStatus = async () => {
  try {
    const res = await api.get('/user/profile/show');
    const data = res.data.data || res.data;
    if (data.has_active_subscription) user.value.has_active_subscription = true;
  } catch (e) { console.error(e); }
};

const handlePaymentVerification = async (reference, planId) => {
  isVerifying.value = true;
  try {
    const res = await api.get(`/user/advisory/verify-payment?reference=${reference}&plan_id=${planId}`);
    if (res.data.success) {
      user.value.has_active_subscription = true;
      await fetchDashboardData();
      router.replace('/advisory');
    }
  } catch (error) { showFeedback('Error', 'Verification failed.', 'error'); }
  finally { isVerifying.value = false; }
};

const fetchDashboardData = async () => {
  try {
    const [planRes, freeRes] = await Promise.all([api.get('/user/advisory/plans'), api.get('/user/advisory/free-posts')]);
    plans.value = planRes.data.data || planRes.data;
    freePosts.value = freeRes.data.data;

    if (user.value.has_active_subscription) {
      const [postsRes, portfoliosRes, picksRes] = await Promise.all([
        api.get('/user/advisory/premium-posts'),
        api.get('/user/advisory/model-portfolios'),
        api.get('/user/advisory/ai-picks')
      ]);
      premiumPosts.value = postsRes.data.data;
      portfolios.value = portfoliosRes.data.data;
      aiPicks.value = picksRes.data.data;
    }
  } catch (e) { console.log("Not VIP"); }
};

const subscribe = async (planId) => {
  processingPlanId.value = planId;
  try {
    const res = await api.post('/user/advisory/subscribe', { plan_id: planId });
    window.location.href = res.data.data.authorization_url;
  } catch (error) { showFeedback('Error', 'Payment failed.', 'error'); }
  finally { processingPlanId.value = null; }
};

const confirmCancelSubscription = async () => {
  isCancelling.value = true;
  try {
    await api.get('/sanctum/csrf-cookie', { baseURL: '/' }); 
    await api.post('/user/advisory/cancel');
    user.value.has_active_subscription = false;
    showCancelModal.value = false;
    showFeedback('Cancelled', 'Your subscription was removed.');
  } catch (error) { showFeedback('Error', 'Cancel failed.', 'error'); }
  finally { isCancelling.value = false; }
};

const copyPortfolio = async (portfolioId) => {
  const amount = copyAmounts.value[portfolioId];
  if (!amount || amount < 5000) {
    showFeedback('Budget Too Low', 'Minimum amount is ₦5,000.', 'error');
    return;
  }
  
  processingPortfolioId.value = portfolioId; 
  
  try {
    await api.post(`/user/advisory/model-portfolios/${portfolioId}/copy`, { amount });
    
    // Redirect to orders on success
    router.push('/orders'); 
    
  } catch (error) {
    showFeedback('Error', error.response?.data?.message || 'Copy failed. Check balance.', 'error');
    processingPortfolioId.value = null; 
  }
};

const scrollToPricing = () => {
  activePost.value = null;
  document.getElementById('pricing-section')?.scrollIntoView({ behavior: 'smooth' });
};
</script>