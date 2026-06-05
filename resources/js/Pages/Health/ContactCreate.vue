<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { ArrowLeft, Save, User, Building, Phone, Mail, MapPin, Upload, Image as ImageIcon } from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Add Contact', parent: 'Health', parentUrl: '/health' }, () => page)
});

const form = useForm({
    name: '',
    role: '',
    company: '',
    phone: '',
    email: '',
    address: '',
    emergency: false,
    notes: '',
    profile_photo: null,
});

const profilePreview = ref(null);
const malaysiaPhonePattern = '^(?:\\+?60|0)(?:1\\d{8,9}|[3-9]\\d{7,8})$';

const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.profile_photo = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            profilePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removeImage = () => {
    form.profile_photo = null;
    profilePreview.value = null;
};

const save = () => {
    form.post('/health/contact', {
        onSuccess: () => {
            // Success redirect is handled by the controller
        },
    });
};

const cancel = () => {
    router.visit('/health/contact');
};

</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="cancel" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Add Contact</h1>
                    <p class="text-sm text-gray-500 mt-1">Add new veterinary contact.</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button @click="cancel" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">Cancel</button>
                <button @click="save" :disabled="form.processing" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors disabled:opacity-50">
                    <Save class="w-4 h-4" />
                    {{ form.processing ? 'Saving...' : 'Save Contact' }}
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden max-w-3xl">
            <div class="p-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <User class="w-4 h-4 text-[#34554a]" />
                    Contact Information
                </h3>
            </div>
            <div class="p-6 space-y-6">
                <div v-if="Object.keys(form.errors || {}).length" class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                    Please fix the highlighted fields and try again.
                </div>
                <!-- PROFILE PICTURE UPLOAD -->
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <h3 class="text-xs font-bold text-gray-700 uppercase mb-3 tracking-wider">Contact Profile Picture</h3>
                    <div class="flex items-start gap-4">
                        <div class="w-24 h-24 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center bg-white overflow-hidden shadow-sm">
                            <img v-if="profilePreview" :src="profilePreview" alt="Preview" class="w-full h-full object-cover" />
                            <ImageIcon v-else class="w-10 h-10 text-gray-300" />
                        </div>

                        <div class="flex-1">
                            <input
                                type="file"
                                @change="handleImageUpload"
                                accept="image/*"
                                class="hidden"
                                id="profile-upload"
                            />
                            <label
                                for="profile-upload"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer shadow-sm transition-all">
                                <Upload class="w-4 h-4 text-[#34554a]" />
                                Upload Picture
                            </label>
                            <button
                                v-if="profilePreview"
                                @click="removeImage"
                                type="button"
                                class="ml-2 px-4 py-2 bg-red-50 text-red-600 rounded-lg text-sm font-medium hover:bg-red-100 transition-all">
                                Remove
                            </button>
                            <p class="text-[10px] text-gray-500 mt-2 font-medium">JPG, PNG (max 2MB)</p>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Name *</label>
                    <input v-model="form.name" type="text" placeholder="Full name" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Type *</label>
                        <select v-model="form.type" class="w-full px-3 py-2 rounded-lg border border-gray-200 bg-white outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                            <option value="veterinarian">Veterinarian</option>
                            <option value="clinic">Clinic</option>
                            <option value="supplier">Supplier</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Position</label>
                        <input v-model="form.position" type="text" placeholder="e.g., Farm Veterinarian" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Organization</label>
                    <input v-model="form.organization" type="text" placeholder="Company or clinic name" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Phone *</label>
                        <input v-model="form.phone" type="tel" inputmode="numeric" :pattern="malaysiaPhonePattern" title="Enter valid Malaysia phone number, e.g. 0123456789 or +60123456789" placeholder="Primary phone" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Alternative Phone</label>
                        <input v-model="form.alt_phone" type="tel" inputmode="numeric" :pattern="malaysiaPhonePattern" title="Enter valid Malaysia phone number, e.g. 0123456789 or +60123456789" placeholder="Secondary phone" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                        <p v-if="form.errors.alt_phone" class="mt-1 text-xs text-red-600">{{ form.errors.alt_phone }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Email</label>
                    <input v-model="form.email" type="email" maxlength="255" autocomplete="email" title="Enter a valid email address, e.g. name@example.com" placeholder="email@example.com" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Address</label>
                    <textarea v-model="form.address" rows="2" placeholder="Full address" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Availability</label>
                        <input v-model="form.availability" type="text" placeholder="e.g., Mon-Sat 8AM-5PM" class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]">
                    </div>
                    <div class="flex items-center pt-6">
                        <input v-model="form.emergency" type="checkbox" id="emergency" class="rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer mr-2">
                        <label for="emergency" class="text-sm text-gray-700 cursor-pointer">24/7 Emergency Service</label>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Notes</label>
                    <textarea v-model="form.notes" rows="2" placeholder="Additional notes..." class="w-full px-3 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"></textarea>
                </div>
            </div>
        </div>
    </div>
</template>
