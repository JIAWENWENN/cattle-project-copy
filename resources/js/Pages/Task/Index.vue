<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { 
    ClipboardCheck, Plus, Calendar, Clock, 
    CheckCircle, AlertCircle, Users, TrendingUp,
    ChevronRight, ChevronLeft, Eye, Edit, Trash2,
    Search, Filter, Settings2, X,
    ChevronUp, ChevronDown, ChevronsUpDown
} from 'lucide-vue-next';

const props = defineProps({
    tasks: Array,
    users: Array,
});

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Task Management', parent: 'Task', parentUrl: '/task' }, () => page)
});

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role || '');

const taskPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Task'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasTaskPermission = (action) => {
    if (String(page.props.auth?.user?.role || '').toLowerCase() === 'admin') return true;
    const perms = taskPermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canCreateTask = computed(() => hasTaskPermission('create'));
const canEditTask = computed(() => hasTaskPermission('edit'));
const canDeleteTask = computed(() => hasTaskPermission('delete'));

const searchQuery = ref('');
const statusFilter = ref('');
const priorityFilter = ref('');
const selectedYear = ref('');
const selectedMonth = ref('');
const sortKey = ref('due_date');
const sortDir = ref('asc');

const monthOptions = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];

const years = computed(() => {
    const dueDateYears = props.tasks
        .map((task) => (task.due_date ? String(new Date(task.due_date).getFullYear()) : ''))
        .filter(Boolean);
    return [...new Set(dueDateYears)].sort((a, b) => Number(b) - Number(a));
});

const bulkMode = ref(false);
const selectedIds = ref([]);

// Modal State
const showModal = ref(false);
const showViewModal = ref(false);
const editingTask = ref(null);
const viewingTask = ref(null);
const showDeleteModal = ref(false);
const deletingTask = ref(null);
const showBulkDeleteModal = ref(false);

const form = useForm({
    title: '',
    description: '',
    status: 'pending',
    priority: 'Medium',
    category: '',
    due_date: '',
    assignee_id: '',
});

const stats = computed(() => [
    { label: 'Total Tasks', value: props.tasks.length, icon: ClipboardCheck, color: 'bg-[#34554a]', change: '' },
    { label: 'Pending', value: props.tasks.filter(t => t.status === 'pending').length, icon: Clock, color: 'bg-amber-500', change: '' },
    { label: 'In Progress', value: props.tasks.filter(t => t.status === 'in_progress').length, icon: TrendingUp, color: 'bg-blue-500', change: '' },
    { label: 'Completed', value: props.tasks.filter(t => t.status === 'completed').length, icon: CheckCircle, color: 'bg-green-500', change: '' },
]);

const filteredTasks = computed(() => {
    let result = [...props.tasks];
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(t => 
            t.title.toLowerCase().includes(query) ||
            t.assignee?.name?.toLowerCase().includes(query) ||
            t.category?.toLowerCase().includes(query)
        );
    }
    
    if (statusFilter.value) {
        result = result.filter(t => t.status === statusFilter.value);
    }
    
    if (priorityFilter.value) {
        result = result.filter(t => t.priority === priorityFilter.value);
    }

    if (selectedYear.value) {
        result = result.filter((t) => {
            if (!t.due_date) return false;
            return String(new Date(t.due_date).getFullYear()) === selectedYear.value;
        });
    }

    if (selectedMonth.value) {
        const monthIndex = monthOptions.indexOf(selectedMonth.value);
        result = result.filter((t) => {
            if (!t.due_date || monthIndex < 0) return false;
            return new Date(t.due_date).getMonth() === monthIndex;
        });
    }
    
    result.sort((a, b) => {
        const direction = sortDir.value === 'asc' ? 1 : -1;

        if (sortKey.value === 'title') {
            return (a.title || '').localeCompare(b.title || '') * direction;
        }

        if (sortKey.value === 'assignee') {
            return (a.assignee?.name || '').localeCompare(b.assignee?.name || '') * direction;
        }

        if (sortKey.value === 'assignor') {
            return (a.assignor?.name || '').localeCompare(b.assignor?.name || '') * direction;
        }

        if (sortKey.value === 'due_date') {
            if (!a.due_date && !b.due_date) return 0;
            if (!a.due_date) return 1;
            if (!b.due_date) return -1;
            return (new Date(a.due_date) - new Date(b.due_date)) * direction;
        }

        if (sortKey.value === 'priority') {
            const priorityOrder = { High: 1, Medium: 2, Low: 3 };
            return ((priorityOrder[a.priority] || 99) - (priorityOrder[b.priority] || 99)) * direction;
        }

        if (sortKey.value === 'status') {
            const statusOrder = { pending: 1, in_progress: 2, completed: 3 };
            return ((statusOrder[a.status] || 99) - (statusOrder[b.status] || 99)) * direction;
        }

        return 0;
    });
    
    return result;
});

const allSelected = computed(() => {
    return selectedIds.value.length === filteredTasks.value.length && filteredTasks.value.length > 0;
});

const toggleBulkMode = () => {
    bulkMode.value = !bulkMode.value;
    if (!bulkMode.value) {
        selectedIds.value = [];
    }
};

const exitBulkMode = () => {
    bulkMode.value = false;
    selectedIds.value = [];
};

const handleEscapeKey = (event) => {
    if (event.key === 'Escape' && bulkMode.value) {
        exitBulkMode();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleEscapeKey);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEscapeKey);
});

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = filteredTasks.value.map(t => t.id);
    }
};

const toggleRowSelect = (id) => {
    const index = selectedIds.value.indexOf(id);
    if (index === -1) {
        selectedIds.value.push(id);
    } else {
        selectedIds.value.splice(index, 1);
    }
};

const getPriorityClass = (priority) => {
    const classes = {
        'High': 'bg-red-100 text-red-700',
        'Medium': 'bg-amber-100 text-amber-700',
        'Low': 'bg-green-100 text-green-700',
    };
    return classes[priority] || 'bg-gray-100 text-gray-700';
};

const getStatusClass = (status) => {
    const classes = {
        'pending': 'bg-amber-100 text-amber-700',
        'in_progress': 'bg-blue-100 text-blue-700',
        'completed': 'bg-[#1f5c19] text-white border-[#1f5c19]',
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
};

const handleSort = (key) => {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
};

const openCreateModal = () => {
    editingTask.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (task) => {
    editingTask.value = task;
    form.title = task.title;
    form.description = task.description;
    form.status = task.status;
    form.priority = task.priority;
    form.category = task.category;
    form.due_date = task.due_date;
    form.assignee_id = task.assignee_id;
    showModal.value = true;
};

const openViewModal = (task) => {
    viewingTask.value = task;
    showViewModal.value = true;
};

const submitForm = () => {
    if (editingTask.value) {
        form.put(route('task.update', editingTask.value.id), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    } else {
        form.post(route('task.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    }
};

const markTaskCompleted = (task) => {
    const payload = {
        title: task.title,
        description: task.description,
        status: 'completed',
        priority: task.priority,
        category: task.category,
        due_date: task.due_date,
        assignee_id: task.assignee_id,
    };

    router.put(route('task.update', task.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            fetch(route('task-notifications.read-by-task'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ task_id: task.id }),
            });
        },
    });
};

const requestDeleteTask = (task) => {
    deletingTask.value = task;
    showDeleteModal.value = true;
};

const confirmDeleteTask = () => {
    if (!deletingTask.value) return;

    router.delete(route('task.destroy', deletingTask.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            deletingTask.value = null;
        }
    });
};

const deleteSelectedTasks = () => {
    if (selectedIds.value.length === 0) return;
    showBulkDeleteModal.value = true;
};

const confirmBulkDeleteTasks = () => {
    if (selectedIds.value.length === 0) {
        showBulkDeleteModal.value = false;
        return;
    }

    router.delete(route('task.bulk-destroy'), {
        data: { ids: selectedIds.value },
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkMode.value = false;
            showBulkDeleteModal.value = false;
        }
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    priorityFilter.value = '';
    selectedYear.value = '';
    selectedMonth.value = '';
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString();
};

const formatStatusLabel = (status) => {
    if (!status) return 'Pending';
    const normalized = String(status).replace(/_/g, ' ').toLowerCase();
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Task Management</h1>
                <p class="text-sm text-gray-500 mt-1">Track and manage all tasks across your operations.</p>
            </div>
            <button v-if="canCreateTask" @click="openCreateModal" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors">
                <Plus class="w-4 h-4" />
                Create Task
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div v-for="stat in stats" :key="stat.label" class="bg-white p-6 rounded-xl border border-gray-100 flex items-center gap-5 shadow-sm">
                <div :class="['w-14 h-14 rounded-full flex items-center justify-center', stat.color]">
                    <component :is="stat.icon" class="w-7 h-7 text-white" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">{{ stat.label }}</p>
                    <p class="text-3xl font-black text-gray-900">{{ stat.value }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="p-4 flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-[200px]">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                    <input 
                        v-model="searchQuery"
                        type="text" 
                        placeholder="Search tasks..." 
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                    >
                </div>
                <select 
                    v-model="statusFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
                <select 
                    v-model="priorityFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Priority</option>
                    <option value="High">High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                </select>
                <select
                    v-model="selectedYear"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Year</option>
                    <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                </select>
                <select
                    v-model="selectedMonth"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Month</option>
                    <option v-for="month in monthOptions" :key="month" :value="month">{{ month }}</option>
                </select>
                <button 
                    v-if="!bulkMode"
                    @click="toggleBulkMode"
                    class="flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm text-gray-600 hover:bg-gray-50 transition-colors"
                >
                    <Settings2 class="w-4 h-4" />
                    Bulk Action
                </button>
                <button
                    v-else
                    @click="exitBulkMode"
                    class="flex items-center gap-2 px-3 py-2 border border-red-200 rounded-lg bg-red-50 text-sm text-red-600 hover:bg-red-100 transition-colors"
                >
                    <X class="w-4 h-4" />
                    Cancel Bulk
                </button>
                <button
                    v-if="bulkMode"
                    @click="deleteSelectedTasks"
                    :disabled="selectedIds.length === 0"
                    class="flex items-center gap-2 px-3 py-2 border border-red-200 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <Trash2 class="w-4 h-4" />
                    Delete Selected
                </button>
                <button 
                    v-if="searchQuery || statusFilter || priorityFilter || selectedYear || selectedMonth"
                    @click="clearFilters"
                    class="px-3 py-2 text-gray-500 text-sm hover:text-gray-700 font-medium"
                >
                    Clear
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th v-if="bulkMode" class="p-4 w-12">
                                <input 
                                    type="checkbox" 
                                    :checked="allSelected"
                                    @change="toggleSelectAll"
                                    class="rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                >
                            </th>
                            <th class="p-4 font-semibold">
                                <button type="button" @click="handleSort('title')" class="inline-flex items-center gap-2">
                                    <span>Task</span>
                                    <span v-if="sortKey === 'title'"><ChevronUp v-if="sortDir === 'asc'" class="w-4 h-4" /><ChevronDown v-else class="w-4 h-4" /></span>
                                    <ChevronsUpDown v-else class="w-4 h-4 opacity-40" />
                                </button>
                            </th>
                            <th class="p-4 font-semibold">
                                <button type="button" @click="handleSort('assignee')" class="inline-flex items-center gap-2">
                                    <span>Assignee</span>
                                    <span v-if="sortKey === 'assignee'"><ChevronUp v-if="sortDir === 'asc'" class="w-4 h-4" /><ChevronDown v-else class="w-4 h-4" /></span>
                                    <ChevronsUpDown v-else class="w-4 h-4 opacity-40" />
                                </button>
                            </th>
                            <th class="p-4 font-semibold">
                                <button type="button" @click="handleSort('assignor')" class="inline-flex items-center gap-2">
                                    <span>Assigned By</span>
                                    <span v-if="sortKey === 'assignor'"><ChevronUp v-if="sortDir === 'asc'" class="w-4 h-4" /><ChevronDown v-else class="w-4 h-4" /></span>
                                    <ChevronsUpDown v-else class="w-4 h-4 opacity-40" />
                                </button>
                            </th>
                            <th class="p-4 font-semibold">
                                <button type="button" @click="handleSort('due_date')" class="inline-flex items-center gap-2">
                                    <span>Due Date</span>
                                    <span v-if="sortKey === 'due_date'"><ChevronUp v-if="sortDir === 'asc'" class="w-4 h-4" /><ChevronDown v-else class="w-4 h-4" /></span>
                                    <ChevronsUpDown v-else class="w-4 h-4 opacity-40" />
                                </button>
                            </th>
                            <th class="p-4 font-semibold">
                                <button type="button" @click="handleSort('priority')" class="inline-flex items-center gap-2">
                                    <span>Priority</span>
                                    <span v-if="sortKey === 'priority'"><ChevronUp v-if="sortDir === 'asc'" class="w-4 h-4" /><ChevronDown v-else class="w-4 h-4" /></span>
                                    <ChevronsUpDown v-else class="w-4 h-4 opacity-40" />
                                </button>
                            </th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm text-gray-700">
                        <tr v-for="task in filteredTasks" :key="task.id" class="transition-colors">
                            <td v-if="bulkMode" class="p-4">
                                <input 
                                    type="checkbox" 
                                    :checked="selectedIds.includes(task.id)"
                                    @change="toggleRowSelect(task.id)"
                                    class="rounded border-gray-300 text-[#34554a] focus:ring-[#34554a] cursor-pointer"
                                >
                            </td>
                            <td class="p-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ task.title }}</p>
                                    <p class="text-xs text-gray-400 capitalize">{{ task.category || 'No Category' }} • #{{ task.id }}</p>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="font-medium text-gray-900">{{ task.assignee?.name || 'Unassigned' }}</span>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900">{{ task.assignor?.name || 'N/A' }}</span>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="font-medium text-gray-900">{{ formatDate(task.due_date) }}</span>
                            </td>
                            <td class="p-4">
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold', getPriorityClass(task.priority)]">
                                    {{ task.priority }}
                                </span>
                            </td>
                            <td class="p-4 text-right flex justify-end gap-1">
                                <button
                                    v-if="task.status !== 'completed'"
                                    @click="markTaskCompleted(task)"
                                    class="w-7 h-7 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors"
                                    title="Mark completed"
                                >
                                    <CheckCircle class="w-3.5 h-3.5" />
                                </button>
                                <button @click="openViewModal(task)" class="w-7 h-7 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                                    <Eye class="w-3.5 h-3.5" />
                                </button>
                                <button @click="openEditModal(task)" class="w-7 h-7 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                                    <Edit class="w-3.5 h-3.5" />
                                </button>
                                <button @click="requestDeleteTask(task)" class="w-7 h-7 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="filteredTasks.length === 0">
                            <td :colspan="bulkMode ? 7 : 6" class="p-8 text-center text-gray-400 italic">
                                <ClipboardCheck class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <p class="text-sm">No tasks found</p>
                                <button @click="openCreateModal" class="mt-2 text-[#34554a] text-sm font-medium hover:underline">
                                    Create your first task
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 border-t bg-gray-50 flex items-center justify-between">
                <p class="text-sm text-gray-500 font-medium">Showing {{ filteredTasks.length }} tasks</p>
                <div class="flex items-center gap-2">
                    <button class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-50 transition-all" disabled>
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <button class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg bg-white text-gray-600 hover:bg-gray-50 transition-all">
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Task View Modal (Medication-style) -->
        <div v-if="showViewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm transition-all" @click.self="showViewModal = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden flex flex-col border border-gray-100">
                <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-900">Task Details</h3>
                    <button @click="showViewModal = false" class="text-gray-400 hover:text-gray-600 transition-colors"><X class="w-5 h-5" /></button>
                </div>
                <div class="p-6 space-y-4 bg-white">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-16 h-16 bg-blue-50 text-[#34554a] rounded-full flex items-center justify-center shadow-inner"><ClipboardCheck class="w-8 h-8" /></div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 tracking-tight">{{ viewingTask?.title }}</h4>
                            <p class="text-sm text-gray-500 font-medium">{{ viewingTask?.category || 'No Category' }}</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 tracking-wide">Priority</span>
                            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black border', getPriorityClass(viewingTask?.priority)]">{{ viewingTask?.priority || '-' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 tracking-wide">Status</span>
                            <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black border', getStatusClass(viewingTask?.status)]">{{ formatStatusLabel(viewingTask?.status) }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 tracking-wide">Description</span>
                            <span class="text-sm font-semibold text-gray-900 text-right max-w-[60%] whitespace-pre-wrap">{{ viewingTask?.description || '-' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 tracking-wide">Due Date</span>
                            <span class="text-sm font-semibold text-gray-900">{{ formatDate(viewingTask?.due_date) }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 tracking-wide">Assignee</span>
                            <span class="text-sm font-semibold text-gray-900">{{ viewingTask?.assignee?.name || 'Unassigned' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 tracking-wide">Assigned By</span>
                            <span class="text-sm font-semibold text-gray-900">{{ viewingTask?.assignor?.name || 'Self/System' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 border-gray-100">
                            <span class="text-xs font-bold text-gray-400 tracking-wide">Created By</span>
                            <span class="text-sm font-semibold text-gray-900">{{ viewingTask?.creator?.name || 'System' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 border-gray-100 last:border-0">
                            <span class="text-xs font-bold text-gray-400 tracking-wide">Updated By</span>
                            <span class="text-sm font-semibold text-gray-900">{{ viewingTask?.updater?.name || 'None' }}</span>
                        </div>
                    </div>
                </div>
                <div class="p-4 bg-gray-50 flex justify-end rounded-b-xl border-t">
                    <button @click="showViewModal = false" class="px-6 py-2 border bg-white rounded-lg text-sm font-bold shadow-sm hover:bg-gray-50 transition-all text-gray-700">Close Details</button>
                </div>
            </div>
        </div>

        <!-- Task Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="showModal = false"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-gray-900" id="modal-title">
                                {{ editingTask ? 'Edit Task' : 'Create New Task' }}
                            </h3>
                            <button @click="showModal = false" class="text-gray-400 hover:text-gray-500">
                                <X class="w-5 h-5" />
                            </button>
                        </div>

                        <form @submit.prevent="submitForm" class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Task Title</label>
                                <input 
                                    v-model="form.title"
                                    type="text" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none transition-all"
                                    placeholder="Enter task title"
                                >
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Description</label>
                                <textarea 
                                    v-model="form.description"
                                    rows="3"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none transition-all"
                                    placeholder="Enter task details..."
                                ></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Assignee</label>
                                    <select 
                                        v-model="form.assignee_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none bg-white font-medium"
                                    >
                                        <option value="">Unassigned</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">
                                            {{ user.name }} ({{ user.role }})
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Category</label>
                                    <input 
                                        v-model="form.category"
                                        type="text" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none transition-all font-medium"
                                        placeholder="e.g. Health, Feeding"
                                    >
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Priority</label>
                                    <select 
                                        v-model="form.priority"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none bg-white font-medium"
                                    >
                                        <option value="High">High</option>
                                        <option value="Medium">Medium</option>
                                        <option value="Low">Low</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1">Status</label>
                                    <select 
                                        v-model="form.status"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none bg-white font-medium"
                                    >
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1">Due Date</label>
                                <input 
                                    v-model="form.due_date"
                                    type="date" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] outline-none transition-all"
                                >
                            </div>

                            <div class="mt-8 flex gap-3">
                                <button 
                                    type="button"
                                    @click="showModal = false"
                                    class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors font-medium"
                                >
                                    Cancel
                                </button>
                                <button 
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex-1 px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] transition-colors font-bold disabled:opacity-50"
                                >
                                    {{ editingTask ? 'Update Task' : 'Create Task' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete Task"
            message="Are you sure you want to delete this task"
            :item-name="deletingTask?.title || 'this task'"
            @close="showDeleteModal = false; deletingTask = null"
            @confirm="confirmDeleteTask"
        />

        <DeleteConfirmationModal
            :show="showBulkDeleteModal"
            title="Delete Selected Tasks"
            message="Are you sure you want to delete"
            :item-name="`${selectedIds.length} selected task(s)`"
            @close="showBulkDeleteModal = false"
            @confirm="confirmBulkDeleteTasks"
        />

    </div>
</template>
