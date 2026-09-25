<template>
  <div
    v-if="visibleItems.length > 0"
    class="sidebar-group"
  >
    <button
      type="button"
      class="flex items-center justify-between w-full px-3 py-2 rounded-lg text-left transition hover:bg-[#1C2541]/70"
      :class="headerClass"
      :aria-expanded="isExpanded"
      :aria-controls="`sidebar-group-${groupKey}`"
      @click="emit('toggle', groupKey)"
    >
      <span>{{ title }}</span>

      <span class="text-blue-200 text-xs">
        {{ isExpanded ? "−" : "+" }}
      </span>
    </button>

    <!-- ACCORDION: parent owns which single group is open -->
    <Transition name="accordion">
      <div
        v-if="isExpanded"
        :id="`sidebar-group-${groupKey}`"
        class="space-y-1 mt-1"
      >
        <SidebarLink
          v-for="item in visibleItems"
          :key="item.to"
          :to="item.to"
          :icon="item.icon"
          :active="isItemActive(item)"
          @click="emit('navigate', item)"
        >
          {{ item.label }}
        </SidebarLink>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";

import SidebarLink from "@/Components/SidebarLink.vue";

const props = defineProps({
  groupKey: {
    type: String,
    required: true,
  },

  title: {
    type: String,
    required: true,
  },

  items: {
    type: Array,
    default: () => [],
  },

  headerClass: {
    type: String,
    default:
      "px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase",
  },

  // The single group the parent currently has open (null = all collapsed).
  expandedGroup: {
    type: String,
    default: null,
  },

  // Globally-resolved active sidebar entry (path) coming from the layout.
  activePath: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["toggle", "navigate"]);

const route = useRoute();

const isExpanded = computed(() => props.expandedGroup === props.groupKey);

// Keep only the items the current user is allowed to see.
const visibleItems = computed(() =>
  props.items.filter((item) => !item.access || item.access())
);

const matchesRoute = (item) =>
  route.path === item.to || route.path.startsWith(`${item.to}/`);

const activeItem = computed(() => {
  if (props.activePath) {
    return (
      visibleItems.value.find((item) => item.to === props.activePath) || null
    );
  }

  return (
    [...visibleItems.value]
      .filter(matchesRoute)
      .sort((a, b) => b.to.length - a.to.length)[0] || null
  );
});

const isItemActive = (item) =>
  !!activeItem.value && item.to === activeItem.value.to;
</script>

<style scoped>
.accordion-enter-active,
.accordion-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}

.accordion-enter-from,
.accordion-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>