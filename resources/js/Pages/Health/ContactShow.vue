<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Phone, Mail, MapPin, Clock, Star } from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Contact Details', parent: 'Health', parentUrl: '/health' }, () => page)
});

const props = defineProps({
    contact: Object
});

const goBack = () => {
    router.visit('/health/contact');
};

const editContact = () => {
    router.visit(`/health/contact/${props.contact.id}/edit`);
};

const callContact = () => {
    window.location.href = `tel:${props.contact.phone}`;
};

</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="goBack" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Contact Details</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ props.contact.name }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button @click="callContact" class="flex items-center gap-2 px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm font-medium hover:bg-[#2a443b] transition-colors">
                    <Phone class="w-4 h-4" />
                    Call
                </button>
                <button @click="editContact" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    <Edit class="w-4 h-4" />
                    Edit
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="p-6">
                <div class="flex items-start gap-6">
                    <div v-if="props.contact.photo_url" class="w-20 h-20 rounded-full overflow-hidden border-2 border-[#34554a]/20 shadow-sm">
                        <img :src="props.contact.photo_url" alt="Profile" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-20 h-20 bg-[#34554a]/10 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-2xl font-bold text-[#34554a]">{{ props.contact.name.charAt(0) }}</span>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 class="text-xl font-bold text-gray-900">{{ props.contact.name }}</h3>
                            <span v-if="props.contact.emergency" class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">24/7 Emergency</span>
                        </div>
                        <p class="text-gray-600">{{ props.contact.position }} at {{ props.contact.organization }}</p>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-100 p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center gap-3">
                    <Phone class="w-5 h-5 text-gray-400" />
                    <div>
                        <p class="text-xs text-gray-500">Phone</p>
                        <p class="text-gray-900 font-medium">{{ props.contact.phone }} <span v-if="props.contact.alt_phone" class="text-gray-400">({{ props.contact.alt_phone }})</span></p>
                    </div>
                </div>
                <div v-if="props.contact.email" class="flex items-center gap-3">
                    <Mail class="w-5 h-5 text-gray-400" />
                    <div>
                        <p class="text-xs text-gray-500">Email</p>
                        <p class="text-gray-900 font-medium">{{ props.contact.email }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 md:col-span-2">
                    <MapPin class="w-5 h-5 text-gray-400 mt-0.5" />
                    <div>
                        <p class="text-xs text-gray-500">Address</p>
                        <p class="text-gray-900 font-medium">{{ props.contact.address }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Clock class="w-5 h-5 text-gray-400" />
                    <div>
                        <p class="text-xs text-gray-500">Availability</p>
                        <p class="text-gray-900 font-medium">{{ props.contact.availability }}</p>
                    </div>
                </div>
            </div>
            <div v-if="props.contact.notes" class="border-t border-gray-100 p-4 bg-gray-50">
                <p class="text-xs text-gray-500 mb-1">Notes</p>
                <p class="text-gray-700">{{ props.contact.notes }}</p>
            </div>
        </div>
    </div>
</template>
