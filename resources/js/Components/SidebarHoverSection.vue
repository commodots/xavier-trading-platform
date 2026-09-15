<template>
  <div
    v-if="visibleItems.length > 0"
    class="sidebar-group"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
  >
    <div :class="headerClass">{{ title }}</div>

    <!-- COLLAPSIBLE: only the active representative is shown until hovered / tapped -->
    <template v-if="collapsible">
      <button
        v-if="!expanded"
        type="button"
        class="flex items-center gap-3 w-full px-3 py-2 rounded-lg border-l-2 transition text-left"
        :class="activeItem
          ? 'bg-[#1C2541] border-[#00D4FF]'
          : 'border-transparent hover:bg-[#1C2541]/70'"
        :aria-expanded="expanded"
        :title="`${title} — hover to expand`"
        @click="onRepresentativeClick"
      >
        <component :is="representative.icon" class="w-5 h-5 shrink-0 text-[#00D4FF]" />
        <span class="flex-1 text-sm text-gray-200 truncate">{{ representative.label }}</span>
        <ChevronDown class="w-4 h-4 shrink-0 text-blue-200" />
      </button>

      <!-- EXPANDED: all options revealed on hover / tap -->
      <Transition name="fade">
        <div v-if="expanded" class="space-y-1">
          <SidebarLink
            v-for="item in visibleItems"
            :key="item.to"
            :to="item.to"
            :icon="item.icon"
            :active="isItemActive(item)"
            @click="persistSelection(item)"
          >
            {{ item.label }}
          </SidebarLink>
        </div>
      </Transition>
    </template>

    <!-- NOT COLLAPSIBLE: always render every option (single-item sections) -->
    <div v-else class="space-y-1">
      <SidebarLink
        v-for="item in visibleItems"
        :key="item.to"
        :to="item.to"
        :icon="item.icon"
        :active="isItemActive(item)"
        @click="persistSelection(item)"
      >
        {{ item.label }}
      </SidebarLink>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { ChevronDown } from "lucide-vue-next";

import SidebarLink from "@/Components/SidebarLink.vue";

const props = defineProps({
  title: { type: String, required: true },
  groupKey: { type: String, required: true },
  items: { type: Array, default: () => [] },
  headerClass: {
    type: String,
    default:
      "px-3 mt-6 mb-2 text-[10px] tracking-widest text-gray-400 uppercase",
  },
  collapsible: { type: Boolean, default: true },
  // Globally-resolved active sidebar entry (path) coming from the layout.
  // Lets this section highlight exactly the entry that represents the current page.
  activePath: { type: String, default: null },
});

const route = useRoute();
const router = useRouter();

const hovered = ref(false);
const touchExpanded = ref(false);

const storageKey = `sidebar_active_${props.groupKey}`;

// Hover-capable devices expand on mouseenter/mouseleave; touch devices tap to expand.
const supportsHover =
  typeof window !== "undefined" &&
  !!window.matchMedia &&
  window.matchMedia("(hover: hover)").matches;

const expanded = computed(() => hovered.value || touchExpanded.value);

// Keep only the items the current user is allowed to see.
const visibleItems = computed(() =>
  props.items.filter((item) => !item.access || item.access())
);

// A section with 0 or 1 allowed items must never collapse.
const collapsible = computed(
  () => props.collapsible && visibleItems.value.length > 1
);

const matchesRoute = (item) =>
  route.path === item.to || route.path.startsWith(`${item.to}/`);

// The entry of this section that represents the currently active page. Prefers the layout-resolved active path; falls back to the best route match.
const activeItem = computed(() => {
  if (props.activePath !== null) {
    return (
      visibleItems.value.find((item) => item.to === props.activePath) || null
    );
  }
  const routeMatch = [...visibleItems.value]
    .filter(matchesRoute)
    .sort((a, b) => b.to.length - a.to.length)[0];
  return routeMatch || null;
});

const isItemActive = (item) =>
  !!activeItem.value && item.to === activeItem.value.to;

// The option shown while the section is collapsed:
// 1. the active entry (so the pill reflects the current page)
// 2. the option previously chosen in this section (persisted), if still available
// 3. whichever option best matches the current route (longest prefix wins)
// 4. the first option
const representative = computed(() => {
  if (activeItem.value) return activeItem.value;

  let stored = null;
  try {
    stored = localStorage.getItem(storageKey);
  } catch (e) {
  }

  const fromStorage = visibleItems.value.find((item) => item.to === stored);
  if (fromStorage) return fromStorage;

  const routeMatch = [...visibleItems.value]
    .filter(matchesRoute)
    .sort((a, b) => b.to.length - a.to.length)[0];
  if (routeMatch) return routeMatch;

  return visibleItems.value[0];
});

const persistSelection = (item) => {
  try {
    localStorage.setItem(storageKey, item.to);
  } catch (e) {
  }
};

const onRepresentativeClick = () => {
  if (supportsHover) {
    persistSelection(representative.value);
    router.push(representative.value.to);
  } else {
    // Touch fallback: first tap expands, tapping again collapses.
    touchExpanded.value = !touchExpanded.value;
  }
};

// When the route changes, collapse sections back to the active option.
watch(
  () => route.path,
  () => {
    hovered.value = false;
    touchExpanded.value = false;
  }
);
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>