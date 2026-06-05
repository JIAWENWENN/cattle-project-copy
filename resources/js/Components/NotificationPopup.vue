<script setup>
import { onBeforeUnmount, onMounted, ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const isOpen = ref(false);
const rootRef = ref(null);

const page = usePage();
const notifications = ref([]);
const loading = ref(false);
const unreadCount = computed(() => page.props.auth?.notificationCount ?? notifications.value.length);

const canViewTaskNotifications = computed(() => {
    const user = page.props.auth?.user;
    return !!user;
});

const formatTime = (value) => {
    if (!value) return '';
    const then = new Date(value);
    const now = new Date();
    const diffMinutes = Math.floor((now - then) / 60000);
    if (diffMinutes < 60) return `${Math.max(diffMinutes, 1)} min ago`;
    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays === 1) return 'Yesterday';
    return `${diffDays} days ago`;
};

const fetchNotifications = async () => {
    loading.value = true;
    try {
        const response = await fetch(route('task-notifications.index'), {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        notifications.value = (data.notifications || []).map((item) => ({
            id: item.id,
            type: item.type || 'task_assigned',
            name: item.title || 'Task',
            message: item.message || 'No description provided.',
            time: formatTime(item.created_at),
            unread: true,
            task_id: item.task_id,
            avatar: item.user?.profile_photo || null,
        }));
    } finally {
        loading.value = false;
    }
};

const toggle = () => {
    if (!canViewTaskNotifications.value) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        fetchNotifications();
    }
};

const close = () => {
    isOpen.value = false;
};

const onDocClick = (event) => {
    if (!rootRef.value) return;
    if (!rootRef.value.contains(event.target)) {
        close();
    }
};

onMounted(() => {
    document.addEventListener('click', onDocClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
});

const openTask = async (item) => {
    try {
        await fetch(route('task-notifications.read', item.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                Accept: 'application/json',
            },
            body: JSON.stringify({}),
        });
    } catch (e) {
    }

    router.get(route('task-notifications.page'));
};
</script>

<template>
    <div v-if="canViewTaskNotifications" ref="rootRef" class="relative">
        <button
            @click.stop="toggle"
            class="relative w-16 h-16 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors flex items-center justify-center"
            type="button"
        >
            <svg class="w-12 h-12 text-[#424242]" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <mask id="badge-cutout-notif">
                        <rect width="120" height="120" fill="white" />
                        <circle cx="94" cy="20" r="34" fill="black" />
                    </mask>
                </defs>
                <g mask="url(#badge-cutout-notif)">
                    <path d="M56 30v-7a5 5 0 0 1 10 0v7c13 0 20 12 20 30s12 30 18 30H18c6 0 18-12 18-30s7-30 20-30h0Z" stroke="#424242" stroke-width="7.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M50 90a12 12 0 0 0 24 0" stroke="#424242" stroke-width="7.5" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
                <circle cx="94" cy="20" r="32" fill="#FF3B00" v-if="unreadCount > 0"/>
                <text v-if="unreadCount > 0" x="94" y="20" text-anchor="middle" dominant-baseline="central" dy="2" fill="#ffffff" font-weight="800" font-size="32px">{{ unreadCount > 9 ? '9+' : unreadCount }}</text>
            </svg>
        </button>

        <transition name="notif-fade">
            <div
                v-if="isOpen"
                class="absolute right-0 top-12 w-[420px] bg-white rounded-lg shadow-[0_15px_40px_-10px_rgba(0,0,0,0.1),0_5px_15px_-5px_rgba(0,0,0,0.05)] z-50 overflow-hidden"
            >
                <div class="relative bg-[#f05a5e] px-6 py-4 flex items-center justify-between">
                    <h2 class="text-white text-[17px] font-bold">Notifications</h2>
                    <button @click="close" type="button" class="text-white/90 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18.3 5.71a1 1 0 0 0-1.41 0L12 10.59 7.11 5.7A1 1 0 1 0 5.7 7.11L10.59 12l-4.9 4.89a1 1 0 1 0 1.42 1.42L12 13.41l4.89 4.9a1 1 0 0 0 1.42-1.42L13.41 12l4.9-4.89a1 1 0 0 0-.01-1.4Z" />
                        </svg>
                    </button>
                    <div class="absolute -top-2 right-5 w-4 h-4 bg-[#f05a5e] rotate-45"></div>
                </div>

                <div>
                    <div v-if="loading" class="px-6 py-4 text-sm text-gray-500">Loading...</div>
                    <div
                        v-for="item in notifications"
                        :key="item.id"
                        class="flex items-center px-6 py-[18px] border-b border-[#f0f2f5] hover:bg-gray-50 transition-colors"
                        @click="openTask(item)"
                    >

                        <div class="flex-grow">
                            <p class="text-[15.5px] leading-snug">
                                <span class="font-bold text-black">{{ item.name }}</span>
                            </p>
                            <p class="text-[13px] text-gray-600 mt-0.5 line-clamp-2">{{ item.message }}</p>
                            <p class="text-[13.5px] text-[#9fa5ad] mt-0.5">{{ item.time }}</p>
                        </div>

                        <div class="shrink-0 ml-4 w-[9px] flex items-center justify-center">
                            <div v-if="item.unread" class="w-[9px] h-[9px] rounded-full bg-[#34b7b2]"></div>
                        </div>
                    </div>
                </div>

                <div class="text-center px-6 py-5 hover:bg-gray-50 transition-colors">
                    <a :href="route('task-notifications.page')" class="text-[#34b7b2] font-bold text-[15.5px] hover:underline underline-offset-4">See all recent activity</a>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.notif-fade-enter-active,
.notif-fade-leave-active {
    transition: all 0.2s ease;
}

.notif-fade-enter-from,
.notif-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
