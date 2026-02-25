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
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col relative animate-fade-in-up">
          <button @click="closePostModal" class="absolute z-10 p-1 text-gray-400 bg-white rounded-full top-4 right-4 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
          <div class="relative p-8 overflow-y-auto">
            <span class="block mb-2 text-xs font-bold tracking-wider text-blue-600 uppercase">{{ activePost.market_type }}</span>
            <h2 class="mb-4 text-3xl font-bold text-gray-900">{{ activePost.title }}</h2>
            <div :class="['text-gray-700 leading-relaxed text-lg', !user.has_active_subscription ? 'max-h-48 overflow-hidden relative' : '']">
              {{ activePost.content }}
              <div v-if="!user.has_active_subscription" class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-white to-transparent"></div>
            </div>
            <div v-if="!user.has_active_subscription" class="flex flex-col items-center justify-center mt-6">
              <button @click="scrollToPricing" class="px-8 py-3 font-bold text-white transition bg-blue-600 rounded-full shadow-lg hover:bg-blue-700">View Plans to Read More</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="isVerifying || isInitialLoading" class="fixed inset-0 z-50 flex flex-col items-center justify-center text-white bg-gray-900/90 backdrop-blur-sm">
        <div class="w-16 h-16 mb-4 border-t-4 border-blue-500 rounded-full animate-spin"></div>
        <h2 class="text-2xl font-bold">{{ isVerifying ? 'Verifying Payment...' : 'Loading Advisory...' }}</h2>
      </div>

      <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-white">Xavier Advisory</h1>
        <div class="flex items-center gap-4">
          
          <div class="relative">
            <button @click="showNotifications = !showNotifications" class="relative p-2 text-gray-600 transition bg-white border rounded-full shadow-sm hover:bg-gray-50">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <span v-if="unreadCount > 0" class="absolute top-0 right-0 flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-red-500 rounded-full border-2 border-white">
                {{ unreadCount }}
              </span>
            </button>

            <div v-if="showNotifications" class="absolute right-0 z-40 mt-3 overflow-hidden bg-white border border-gray-100 shadow-2xl w-80 rounded-2xl">
              <div class="flex items-center justify-between p-4 border-b border-gray-100 bg-gray-50">
                <h3 class="font-bold text-gray-800">Notifications</h3>
                <button v-if="unreadCount > 0" @click="markAllAsRead" class="text-xs font-bold text-blue-600 hover:text-blue-800">Mark all as read</button>
              </div>
              <div class="overflow-y-auto max-h-80">
                <div v-if="notifications.length === 0" class="p-6 text-sm text-center text-gray-500">
                  No new notifications.
                </div>
                <div v-for="notif in notifications" :key="notif.id" 
                     @click="handleNotificationClick(notif)"
                     :class="['p-4 border-b border-gray-50 cursor-pointer transition hover:bg-gray-50', !notif.read_at ? 'bg-blue-50/30' : '']">
                  <div class="flex items-start gap-3">
                    <div :class="['w-2 h-2 mt-2 rounded-full flex-shrink-0', !notif.read_at ? 'bg-blue-500' : 'bg-gray-300']"></div>
                    <div>
                      <h4 class="text-sm font-bold text-gray-900">{{ notif.data.title }}</h4>
                      <p class="text-xs text-gray-600 mt-0.5 line-clamp-2">{{ notif.data.message }}</p>
                      <span class="text-[10px] text-gray-400 mt-2 block">{{ new Date(notif.created_at).toLocaleDateString() }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <template v-if="user.has_active_subscription">
            <span class="flex items-center gap-2 px-4 py-1.5 text-sm font-bold text-blue-800 bg-blue-100 rounded-full shadow-sm">👑 VIP Active</span>
            <button @click="showCancelModal = true" class="text-sm font-semibold text-gray-400 underline transition hover:text-red-500">Cancel Plan</button>
          </template>
        </div>
      </div>

      <div v-if="user.has_active_subscription && !isInitialLoading" class="space-y-8 animate-fade-in">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
          
          <div class="space-y-6 md:col-span-2">
            <div class="flex gap-8 border-b border-[#1f3348]">
              <button 
                @click="activeTab = 'regular'" 
                :class="['pb-3 text-xl font-bold transition', activeTab === 'regular' ? 'text-white border-b-2 border-blue-500' : 'text-gray-500 hover:text-gray-300']"
              >
                Regular
              </button>
              <button 
                @click="activeTab = 'vip'" 
                :class="['pb-3 text-xl font-bold transition', activeTab === 'vip' ? 'text-white border-b-2 border-blue-500' : 'text-gray-500 hover:text-gray-300']"
              >
                VIP
              </button>
            </div>

            <section v-if="activeTab === 'regular'" class="p-6 border shadow-sm bg-[#0F1724] border-[#1f3348] rounded-xl animate-fade-in">
              <div v-for="post in freePosts" :key="post.id" @click="openPost(post)" class="relative pb-4 mb-4 border-b border-gray-200 cursor-pointer last:border-0 group">
                <div v-if="isPostUnread(post.id)" class="absolute left-[-16px] top-2 w-2 h-2 bg-blue-500 rounded-full"></div>
                <h3 class="text-lg font-semibold text-gray-300 transition group-hover:text-blue-600">{{ post.title }}</h3>
                <p class="mt-1 text-gray-500 line-clamp-2">
                  {{ post.content }}
                </p>
                <span class="block mt-1 text-xs font-bold text-blue-500 transition-opacity opacity-0 group-hover:opacity-100">See more...</span>
              </div>
            </section>

            <section v-if="activeTab === 'vip'" class="p-6 bg-[#0F1724] border-[#1f3348] border shadow-sm rounded-xl animate-fade-in">
              <div v-for="post in premiumPosts" :key="post.id" @click="openPost(post)" class="relative pb-4 mb-6 border-b cursor-pointer last:border-0 group">
                <div v-if="isPostUnread(post.id)" class="absolute left-[-16px] top-2 w-2 h-2 bg-blue-500 rounded-full"></div>
                <h3 class="text-xl font-bold text-gray-300 transition group-hover:text-blue-600">{{ post.title }}</h3>
                <p class="mt-1 text-gray-600 line-clamp-2">
                  {{ post.content }}
                </p>
                <span class="block mt-2 font-bold text-blue-500 transition-opacity opacity-0 group-hover:opacity-100">See more...</span>
              </div>
            </section>
          </div>

          <div class="space-y-6 md:col-span-1">
            
            <section class="p-6 text-white bg-[#0F1724] border border-[#1f3348] shadow-md rounded-2xl">
              <div>
                <div>
                  <h2 class="text-lg font-bold">What are Model Portfolios?</h2>
                  <p class="mt-1 text-sm text-blue-50 text-balance">
                    These are "Investment Blueprints" curated by our team. Instead of picking stocks one-by-one, you can <strong>Copy Trade</strong> a whole basket. Our engine automatically buys each stock in the blueprint based on your budget.
                  </p>
                </div>
              </div>
            </section>

            <section>
              <h2 class="pb-2 mb-4 text-xl font-black text-white">Model Portfolios</h2>
              <div class="grid grid-cols-1 gap-6 text-gray-600">
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
      </div>

      <div v-else-if="!isInitialLoading" class="space-y-8 animate-fade-in">
        <section>
          <h2 class="pb-2 mb-4 text-xl font-bold text-gray-800 border-b">Free Market Outlook</h2>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div v-for="post in freePosts" :key="post.id" @click="openPost(post)" class="relative p-5 transition bg-white border rounded-lg shadow-sm cursor-pointer hover:shadow-md group">
              <div v-if="isPostUnread(post.id)" class="absolute w-2 h-2 bg-blue-500 rounded-full left-2 top-6"></div>
              <span class="block mb-1 ml-2 text-xs font-bold text-gray-400 uppercase">{{ post.market_type }}</span>
              <h3 class="ml-2 text-lg font-bold text-gray-800 group-hover:text-blue-600">{{ post.title }}</h3>
              <p class="mt-2 ml-2 text-sm text-gray-600 line-clamp-3">{{ post.content }}</p>
              <p class="mt-4 ml-2 text-xs font-bold text-blue-500 opacity-0 group-hover:opacity-100">See more &rarr;</p>
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
                  <p class="my-3 text-3xl font-black text-blue-600">₦{{ Number(plan.price).toLocaleString() }}</p>
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

// Added missing state for tabs
const activeTab = ref('regular');

// Notification State
const showNotifications = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);

// Feedback State
const feedback = ref({ show: false, title: '', message: '', type: 'success' });
const showFeedback = (title, message, type = 'success') => {
  feedback.value = { show: true, title, message, type };
};

// UI State
const isInitialLoading = ref(true); 
const showSubscribeBtn = ref(false);
const activePost = ref(null);
const isVerifying = ref(false);
const showCancelModal = ref(false);
const isCancelling = ref(false);
const processingPlanId = ref(null);
const processingPortfolioId = ref(null); 

onMounted(async () => {
  await fetchAllData();
  
  setTimeout(() => { showSubscribeBtn.value = true; }, 1500);

  if (route.query.reference && route.query.plan_id) {
    handlePaymentVerification(route.query.reference, route.query.plan_id);
  }
});

// --- API Methods ---
const fetchAllData = async () => {
  isInitialLoading.value = true;
  try {
    const profileRes = await api.get('/user/profile/show');
    user.value.has_active_subscription = !!profileRes.data?.data?.has_active_subscription;

    const requests = [
      api.get('/user/advisory/plans'),
      api.get('/user/advisory/free-posts'),
      api.get('/user/notifications')
    ];

    if (user.value.has_active_subscription) {
      requests.push(api.get('/user/advisory/premium-posts'));
      requests.push(api.get('/user/advisory/model-portfolios'));
      requests.push(api.get('/user/advisory/ai-picks'));
    }

    const results = await Promise.allSettled(requests);

    if (results[0].status === 'fulfilled') plans.value = results[0].value.data.data || results[0].value.data;
    if (results[1].status === 'fulfilled') freePosts.value = results[1].value.data.data;
    
    if (results[2].status === 'fulfilled') {
      notifications.value = results[2].value.data.notifications || [];
      unreadCount.value = results[2].value.data.unread_count || 0;
    }

    if (user.value.has_active_subscription) {
      if (results[3].status === 'fulfilled') premiumPosts.value = results[3].value.data.data;
      if (results[4].status === 'fulfilled') portfolios.value = results[4].value.data.data;
      if (results[5].status === 'fulfilled') aiPicks.value = results[5].value.data.data;
    }

  } catch (e) {
    console.error("Failed to load dashboard data", e);
  } finally {
    isInitialLoading.value = false;
  }
};

// --- Post & Notification Logic ---
const isPostUnread = (postId) => {
  return notifications.value.some(n => n.data.post_id === postId && !n.read_at);
};

const openPost = (post) => {
  activePost.value = post;
  
  const relatedNotif = notifications.value.find(n => n.data.post_id === post.id && !n.read_at);
  if (relatedNotif) {
    handleNotificationClick(relatedNotif, false); 
  }
};

const closePostModal = () => {
  activePost.value = null;
};

const handleNotificationClick = async (notif, shouldOpenModal = true) => {
  showNotifications.value = false;
  
  if (shouldOpenModal) {
    const postId = notif.data.post_id;
    const post = [...freePosts.value, ...premiumPosts.value].find(p => p.id === postId);
    if (post) activePost.value = post;
  }

  if (!notif.read_at) {
    try {
      await api.post(`/user/notifications/${notif.id}/read`);
      notif.read_at = new Date().toISOString();
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (e) { console.error(e); }
  }
};

const markAllAsRead = async () => {
  try {
    await api.post('/user/notifications/read-all');
    notifications.value.forEach(n => n.read_at = n.read_at || new Date().toISOString());
    unreadCount.value = 0;
  } catch (e) { console.error(e); }
};

// --- Action Methods ---
const handlePaymentVerification = async (reference, planId) => {
  isVerifying.value = true;
  try {
    const res = await api.get(`/user/advisory/verify-payment?reference=${reference}&plan_id=${planId}`);
    if (res.data.success) {
      await fetchAllData();
      router.replace('/advisory');
    }
  } catch (error) { showFeedback('Error', 'Verification failed.', 'error'); }
  finally { isVerifying.value = false; }
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

<style scoped>
.animate-fade-in { animation: fadeIn 0.3s ease-in-out; }
.animate-fade-in-up { animation: fadeInUp 0.4s ease-out; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>