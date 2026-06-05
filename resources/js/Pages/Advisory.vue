<template>
  <MainLayout>
    <div class="min-h-screen text-slate-100 p-4 md:p-8">
      <!-- Header Section -->
      <header class="flex items-center justify-between max-w-6xl mx-auto mb-8">
        <div>
          <h1 class="text-xl font-black tracking-tight md:text-3xl uppercase">Xavier Advisory</h1>
          <p class="text-sm text-slate-400">Expert insight allocations and model portfolios</p>
        </div>

        <div class="flex items-center gap-3">
          <!-- Active Trial Countdown Badge -->
          <div v-if="user.on_trial" class="hidden sm:flex items-center gap-2 px-3 py-1.5 text-xs font-mono rounded-lg border border-amber-500/30 bg-amber-500/10 text-amber-400">
            <span>⏳</span> Trial: {{ trialCountdown }}
          </div>

          <!-- Call to action button to subscribe to premium -->
          <button 
            v-if="!user.has_active_subscription && !user.on_trial"
            @click="activeTab = 'premium'"
            class="hidden md:flex items-center gap-2 px-4 py-2 text-xs font-black tracking-widest text-white uppercase bg-indigo-600 rounded-lg hover:bg-indigo-500 transition-all shadow-lg shadow-indigo-900/20 active:scale-95"
          >
            <span>⭐</span> Get Premium
          </button>

          <!-- Notifications Bell Interface & Menu Dropdown Parent Container -->
          <div ref="notificationContainer" class="relative">
            <button 
              @click="showNotifications = !showNotifications"
              class="relative p-2.5 transition rounded-lg border border-slate-800 bg-slate-900/60 hover:bg-slate-800"
            >
              <span>🔔</span>
              <span 
                v-if="unreadCount > 0" 
                class="absolute top-1.5 right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-blue-600 text-[10px] font-bold text-white"
              >
                {{ unreadCount }}
              </span>
            </button>

            <!-- Dropdown Menu Box -->
            <div
              v-if="showNotifications" 
              class="absolute right-0 z-50 w-80 mt-2 overflow-hidden border rounded-xl border-slate-800 bg-slate-950 shadow-2xl animate-fade-in"
            >
              <div class="flex items-center justify-between p-3 border-b border-slate-800 bg-slate-900/40">
                <span class="text-xs font-bold text-slate-300">Notifications</span>
                <button 
                  v-if="unreadCount > 0"
                  @click="markAllAsRead" 
                  class="text-[11px] text-blue-400 hover:underline font-medium"
                >
                  Mark all read
                </button>
              </div>
              <div class="max-h-64 overflow-y-auto divide-y divide-slate-900">
                <div v-if="notifications.length === 0" class="py-8 text-center text-xs text-slate-500">
                  No new alerts.
                </div>
                <div 
                  v-for="notif in notifications" 
                  :key="notif.id"
                  @click="handleNotificationClick(notif)"
                  class="p-3 text-xs cursor-pointer transition-colors hover:bg-slate-900/60"
                  :class="[!notif.read_at ? 'bg-slate-900/30 border-l-2 border-blue-500' : '']"
                >
                  <p class="text-slate-200 mb-1">{{ notif.data.message || 'New publication update available' }}</p>
                  <span class="text-[10px] text-slate-500">Click to view post details</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main class="max-w-6xl mx-auto">
        <!-- Floating Transient Feedback Alert Overlay UI -->
        <div v-if="feedback.show" class="fixed top-5 right-5 z-50 p-4 rounded-xl border shadow-xl max-w-sm animate-fade-in"
          :class="[feedback.type === 'error' ? 'bg-rose-950/80 border-rose-800 text-rose-200' : 'bg-slate-900/90 border-slate-800 text-emerald-400']"
        >
          <h4 class="text-xs font-black uppercase tracking-wider mb-0.5">{{ feedback.title }}</h4>
          <p class="text-xs text-slate-300 leading-normal">{{ feedback.message }}</p>
        </div>

        <!-- Dynamic Verification Banner context state node -->
        <EmailVerificationPrompt v-if="showPrompt" @close="showPrompt = false"/>

        <!-- Loading State using Skeleton Loader Component -->
        <div v-if="isInitialLoading" class="max-w-6xl mx-auto">
          <SkeletonLoader />
        </div>

        <div v-else class="space-y-8 animate-fade-in-up">
          <!-- Navigation Menu Tabs -->
          <div class="flex gap-6 text-sm font-bold border-b border-slate-800">
            <button 
              @click="activeTab = 'regular'"
              class="pb-3 transition-colors relative"
              :class="[activeTab === 'regular' ? 'text-blue-400' : 'text-slate-400 hover:text-slate-200']"
            >
              Regular
              <span v-if="activeTab === 'regular'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-500"></span>
            </button>
            <button 
              @click="activeTab = 'premium'"
              class="pb-3 transition-colors relative flex items-center gap-1.5"
              :class="[activeTab === 'premium' ? 'text-indigo-400' : 'text-slate-400 hover:text-slate-200']"
            >
              Premium
              <span v-if="activeTab === 'premium'" class="absolute bottom-0 left-0 w-full h-0.5 bg-indigo-500"></span>
            </button>
          </div>

          <!--  REGULAR TAB VIEW -->
          <div v-if="activeTab === 'regular'" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-4">
              <div v-if="regularPosts.length === 0" class="p-8 text-center border border-dashed rounded-xl border-slate-800 text-slate-500">
                Nothing to see yet.
              </div>
              <AdvisoryCard 
                v-for="post in regularPosts" 
                :key="post.id" 
                :post="post" 
                :is-unread="isPostUnread(post.id)" 
                @select="openPost"
                @unlock-tier="activeTab = 'premium'"
              />
            </div>

            <!-- Subscription Promotion Sidebar Panel -->
            <div v-if="canStartRegularTrial" class="p-6 h-fit border rounded-xl border-blue-900/30 bg-gradient-to-b from-blue-950/20 to-transparent space-y-4">
              <div>
                <h3 class="text-base font-bold text-blue-400 mb-2">Unlock Premium Access</h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                  Get access to structural model portfolios, real-time buy/sell tickers, and AI predictive model signals.
                </p>
              </div>

              <!-- Quick Start Trial Action trigger context -->
              <div class="p-4 rounded-xl bg-slate-900/50 border border-slate-800 text-center">
                <p class="text-xs text-slate-300 mb-3">Want to sample indicators first?</p>
                <button 
                  @click="startTrial('premium')"
                  :disabled="isActivatingTrial"
                  class="w-full py-2 text-xs font-bold text-slate-950 bg-amber-400 hover:bg-amber-300 transition-colors rounded-lg disabled:opacity-50"
                >
                  {{ isActivatingTrial ? 'Activating trial access...' : `Start ${trialDays} Days Free Trial` }}
                </button>
              </div>

              <div v-if="plans.length > 0" class="space-y-3">
                <div 
                  v-for="plan in plans" 
                  :key="plan.id"
                  class="p-4 border rounded-lg bg-slate-950/60 border-slate-800 flex items-center justify-between"
                >
                  <div>
                    <h4 class="text-xs font-bold text-slate-200">{{ plan.name }}</h4>
                    <p class="text-sm font-black text-white mt-1">₦{{ Number(plan.price).toLocaleString() }}<span class="text-[10px] text-slate-500 font-normal">/mo</span></p>
                  </div>
                  <button 
                    @click="subscribe(plan.id)" 
                    :disabled="processingPlanId === plan.id"
                    class="px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-500 rounded-md transition disabled:opacity-50"
                  >
                    {{ processingPlanId === plan.id ? 'Connecting...' : 'Upgrade' }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- PREMIUM TAB VIEW -->
          <div v-else-if="activeTab === 'premium'">
            <!-- Access Guard Check -->
            <div v-if="!user.has_active_subscription && !user.on_trial" class="max-w-md mx-auto py-12 text-center">
              <span class="text-4xl mb-4 block">🔒</span>
              <h2 class="text-lg font-bold text-white mb-2">Premium Subscription Required</h2>
              <p class="text-sm text-slate-400 mb-4">Premium access unlocks active models, quantitative portfolio nodes, and internal indicators.</p>
              
              <!-- Quick CTA Link to skip straight down to the billing node models below -->
              <button 
                @click="plans.length > 0 && subscribe(plans[0].id)"
                class="inline-flex items-center gap-2 mb-8 px-5 py-2 text-xs font-black tracking-widest text-slate-950 uppercase bg-gradient-to-r from-blue-400 to-blue-300 rounded-lg shadow-xl shadow-blue-500/10 hover:brightness-110 active:scale-95 transition-all"
              >
                Choose Plan & Unlock Access
              </button>
              
              <div class="grid gap-4">
                <div 
                  v-for="plan in plans" 
                  :key="plan.id"
                  class="p-5 border rounded-xl bg-slate-900/40 border-slate-800 text-left"
                >
                  <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-white">{{ plan.name }}</h3>
                    <span class="text-lg font-black text-indigo-400">₦{{ Number(plan.price).toLocaleString() }}</span>
                  </div>
                  <ul class="text-xs text-slate-400 space-y-1.5 mb-4">
                    <li v-for="(feature, idx) in getFeaturesList(plan.features)" :key="idx" class="flex items-center gap-1.5">
                      <span class="text-emerald-500">✓</span> {{ feature }}
                    </li>
                  </ul>
                  <div class="grid gap-2">
                    <button 
                      @click="subscribe(plan.id)" 
                      :disabled="processingPlanId === plan.id"
                      class="w-full py-2 text-xs font-black tracking-wider text-center uppercase text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg transition disabled:opacity-50"
                    >
                      {{ processingPlanId === plan.id ? 'Processing Gateway...' : 'Purchase Node Access' }}
                    </button>
                    <button 
                      v-if="canStartRegularTrial"
                      @click="startTrial('premium')"
                      :disabled="isActivatingTrial"
                      class="w-full py-2 text-xs font-bold text-center text-amber-400 border border-amber-500/30 hover:bg-amber-500/10 rounded-lg transition"
                    >
                      Test Drive Access Node Free
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Unlocked Content Frame Layout -->
            <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-3">
              <!-- Left Side Feed: Posts Stack -->
              <div class="lg:col-span-2 space-y-4">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Quantitative Signals</h2>
                <div v-if="premiumPosts.length === 0" class="p-8 text-center border border-dashed rounded-xl border-slate-800 text-slate-500">
                  No active premium publications logged.
                </div>
                <AdvisoryCard 
                  v-for="post in premiumPosts" 
                  :key="post.id" 
                  :post="post" 
                  :is-premium="true" 
                  :is-unread="isPostUnread(post.id)" 
                  @select="openPost"
                  @unlock-tier="activeTab = 'premium'"
                />
              </div>

              <!-- Right Side Panel: AI Tickers & Portfolio Engine -->
              <div class="space-y-6">
                <!-- AI Engine Metrics -->
                <div class="p-5 border rounded-xl border-indigo-950/40 bg-gradient-to-b from-indigo-950/10 to-transparent">
                  <h3 class="text-xs font-black uppercase tracking-wider text-indigo-400 mb-3">Neural Model Allocations</h3>
                  <div v-if="aiPicks.length === 0" class="text-xs text-slate-500 py-2">Recalibrating parameters...</div>
                  <div class="divide-y divide-slate-900">
                    <div v-for="pick in aiPicks" :key="pick.id" class="py-2.5 flex items-center justify-between text-xs">
                      <span class="font-bold text-slate-200">{{ pick.asset }}</span>
                      <span class="px-2 py-0.5 rounded font-bold text-[10px]" :class="[pick.action === 'BUY' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400']">
                        {{ pick.action }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Model Portfolios Engine -->
                <div class="space-y-4">
                  <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Active Model Portfolios</h3>
                  <div v-if="portfolios.length === 0" class="text-xs text-slate-500 py-2">No profiles active.</div>
                  <div 
                    v-for="portfolio in portfolios" 
                    :key="portfolio.id"
                    class="p-4 border rounded-xl border-slate-800 bg-slate-950/40 space-y-3"
                  >
                    <div class="flex items-start justify-between">
                      <div>
                        <h4 class="text-sm font-bold text-white">{{ portfolio.name }}</h4>
                        <p class="text-xs text-slate-400 mt-0.5">{{ portfolio.description }}</p>
                      </div>
                      <span class="text-xs font-black text-emerald-400 text-nowrap ml-2">{{ portfolio.expected_return }} ROI</span>
                    </div>
                    
                    <!-- Form Processing Input Element -->
                    <div class="flex gap-2 pt-1">
                      <div class="relative flex-1">
                        <span class="absolute left-3 top-2 text-xs font-bold text-slate-500">₦</span>
                        <input 
                          type="number" 
                          v-model="copyAmounts[portfolio.id]"
                          placeholder="5,000 min"
                          class="w-full pl-6 pr-3 py-1.5 text-xs rounded-md bg-slate-900 border border-slate-800 text-white focus:outline-none focus:border-slate-700"
                        />
                      </div>
                      <button 
                        @click="copyPortfolio(portfolio.id)"
                        :disabled="processingPortfolioId === portfolio.id"
                        class="px-4 py-1.5 text-xs font-bold text-slate-950 bg-emerald-400 hover:bg-emerald-300 rounded-md transition disabled:opacity-50 text-nowrap"
                      >
                        {{ processingPortfolioId === portfolio.id ? 'Syncing...' : 'Copy Model' }}
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Cancellation Area Context -->
                <div v-if="user.has_active_subscription" class="pt-4 border-t border-slate-900">
                  <button 
                    @click="showCancelModal = true"
                    class="text-xs text-rose-500/70 hover:text-rose-400 hover:underline"
                  >
                    Cancel subscription node
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>

     
      <div v-if="activePost" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
        <div class="w-full max-w-xl p-6 border rounded-xl bg-slate-950 border-slate-800 space-y-4 shadow-2xl">
          <div class="flex items-start justify-between">
            <h2 class="text-lg font-bold text-white">{{ activePost.title }}</h2>
            <button @click="closePostModal" class="p-1 text-slate-400 hover:text-white text-sm">✕</button>
          </div>
          <div class="text-xs text-slate-500 flex gap-3 items-center">
            <span v-if="activePost.recommendation" class="font-bold text-blue-400 uppercase tracking-wider">
              Action Trigger: {{ activePost.recommendation }}
            </span>
            <span>•</span>
            <span>Logged: {{ new Date(activePost.created_at || Date.now()).toLocaleDateString() }}</span>
          </div>
          <p class="text-sm leading-relaxed text-slate-300 whitespace-pre-wrap py-2 border-t border-b border-slate-900">
            {{ activePost.content || activePost.body }}
          </p>
          <div class="text-right">
            <button @click="closePostModal" class="px-4 py-1.5 text-xs font-bold rounded-md bg-slate-800 text-slate-200 hover:bg-slate-700">
              Close
            </button>
          </div>
        </div>
      </div>

      <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in">
        <div class="w-full max-w-sm p-6 border rounded-xl bg-slate-950 border-slate-800 space-y-4">
          <h3 class="text-base font-bold text-white">Confirm Cancellation Request</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Terminating access drops connection to real-time copy instances and data pipeline updates.
          </p>
          <div class="flex justify-end gap-3 pt-2">
            <button 
              @click="showCancelModal = false" 
              :disabled="isCancelling"
              class="px-3 py-1.5 text-xs font-bold text-slate-300 bg-slate-900 hover:bg-slate-800 rounded-md"
            >
              Go Back
            </button>
            <button 
              @click="confirmCancelSubscription" 
              :disabled="isCancelling"
              class="px-3 py-1.5 text-xs font-bold text-white bg-rose-600 hover:bg-rose-500 rounded-md disabled:opacity-50"
            >
              {{ isCancelling ? 'Processing...' : 'Confirm Termination' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>
<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';
import AdvisoryCard from '@/Components/AdvisoryCard.vue';
import EmailVerificationPrompt from '@/Components/EmailVerificationPrompt.vue';
import SkeletonLoader from "@/Components/SkeletonLoader.vue";

const route = useRoute();
const router = useRouter();

// --- STATE MANAGEMENT ---
const user = ref({
  has_active_subscription: false,
  on_trial: false,
  trial_expires_at: null,
  trading_mode: 'live',
  email_verified_at: null,
});
const isDemo = computed(() => user.value.trading_mode === 'demo');
const plans = ref([]);
const regularPosts = ref([]);
const premiumPosts = ref([]);
const portfolios = ref([]);
const aiPicks = ref([]);
const copyAmounts = ref({});
const activeTab = ref('regular');
const showNotifications = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);
const feedback = ref({ show: false, title: '', message: '', type: 'success' });
const isInitialLoading = ref(true);
const activePost = ref(null);
const isVerifying = ref(false);
const showCancelModal = ref(false);
const isCancelling = ref(false);
const processingPlanId = ref(null);
const processingPortfolioId = ref(null);
const isActivatingTrial = ref(false);
const trialDays = ref(7);

const notificationContainer = ref(null);
const showPrompt = ref(false);

const isAdminUser = (u) => {
  if (!u) return false;
  const role = (u.role || '').toString().toLowerCase();
  return role.includes('admin');
};

const isUserVerified = computed(() => {
  const u = user.value || {};
  return Boolean(u.email_verified_at) || isAdminUser(u);
});

// --- TRIAL ACTIVATION ENGINE ---
const startTrial = async (tier = 'regular') => {
  if (!isUserVerified.value && !isDemo.value) return (showPrompt.value = true);

  const apiTier = tier === 'vip' ? 'premium' : tier;

  if (apiTier === 'premium' && user.value.on_trial) {
    if (!confirm("Upgrading to PREMIUM trial will replace your current regular trial. Proceed?")) return;
  }
  isActivatingTrial.value = true;
  try {
    const res = await api.post('/user/advisory/activate-trial', { tier: apiTier });

    if (res.data.success) {
      showFeedback(
        'Trial Started!',
        `You now have ${trialDays.value} days of ${tier.toUpperCase()} tier access.`,
        'success'
      );
      user.value.on_trial = true;
      await fetchAllData();
      activeTab.value = 'premium';
    }
  } catch (error) {
    showFeedback('Error', error.response?.data?.message || 'Could not start trial.', 'error');
  } 
};

// --- REAL-TIME COUNTDOWN TICKER ---
const trialCountdown = ref('00:00:00');
let timerInterval = null;

const updateCountdown = () => {
  if (!user.value.trial_expires_at) return;

  const end = new Date(user.value.trial_expires_at).getTime();
  const now = new Date().getTime();
  const diff = end - now;

  if (diff <= 0) {
    trialCountdown.value = 'Expired';
    if (user.value.on_trial) {
      user.value.on_trial = false;
      fetchAllData();
    }
    clearInterval(timerInterval);
    return;
  }

  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((diff % (1000 * 60)) / 1000);

  const dayStr = days > 0 ? `${days}d ` : '';
  trialCountdown.value = `${dayStr}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

const showFeedback = (title, message, type = 'success') => {
  feedback.value = { show: true, title, message, type };
  setTimeout(() => {
    feedback.value.show = false;
  }, 4000);
};

// --- DATA SYNCHRONIZATION ---
const fetchAllData = async () => {
  isInitialLoading.value = true;
  try {
    const profileRes = await api.get('/user/profile/show');
    const userData = profileRes.data?.data;

    user.value = {
      has_active_subscription: !!userData?.has_active_subscription,
      on_trial: !!userData?.on_trial,
      trial_expires_at: userData?.trial_expires_at,
      trading_mode: userData?.trading_mode,
      email_verified_at: userData?.email_verified_at,
    };

    updateCountdown();

    const [plansRes, notificationsRes, rPostsRes] = await Promise.all([
      api.get('/user/advisory/plans'),
      api.get('/user/notifications'),
      api.get('/user/advisory/regular-posts').catch(() => ({ data: { data: [] } }))
    ]);

    plans.value = plansRes.data.data || [];
    if (plansRes.data.trial_settings) {
      trialDays.value = plansRes.data.trial_settings.days;
    }
    notifications.value = notificationsRes.data.notifications || [];
    unreadCount.value = notificationsRes.data.unread_count || 0;
    regularPosts.value = rPostsRes.data.data || [];

    if (user.value.has_active_subscription || user.value.on_trial) {
      const premiumResults = await Promise.allSettled([
        api.get('/user/advisory/premium-posts').catch(() => null),
        api.get('/user/advisory/model-portfolios').catch(() => null),
        api.get('/user/advisory/ai-picks').catch(() => null)
      ]);

      if (premiumResults[0]?.value) premiumPosts.value = premiumResults[0].value.data.data;
      if (premiumResults[1]?.value) portfolios.value = premiumResults[1].value.data.data;
      if (premiumResults[2]?.value) aiPicks.value = premiumResults[2].value.data.data;
    }
  } catch (e) {
    console.warn("Silent fetch tracking:", e.message);
  } finally {
    isInitialLoading.value = false;
  }
};

// --- NOTIFICATION HANDLERS ---
const isPostUnread = (postId) => notifications.value.some(n => n.data.post_id === postId && !n.read_at);

const openPost = (post) => {
  activePost.value = post;
  const relatedNotif = notifications.value.find(n => n.data.post_id === post.id && !n.read_at);
  if (relatedNotif) handleNotificationClick(relatedNotif, false);
};

const closePostModal = () => { activePost.value = null; };

const handleNotificationClick = async (notif, shouldOpenModal = true) => {
  showNotifications.value = false;
  if (shouldOpenModal) {
    const post = [...regularPosts.value, ...premiumPosts.value].find(p => p.id === notif.data.post_id);
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

const getFeaturesList = (features) => {
  if (!features) return [];
  return features.split(',').map(item => item.trim());
};

const handlePaymentVerification = async (reference, planId) => {
  isVerifying.value = true;
  try {
    const res = await api.get(`/user/advisory/verify-payment?reference=${reference}&plan_id=${planId}`);
    if (res.data.success) {
      await fetchAllData();
      router.replace('/advisory');
    }
  } catch (error) {
    showFeedback('Error', 'Verification failed.', 'error');
  } finally {
    isVerifying.value = false;
  }
};

const subscribe = async (planId) => {
  if (!isUserVerified.value && !isDemo.value) return (showPrompt.value = true);

  processingPlanId.value = planId;
  try {
    const res = await api.post('/user/advisory/subscribe', { plan_id: planId });
    window.location.href = res.data.data.authorization_url;
  } catch (error) {
    showFeedback('Error', 'Payment Initialization failed.', 'error');
  } finally {
    processingPlanId.value = null;
  }
};

const confirmCancelSubscription = async () => {
  isCancelling.value = true;
  try {
    await api.post('/user/advisory/cancel');
    
    user.value.has_active_subscription = false;
    showCancelModal.value = false;
    showFeedback('Cancelled', 'Your subscription was removed successfully.');
    
    setTimeout(() => {
      window.location.reload();
    }, 1200);
  } catch (error) {
    showFeedback('Error', 'Cancellation request failed.', 'error');
  } finally {
    isCancelling.value = false;
  }
};

const copyPortfolio = async (portfolioId) => {
  if (!isUserVerified.value && !isDemo.value) return (showPrompt.value = true);

  const amount = copyAmounts.value[portfolioId];
  if (!amount || amount < 5000) return showFeedback('Budget Too Low', 'Minimum amount is ₦5,000.', 'error');
  processingPortfolioId.value = portfolioId;
  try {
    await api.post(`/user/advisory/model-portfolios/${portfolioId}/copy`, { amount });
    router.push('/orders');
  } catch (error) {
    showFeedback('Error', error.response?.data?.message || 'Copy execution failed.', 'error');
    processingPortfolioId.value = null;
  }
};

const canStartRegularTrial = computed(() => {
  return !user.value.has_active_subscription && !user.value.on_trial;
});

const handleClickOutside = (event) => {
  if (notificationContainer.value && !notificationContainer.value.contains(event.target)) {
    showNotifications.value = false;
  }
};

const handleEscape = (e) => {
  if (e.key === 'Escape' && showNotifications.value) {
    showNotifications.value = false;
  }
};

onMounted(async () => {
  await fetchAllData();
  if (route.query.reference && route.query.plan_id) {
    handlePaymentVerification(route.query.reference, route.query.plan_id);
  }
  timerInterval = setInterval(updateCountdown, 1000);
  document.addEventListener('mousedown', handleClickOutside);
  document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval);
  document.removeEventListener('mousedown', handleClickOutside);
  document.removeEventListener('keydown', handleEscape);
});
</script>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.2s ease-in-out forwards;
}
.animate-fade-in-up {
  animation: fadeInUp 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>