<template>
  <MainLayout>
    <div class="relative p-6 mx-auto max-w-7xl">

      <div v-if="feedback.show"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
        <div class="w-full max-w-sm p-6 text-center bg-white shadow-2xl rounded-2xl">
          <div
            :class="['flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full', feedback.type === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600']">
            <svg v-if="feedback.type === 'success'" xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none"
              viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h3 class="mb-2 text-xl font-bold text-gray-900">{{ feedback.title }}</h3>
          <p class="mb-6 text-gray-600">{{ feedback.message }}</p>
          <button @click="feedback.show = false"
            class="w-full py-3 font-bold text-white transition bg-gray-900 rounded-xl hover:bg-gray-800">Got it</button>
        </div>
      </div>

      <div v-if="showCancelModal"
        class="fixed inset-0 z-[90] flex items-center justify-center p-4 bg-gray-900/80 backdrop-blur-sm">
        <div class="w-full max-w-md p-6 text-center bg-white shadow-2xl rounded-2xl">
          <h3 class="mb-2 text-2xl font-bold text-gray-900">Cancel Subscription?</h3>
          <p class="mb-8 text-gray-600">Are you sure you want to cancel?</p>
          <div class="flex justify-center gap-3">
            <button @click="showCancelModal = false"
              class="px-6 py-2.5 font-bold text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">No, Don't
              Cancel</button>
            <button @click="confirmCancelSubscription" :disabled="isCancelling"
              class="px-6 py-2.5 font-bold text-white bg-red-600 rounded-lg hover:bg-red-700 transition disabled:opacity-50 flex justify-center w-40">
              <span v-if="isCancelling">Canceling...</span>
              <span v-else>Yes, Cancel Plan</span>
            </button>
          </div>
        </div>
      </div>

      <div v-if="activePost"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div
          class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col relative">
          <button @click="closePostModal"
            class="absolute z-10 p-1 text-gray-400 bg-white rounded-full top-4 right-4 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <div class="relative p-8 overflow-y-auto">
            <span class="block mb-2 text-xs font-bold tracking-wider text-blue-600 uppercase">{{ activePost.market_type
            }}</span>
            <h2 class="mb-4 text-3xl font-bold text-gray-900">{{ activePost.title }}</h2>
            <div
              :class="['text-gray-700 leading-relaxed text-lg', !(user.has_active_subscription || user.on_trial) ? 'max-h-48 overflow-hidden relative' : '']">
              {{ activePost.content }}
              <div v-if="!(user.has_active_subscription || user.on_trial)"
                class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-white to-transparent"></div>
            </div>
            <div v-if="!(user.has_active_subscription || user.on_trial)"
              class="flex flex-col items-center justify-center mt-6">
              <button @click="showPricingModal = true"
                class="px-8 py-3 font-bold text-white transition bg-blue-600 rounded-full shadow-lg hover:bg-blue-700">
                View Plans to Read More
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showPricingModal"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-gray-950/90 backdrop-blur-md">
        <div
          class="bg-[#0F1724] border border-[#1f3348] rounded-3xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden flex flex-col relative animate-fade-in-up">
          <button @click="showPricingModal = false"
            class="absolute p-2 text-gray-400 transition top-6 right-6 hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24"
              stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <div class="p-8 overflow-y-auto md:p-12 w-full">
            <div class="mb-10 text-center">
              <h2 class="mb-2 text-4xl font-black text-white">Choose Your Plan</h2>
              <p class="text-gray-400">Secure your spot for long-term growth and premium insights.</p>
            </div>
            <div class="flex flex-col lg:flex-row gap-6">
              <div v-for="plan in plans" :key="plan.id"
                class="w-full bg-[#111827] p-8 rounded-3xl border border-blue-500 transition-all group flex flex-col">
                <h3 class="text-xl font-bold text-white">{{ plan.name }}</h3>
                <p class="my-4 text-4xl font-black text-blue-500">₦{{ Number(plan.price).toLocaleString() }}</p>
                <p class="mb-2 text-blue-400 italic">Duration: {{plan.duration_days}} days</p>
                <ul class="mb-8 space-y-4 text-sm text-gray-400">
                  <li v-for="feature in getFeaturesList(plan.features)" :key="feature" class="flex items-center gap-3">
                    <span
                      class="flex items-center justify-center w-5 h-5 text-blue-500 bg-blue-500/10 rounded-full text-[10px]">
                      ✓
                    </span>
                    {{ feature }}
                  </li>
                </ul>
                <button @click="subscribe(plan.id)" :disabled="processingPlanId === plan.id"
                  class="w-full py-4 mt-auto font-bold text-white transition bg-blue-600 rounded-2xl hover:bg-blue-700 shadow-lg shadow-blue-900/20">
                  {{ processingPlanId === plan.id ? 'Processing...' : 'Subscribe Now' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <h1 class="text-3xl font-bold text-white">Xavier Advisory</h1>
        <div class="flex items-center gap-4">
          <div class="relative" ref="notificationContainer">
            <button @click="showNotifications = !showNotifications"
              class="relative p-2 text-gray-600 transition bg-white border rounded-full shadow-sm hover:bg-gray-50">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <span v-if="unreadCount > 0"
                class="absolute top-0 right-0 flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-red-500 rounded-full border-2 border-white">{{
                  unreadCount }}</span>
            </button>

            <div v-if="showNotifications"
              class="fixed right-4 left-4 top-20 sm:absolute sm:right-0 sm:left-auto sm:top-full sm:w-80 mt-2 origin-top-right bg-white rounded-xl shadow-2xl z-50 border border-gray-200 animate-fade-in-up text-left">
              <div class="p-3 flex justify-between items-center border-b">
                <h3 class="font-bold text-gray-800">Notifications</h3>
                <button v-if="unreadCount > 0" @click="markAllAsRead"
                  class="text-xs text-blue-600 font-semibold hover:underline">Mark all as read</button>
              </div>
              <div class="max-h-96 overflow-y-auto">
                <div v-if="notifications.length === 0" class="text-center text-gray-500 py-12">
                  <p class="text-sm">You have no notifications.</p>
                </div>
                <div v-else>
                  <a v-for="notif in notifications" :key="notif.id" @click.prevent="handleNotificationClick(notif)"
                    href="#"
                    :class="['block p-4 border-b border-gray-100 hover:bg-gray-50', !notif.read_at ? 'bg-blue-50' : '']">
                    <div class="flex items-start gap-3">
                      <div
                        :class="['w-2 h-2 rounded-full mt-1.5 flex-shrink-0', !notif.read_at ? 'bg-blue-500' : 'bg-gray-300']">
                      </div>
                      <div class="flex-1">
                        <p class="text-sm text-gray-700 leading-tight">{{ notif.data.message }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ new Date(notif.created_at).toLocaleString() }}</p>
                      </div>
                    </div>
                  </a>
                </div>
              </div>
            </div>
          </div>
          <template v-if="user.has_active_subscription">
            <button @click="showCancelModal = true"
              class="text-sm font-semibold text-gray-400 underline hover:text-red-500">Cancel Plan</button>
          </template>
          <template v-if="user.on_trial">
            <div class="flex flex-col items-end">
              <span class="text-[10px] font-bold text-amber-500 uppercase tracking-widest">Trial Ends In</span>
              <span class="font-mono text-lg font-bold text-white">{{ trialCountdown }}</span>
            </div>
          </template>
        </div>
      </div>

      <div class="relative min-h-[400px]">
        <div v-if="isInitialLoading || isVerifying"
          class="absolute inset-0 z-10 flex flex-col items-center justify-center text-white bg-gray-900/50 backdrop-blur-sm rounded-xl">
          <div class="w-16 h-16 mb-4 border-t-4 border-blue-500 rounded-full animate-spin"></div>
          <h2 class="text-2xl font-bold">{{ isVerifying ? 'Verifying Payment...' : 'Loading Advisory...' }}</h2>
        </div>

        <div v-else>
          <div v-if="(user.has_active_subscription || user.on_trial)" class="space-y-8 animate-fade-in">
            <div class="flex flex-col gap-6">
              <div class="transition-all duration-300 space-y-6">
                <div class="flex gap-8 border-b border-[#1f3348]">
                  <button @click="activeTab = 'regular'"
                    :class="['pb-3 text-xl font-bold transition-all', activeTab === 'regular' ? 'text-white border-b-2 border-blue-500' : 'text-gray-500 hover:text-gray-300']">Regular</button>
                  <button @click="activeTab = 'premium'"
                    :class="['pb-3 text-xl font-bold transition-all', activeTab === 'premium' ? 'text-white border-b-2 border-blue-500' : 'text-gray-500 hover:text-gray-300']">Premium</button>
                </div>

                <div class="min-h-[200px]">
                  <div v-if="activeTab === 'regular'"
                    class="p-6 border bg-[#0F1724] border-[#1f3348] rounded-xl space-y-4">
                    <div v-if="regularPosts.length === 0" class="text-center text-gray-500 py-10">
                      No regular posts available.
                    </div>
                    <div v-for="post in regularPosts" :key="post.id" @click="openPost(post)"
                      class="p-4 transition border border-gray-800 rounded-lg cursor-pointer hover:bg-gray-800/50">
                      <h3 class="font-bold text-white">{{ post.title }}</h3>
                      <p class="text-sm text-gray-400 line-clamp-2">{{ post.content }}</p>
                    </div>
                  </div>

                  <div v-if="activeTab === 'premium'">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                      <div class="lg:col-span-2 space-y-6">
                        <div class="p-6 border bg-[#0F1724] border-[#1f3348] rounded-xl">
                          <h3 class="text-2xl font-bold text-white mb-6">Premium Posts</h3>
                          <div v-if="premiumPosts.length === 0" class="text-gray-500 text-center py-8">
                            No premium insights posted recently.
                          </div>
                          <div v-else class="space-y-4">
                            <div v-for="post in premiumPosts" :key="post.id" @click="openPost(post)"
                              class="p-4 transition border border-blue-900/30 rounded-lg cursor-pointer hover:bg-blue-900/10">
                              <div class="flex items-center gap-2 mb-1">
                                <span
                                  class="text-[10px] bg-blue-600 text-white px-2 py-0.5 rounded font-black uppercase">Premium</span>
                                <h3 class="font-bold text-white">{{ post.title }}</h3>
                              </div>
                              <p class="text-sm text-gray-400 line-clamp-2">{{ post.content }}</p>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="space-y-6">
                        <div v-if="portfolios.length > 0">
                          <section
                            class="p-6 text-white bg-[#0F1724] border border-[#1f3348] shadow-md rounded-2xl mb-4">
                            <div>
                              <h2 class="text-lg font-bold">What are Model Portfolios?</h2>
                              <p class="mt-1 text-sm text-blue-50 text-balance">
                                These are "Investment Blueprints" curated by our team. Instead of picking stocks
                                one-by-one,
                                you can <strong>Copy Trade</strong> a whole basket. Our engine automatically buys each
                                stock
                                in the blueprint based on your budget.
                              </p>
                            </div>
                          </section>

                          <section>
                            <div class="grid grid-cols-1 gap-6 text-gray-600">
                              <div v-for="portfolio in portfolios" :key="portfolio.id"
                                class="relative flex flex-col p-6 transition bg-white border border-gray-100 shadow-sm rounded-2xl hover:shadow-lg">
                                <h3 class="mb-1 text-xl font-black text-gray-900">{{ portfolio.name }}</h3>
                                <p class="mb-4 text-xs font-bold tracking-widest text-gray-400 uppercase">{{
                                  portfolio.risk_profile }} Strategy</p>
                                <div class="mb-6 space-y-3">
                                  <div v-for="stock in portfolio.stocks" :key="stock.id" class="space-y-1">
                                    <div class="flex justify-between text-xs font-bold text-gray-700">
                                      <span>{{ stock.symbol }}</span><span>{{ stock.allocation_percentage }}%</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                      <div class="h-full bg-blue-500"
                                        :style="{ width: stock.allocation_percentage + '%' }">
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                <div class="flex items-center gap-2 pt-4 mt-auto border-t">
                                  <input type="number" v-model="copyAmounts[portfolio.id]" placeholder="Budget (₦)"
                                    class="w-full px-4 py-2 text-sm border rounded-lg outline-none focus:border-blue-500" />
                                  <button @click="copyPortfolio(portfolio.id)"
                                    :disabled="processingPortfolioId === portfolio.id"
                                    class="px-5 py-2 text-sm font-bold text-white bg-gray-900 rounded-lg hover:bg-blue-600 disabled:opacity-50">
                                    <span v-if="processingPortfolioId === portfolio.id">Copying...</span>
                                    <span v-else>Copy</span>
                                  </button>
                                </div>
                              </div>
                            </div>
                          </section>
                        </div>

                        <div v-if="aiPicks.length > 0" class="p-6 border bg-[#0F1724] border-[#1f3348] rounded-xl">
                          <section class="p-6 text-white bg-gray-900 shadow-2xl rounded-2xl">
                            <h2 class="pb-4 mb-4 text-xl font-black border-b border-gray-800">🤖 AI Top Picks</h2>
                            <ul class="space-y-3">
                              <li v-for="pick in aiPicks" :key="pick.symbol"
                                class="flex items-center justify-between p-4 bg-gray-800 rounded-xl">
                                <span class="text-xl font-black">{{ pick.symbol }}</span>
                                <span class="px-2 py-1 text-xs font-black text-green-400 rounded-md bg-green-400/10">{{
                                  pick.confidence }}%</span>
                              </li>
                            </ul>
                          </section>
                        </div>


                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="max-w-6xl py-12 mx-auto animate-fade-in">
            <div class="mb-12 text-center">
              <h2 class="mb-4 text-4xl font-black text-white">Unlock Xavier Advisory</h2>
              <p class="text-gray-400">Choose how you want to access our premium market insights and AI picks.</p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
              <div
                class="bg-[#111827] border-2 border-gray-700/50 p-8 rounded-3xl text-center flex flex-col hover:border-blue-500/50 transition-all">
                <div
                  class="flex items-center justify-center w-16 h-16 mx-auto mb-6 text-gray-400 rounded-2xl bg-gray-500/10">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13 10V3L4 14h7v7l9-11h-7z" />
                  </svg>
                </div>
                <h3 class="mb-2 text-2xl font-bold text-white">Regular Trial Access</h3>
                <p class="mb-8 text-sm text-gray-400">Explore the fundamentals with a free {{ trialDays }}-day regular
                  trial.
                </p>
                <div v-if="!user.has_active_subscription && !user.on_trial">
                  <button v-if="!user.has_used_regular" @click="startTrial('regular')" :disabled="isActivatingTrial"
                    class="w-full py-4 mt-auto font-bold text-gray-900 bg-white rounded-xl hover:bg-gray-100 transition shadow-lg disabled:opacity-50">
                    <span v-if="isActivatingTrial">Starting...</span>
                    <span v-else>Start Regular Trial</span>
                  </button>
                  <button v-else disabled
                    class="w-full py-4 mt-auto font-bold text-gray-500 bg-gray-800 cursor-not-allowed rounded-xl">
                    Regular Trial Used
                  </button>
                </div>
              </div>

              <div
                class="bg-[#111827] border-2 border-amber-500/20 p-8 rounded-3xl text-center flex flex-col hover:border-amber-500/50 transition-all">
                <div
                  class="flex items-center justify-center w-16 h-16 mx-auto mb-6 text-amber-500 rounded-2xl bg-amber-500/10">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                  </svg>
                </div>
                <h3 class="mb-2 text-2xl font-bold text-white">Premium Trial Access</h3>
                <p class="mb-8 text-sm text-gray-400">Get a taste of premium picks and market insights for {{ trialDays
                }} days.
                </p>
                <button @click="startTrial('premium')" :disabled="isActivatingTrial || user.has_used_premium"
                  class="w-full py-4 mt-auto font-bold text-white bg-amber-600 rounded-xl hover:bg-amber-500 disabled:opacity-50 transition-colors">
                  <span v-if="isActivatingTrial">Starting...</span>
                  <span v-else-if="user.has_used_premium">Premium Trial Used</span>
                  <span v-else>Start Premium Trial</span></button>
              </div>

              <div
                class="bg-[#111827] border-2 border-blue-500/20 p-8 rounded-3xl text-center flex flex-col hover:border-blue-500/50 relative transition-all">
                <span
                  class="absolute -top-3 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-[10px] font-black px-4 py-1 rounded-full uppercase tracking-widest shadow-lg shadow-blue-900/40">Recommended</span>
                <div
                  class="flex items-center justify-center w-16 h-16 mx-auto mb-6 text-blue-500 rounded-2xl bg-blue-500/10">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                </div>
                <h3 class="mb-2 text-2xl font-bold text-white">Want to skip trials? <br> Subscribe now!</h3>
                <p class="mb-8 text-sm text-gray-400">Skip the trial and secure your spot for long-term growth.</p>
                <button @click="showPricingModal = true"
                  class="w-full py-4 mt-auto font-bold text-white transition bg-blue-600 rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-500/20">
                  View Subscription Details
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>


<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';

const route = useRoute();
const router = useRouter();

// --- STATE ---
const user = ref({
  has_active_subscription: false,
  on_trial: false,
  trial_expires_at: null,
  has_used_regular: false,
  has_used_premium: false,
});
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
const showSubscribeBtn = ref(false);
const activePost = ref(null);
const isVerifying = ref(false);
const showCancelModal = ref(false);
const isCancelling = ref(false);
const processingPlanId = ref(null);
const processingPortfolioId = ref(null);
const isActivatingTrial = ref(false);
const trialDays = ref(7);

// New state for the pricing modal
const notificationContainer = ref(null);
const showPricingModal = ref(false);

// --- TRIAL LOGIC ---
const startTrial = async (tier = 'regular') => {
  // Map 'vip' to 'premium' for the API call to match backend/database expectations
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
        `You now have ${trialDays.value} days of ${tier.toUpperCase()} access.`,
        'success'
      );
      user.value.on_trial = true;
      await fetchAllData();
      activeTab.value = 'premium'; // Always switch to premium tab on trial activation
    }
  } catch (error) {
    showFeedback('Error', error.response?.data?.message || 'Could not start trial.', 'error');
  } finally {
    isActivatingTrial.value = false;
  }
};

// --- COUNTDOWN LOGIC ---
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

  // Calculate time units
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((diff % (1000 * 60)) / 1000);

  // Format: "2d 05:12:08" or just "05:12:08" if less than a day
  const dayStr = days > 0 ? `${days}d ` : '';
  trialCountdown.value = `${dayStr}${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

// --- UTILS ---
const showFeedback = (title, message, type = 'success') => {
  feedback.value = { show: true, title, message, type };
};

// --- DATA FETCHING ---
const fetchAllData = async () => {
  isInitialLoading.value = true;

  try {
    // Fetch the Profile first
    const profileRes = await api.get('/user/profile/show');
    const userData = profileRes.data?.data;

    user.value = {
      has_active_subscription: !!userData?.has_active_subscription,
      on_trial: !!userData?.on_trial,
      trial_expires_at: userData?.trial_expires_at,
      has_used_regular: !!userData?.has_used_regular,
      has_used_premium: !!userData?.has_used_premium,
    };

    updateCountdown();

    
    // Catch errors on regular-posts (403 Forbidden for new users) so it doesn't block plans from loading
    const [plansRes, notificationsRes, rPostsRes] = await Promise.all([
      api.get('/user/advisory/plans'),
      api.get('/user/notifications'),
      api.get('/user/advisory/regular-posts').catch(() => ({ data: { data: [] } }))
    ]);

    plans.value = plansRes.data.data;
    if (plansRes.data.trial_settings) {
      trialDays.value = plansRes.data.trial_settings.days;
    }
    notifications.value = notificationsRes.data.notifications || [];
    unreadCount.value = notificationsRes.data.unread_count || 0;
    regularPosts.value = rPostsRes.data.data;

    //  Fetch Premium data ONLY if authorized
    // We add a .catch(() => null) to each to prevent global error popups
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
    // Only log the error, don't show a popup if it's just a 403 for premium content
    console.warn("Silent fetch check:", e.message);
  } finally {
    isInitialLoading.value = false;
  }
};

// --- HANDLERS ---
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
  // Splits by comma and trims whitespace from each item
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
  processingPlanId.value = planId;
  try {
    const res = await api.post('/user/advisory/subscribe', { plan_id: planId });
    window.location.href = res.data.data.authorization_url;
  } catch (error) {
    showFeedback('Error', 'Payment failed.', 'error');
  } finally {
    processingPlanId.value = null;
  }
};

const confirmCancelSubscription = async () => {
  isCancelling.value = true;
  try {
    await api.get('/sanctum/csrf-cookie', { baseURL: '/' });
    await api.post('/user/advisory/cancel');

    user.value.has_active_subscription = false;
    showCancelModal.value = false;

    window.location.reload();

    showFeedback('Cancelled', 'Your subscription was removed.');
  } catch (error) {
    showFeedback('Error', 'Cancel failed.', 'error');
  } finally {
    isCancelling.value = false;
  }
};

const copyPortfolio = async (portfolioId) => {
  const amount = copyAmounts.value[portfolioId];
  if (!amount || amount < 5000) return showFeedback('Budget Too Low', 'Minimum amount is ₦5,000.', 'error');
  processingPortfolioId.value = portfolioId;
  try {
    await api.post(`/user/advisory/model-portfolios/${portfolioId}/copy`, { amount });
    router.push('/orders');
  } catch (error) {
    showFeedback('Error', error.response?.data?.message || 'Copy failed. Check balance.', 'error');
    processingPortfolioId.value = null;
  }
};

const canStartRegularTrial = computed(() => {
  // Basic check: hide if they have any sub history that isn't empty 
  // might want to pass 'has_used_trial' from the backend profile response
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

// --- LIFECYCLE ---
onMounted(async () => {
  await fetchAllData();
  setTimeout(() => { showSubscribeBtn.value = true; }, 1500);
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
  animation: fadeIn 0.3s ease-in-out;
}

.animate-fade-in-up {
  animation: fadeInUp 0.4s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }

  to {
    opacity: 1;
  }
}

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
</style>