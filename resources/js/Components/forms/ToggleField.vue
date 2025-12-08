<template>
  <label class="flex items-center gap-3 cursor-pointer">
    <input type="checkbox"
      v-model="modelValue"
      @change="$emit('update:modelValue', modelValue)"
      class="w-5 h-5"
    />
    <span class="text-gray-300">{{ label }}</span>
  </label>
</template>

<script setup>
defineProps({
  label: String,
  modelValue: Boolean,
});

defineEmits(["update:modelValue"]);
</script>
