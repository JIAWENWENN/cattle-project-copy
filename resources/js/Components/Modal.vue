<script setup>
import { watch, onUnmounted } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
    show: Boolean,
    title: String,
    maxWidth: {
        type: String,
        default: 'max-w-2xl'
    }
});

const emit = defineEmits(['close']);

const close = () => emit('close');

watch(() => props.show, (show) => {
    document.body.style.overflow = show ? 'hidden' : 'auto';
}, { immediate: true });

onUnmounted(() => {
    document.body.style.overflow = 'auto';
});
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="close"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div :class="`inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full ${maxWidth}`">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-gray-100">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            {{ title }}
                        </h3>
                        <button @click="close" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                <div class="bg-white">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
