<script setup>
import { useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    type: {
        type: String,
        default: 'block',
        validator: (value) => ['block', 'card', 'table', 'list', 'banner'].includes(value),
    },
    count: {
        type: Number,
        default: 1,
    },
});

const attrs = useAttrs();
</script>

<template>
    <div
        v-if="props.type === 'block'"
        v-bind="attrs"
        class="h-4 w-full rounded bg-[#1f3348] animate-pulse motion-reduce:animate-none"
        aria-hidden="true"
    ></div>

    <div
        v-else-if="props.type === 'card'"
        v-bind="attrs"
        class="grid w-full grid-cols-1 gap-4 animate-pulse motion-reduce:animate-none"
        aria-hidden="true"
    >
        <div
            v-for="i in props.count"
            :key="i"
            class="space-y-3 rounded-xl border border-[#1f3348] bg-[#0F1724] p-6"
        >
            <div class="h-4 w-1/3 rounded bg-[#1f3348]"></div>
            <div class="h-8 w-2/3 rounded bg-[#1f3348]"></div>
            <div class="space-y-2 pt-2">
                <div class="h-3 w-full rounded bg-[#1f3348]"></div>
                <div class="h-3 w-5/6 rounded bg-[#1f3348]"></div>
            </div>
        </div>
    </div>

    <div
        v-else-if="props.type === 'table'"
        v-bind="attrs"
        class="w-full animate-pulse motion-reduce:animate-none"
        aria-hidden="true"
    >
        <div class="overflow-hidden rounded-xl border border-[#1f3348] bg-[#0F1724]">
            <div class="flex justify-between border-b border-[#1f3348] bg-[#111827] p-4">
                <div class="h-4 w-1/4 rounded bg-[#1f3348]"></div>
                <div class="h-4 w-1/6 rounded bg-[#1f3348]"></div>
            </div>
            <div
                v-for="i in props.count"
                :key="i"
                class="flex items-center justify-between space-x-4 border-b border-[#1f3348] p-4 last:border-b-0"
            >
                <div class="h-4 w-1/5 rounded bg-[#1f3348]"></div>
                <div class="h-4 w-1/4 rounded bg-[#1f3348]"></div>
                <div class="h-4 w-12 rounded bg-[#1f3348]"></div>
            </div>
        </div>
    </div>

    <div
        v-else-if="props.type === 'list'"
        v-bind="attrs"
        class="grid w-full grid-cols-1 gap-3 animate-pulse motion-reduce:animate-none"
        aria-hidden="true"
    >
        <div
            v-for="i in props.count"
            :key="i"
            class="flex items-center space-x-4 rounded-lg border border-[#1f3348] bg-[#0F1724] p-3"
        >
            <div class="h-10 w-10 shrink-0 rounded-full bg-[#1f3348]"></div>
            <div class="flex-1 space-y-2">
                <div class="h-3 w-1/4 rounded bg-[#1f3348]"></div>
                <div class="h-3 w-3/4 rounded bg-[#1f3348]"></div>
            </div>
        </div>
    </div>

    <div
        v-else
        v-bind="attrs"
        class="h-20 w-full animate-pulse rounded-xl bg-[#1f3348] motion-reduce:animate-none"
        aria-hidden="true"
    ></div>
</template>
