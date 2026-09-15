<template>
  <router-link
    :to="to"
    class="flex items-center gap-3 px-3 py-2 rounded-lg border-l-2 transition"
    :class="isActive
      ? 'bg-[#1C2541] border-[#00D4FF] font-medium'
      : 'border-transparent hover:bg-[#1C2541]/70'"
  >
    <component :is="icon" class="w-5 h-5 text-[#00D4FF]" />
    <span><slot /></span>
  </router-link>
</template>

<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";

const props = defineProps({
  to: { type: [String, Object], required: true },
  icon: { type: [Object, Function], required: true },
  // Explicit active state; when omitted, falls back to route matching.
  active: { type: Boolean, default: null },
  // Globally-resolved active sidebar entry (path) provided by the layout.
  // The link is active only when it equals this path.
  activePath: { type: String, default: null },
});

const route = useRoute();

const toPath = (target) =>
  typeof target === "string" ? target : target.path;

// Exact match, or a nested page living under the link's path (e.g. the routes
// /fixed-income/:id belong to the /fixed-income sidebar entry).
const matches = (target, currentPath) => {
  const targetPath = toPath(target);
  if (!targetPath || targetPath === "/") return currentPath === targetPath;
  return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`);
};

const isActive = computed(() => {
  if (props.active !== null) return props.active;
  if (props.activePath !== null) return toPath(props.to) === props.activePath;
  return matches(props.to, route.path);
});
</script>
