<template>
  <MainLayout>
    <div class="relative p-6 mx-auto max-w-7xl">
      <h1 class="mb-6 text-3xl font-bold text-white">Advisory & Subscriptions Admin</h1>

      <div v-if="modal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-sm p-6 text-center bg-white rounded-lg shadow-xl">
          <div :class="['flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full', modal.isError ? 'text-red-600 bg-red-100' : 'text-green-600 bg-green-100']">
            <svg v-if="!modal.isError" xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </div>
          <h3 class="mb-2 text-xl font-bold text-gray-800">{{ modal.title }}</h3>
          <p class="mb-6 text-gray-600">{{ modal.message }}</p>
          <button @click="closeModal" class="w-full px-6 py-2 font-bold text-white transition bg-gray-900 rounded hover:bg-gray-800">Okay</button>
        </div>
      </div>

      <div class="flex mb-6 border-b border-gray-700">
        <button v-for="tab in ['Plans', 'Posts', 'Portfolios']" :key="tab" @click="activeTab = tab"
          :class="['py-3 px-6 font-bold transition-all duration-200', activeTab === tab ? 'border-b-2 border-blue-500 text-blue-400 bg-blue-500/10' : 'text-gray-400 hover:text-gray-200 hover:bg-gray-800/50']">
          Manage {{ tab }}
        </button>
      </div>

      <div v-if="activeTab === 'Plans'" class="space-y-8 animate-fade-in">
        <div class="p-6 bg-[#0F1724] border border-gray-800 rounded-xl shadow-lg">
          <h2 class="mb-6 text-xl font-bold text-white">
            <span class="text-blue-500">{{ isEditingPlan ? 'Edit' : 'Create New' }}</span> Subscription Plan
          </h2>
          <form @submit.prevent="submitPlan" class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <input v-model="planForm.name" placeholder="Plan Name (e.g. VIP Monthly)" class="p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required />
            <input v-model="planForm.price" type="number" placeholder="Price (₦)" class="p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required />
            <input v-model="planForm.duration_days" type="number" placeholder="Duration (Days, e.g. 30)" class="p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required />
            <input v-model="planForm.paystack_plan_code" placeholder="Paystack Plan Code (PLN_xxx)" class="p-3 text-yellow-500 bg-gray-900 border rounded-lg outline-none border-yellow-900/50 focus:border-yellow-500" required />
            <textarea v-model="planForm.features" placeholder="Features (comma separated)" class="p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none md:col-span-2 focus:border-blue-500"></textarea>

            <div class="flex gap-3 md:col-span-2">
              <button type="submit" :disabled="isSubmittingPlan" class="px-8 py-3 font-bold text-white transition bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 min-w-[180px]">
                <span v-if="isSubmittingPlan">
                  {{ isEditingPlan ? 'Updating plan...' : 'Creating plan...' }}
                </span>
                <span v-else>
                  {{ isEditingPlan ? 'Update Plan' : 'Create Plan' }}
                </span>
              </button>
              <button v-if="isEditingPlan" type="button" @click="cancelEdit('plan')" :disabled="isSubmittingPlan" class="px-6 py-3 font-bold text-gray-300 transition border border-gray-600 rounded-lg hover:bg-gray-800 disabled:opacity-50">Cancel Edit</button>
            </div>
          </form>
        </div>

        <div>
          <h2 class="mb-4 text-xl font-bold text-white">Existing Plans</h2>
          <div v-if="isLoading" class="italic text-blue-400">Loading plans...</div>
          <div v-else-if="plansList.length === 0" class="italic text-gray-400">No plans created yet.</div>
          <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div v-for="plan in plansList" :key="plan.id" class="flex flex-col justify-between p-6 transition bg-[#0F1724] border border-gray-800 rounded-xl hover:border-gray-600">
              <div>
                <h3 class="text-xl font-bold text-white">{{ plan.name }}</h3>
                <p class="my-2 text-2xl font-black text-blue-500">₦{{ Number(plan.price).toLocaleString() }}</p>
                <p class="text-sm text-gray-400">Duration: {{ plan.duration_days }} days</p>
                <p class="inline-block p-1 px-2 mt-3 font-mono text-xs text-yellow-500 rounded bg-yellow-500/10">{{ plan.paystack_plan_code }}</p>
              </div>
              <div class="flex gap-3 pt-5 mt-5 border-t border-gray-800">
                <button @click="editItem('plan', plan)" class="flex-1 py-2 text-sm font-bold text-blue-400 transition rounded-lg bg-blue-500/10 hover:bg-blue-500/20">Edit</button>
                <button @click="deleteItem('plan', plan.id)" :disabled="deletingId === 'plan-'+plan.id" class="flex-1 py-2 text-sm font-bold text-red-400 transition rounded-lg bg-red-500/10 hover:bg-red-500/20 disabled:opacity-50">
                  {{ deletingId === 'plan-'+plan.id ? 'Deleting...' : 'Delete' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="activeTab === 'Posts'" class="space-y-8 animate-fade-in">
        <div class="p-6 bg-[#0F1724] border border-gray-800 rounded-xl shadow-lg">
          <h2 class="mb-6 text-xl font-bold text-white">
            <span class="text-blue-500">{{ isEditingPost ? 'Edit' : 'Publish New' }}</span> Advisory Insight
          </h2>
          <form @submit.prevent="submitPost" class="space-y-5">
            <input v-model="postForm.title" placeholder="Post Title" class="w-full p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required />

            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
              <select v-model="postForm.market_type" class="p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required>
                <option value="local">Local (NGX)</option>
                <option value="international">International</option>
                <option value="crypto">Crypto</option>
              </select>
              <select v-model="postForm.risk_level" class="p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required>
                <option value="low">Low Risk</option>
                <option value="medium">Medium Risk</option>
                <option value="high">High Risk</option>
              </select>
              <div class="flex items-center p-3 space-x-3 bg-gray-900 border border-gray-700 rounded-lg">
                <input type="checkbox" v-model="postForm.is_premium" id="premium" class="w-5 h-5 text-blue-600 bg-gray-800 border-gray-600 rounded focus:ring-blue-600 focus:ring-offset-gray-900" />
                <label for="premium" class="font-bold text-blue-400 cursor-pointer">Premium VIP Only</label>
              </div>
            </div>

            <textarea v-model="postForm.content" placeholder="Write your market insight..." rows="6" class="w-full p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required></textarea>
            
            <div class="flex gap-3">
              <button type="submit" :disabled="isSubmittingPost" class="px-8 py-3 font-bold text-white transition bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 min-w-[180px]">
                <span v-if="isSubmittingPost">
                  {{ isEditingPost ? 'Updating...' : 'Publishing...' }}
                </span>
                <span v-else>
                  {{ isEditingPost ? 'Update Post' : 'Publish Post' }}
                </span>
              </button>
              <button v-if="isEditingPost" type="button" @click="cancelEdit('post')" :disabled="isSubmittingPost" class="px-6 py-3 font-bold text-gray-300 transition border border-gray-600 rounded-lg hover:bg-gray-800 disabled:opacity-50">Cancel Edit</button>
            </div>
          </form>
        </div>

        <div>
          <h2 class="mb-4 text-xl font-bold text-white">Recent Insights</h2>
          <div v-if="isLoading" class="italic text-blue-400">Loading posts...</div>
          <div v-else-if="postsList.length === 0" class="italic text-gray-400">No insights published yet.</div>
          <div v-else class="space-y-4">
            <div v-for="post in postsList" :key="post.id" class="flex flex-col md:flex-row items-start justify-between p-6 transition bg-[#0F1724] border border-gray-800 rounded-xl hover:border-gray-600 relative">
              <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                  <span v-if="post.is_premium" class="px-2 py-1 text-[10px] font-bold tracking-widest text-blue-300 uppercase bg-blue-900/50 rounded">VIP Premium</span>
                  <span v-else class="px-2 py-1 text-[10px] font-bold tracking-widest text-green-300 uppercase bg-green-900/50 rounded">Free</span>
                  <span class="text-xs text-gray-500">{{ new Date(post.created_at).toLocaleDateString() }}</span>
                </div>
                <h3 class="text-xl font-bold text-white">{{ post.title }}</h3>
                <div class="mt-2 text-sm text-gray-400">
                  <span :class="{ 'line-clamp-2': !post.expanded }">{{ post.content }}</span>
                  <button v-if="post.content.length > 100" @click="post.expanded = !post.expanded" class="mt-1 text-xs font-semibold text-blue-500 transition hover:text-blue-400">
                    {{ post.expanded ? 'See less' : 'See more...' }}
                  </button>
                </div>
              </div>
              <div class="flex gap-2 mt-4 md:mt-0 md:ml-6 md:flex-col shrink-0">
                <button @click="editItem('post', post)" class="px-6 py-2 text-sm font-bold text-blue-400 transition rounded-lg bg-blue-500/10 hover:bg-blue-500/20">Edit</button>
                <button @click="deleteItem('post', post.id)" :disabled="deletingId === 'post-'+post.id" class="px-6 py-2 text-sm font-bold text-red-400 transition rounded-lg bg-red-500/10 hover:bg-red-500/20 disabled:opacity-50">
                  {{ deletingId === 'post-'+post.id ? 'Deleting...' : 'Delete' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="activeTab === 'Portfolios'" class="space-y-8 animate-fade-in">
        <div class="p-6 bg-[#0F1724] border border-gray-800 rounded-xl shadow-lg">
          <h2 class="mb-6 text-xl font-bold text-white">
            <span class="text-blue-500">{{ isEditingPortfolio ? 'Edit' : 'Create New' }}</span> Model Portfolio
          </h2>
          <form @submit.prevent="submitPortfolio" class="space-y-6">

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
              <input v-model="portfolioForm.name" placeholder="Portfolio Name (e.g. Safe Tech)" class="p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required />
              <input v-model="portfolioForm.starting_value" type="number" placeholder="Starting Value Benchmark" class="p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required />
              <select v-model="portfolioForm.risk_profile" class="p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required>
                <option value="conservative">Conservative</option>
                <option value="balanced">Balanced</option>
                <option value="aggressive">Aggressive</option>
              </select>
              <div class="flex items-center p-3 space-x-3 bg-gray-900 border border-gray-700 rounded-lg">
                <input type="checkbox" v-model="portfolioForm.is_premium" id="port_premium" class="w-5 h-5 text-blue-600 bg-gray-800 border-gray-600 rounded focus:ring-blue-600 focus:ring-offset-gray-900" />
                <label for="port_premium" class="font-bold text-blue-400 cursor-pointer">Premium VIP Only</label>
              </div>
              <textarea v-model="portfolioForm.description" placeholder="Strategy description..." class="p-3 text-white bg-gray-900 border border-gray-700 rounded-lg outline-none md:col-span-2 focus:border-blue-500" required></textarea>
            </div>

            <div class="p-5 bg-gray-900 border border-gray-700 rounded-lg">
              <div class="flex items-center justify-between pb-4 mb-4 border-b border-gray-800">
                <h3 class="font-bold text-white">Stock Allocations (Must equal 100%)</h3>
                <span :class="['font-black px-3 py-1 rounded', totalAllocation === 100 ? 'text-green-400 bg-green-900/30' : 'text-red-400 bg-red-900/30']">
                  Total: {{ totalAllocation }}%
                </span>
              </div>

              <div v-for="(stock, index) in portfolioForm.stocks" :key="index" class="flex gap-4 mb-3">
                <input v-model="stock.symbol" placeholder="Symbol (e.g. AAPL)" class="w-1/2 p-3 text-white uppercase bg-gray-800 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required />
                <input v-model="stock.allocation" type="number" placeholder="Percentage %" class="w-1/3 p-3 text-white bg-gray-800 border border-gray-700 rounded-lg outline-none focus:border-blue-500" required />
                <button type="button" @click="removeStock(index)" class="px-4 font-black text-red-400 transition rounded-lg bg-red-900/20 hover:bg-red-900/50">X</button>
              </div>

              <button type="button" @click="addStock" class="mt-2 text-sm font-bold text-blue-400 transition hover:text-blue-300">+ Add Another Stock</button>
            </div>

            <div class="flex gap-3">
              <button type="submit" :disabled="totalAllocation !== 100 || isSubmittingPortfolio" class="px-8 py-3 font-bold text-white transition bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 min-w-[180px]">
                <span v-if="isSubmittingPortfolio">
                  {{ isEditingPortfolio ? 'Updating portfolio...' : 'Creating portfolio...' }}
                </span>
                <span v-else>
                  {{ isEditingPortfolio ? 'Update Portfolio' : 'Create Portfolio' }}
                </span>
              </button>
              <button v-if="isEditingPortfolio" type="button" @click="cancelEdit('portfolio')" :disabled="isSubmittingPortfolio" class="px-6 py-3 font-bold text-gray-300 transition border border-gray-600 rounded-lg hover:bg-gray-800 disabled:opacity-50">Cancel Edit</button>
            </div>
          </form>
        </div>

        <div>
          <h2 class="mb-4 text-xl font-bold text-white">Active Portfolios</h2>
          <div v-if="isLoading" class="italic text-blue-400">Loading portfolios...</div>
          <div v-else-if="portfoliosList.length === 0" class="italic text-gray-400">No portfolios active.</div>
          <div v-else class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            <div v-for="portfolio in portfoliosList" :key="portfolio.id" class="flex flex-col p-6 transition bg-[#0F1724] border border-gray-800 rounded-xl hover:border-gray-600">
              <div class="flex-1">
                <div class="flex items-center justify-between mb-2">
                  <h3 class="text-xl font-black text-white">{{ portfolio.name }}</h3>
                  <span v-if="portfolio.is_premium" class="px-2 py-0.5 text-[10px] font-bold text-blue-300 bg-blue-900/50 rounded">VIP</span>
                </div>
                <p class="mb-4 text-xs font-bold tracking-widest text-gray-500 uppercase">{{ portfolio.risk_profile }} Strategy</p>
                
                <div class="flex flex-wrap gap-2 mb-4">
                  <span v-for="stock in portfolio.stocks" :key="stock.id" class="px-2 py-1 text-xs font-medium text-gray-300 border border-gray-700 rounded bg-gray-800/50">
                    <strong class="text-white">{{ stock.symbol }}</strong> {{ stock.allocation_percentage }}%
                  </span>
                </div>
              </div>
              <div class="flex gap-2 pt-4 mt-auto border-t border-gray-800">
                <button @click="editItem('portfolio', portfolio)" class="flex-1 py-2 text-sm font-bold text-blue-400 transition rounded-lg bg-blue-500/10 hover:bg-blue-500/20">Edit</button>
                <button @click="deleteItem('portfolio', portfolio.id)" :disabled="deletingId === 'portfolio-'+portfolio.id" class="flex-1 py-2 text-sm font-bold text-red-400 transition rounded-lg bg-red-500/10 hover:bg-red-500/20 disabled:opacity-50">
                  {{ deletingId === 'portfolio-'+portfolio.id ? 'Deleting...' : 'Delete' }}
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
import { ref, computed, onMounted } from 'vue';
import api from '@/api';
import MainLayout from '@/Layouts/MainLayout.vue';

// Tabs & Global Loading State
const activeTab = ref('Plans');
const isLoading = ref(true); 

// Specific Submitting States
const isSubmittingPlan = ref(false);
const isSubmittingPost = ref(false);
const isSubmittingPortfolio = ref(false);
const deletingId = ref(null);

// Modal State
const modal = ref({ show: false, title: '', message: '', isError: false });

// Data Lists
const plansList = ref([]);
const postsList = ref([]);
const portfoliosList = ref([]);

// Edit States
const isEditingPlan = ref(false);
const editingPlanId = ref(null);

const isEditingPost = ref(false);
const editingPostId = ref(null);

const isEditingPortfolio = ref(false);
const editingPortfolioId = ref(null);

// Forms
const planForm = ref({ name: '', price: '', duration_days: 30, features: '', paystack_plan_code: '' });
const postForm = ref({ title: '', content: '', market_type: 'local', risk_level: 'medium', is_premium: true });
const portfolioForm = ref({ name: '', description: '', risk_profile: 'balanced', starting_value: 100000, is_premium: true, stocks: [{ symbol: '', allocation: null }] });

const totalAllocation = computed(() => {
  return portfolioForm.value.stocks.reduce((sum, stock) => sum + Number(stock.allocation || 0), 0);
});

onMounted(() => {
  fetchData();
});

// Using Promise.allSettled makes the page load dramatically faster by not waiting for slow endpoints to block fast ones
const fetchData = async () => {
  isLoading.value = true; 
  try {
    const results = await Promise.allSettled([
      api.get('/admin/subscription-plans'),
      api.get('/admin/advisory-posts'),
      api.get('/admin/model-portfolios')
    ]);

    if (results[0].status === 'fulfilled') plansList.value = results[0].value.data;
    
    if (results[1].status === 'fulfilled') {
      // Add 'expanded: false' to each post for the 'See more' toggle UI
      postsList.value = results[1].value.data.map(post => ({ ...post, expanded: false }));
    }
    
    if (results[2].status === 'fulfilled') portfoliosList.value = results[2].value.data;

  } catch (error) {
    console.error("Error fetching admin data", error);
    showModalMessage('Error', 'Failed to load data from server.', true);
  } finally {
    isLoading.value = false;
  }
};

const showModalMessage = (title, message, isError = false) => {
  modal.value = { show: true, title, message, isError };
};

const closeModal = () => {
  modal.value.show = false;
};

// --- GENERIC EDIT & CANCEL ---

const editItem = (type, item) => {
  window.scrollTo({ top: 0, behavior: 'smooth' });
  if (type === 'plan') {
    isEditingPlan.value = true;
    editingPlanId.value = item.id;
    planForm.value = { ...item };
  } else if (type === 'post') {
    isEditingPost.value = true;
    editingPostId.value = item.id;
    postForm.value = { ...item, is_premium: !!item.is_premium };
  } else if (type === 'portfolio') {
    isEditingPortfolio.value = true;
    editingPortfolioId.value = item.id;
    portfolioForm.value = { 
      ...item, 
      is_premium: !!item.is_premium,
      stocks: item.stocks.map(s => ({ symbol: s.symbol, allocation: s.allocation_percentage })) 
    };
  }
};

const cancelEdit = (type) => {
  if (type === 'plan') {
    isEditingPlan.value = false; editingPlanId.value = null;
    planForm.value = { name: '', price: '', duration_days: 30, features: '', paystack_plan_code: '' };
  } else if (type === 'post') {
    isEditingPost.value = false; editingPostId.value = null;
    postForm.value = { title: '', content: '', market_type: 'local', risk_level: 'medium', is_premium: true };
  } else if (type === 'portfolio') {
    isEditingPortfolio.value = false; editingPortfolioId.value = null;
    portfolioForm.value = { name: '', description: '', risk_profile: 'balanced', starting_value: 100000, is_premium: true, stocks: [{ symbol: '', allocation: null }] };
  }
};

// --- GENERIC DELETE ---

const deleteItem = async (type, id) => {
  if (!confirm(`Are you sure you want to delete this ${type}? This cannot be undone.`)) return;
  
  deletingId.value = `${type}-${id}`;
  let endpoint = '';
  
  if (type === 'plan') endpoint = `/admin/subscription-plans/${id}`;
  if (type === 'post') endpoint = `/admin/advisory-posts/${id}`;
  if (type === 'portfolio') endpoint = `/admin/model-portfolios/${id}`;

  try {
    await api.delete(endpoint);
    showModalMessage('Deleted', `The ${type} was deleted successfully.`);
    fetchData();
  } catch (e) {
    showModalMessage('Error', `Failed to delete the ${type}.`, true);
  } finally {
    deletingId.value = null;
  }
};

// --- SPECIFIC SUBMIT HANDLERS ---

const submitPlan = async () => {
  isSubmittingPlan.value = true;
  try {
    if (isEditingPlan.value) {
      await api.put(`/admin/subscription-plans/${editingPlanId.value}`, planForm.value);
      showModalMessage('Plan Updated', `${planForm.value.name} has been updated successfully.`);
    } else {
      await api.post('/admin/subscription-plans', planForm.value);
      showModalMessage('Plan Created', `${planForm.value.name} has been added.`);
    }
    cancelEdit('plan'); 
    fetchData(); 
  } catch (e) {
    showModalMessage('Error', 'Failed to save the plan.', true);
  } finally {
    isSubmittingPlan.value = false;
  }
};

const submitPost = async () => {
  isSubmittingPost.value = true;
  try {
    if (isEditingPost.value) {
      await api.put(`/admin/advisory-posts/${editingPostId.value}`, postForm.value);
      showModalMessage('Post Updated', 'Your insight has been updated.');
    } else {
      await api.post('/admin/advisory-posts', postForm.value);
      showModalMessage('Post Published', 'Your market insight is now live.');
    }
    cancelEdit('post'); 
    fetchData(); 
  } catch (e) {
    showModalMessage('Error', 'Failed to publish the post.', true);
  } finally {
    isSubmittingPost.value = false;
  }
};

const submitPortfolio = async () => {
  if (totalAllocation.value !== 100) return;
  isSubmittingPortfolio.value = true;
  try {
    if (isEditingPortfolio.value) {
      await api.put(`/admin/model-portfolios/${editingPortfolioId.value}`, portfolioForm.value);
      showModalMessage('Portfolio Updated', `${portfolioForm.value.name} has been updated.`);
    } else {
      await api.post('/admin/model-portfolios', portfolioForm.value);
      showModalMessage('Portfolio Live', `${portfolioForm.value.name} is now available.`);
    }
    cancelEdit('portfolio'); 
    fetchData(); 
  } catch (e) {
    showModalMessage('Error', e.response?.data?.error || 'Failed to create the portfolio.', true);
  } finally {
    isSubmittingPortfolio.value = false;
  }
};

const addStock = () => { portfolioForm.value.stocks.push({ symbol: '', allocation: null }); };
const removeStock = (index) => { if (portfolioForm.value.stocks.length > 1) { portfolioForm.value.stocks.splice(index, 1); } };

</script>