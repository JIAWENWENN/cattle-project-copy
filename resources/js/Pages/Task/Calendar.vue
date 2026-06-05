<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';
import { 
    ClipboardCheck, ChevronLeft, ChevronRight, 
    Plus, Calendar as CalendarIcon, Clock, CheckCircle, Eye, X, Filter, Edit
} from 'lucide-vue-next';

const props = defineProps({
    tasks: Array,
    users: Array,
});

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Calendar View', parent: 'Task', parentUrl: '/task' }, () => page)
});

const page = usePage();
const currentDate = ref(new Date());
const selectedDate = ref(new Date());

const showViewModal = ref(false);
const showModal = ref(false);
const viewingTask = ref(null);
const editingTask = ref(null);

const form = useForm({
    title: '',
    description: '',
    status: 'pending',
    priority: 'Medium',
    category: '',
    due_date: '',
    assignee_id: '',
});

const currentMonth = computed(() => currentDate.value.toLocaleString('default', { month: 'long', year: 'numeric' }));

const daysInMonth = computed(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const days = [];
    
    for (let i = 0; i < firstDay.getDay(); i++) {
        days.push({ date: null, isCurrentMonth: false });
    }
    
    for (let i = 1; i <= lastDay.getDate(); i++) {
        days.push({ date: i, isCurrentMonth: true, isToday: isToday(year, month, i) });
    }
    
    return days;
});

const parseLocalDate = (value) => {
    if (!value) return null;

    if (typeof value === 'string') {
        const datePart = value.split('T')[0];
        const match = datePart.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (match) {
            const year = Number(match[1]);
            const month = Number(match[2]) - 1;
            const day = Number(match[3]);
            return new Date(year, month, day);
        }
    }

    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const normalizeTaskDate = (date) => {
    if (!date) return null;
    try {
        if (typeof date === 'string') {
            return date.split('T')[0];
        }

        const d = parseLocalDate(date);
        if (!d) return null;
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    } catch (e) {
        return null;
    }
};

const getTasksForDate = (day) => {
    if (!day) return [];
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    const checkDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    
    return props.tasks.filter(t => normalizeTaskDate(t.due_date) === checkDate);
};

const isToday = (year, month, day) => {
    const today = new Date();
    return today.getFullYear() === year && today.getMonth() === month && today.getDate() === day;
};

const prevMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1);
};

const goToToday = () => {
    currentDate.value = new Date();
    selectedDate.value = new Date();
};

const getPriorityColor = (priority) => {
    const colors = {
        'High': 'bg-red-500',
        'Medium': 'bg-amber-500',
        'Low': 'bg-green-500',
    };
    return colors[priority] || 'bg-gray-500';
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

const openViewModal = (task) => {
    viewingTask.value = task;
    showViewModal.value = true;
};

const selectedDateTasks = computed(() => {
    if (!selectedDate.value) return [];
    const d = selectedDate.value;
    const checkDate = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    return props.tasks.filter(t => normalizeTaskDate(t.due_date) === checkDate);
});

const selectDate = (day) => {
    if (day && day.isCurrentMonth) {
        selectedDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth(), day.date);
    }
};

const formatDate = (date) => {
    if (!date) return 'N/A';
    const parsed = parseLocalDate(date);
    if (!parsed) return 'N/A';
    return parsed.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const formatStatusLabel = (status) => {
    if (!status) return 'Pending';
    const normalized = String(status).replace(/_/g, ' ').toLowerCase();
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const goToEdit = (task) => {
    openEditModal(task);
};

const openCreateModal = () => {
    editingTask.value = null;
    form.reset();
    form.status = 'pending';
    form.priority = 'Medium';
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
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Calendar View</h1>
                <p class="text-sm text-gray-500 mt-1">View and manage tasks on a calendar.</p>
            </div>
            <button @click="goToToday" class="px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 font-medium hover:bg-gray-50 transition-colors">
                Today
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <div class="lg:col-span-3 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                    <div class="flex items-center gap-4">
                        <button @click="prevMonth" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                            <ChevronLeft class="w-5 h-5 text-gray-600" />
                        </button>
                        <h2 class="text-lg font-bold text-gray-900">{{ currentMonth }}</h2>
                        <button @click="nextMonth" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                            <ChevronRight class="w-5 h-5 text-gray-600" />
                        </button>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <span class="text-gray-600">High</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                            <span class="text-gray-600">Medium</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                            <span class="text-gray-600">Low</span>
                        </div>
                    </div>
                </div>
                
                <div class="p-4">
                    <div class="grid grid-cols-7 gap-1 mb-2">
                        <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" class="text-center text-xs font-semibold text-gray-500 py-2">
                            {{ day }}
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-1">
                        <div 
                            v-for="(day, index) in daysInMonth" 
                            :key="index"
                            class="min-h-[120px] p-2 rounded-lg border transition-colors cursor-pointer overflow-hidden group"
                            :class="[
                                day.isCurrentMonth ? 'bg-white hover:bg-gray-50' : 'bg-gray-50 text-gray-400',
                                day.isToday ? 'ring-2 ring-inset ring-[#34554a]' : 'border-gray-100',
                                selectedDate && day.date === selectedDate.getDate() && day.isCurrentMonth ? 'bg-[#34554a]/5 border-[#34554a]' : ''
                            ]"
                            @click="selectDate(day)"
                        >
                            <div v-if="day.date" class="flex justify-between items-start mb-1">
                                <span 
                                    class="text-sm font-bold"
                                    :class="day.isToday ? 'text-[#34554a]' : 'text-gray-700'"
                                >
                                    {{ day.date }}
                                </span>
                                <div v-if="getTasksForDate(day.date).length > 0" class="text-[9px] font-black text-white bg-gray-400 rounded px-1 min-w-[14px] text-center">
                                    {{ getTasksForDate(day.date).length }}
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div 
                                    v-for="task in getTasksForDate(day.date)" 
                                    :key="task.id"
                                    class="text-[10px] px-1.5 py-0.5 rounded text-white truncate font-black tracking-tight cursor-help shadow-sm group-hover:shadow-md transition-shadow"
                                    :class="getPriorityColor(task.priority)"
                                    :title="`${task.title}\nAssignee: ${task.assignee?.name || 'Unassigned'}\nFrom: ${task.assignor?.name || 'N/A'}`"
                                    @click.stop="openViewModal(task)"
                                >
                                    {{ task.title }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col h-full min-h-[500px]">
                <div class="p-4 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-sm font-black text-gray-900 flex items-center gap-2">
                        <CalendarIcon class="w-4 h-4 text-[#34554a]" />
                        {{ selectedDate ? selectedDate.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : 'Select a date' }}
                    </h3>
                </div>
                <div class="p-4 flex-1 overflow-y-auto">
                    <div v-if="selectedDateTasks.length" class="space-y-4">
                        <div 
                            v-for="task in selectedDateTasks" 
                            :key="task.id"
                            class="p-4 rounded-xl border border-gray-100 hover:border-[#34554a]/30 hover:shadow-lg transition-all cursor-pointer bg-white group"
                            @click="openViewModal(task)"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <div :class="['w-2.5 h-2.5 rounded-full shadow-sm', getPriorityColor(task.priority)]"></div>
                                    <span class="text-[9px] font-black tracking-widest text-gray-500">
                                        {{ task.priority }} Priority
                                    </span>
                                </div>
                                <button class="text-gray-400 group-hover:text-[#34554a] transition-colors">
                                    <Eye class="w-4 h-4" />
                                </button>
                            </div>
                            <p class="font-black text-gray-900 text-sm leading-tight mb-2 group-hover:text-[#34554a] transition-colors line-clamp-2">{{ task.title }}</p>
                            <p class="text-xs text-gray-500 line-clamp-2 mb-4">{{ task.description || 'No description provided' }}</p>
                            
                            <div class="flex items-center justify-between border-t pt-3 border-gray-50">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-1.5">
                                        <div class="flex flex-col">
                                            <span class="text-[8px] font-black text-gray-400 leading-none mb-0.5">To</span>
                                            <span class="text-[10px] font-bold text-gray-700 leading-none">{{ task.assignee?.name || 'Unassigned' }}</span>
                                        </div>
                                    </div>
                                    <div class="w-[1px] h-6 bg-gray-100"></div>
                                    <div class="flex flex-col">
                                        <span class="text-[8px] font-black text-gray-400 leading-none mb-0.5">From</span>
                                        <span class="text-[10px] font-bold text-gray-600 leading-none">{{ task.assignor?.name || 'N/A' }}</span>
                                    </div>
                                </div>
                                <span :class="['px-2 py-0.5 rounded-full text-[9px] font-black tracking-wider', getStatusClass(task.status)]">
                                    {{ formatStatusLabel(task.status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 px-6">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-gray-100">
                            <CalendarIcon class="w-8 h-8 text-gray-300" />
                        </div>
                        <p class="text-sm font-black text-gray-900 uppercase tracking-tight">No tasks scheduled</p>
                        <p class="text-xs text-gray-500 mt-2 font-medium">Select another date or manage tasks to add more entries.</p>
                        <button @click="openCreateModal" class="mt-6 text-[#34554a] text-[10px] font-black uppercase tracking-widest hover:underline border-t pt-4 w-full text-center">
                            + Open Task Manager
                        </button>
                    </div>
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
    </div>
</template>
