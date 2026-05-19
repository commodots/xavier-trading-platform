<script setup>
defineProps({
    type: {
        type: String,
        default: 'card', // Options: 'card', 'table', 'list', 'banner'
    },
    count: {
        type: Number,
        default: 1,
    }
});
</script>

<template>
    <div class="w-full space-y-4 animate-pulse">
        <!-- Card Skeleton Layout -->
        <template v-if="type === 'card'">
            <div v-for="i in count" :key="i" class="p-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-3">
                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
                <div class="h-8 bg-gray-200 dark:bg-gray-700 rounded w-2/3"></div>
                <div class="space-y-2 pt-2">
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-full"></div>
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-5/6"></div>
                </div>
            </div>
        </template>

        <!-- Table Rows Skeleton Layout -->
        <template v-else-if="type === 'table'">
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden bg-white dark:bg-gray-800">
                <div class="bg-gray-50 dark:bg-gray-900/50 p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/6"></div>
                </div>
                <div v-for="i in count" :key="i" class="p-4 border-b border-gray-100 dark:border-gray-700/50 flex items-center justify-between space-x-4">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/5"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-12"></div>
                </div>
            </div>
        </template>

        <!-- Feed List Items Skeleton Layout -->
        <template v-else-if="type === 'list'">
            <div v-for="i in count" :key="i" class="flex items-center space-x-4 p-3 bg-gray-50 dark:bg-gray-900/30 rounded-lg">
                <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-full shrink-0"></div>
                <div class="flex-1 space-y-2">
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                </div>
            </div>
        </template>

        <!-- Banner Layout -->
        <template v-else-if="type === 'banner'">
            <div class="h-20 bg-gray-200 dark:bg-gray-700 rounded-xl w-full"></div>
        </template>
    </div>
</template>