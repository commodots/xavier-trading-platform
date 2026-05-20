<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SkeletonLoader from '@/Components/SkeletonLoader.vue';

const props = defineProps({
    assets: Array,
    watchlistSymbols: Array,
    loading: Boolean
});

const activeTab = ref('US');
const tabs = [
    {id: 'US', label: 'US Markets'}, 
    {id: 'UK', label: 'UK Markets'}, 
    {id: 'CRYPTO', label: 'Crypto'},
    {id: 'NGX', label: 'NGX (Local)'}
];

const toggleWatchlist = (symbol) => {
    router.post(route('api.watchlist.toggle'), {
        asset_symbol: symbol,
        market: activeTab.value
    }, {
        preserveScroll: true
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Market Telemetry</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Tab Switching Row -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="-mb-px flex space-x-8">
                        <button 
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            :class="[
                                activeTab === tab.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                                'whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium transition-all'
                            ]"
                        >
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <SkeletonLoader v-if="loading" />
                    
                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-400">
                                    <th class="pb-3">Asset</th>
                                    <th class="pb-3">Market</th>
                                    <th class="pb-3 text-right">Price</th>
                                    <th class="pb-3 text-right">Change</th>
                                    <th class="pb-3 text-center">Watch</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                                <tr v-for="asset in assets.filter(a => a.market === activeTab)" :key="asset.symbol" class="hover:bg-gray-50/50 transition">
                                    <td class="py-4 font-medium text-gray-900">
                                        {{ asset.symbol }} <span class="text-xs text-gray-400 ml-1">{{ asset.name }}</span>
                                    </td>
                                    <td class="py-4">
                                        <span class="px-2 py-1 text-[10px] font-bold rounded bg-gray-100 text-gray-600">{{ asset.market }}</span>
                                    </td>
                                    <td class="py-4 text-right font-mono">${{ asset.price }}</td>
                                    <td :class="['py-4 text-right font-semibold font-mono', asset.changeDirection === 'up' ? 'text-emerald-500' : 'text-rose-500']">
                                        {{ asset.changeDirection === 'up' ? '+' : '' }}{{ asset.percentageChange }}%
                                    </td>
                                    <td class="py-4 text-center">
                                        <button @click="toggleWatchlist(asset.symbol)" class="text-gray-400 hover:text-amber-500 transition focus:outline-none">
                                            <svg 
                                                class="h-5 w-5" 
                                                :class="{ 'fill-amber-400 text-amber-400': watchlistSymbols.includes(asset.symbol) }"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>