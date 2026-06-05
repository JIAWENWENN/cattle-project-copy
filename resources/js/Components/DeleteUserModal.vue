<script setup>
import { useForm } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';

const props = defineProps({
    show: Boolean,
    user: Object
});

const emit = defineEmits(['close']);

const form = useForm({});

const confirmDelete = () => {
    if (!props.user) return;

    form.delete(route('users.destroy', props.user.id), {
        onSuccess: () => {
            emit('close');
        },
        onError: (errors) => {
            console.error('Delete failed:', errors);
        }
    });
};

const close = () => {
    emit('close');
};
</script>

<template>
    <Transition name="modal">
        <div v-if="show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 backdrop-blur-sm" @click.self="close">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all" @click.stop>

                <!-- Close Button (Top Right) -->
                <button @click="close" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-1 rounded transition-colors z-10">
                    <X class="w-5 h-5" />
                </button>

                <!-- Body -->
                <div class="p-6 text-center">
                    <!-- Warning Icon -->
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 13V8m0 8h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>

                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Are you sure?</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Are you sure you want to delete <strong class="text-gray-900">{{ user?.name }}</strong> from the system? This action cannot be undone.
                    </p>

                    <!-- Actions -->
                    <div class="flex justify-center gap-3">
                        <button
                            @click="confirmDelete"
                            :disabled="form.processing"
                            class="px-5 py-2.5 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors font-medium text-sm disabled:opacity-50"
                        >
                            {{ form.processing ? 'Deleting...' : "Yes, I'm sure" }}
                        </button>
                        <button
                            @click="close"
                            class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium text-sm"
                        >
                            No, cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
.modal-enter-active .bg-white,
.modal-leave-active .bg-white {
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-enter-from .bg-white {
    transform: scale(0.95) translateY(10px);
}
.modal-leave-to .bg-white {
    transform: scale(0.95);
}
</style>
