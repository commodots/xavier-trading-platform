<script setup>
defineProps({
    post: {
        type: Object,
        required: true,
       
    },
    isUnread: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['select', 'unlockTier']);

const handleCardClick = (post, isLocked) => {
    if (isLocked) {
        emit('unlockTier', post);
    } else {
        emit('select', post);
    }
};
</script>

<template>
    <div 
        @click="handleCardClick(post, post.is_locked)"
        class="relative bg-white dark:bg-gray-800 border shadow-sm rounded-xl overflow-hidden p-6 transition-all duration-200 hover:shadow-md flex flex-col justify-between cursor-pointer group"
        :class="[
            isUnread ? 'border-l-4 border-l-blue-500 border-gray-100 dark:border-gray-700' : 'border-gray-100 dark:border-gray-700'
        ]"
    >
        <div>
            <!-- Header Tag Information Line -->
            <div class="flex items-center justify-between mb-4">
                <span :class="[
                    'text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-md',
                    post.category === 'crypto' 
                        ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300' 
                        : 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
                ]">
                    {{ post.category || 'General' }}
                </span>
                <div class="flex items-center gap-2">
                    <span v-if="post.recommendation" :class="[
                        'text-[10px] font-black px-2 py-0.5 rounded border',
                        post.recommendation === 'BUY' ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' :
                        post.recommendation === 'SELL' ? 'bg-rose-500/10 text-rose-500 border-rose-500/20' :
                        'bg-amber-500/10 text-amber-500 border-amber-500/20'
                    ]">
                        {{ post.recommendation }}
                    </span>
                    <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">
                        {{ post.created_at }}
                    </span>
                </div>
            </div>

            <!-- Header Title -->
            <h3 class="text-lg font-bold text-gray-900 dark:text-white leading-snug mb-2 group-hover:text-indigo-500 dark:group-hover:text-indigo-400 transition-colors">
                {{ post.title }}
            </h3>

            <!-- Body Section Container (Handles Blur for Premium Locks) -->
            <div class="relative mt-3">
                <p :class="[
                    'text-sm text-gray-600 dark:text-gray-300 leading-relaxed',
                    post.is_locked ? 'select-none blur-sm opacity-40 overflow-hidden max-h-20' : ''
                ]">
                    {{ post.body || post.content }}
                </p>

                <!-- Gate Mask overlay if the post access properties remain locked -->
                <div v-if="post.is_locked" class="absolute inset-0 flex flex-col items-center justify-center bg-gradient-to-t from-white/90 dark:from-gray-800/90 via-white/50 dark:via-gray-800/50 to-transparent p-4 text-center">
                    <svg class="h-6 w-6 text-gray-400 mb-1.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-200">Premium Tier Block</span>
                </div>
            </div>
        </div>

        <!-- Action / Metadata Footer Row -->
        <div class="mt-6 pt-4 border-t border-gray-50 dark:border-gray-700/60 flex items-center justify-between">
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                By {{ post.author || 'Analytics Team' }}
            </span>

            <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 flex items-center space-x-1">
                <span v-if="post.is_locked" class="flex items-center space-x-1 hover:underline">
                    <span>Unlock Analysis</span>
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </span>
                <span v-else class="text-xs font-bold text-emerald-600 dark:text-emerald-400 flex items-center space-x-1">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Available</span>
                </span>
            </div>
        </div>
    </div>
</template>