<template>
  <MainLayout>
    <div class="relative p-6 mx-auto max-w-7xl">
      <h1 class="mb-6 text-3xl font-bold text-white">Advisory & Subscriptions Admin</h1>

      <div v-if="modal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-sm p-6 text-center bg-white rounded-lg shadow-xl">
          <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-green-600 bg-green-100 rounded-full">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 class="mb-2 text-xl font-bold text-gray-800">{{ modal.title }}</h3>
          <p class="mb-6 text-gray-600">{{ modal.message }}</p>
          <button @click="closeModal" class="w-full px-6 py-2 font-bold text-white transition bg-blue-600 rounded hover:bg-blue-700">
            Awesome
          </button>
        </div>
      </div>

      <div class="flex mb-6 border-b border-gray-700">
        <button v-for="tab in ['Plans', 'Posts', 'Portfolios']" :key="tab" @click="activeTab = tab"
          :class="['py-2 px-4 font-semibold transition', activeTab === tab ? 'border-b-2 border-blue-500 text-blue-400' : 'text-gray-400 hover:text-gray-200']">
          Manage {{ tab }}
        </button>
      </div>

      <div v-if="activeTab === 'Plans'">
        
        <div class="p-6 mb-8 text-gray-600 bg-white border rounded-lg shadow-sm">
          <h2 class="mb-4 text-xl font-bold text-gray-800">
            {{ isEditingPlan ? 'Edit Subscription Plan' : 'Create New Plan' }}
          </h2>
          <form @submit.prevent="submitPlan" class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <input v-model="planForm.name" placeholder="Plan Name (e.g. VIP Monthly)" class="p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required />
            <input v-model="planForm.price" type="number" placeholder="Price (₦)" class="p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required />
            <input v-model="planForm.duration_days" type="number" placeholder="Duration (Days, e.g. 30)" class="p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required />
            <input v-model="planForm.paystack_plan_code" placeholder="Paystack Plan Code (PLN_xxx)" class="p-2 border rounded outline-none bg-yellow-50 focus:ring-2 focus:ring-blue-500" required />
            <textarea v-model="planForm.features" placeholder="Features (comma separated)" class="p-2 border rounded outline-none md:col-span-2 focus:ring-2 focus:ring-blue-500"></textarea>

            <div class="flex gap-3 md:col-span-2">
              <button type="submit" class="px-6 py-2 font-bold text-white transition bg-blue-600 rounded hover:bg-blue-700">
                {{ isEditingPlan ? 'Update Plan' : 'Create Plan' }}
              </button>
              <button v-if="isEditingPlan" type="button" @click="cancelEditPlan" class="px-6 py-2 font-bold text-gray-700 transition bg-gray-200 rounded hover:bg-gray-300">
                Cancel
              </button>
            </div>
          </form>
        </div>

        <div>
          <h2 class="mb-4 text-xl font-bold text-white">Existing Plans</h2>
          <div v-if="isLoading" class="italic text-blue-400 animate-pulse">Loading plans...</div>
          <div v-else-if="plansList.length === 0" class="italic text-gray-400">No plans created yet.</div>
          <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div v-for="plan in plansList" :key="plan.id" class="flex flex-col justify-between p-5 bg-gray-800 border border-gray-700 rounded-lg shadow-sm">
              <div>
                <h3 class="text-lg font-bold text-white">{{ plan.name }}</h3>
                <p class="my-1 text-xl font-black text-blue-400">₦{{ plan.price }}</p>
                <p class="text-sm text-gray-400">Duration: {{ plan.duration_days }} days</p>
                <p class="inline-block p-1 mt-2 font-mono text-xs text-gray-500 bg-gray-900 rounded">{{ plan.paystack_plan_code }}</p>
              </div>
              
              <div class="flex gap-2 pt-4 mt-4 border-t border-gray-700">
                <button @click="editPlan(plan)" class="w-1/2 px-3 py-1 text-sm font-bold text-white transition bg-blue-600 rounded hover:bg-blue-700">Edit</button>
                <button @click="deletePlan(plan.id)" class="w-1/2 px-3 py-1 text-sm font-bold text-white transition bg-red-600 rounded hover:bg-red-700">Delete</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="activeTab === 'Posts'">
        <div class="p-6 mb-8 text-gray-600 bg-white border rounded-lg shadow-sm">
          <h2 class="mb-4 text-xl font-bold text-gray-800">Publish Advisory Insight</h2>
          <form @submit.prevent="createPost" class="space-y-4">
            <input v-model="postForm.title" placeholder="Post Title" class="w-full p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <select v-model="postForm.market_type" class="p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="local">Local (NGX)</option>
                <option value="international">International</option>
                <option value="crypto">Crypto</option>
              </select>
              <select v-model="postForm.risk_level" class="p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="low">Low Risk</option>
                <option value="medium">Medium Risk</option>
                <option value="high">High Risk</option>
              </select>
              <div class="flex items-center p-2 space-x-2 border rounded bg-gray-50">
                <input type="checkbox" v-model="postForm.is_premium" id="premium" class="w-4 h-4 text-blue-600" />
                <label for="premium" class="font-semibold text-blue-700 cursor-pointer">Premium VIP Only?</label>
              </div>
            </div>

            <textarea v-model="postForm.content" placeholder="Write your market insight..." rows="5" class="w-full p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required></textarea>
            <button type="submit" class="px-6 py-2 font-bold text-white transition bg-blue-600 rounded hover:bg-blue-700">Publish Post</button>
          </form>
        </div>

        <div>
          <h2 class="mb-4 text-xl font-bold text-white">Recent Insights</h2>
          <div v-if="isLoading" class="italic text-blue-400 animate-pulse">Loading posts...</div>
          <div v-else-if="postsList.length === 0" class="italic text-gray-400">No insights published yet.</div>
          <div v-else class="space-y-4">
            <div v-for="post in postsList" :key="post.id" class="flex items-start justify-between p-5 bg-gray-800 border border-gray-700 rounded-lg">
              <div>
                <h3 class="text-lg font-bold text-white">{{ post.title }}</h3>
                <p class="mt-1 text-sm text-gray-400 line-clamp-2">{{ post.content }}</p>
              </div>
              <div class="flex gap-2 ml-4">
                <span v-if="post.is_premium" class="px-2 py-1 text-xs font-bold text-blue-300 bg-blue-900 rounded">VIP</span>
                <span v-else class="px-2 py-1 text-xs font-bold text-green-300 bg-green-900 rounded">FREE</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="activeTab === 'Portfolios'">
        <div class="p-6 mb-8 text-gray-600 bg-white border rounded-lg shadow-sm">
          <h2 class="mb-4 text-xl font-bold text-gray-800">Create Model Portfolio</h2>
          <form @submit.prevent="createPortfolio" class="space-y-6">

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <input v-model="portfolioForm.name" placeholder="Portfolio Name (e.g. Safe Tech)" class="p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required />
              <input v-model="portfolioForm.starting_value" type="number" placeholder="Starting Value Benchmark" class="p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required />
              <select v-model="portfolioForm.risk_profile" class="p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required>
                <option value="conservative">Conservative</option>
                <option value="balanced">Balanced</option>
                <option value="aggressive">Aggressive</option>
              </select>
              <div class="flex items-center p-2 space-x-2 border rounded bg-gray-50">
                <input type="checkbox" v-model="portfolioForm.is_premium" id="port_premium" class="w-4 h-4 text-blue-600" />
                <label for="port_premium" class="font-semibold text-blue-700 cursor-pointer">Premium VIP Only?</label>
              </div>
              <textarea v-model="portfolioForm.description" placeholder="Strategy description..." class="p-2 border rounded outline-none md:col-span-2 focus:ring-2 focus:ring-blue-500" required></textarea>
            </div>

            <div class="p-4 border rounded bg-gray-50">
              <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold">Stock Allocations (Must equal 100%)</h3>
                <span :class="['font-bold', totalAllocation === 100 ? 'text-green-600' : 'text-red-600']">
                  Total: {{ totalAllocation }}%
                </span>
              </div>

              <div v-for="(stock, index) in portfolioForm.stocks" :key="index" class="flex gap-4 mb-2">
                <input v-model="stock.symbol" placeholder="Symbol (e.g. AAPL)" class="w-1/2 p-2 uppercase border rounded outline-none focus:ring-2 focus:ring-blue-500" required />
                <input v-model="stock.allocation" type="number" placeholder="Percentage %" class="w-1/3 p-2 border rounded outline-none focus:ring-2 focus:ring-blue-500" required />
                <button type="button" @click="removeStock(index)" class="px-3 font-bold text-red-500 transition rounded hover:bg-red-100">X</button>
              </div>

              <button type="button" @click="addStock" class="mt-2 text-sm font-semibold text-blue-600 transition hover:text-blue-800">+ Add Another Stock</button>
            </div>

            <button type="submit" :disabled="totalAllocation !== 100" class="px-6 py-2 font-bold text-white transition bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50">
              Create Portfolio
            </button>
          </form>
        </div>

        <div>
          <h2 class="mb-4 text-xl font-bold text-white">Active Portfolios</h2>
          <div v-if="isLoading" class="italic text-blue-400 animate-pulse">Loading portfolios...</div>
          <div v-else-if="portfoliosList.length === 0" class="italic text-gray-400">No portfolios active.</div>
          <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div v-for="portfolio in portfoliosList" :key="portfolio.id" class="p-5 bg-gray-800 border border-gray-700 rounded-lg">
              <h3 class="text-lg font-bold text-white">{{ portfolio.name }}</h3>
              <p class="mb-3 text-xs font-semibold tracking-wider text-gray-400 uppercase">{{ portfolio.risk_profile }}</p>
              
              <div class="flex flex-wrap gap-1 mt-2">
                <span v-for="stock in portfolio.stocks" :key="stock.id" class="px-2 py-1 text-xs text-gray-200 bg-gray-700 border border-gray-600 rounded">
                  <strong>{{ stock.symbol }}</strong> {{ stock.allocation_percentage }}%
                </span>
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

// State
const activeTab = ref('Plans');
const isLoading = ref(true); 

// Modal State
const modal = ref({ show: false, title: '', message: '' });

// Existing Data Lists
const plansList = ref([]);
const postsList = ref([]);
const portfoliosList = ref([]);

// Edit State for Plans
const isEditingPlan = ref(false);
const editingPlanId = ref(null);

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

const fetchData = async () => {
  isLoading.value = true; 
  try {
    const [plansRes, postsRes, portsRes] = await Promise.all([
      api.get('/admin/subscription-plans'),
      api.get('/admin/advisory-posts'),
      api.get('/admin/model-portfolios')
    ]);
    plansList.value = plansRes.data;
    postsList.value = postsRes.data;
    portfoliosList.value = portsRes.data;
  } catch (error) {
    console.error("Error fetching admin data", error);
  } finally {
    isLoading.value = false;
  }
};

const showSuccessModal = (title, message) => {
  modal.value = { show: true, title, message };
};

const closeModal = () => {
  modal.value.show = false;
};

// --- PLAN MANAGEMENT (Create, Update, Delete) ---

const submitPlan = async () => {
  try {
    if (isEditingPlan.value) {
      await api.put(`/admin/subscription-plans/${editingPlanId.value}`, planForm.value);
      showSuccessModal('Plan Updated', `${planForm.value.name} has been updated successfully.`);
    } else {
      await api.post('/admin/subscription-plans', planForm.value);
      showSuccessModal('Plan Created', `${planForm.value.name} has been added.`);
    }
    cancelEditPlan(); 
    fetchData(); 
  } catch (e) {
    showSuccessModal('Error', 'Failed to save the plan.');
  }
};

const editPlan = (plan) => {
  isEditingPlan.value = true;
  editingPlanId.value = plan.id;
  planForm.value = { ...plan };
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const cancelEditPlan = () => {
  isEditingPlan.value = false;
  editingPlanId.value = null;
  planForm.value = { name: '', price: '', duration_days: 30, features: '', paystack_plan_code: '' };
};

const deletePlan = async (id) => {
  if (confirm("Are you sure you want to delete this plan? This cannot be undone.")) {
    try {
      await api.delete(`/admin/subscription-plans/${id}`);
      showSuccessModal('Deleted', 'The plan was deleted successfully.');
      fetchData();
    } catch (e) {
      showSuccessModal('Error', 'Failed to delete the plan.');
    }
  }
};

// --- POSTS & PORTFOLIOS ---
const addStock = () => { portfolioForm.value.stocks.push({ symbol: '', allocation: null }); };
const removeStock = (index) => { if (portfolioForm.value.stocks.length > 1) { portfolioForm.value.stocks.splice(index, 1); } };

const createPost = async () => {
  try {
    await api.post('/admin/advisory-posts', postForm.value);
    showSuccessModal('Post Published', 'Your market insight is now live.');
    postForm.value = { title: '', content: '', market_type: 'local', risk_level: 'medium', is_premium: true };
    fetchData(); 
  } catch (e) {
    showSuccessModal('Error', 'Failed to publish the post.');
  }
};

const createPortfolio = async () => {
  if (totalAllocation.value !== 100) return;
  try {
    await api.post('/admin/model-portfolios', portfolioForm.value);
    showSuccessModal('Portfolio Live', `${portfolioForm.value.name} is now available.`);
    portfolioForm.value = { name: '', description: '', risk_profile: 'balanced', starting_value: 100000, is_premium: true, stocks: [{ symbol: '', allocation: null }] };
    fetchData(); 
  } catch (e) {
    showSuccessModal('Error', e.response?.data?.error || 'Failed to create the portfolio.');
  }
};
</script>