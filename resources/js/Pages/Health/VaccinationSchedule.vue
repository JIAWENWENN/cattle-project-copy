<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { 
    Calendar, Plus, Search, Filter, ChevronRight, ChevronLeft,
    Eye, Edit, Trash2, Syringe, CheckCircle, Clock, AlertCircle
} from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Vaccination Schedule', parent: 'Health', parentUrl: '/health' }, () => page)
});

const page = usePage();
const searchQuery = ref('');
const statusFilter = ref('');
const monthFilter = ref(new Date().getMonth());

const stats = computed(() => ({
    totalScheduled: schedules.value.length,
    completed: schedules.value.filter(s => s.status === 'completed').length,
    pending: schedules.value.filter(s => s.status === 'scheduled').length,
    overdue: schedules.value.filter(s => s.status === 'overdue').length,
}));

const schedules = ref([
    { id: 1, vaccine: 'Foot and Mouth Disease (FMD)', date: '02/01/26', due_date: '02/01/26', category: 'All Cattle', batch_no: 'FMD-2026-001', administered_by: 'Dr. Ahmad', status: 'completed', notes: 'All cattle vaccinated' },
    { id: 2, vaccine: 'Brucellosis', date: '15/01/26', due_date: '15/01/26', category: 'Heifers 6-12 months', batch_no: 'BRU-2026-001', administered_by: 'Dr. Sarah', status: 'completed', notes: 'Heifers only' },
    { id: 3, vaccine: 'Anthrax', date: '01/02/26', due_date: '01/02/26', category: 'Adult Cattle', batch_no: 'ANTH-2026-001', administered_by: 'Dr. Ahmad', status: 'scheduled', notes: 'Annual vaccination' },
    { id: 4, vaccine: 'Hemorrhagic Septicemia', date: '15/02/26', due_date: '15/02/26', category: 'All Cattle', batch_no: 'HS-2026-001', administered_by: '', status: 'scheduled', notes: 'Pre-monsoon vaccination' },
    { id: 5, vaccine: 'Blackleg', date: '01/03/26', due_date: '01/03/26', category: 'Young Stock', batch_no: 'BLK-2026-001', administered_by: '', status: 'scheduled', notes: 'Calf vaccination program' },
    { id: 6, vaccine: 'Theileria', date: '15/03/26', due_date: '15/03/26', category: 'All Cattle', batch_no: 'THE-2026-001', administered_by: '', status: 'scheduled', notes: 'Tick-borne disease prevention' },
    { id: 7, vaccine: 'Rabies', date: '01/04/26', due_date: '01/04/26', category: 'All Cattle', batch_no: 'RAB-2026-001', administered_by: '', status: 'scheduled', notes: 'Annual booster' },
    { id: 8, vaccine: 'Clostridial', date: '15/04/26', due_date: '15/04/26', category: 'Adult Cattle', batch_no: 'CLO-2026-001', administered_by: '', status: 'scheduled', notes: 'Multi-valent vaccine' },
]);

const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const statusOptions = ['scheduled', 'completed', 'cancelled', 'overdue'];

const filteredSchedules = computed(() => {
    let result = [...schedules.value];
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(s => 
            s.vaccine.toLowerCase().includes(query) ||
            s.category.toLowerCase().includes(query) ||
            s.batch_no.toLowerCase().includes(query)
        );
    }
    
    if (statusFilter.value) {
        result = result.filter(s => s.status === statusFilter.value);
    }
    
    if (monthFilter.value !== '') {
        const monthIndex = parseInt(monthFilter.value);
        result = result.filter(s => {
            if (!s.due_date) return false;
            const parts = s.due_date.split('/');
            return parseInt(parts[1]) - 1 === monthIndex;
        });
    }
    
    return result;
});

const getStatusClass = (status) => {
    const classes = {
        'scheduled': 'bg-blue-100 text-blue-700',
        'completed': 'bg-[#1f5c19] text-white border-[#1f5c19]',
        'cancelled': 'bg-gray-100 text-gray-700',
        'overdue': 'bg-red-100 text-red-700',
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
};

const formatStatusLabel = (status) => {
    if (!status) return 'Pending';
    const normalized = String(status).replace(/_/g, ' ').toLowerCase();
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const viewSchedule = (schedule) => {
    router.visit(`/health/vaccination/${schedule.id}`);
};

const editSchedule = (schedule) => {
    router.visit(`/health/vaccination/${schedule.id}/edit`);
};

const deleteSchedule = (schedule) => {
    if (confirm('Are you sure you want to delete this vaccination schedule?')) {
        const index = schedules.value.findIndex(s => s.id === schedule.id);
        if (index !== -1) {
            schedules.value.splice(index, 1);
        }
    }
};

const exportSchedule = () => {
    alert('Exporting vaccination schedule...');
};
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Vaccination Schedule</h1>
                <p class="text-sm text-gray-500 mt-1">Manage and track vaccination programs for all cattle.</p>
            </div>
            <div class="flex items-center gap-3">
                <button @click="exportSchedule" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    Export
                </button>
                <button @click="$inertia.visit('/health/vaccination/create')" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors">
                    <Plus class="w-4 h-4" />
                    Add Schedule
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-[#34554a]/10 rounded-full flex items-center justify-center">
                    <Calendar class="w-7 h-7 text-[#34554a]" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Total Scheduled</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.totalScheduled }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-blue-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center">
                    <Clock class="w-7 h-7 text-blue-600" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Pending</p>
                    <p class="text-3xl font-black text-blue-600">{{ stats.pending }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-green-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-[#34554a]/10 rounded-full flex items-center justify-center">
                    <svg class="w-7 h-7" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" fill="#34554a" stroke="#34554a" aria-hidden="true">
                        <path fill="#34554a" d="M512 64a448 448 0 1 1 0 896 448 448 0 0 1 0-896zm-55.808 536.384-99.52-99.584a38.4 38.4 0 1 0-54.336 54.336l126.72 126.72a38.272 38.272 0 0 0 54.336 0l262.4-262.464a38.4 38.4 0 1 0-54.272-54.336L456.192 600.384z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Completed</p>
                    <p class="text-3xl font-black text-green-600">{{ stats.completed }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-red-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center">
                    <AlertCircle class="w-7 h-7 text-red-600" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Overdue</p>
                    <p class="text-3xl font-black text-red-600">{{ stats.overdue }}</p>
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
                        placeholder="Search vaccine, category, batch..." 
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                    >
                </div>
                <select 
                    v-model="statusFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Status</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="overdue">Overdue</option>
                </select>
                <select 
                    v-model="monthFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Months</option>
                    <option v-for="(month, index) in months" :key="month" :value="index">{{ month }}</option>
                </select>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th class="p-4 font-semibold">Vaccine</th>
                            <th class="p-4 font-semibold">Due Date</th>
                            <th class="p-4 font-semibold">Category</th>
                            <th class="p-4 font-semibold">Batch No.</th>
                            <th class="p-4 font-semibold">Administered By</th>
                            <th class="p-4 font-semibold">Status</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y text-sm text-gray-700">
                        <tr v-for="schedule in filteredSchedules" :key="schedule.id" class="hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-[#34554a]/10 rounded-lg flex items-center justify-center">
                                        <Syringe class="w-5 h-5 text-[#34554a]" />
                                    </div>
                                    <span class="font-medium text-gray-900">{{ schedule.vaccine }}</span>
                                </div>
                            </td>
                            <td class="p-4 text-gray-600">{{ schedule.due_date }}</td>
                            <td class="p-4 text-gray-600">{{ schedule.category }}</td>
                            <td class="p-4 text-gray-600 font-mono text-xs">{{ schedule.batch_no }}</td>
                            <td class="p-4 text-gray-600">{{ schedule.administered_by || '-' }}</td>
                            <td class="p-4">
                                <span :class="['px-2.5 py-1 rounded-full text-xs font-medium', getStatusClass(schedule.status)]">
                                    {{ formatStatusLabel(schedule.status) }}
                                </span>
                            </td>
                            <td class="p-4 text-right flex justify-end gap-2">
                                <button @click="viewSchedule(schedule)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors" title="View">
                                    <Eye class="w-4 h-4" />
                                </button>
                                <button @click="editSchedule(schedule)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors" title="Edit">
                                    <Edit class="w-4 h-4" />
                                </button>
                                <button @click="deleteSchedule(schedule)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors" title="Delete">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="filteredSchedules.length === 0">
                            <td colspan="7" class="p-8 text-center text-gray-400 italic">
                                <Syringe class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                <p class="text-sm">No vaccination schedules found</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 border-t bg-gray-50 flex items-center justify-between">
                <p class="text-sm text-gray-500 font-medium">Showing {{ filteredSchedules.length }} schedules</p>
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
    </div>
</template>
