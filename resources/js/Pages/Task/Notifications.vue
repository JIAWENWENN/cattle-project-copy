<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Bell, CheckCircle, Clock, Users, CheckCheck, ChevronLeft, ChevronRight } from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h(AppLayout, { title: 'Task Notifications', parent: 'Task', parentUrl: '/task' }, () => page)
});

const props = defineProps({
    notifications: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, per_page: 50, total: 0, from: null, to: null }) },
});

const selectedIds = ref([]);

const currentPage = computed(() => Number(props.pagination?.current_page ?? 1));
const totalPages = computed(() => {
    const total = Number(props.pagination?.last_page ?? 1);
    return total > 0 ? total : 1;
});
const itemsPerPage = computed(() => Number(props.pagination?.per_page ?? 50));
const showBulkActions = ref(false);
const unreadNotifications = computed(() => props.notifications.filter((n) => !n.is_read));

const allSelected = computed(() => unreadNotifications.value.length > 0 && selectedIds.value.length === unreadNotifications.value.length);

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = unreadNotifications.value.map((n) => n.id);
    }
};

const toggleSelect = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(idx, 1);
};

const markOneComplete = (notification) => {
    router.post(route('task-notifications.read', notification.id), {}, { preserveScroll: true });
};

const markSelectedComplete = () => {
    if (!selectedIds.value.length) return;
    selectedIds.value.forEach((id) => {
        router.post(route('task-notifications.read', id), {}, { preserveScroll: true, preserveState: true });
    });
    setTimeout(() => router.reload({ preserveScroll: true }), 250);
};

const markAllComplete = () => {
    router.post(route('task-notifications.mark-all-read'), {}, { preserveScroll: true });
};

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value).toLocaleDateString();
};

const goToPage = (page) => {
    if (page < 1 || page > totalPages.value) return;
    router.get(route('task-notifications.page'), { page }, { preserveScroll: true });
};

const previousPage = () => goToPage(currentPage.value - 1);
const nextPage = () => goToPage(currentPage.value + 1);
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Task Notifications</h1>
                <p class="text-sm text-gray-500 mt-1">Only tasks assigned to you are listed here.</p>
            </div>
            <div class="flex gap-2">
                <button
                    @click="markSelectedComplete"
                    :disabled="selectedIds.length === 0"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                >
                    <CheckCircle class="w-4 h-4" />
                    Mark Selected Complete
                </button>
                <button
                    @click="markAllComplete"
                    :disabled="unreadNotifications.length === 0"
                    class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] disabled:opacity-50"
                >
                    <CheckCheck class="w-4 h-4" />
                    Mark All Complete
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-4 shadow-sm">
                <div class="w-12 h-12 rounded-full bg-[#34554a] flex items-center justify-center">
                    <Bell class="w-6 h-6 text-white" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Unread Notifications</p>
                    <p class="text-3xl font-black text-gray-900">{{ unreadNotifications.length }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-4 shadow-sm">
                <div class="w-12 h-12 rounded-full bg-amber-500 flex items-center justify-center">
                    <Clock class="w-6 h-6 text-white" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Pending Assigned</p>
                    <p class="text-3xl font-black text-gray-900">{{ unreadNotifications.filter(n => n.task?.status !== 'completed').length }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-4 shadow-sm">
                <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center">
                    <Users class="w-6 h-6 text-white" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Selected</p>
                    <p class="text-3xl font-black text-gray-900">{{ selectedIds.length }}</p>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Toggle -->
        <div class="mb-4 flex justify-end">
            <button
                @click="showBulkActions = !showBulkActions"
                :class="showBulkActions ? 'bg-[#34554a] text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
            >
                {{ showBulkActions ? 'Bulk Actions' : 'Bulk Actions' }}
            </button>
        </div>

        <!-- Bulk Action Toolbar -->
        <div v-if="showBulkActions && selectedIds.length > 0" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-red-800">
                    {{ selectedIds.length }} selected
                </span>
            </div>
            <div class="flex items-center gap-3">
                <button 
                    @click="selectedIds = []"
                    class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors"
                >
                    Clear Selection
                </button>
                <button 
                    @click="markSelectedComplete"
                    class="flex items-center gap-2 px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm font-medium hover:bg-[#2a443b] transition-colors"
                >
                    <CheckCircle class="w-4 h-4" />
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th v-if="showBulkActions" class="p-4 w-12">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    @change="toggleSelectAll"
                                    class="rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                >
                            </th>
                            <th class="p-4 font-semibold">Notification</th>
                            <th class="p-4 font-semibold">Task Title</th>
                            <!-- Removed Task Status column -->
                            <th class="p-4 font-semibold">Priority</th>
                            <th class="p-4 font-semibold">Due Date</th>
                            <th class="p-4 font-semibold">Assigned By</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm text-gray-700">
                        <tr v-for="item in notifications" :key="item.id" class="hover:bg-gray-50 transition-colors">
                            <td v-if="showBulkActions" class="p-4">
                                <input
                                    type="checkbox"
                                    :checked="selectedIds.includes(item.id)"
                                    @change="toggleSelect(item.id)"
                                    class="rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                >
                            </td>
                            <td class="p-4">
                                <div class="font-medium text-gray-900">{{ item.title }}</div>
                                <div class="text-xs text-gray-500">{{ item.message }}</div>
                            </td>
                            <td class="p-4 font-medium text-gray-900">{{ item.task?.title || '-' }}</td>
                            
                            <td class="p-4">{{ item.task?.priority || '-' }}</td>
                            <td class="p-4">{{ formatDate(item.task?.due_date) }}</td>
                            <td class="p-4">{{ item.task?.assignor?.name || '-' }}</td>
                            <td class="p-4 text-right">
                                <button
                                    v-if="!item.is_read"
                                    @click="markOneComplete(item)"
                                    class="inline-flex items-center justify-center w-8 h-8 bg-[#34554a] text-white rounded hover:bg-[#2a443b] transition-colors"
                                    title="Mark Complete"
                                >
                                    <CheckCircle class="w-4 h-4" />
                                </button>
                                <span v-else class="inline-flex items-center justify-center w-8 h-8 bg-gray-100 text-gray-400 rounded">
                                    <CheckCircle class="w-4 h-4" />
                                </span>
                            </td>
                        </tr>
                        <tr v-if="notifications.length === 0">
                            <td :colspan="showBulkActions ? 7 : 6" class="p-8 text-center text-gray-400 italic">No assigned task notifications.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex flex-col md:flex-row justify-between items-center p-4 border-t border-gray-100 bg-gray-50">
            <div class="flex items-center gap-2 mb-4 md:mb-0">
                <button
                    type="button"
                    @click="previousPage"
                    :disabled="currentPage === 1"
                    :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                    class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                >
                    <ChevronLeft class="w-4 h-4" />
                </button>
                <div class="flex items-center gap-1">
                    <template v-for="page in totalPages" :key="page">
                        <button
                            v-if="page === 1 || page === totalPages || (page >= currentPage - 1 && page <= currentPage + 1)"
                            type="button"
                            @click="goToPage(page)"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors"
                            :class="page === currentPage ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white'"
                        >
                            {{ page }}
                        </button>
                        <span v-else-if="page === currentPage - 2 || page === currentPage + 2" class="text-gray-400">...</span>
                    </template>
                </div>
                <button
                    type="button"
                    @click="nextPage"
                    :disabled="currentPage === totalPages"
                    :class="currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                    class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                >
                    <ChevronRight class="w-4 h-4" />
                </button>
            </div>
            <div class="text-sm text-gray-500">
                Showing <span class="font-semibold text-gray-800">{{ pagination.from || 0 }}</span> to <span class="font-semibold text-gray-800">{{ pagination.to || 0 }}</span> of <span class="font-semibold text-gray-800">{{ pagination.total || 0 }}</span> records
            </div>
        </div>
    </div>
</template>
