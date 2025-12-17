<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
    <div class="bg-[#1C1F2E] rounded-2xl p-6 w-full max-w-lg border border-[#1F2A44]">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">Assign Role — {{ localUser.email || 'user' }}</h3>
        <button @click="$emit('close')" class="text-gray-400 hover:text-white">✖</button>
      </div>

      <div class="space-y-4">
        <div>
          <label class="text-sm text-gray-400">Select role</label>
          <select v-model="selected" class="w-full px-3 py-2 mt-2 bg-transparent border border-gray-600 rounded text-neutral-400">
            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>

        <div v-if="note" class="text-xs text-yellow-300">
          {{ note }}
        </div>

        <div class="flex justify-end gap-3 mt-4">
          <button @click="$emit('close')" class="px-3 py-2 text-sm bg-transparent border border-gray-600 rounded">Cancel</button>
          <button @click="save" :disabled="saving" class="px-4 py-2 rounded bg-[#00D4FF] text-black font-semibold">
            {{ saving ? 'Saving...' : 'Save Role' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
const props = defineProps({
  user: { type: Object, required: true }
});
const emit = defineEmits(['close','role-updated']);

const roles = ['user','admin','accounts','compliance','manager','support'];
const selected = ref(props.user?.role ?? 'user');
const saving = ref(false);
const note = ref('Admins can access the admin dashboard and user management.');

const localUser = ref(props.user || {});

watch(() => props.user, (v) => {
  localUser.value = v || {};
  selected.value = v?.role ?? 'user';
}, { immediate: true });

const save = async () => {
  if (!localUser.value.id) {
    alert('Missing user id');
    return;
  }
  saving.value = true;
  try {
    const token = localStorage.getItem('xavier_token');
    const res = await axios.post(`/admin/users/${localUser.value.id}/role`, { role: selected.value }, {
      headers: token ? { Authorization: `Bearer ${token}` } : {}
    });

    if (res.data && res.data.success) {
      emit('role-updated', selected.value);
    } else {
      alert(res.data.message || 'Failed to update role');
    }
  } catch (err) {
    console.error(err);
    alert(err.response?.data?.message || 'Server error');
  } finally {
    saving.value = false;
    emit('close');
  }
};
</script>

<style scoped>
/* Modal specific adjustments */
</style>
