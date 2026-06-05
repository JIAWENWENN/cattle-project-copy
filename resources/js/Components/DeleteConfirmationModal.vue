<script setup>
import { X, AlertOctagon } from 'lucide-vue-next';

const props = defineProps({
    show: Boolean,
    title: String,
    message: String,
    itemName: String,
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm transition-all" @click.self="emit('close')">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm mx-4 overflow-hidden border-2 border-red-100">
            <div class="p-6 text-center">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <AlertOctagon class="w-6 h-6" />
                </div>

                <h3 class="text-lg font-bold text-gray-900 mb-2">{{ title || 'Confirm Delete' }}</h3>
                <p class="text-sm text-gray-500 mb-2">
                    {{ message || 'Are you sure you want to remove' }}
                    <span class="font-bold text-gray-800">{{ itemName }}</span>?
                </p>
                <p class="text-xs text-red-600 font-bold bg-red-50 py-2 rounded mb-6 italic">
                    ⚠️ This action cannot be undone.
                </p>

                <div class="flex gap-3 justify-center">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="emit('confirm')"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 shadow-sm transition-all"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
