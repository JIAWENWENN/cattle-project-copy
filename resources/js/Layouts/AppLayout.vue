<script setup>
import Sidebar from '@/Components/Sidebar.vue';
import { usePage, router } from '@inertiajs/vue3';
import { ChevronRight, LogOut, Camera } from 'lucide-vue-next';
import NotificationPopup from '@/Components/NotificationPopup.vue';
import { ref, computed } from 'vue';

defineProps({
    title: String,
    parent: String,
    parentUrl: {
        type: String,
        default: '/dashboard'
    }
});

const page = usePage();
const showUserMenu = ref(false);
const isLoggingOut = ref(false);
const showPhotoModal = ref(false);
const selectedFile = ref(null);
const selectedPreview = ref(null);
const isUploading = ref(false);
const dropzoneRef = ref(null);
const allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
const allowedFileExtensions = '.jpg,.jpeg,.png,.gif,.webp,.bmp';
const allowedExtensionsList = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
const maxPhotoSizeBytes = 4 * 1024 * 1024;

const isAllowedPhoto = (file) => {
    const mimeAllowed = file.type ? allowedMimeTypes.includes(file.type.toLowerCase()) : false;
    const ext = file.name?.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
    const extensionAllowed = allowedExtensionsList.includes(ext);
    return mimeAllowed || extensionAllowed;
};

const validateAndSetFile = (file) => {
    if (!file) return;

    if (file.size > maxPhotoSizeBytes) {
        alert('File is too large. Maximum size is 4MB.');
        return;
    }

    if (!isAllowedPhoto(file)) {
        alert('Invalid photo format. Allowed formats: JPG, JPEG, PNG, GIF, WEBP, BMP.');
        return;
    }

    selectedFile.value = file;

    const reader = new FileReader();
    reader.onload = (e) => {
        selectedPreview.value = e.target?.result || null;
    };
    reader.readAsDataURL(file);
};

const userData = ref({
    name: page.props.auth?.user?.name || 'User',
    email: page.props.auth?.user?.email || '',
    role: page.props.auth?.user?.role || 'Staff',
    profile_photo: page.props.auth?.user?.profile_photo || null
});

const profilePhotoUrl = computed(() => {
    if (userData.value.profile_photo) {
        return `/storage/${userData.value.profile_photo}`;
    }
    return null;
});

const userInitials = computed(() => {
    return userData.value.name.charAt(0).toUpperCase();
});

const logout = () => {
    if (isLoggingOut.value) return;

    isLoggingOut.value = true;
    showUserMenu.value = false;

    router.post('/logout');
};

const openPhotoModal = () => {
    showUserMenu.value = false;
    showPhotoModal.value = true;
    selectedFile.value = null;
    selectedPreview.value = null;
};

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    validateAndSetFile(file);
    event.target.value = '';
};

const handleDrop = (event) => {
    event.preventDefault();
    const file = event.dataTransfer.files[0];
    validateAndSetFile(file);
};

const handleDragOver = (event) => {
    event.preventDefault();
};

const uploadPhoto = async () => {
    if (!selectedFile.value || isUploading.value) return;
    
    isUploading.value = true;
    const formData = new FormData();
    formData.append('photo', selectedFile.value);
    
    try {
        const response = await fetch('/profile/photo', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            const photoPath = data.photo_path || (data.photo_url ? data.photo_url.replace('/storage/', '') : null);
            userData.value.profile_photo = photoPath;
            // Update page props
            if (page.props.auth) {
                page.props.auth.user.profile_photo = photoPath;
            }
            alert(data.message || 'Profile photo updated successfully!');
            closeModal();
        } else {
            alert(data.message || 'Failed to upload photo');
        }
    } catch (error) {
        console.error('Upload error:', error);
        alert('Failed to upload photo');
    } finally {
        isUploading.value = false;
    }
};

const deletePhoto = async () => {
    if (!confirm('Are you sure you want to remove your profile photo?')) return;
    
    isUploading.value = true;
    try {
        const response = await fetch('/profile/photo', {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            userData.value.profile_photo = null;
            if (page.props.auth) {
                page.props.auth.user.profile_photo = null;
            }
            alert(data.message || 'Profile photo removed successfully!');
            closeModal();
        } else {
            alert(data.message || 'Failed to remove photo');
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('Failed to remove photo');
    } finally {
        isUploading.value = false;
    }
};

const closeModal = () => {
    showPhotoModal.value = false;
    selectedFile.value = null;
    selectedPreview.value = null;
};

const fetchProfileData = async () => {
    try {
        const response = await fetch('/profile/photo');
        const data = await response.json();
        if (data.photo_url) {
            userData.value.profile_photo = data.photo_url.replace('/storage/', '');
        }
    } catch (error) {
        console.error('Failed to fetch profile data:', error);
    }
};
</script>

<template>
    <div class="h-screen bg-gray-100 flex overflow-hidden">
        <Sidebar />

        <div class="flex-1 ml-64 flex flex-col overflow-hidden">
            <!-- Top Navbar -->
            <header class="sticky top-0 h-16 bg-white border-b border-gray-200 flex items-center justify-between px-8 z-20">
                <div class="flex items-center text-sm text-gray-500">
                    <template v-if="parent">
                        <a :href="parentUrl" class="text-gray-400 hover:text-gray-700 transition-colors">{{ parent }}</a>
                        <ChevronRight class="w-4 h-4 mx-2 text-gray-300" />
                        <span class="text-gray-800 font-medium">{{ title || 'Page' }}</span>
                    </template>
                    <template v-else>
                        <span class="text-gray-800 font-medium">{{ title || 'Page' }}</span>
                    </template>
                </div>
                <div class="flex items-center gap-6">
                    <NotificationPopup />

                    <!-- User Dropdown (Modified) -->
                    <div class="relative flex items-center gap-3 pl-6 border-l border-gray-100">
                        <div class="text-right hidden md:block">
                            <div class="text-sm font-bold text-gray-900 leading-tight">{{ page.props.auth?.user?.name || 'Admin' }}</div>
                            <div class="text-xs text-gray-500 leading-tight">{{ page.props.auth?.user?.role || 'Admin' }}</div>
                        </div>
                        <button
                            @click="showUserMenu = !showUserMenu"
                            class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm shadow-md hover:ring-4 ring-gray-100 transition-all overflow-hidden"
                        >
                            <img 
                                v-if="profilePhotoUrl" 
                                :src="profilePhotoUrl" 
                                alt="Profile" 
                                class="w-full h-full object-cover"
                            />
                            <span v-else>{{ userInitials }}</span>
                        </button>

                        <!-- Logout Dropdown -->
                        <transition name="dropdown">
                            <div
                                v-if="showUserMenu"
                                class="absolute right-0 top-12 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50"
                            >
                                <button
                                    @click="openPhotoModal"
                                    class="w-full flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                                >
                                    <Camera class="w-4 h-4" />
                                    Change Photo
                                </button>
                                <button
                                    @click="logout"
                                    class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors"
                                >
                                    <LogOut class="w-4 h-4" />
                                    Logout
                                </button>
                            </div>
                        </transition>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-8 bg-[#f4f5f7] overflow-auto">
                <slot />
            </main>
        </div>
    </div>

    <!-- Profile Photo Modal -->
    <Teleport to="body">
        <div v-if="showPhotoModal" class="fixed inset-0 z-[100] flex items-center justify-center">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/50" @click="closeModal"></div>
            
            <!-- Modal -->
            <div class="relative bg-white w-full max-w-[480px] rounded-[3px] overflow-hidden shadow-2xl">
                <!-- Header -->
                <div class="bg-[#3da3d9] text-white px-5 py-3">
                    <h2 class="text-[17px] font-normal tracking-wide">Change your profile photo</h2>
                </div>

                <!-- Body -->
                <div class="p-5">
                    <!-- Dropzone -->
                    <div 
                        ref="dropzoneRef"
                        @drop="handleDrop"
                        @dragover="handleDragOver"
                        class="relative border-[1.5px] border-dashed border-[#e0e0e0] p-10 flex flex-col items-center justify-center transition-colors duration-200"
                        style="cursor: pointer;"
                        :class="{ 'bg-[#f8fcff] border-[#3da3d9]': selectedFile }"
                    >
                        <!-- Show preview if file selected -->
                        <template v-if="selectedFile">
                            <img 
                                :src="selectedPreview" 
                                alt="Preview" 
                                class="w-32 h-32 object-cover rounded-full mb-4 border-4 border-[#3da3d9]"
                            />
                            <h3 class="text-[#3da3d9] text-[20px] mb-1 truncate w-full text-center">{{ selectedFile.name }}</h3>
                            <p class="text-[#888] text-[13px] mb-6">{{ (selectedFile.size / (1024 * 1024)).toFixed(2) }} MB</p>
                        </template>
                        <template v-else>
                            <!-- Camera Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="85" height="85" viewBox="0 0 24 24" fill="#363636" class="mb-4">
                                <path d="M4.5 6.5h2.8l1.5-2h6.4l1.5 2h2.8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4.5a2 2 0 0 1-2-2v-10a2 2 0 0 1 2-2zm7.5 11a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9zm0-2.5a2 2 0 1 1 0-4 2 2 0 0 1 0 4z"/>
                            </svg>
                            <h3 class="text-[#333] text-[20px] mb-1">Upload your photo here</h3>
                            <p class="text-[#888] text-[13px] mb-1">Allowed: JPG, JPEG, PNG, GIF, WEBP, BMP</p>
                            <p class="text-[#888] text-[13px] mb-6">( max: 4MB )</p>
                        </template>

                        <!-- Hidden File Input -->
                        <input 
                            type="file" 
                            id="photo-file-input"
                            class="absolute inset-0 opacity-0 cursor-pointer" 
                            :accept="allowedFileExtensions"
                            @change="handleFileSelect"
                        >
                        
                        <!-- Upload Button -->
                        <label 
                            for="photo-file-input"
                            id="upload-btn"
                            class="bg-[#3da3d9] hover:bg-[#3492c4] text-white px-8 py-[7px] rounded-[3px] text-[14px] transition-colors focus:outline-none focus:ring-2 focus:ring-[#3da3d9] focus:ring-opacity-50 cursor-pointer z-10"
                        >
                            {{ selectedFile ? 'Change Photo' : 'Upload' }}
                        </label>
                    </div>

                    <!-- Footer -->
                    <div class="mt-5 flex justify-between">
                        <button 
                            v-if="profilePhotoUrl && !selectedFile"
                            @click="deletePhoto"
                            :disabled="isUploading"
                            class="px-6 py-[7px] rounded-[3px] text-[14px] text-red-600 hover:bg-red-50 transition-colors focus:outline-none disabled:opacity-50"
                        >
                            {{ isUploading ? 'Removing...' : 'Remove Photo' }}
                        </button>
                        <div class="flex justify-end space-x-2 ml-auto">
                            <button 
                                @click="uploadPhoto" 
                                :disabled="!selectedFile || isUploading"
                                class="px-6 py-[7px] rounded-[3px] text-[14px] transition-colors focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="selectedFile ? 'bg-[#3da3d9] hover:bg-[#3492c4] text-white shadow-sm' : 'bg-[#9bcde9] text-white cursor-not-allowed'"
                            >
                                {{ isUploading ? 'Applying...' : 'Apply' }}
                            </button>
                            <button 
                                @click="closeModal"
                                class="bg-[#eeeeee] hover:bg-[#e4e4e4] text-[#555] px-6 py-[7px] rounded-[3px] text-[14px] transition-colors focus:outline-none"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.dropdown-enter-active, .dropdown-leave-active {
    transition: all 0.2s ease;
}
.dropdown-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
