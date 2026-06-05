<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { 
    AlertTriangle, AlertCircle, CheckCircle, Clock, Plus,
    Search, Filter, ChevronRight, Eye, Edit, Trash2, Bell,
    Thermometer, Activity, HeartPulse
} from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Health Alerts', parent: 'Health', parentUrl: '/health' }, () => page)
});

const page = usePage();
const searchQuery = ref('');
const severityFilter = ref('');
const statusFilter = ref('');

const stats = computed(() => ({
    critical: alerts.value.filter(a => a.severity === 'critical').length,
    warning: alerts.value.filter(a => a.severity === 'warning').length,
    resolved: alerts.value.filter(a => a.status === 'resolved').length,
    pending: alerts.value.filter(a => a.status === 'pending').length,
}));

const alerts = ref([
    { id: 1, date: '14/01/26', tag_no: 'BF 24/01', category: 'Anak', severity: 'critical', type: 'High Fever', description: 'Temperature 40.5°C - Immediate attention required', status: 'pending', action_taken: '' },
    { id: 2, date: '13/01/26', tag_no: 'IPEG 23/11', category: 'W/n', severity: 'warning', type: 'Not Eating', description: 'Reduced feed intake for 2 consecutive days', status: 'pending', action_taken: '' },
    { id: 3, date: '12/01/26', tag_no: 'BF 24/03', category: 'Anak', severity: 'critical', type: 'Diarrhea Severe', description: 'Acute diarrhea with signs of dehydration', status: 'resolved', action_taken: 'OTC treatment administered + fluid therapy' },
    { id: 4, date: '11/01/26', tag_no: 'SMP 24 5156', category: 'Anak', severity: 'warning', type: 'Limp/Lame', description: 'Signs of lameness in left hind leg', status: 'pending', action_taken: '' },
    { id: 5, date: '10/01/26', tag_no: 'BF 24/05', category: 'Anak', severity: 'warning', type: 'Coughing', description: 'Persistent dry cough - possible respiratory issue', status: 'in_progress', action_taken: 'Started antibiotic treatment' },
    { id: 6, date: '09/01/26', tag_no: 'BF 24/08', category: 'Cow', severity: 'critical', type: 'Mastitis', description: 'Signs of mastitis in right quarter', status: 'resolved', action_taken: 'Antibiotic injection + hot compress' },
]);

const severityOptions = ['critical', 'warning', 'info'];
const statusOptions = ['pending', 'in_progress', 'resolved'];

const filteredAlerts = computed(() => {
    let result = [...alerts.value];
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(a => 
            a.tag_no.toLowerCase().includes(query) ||
            a.type.toLowerCase().includes(query) ||
            a.description.toLowerCase().includes(query)
        );
    }
    
    if (severityFilter.value) {
        result = result.filter(a => a.severity === severityFilter.value);
    }
    
    if (statusFilter.value) {
        result = result.filter(a => a.status === statusFilter.value);
    }
    
    return result;
});

const getSeverityClass = (severity) => {
    const classes = {
        'critical': 'bg-red-100 text-red-700 border-red-200',
        'warning': 'bg-amber-100 text-amber-700 border-amber-200',
        'info': 'bg-blue-100 text-blue-700 border-blue-200',
    };
    return classes[severity] || 'bg-gray-100 text-gray-700 border-gray-200';
};

const getStatusClass = (status) => {
    const classes = {
        'pending': 'bg-amber-100 text-amber-700',
        'in_progress': 'bg-blue-100 text-blue-700',
        'resolved': 'bg-green-100 text-green-700',
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
};

const getSeverityIcon = (severity) => {
    const icons = {
        'critical': AlertTriangle,
        'warning': AlertCircle,
        'info': Activity,
    };
    return icons[severity] || AlertCircle;
};

const viewAlert = (alert) => {
    router.visit(`/health/alert/${alert.id}`);
};

const updateStatus = (alert, newStatus) => {
    const index = alerts.value.findIndex(a => a.id === alert.id);
    if (index !== -1) {
        alerts.value[index].status = newStatus;
    }
};

const deleteAlert = (alert) => {
    if (confirm('Are you sure you want to delete this alert?')) {
        const index = alerts.value.findIndex(a => a.id === alert.id);
        if (index !== -1) {
            alerts.value.splice(index, 1);
        }
    }
};
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Health Alerts</h1>
                <p class="text-sm text-gray-500 mt-1">Monitor and manage health alerts for all cattle.</p>
            </div>
            <button @click="$inertia.visit('/health/alert/create')" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors">
                <Plus class="w-4 h-4" />
                Add Alert
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-red-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center">
                    <AlertTriangle class="w-7 h-7 text-red-600" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Critical</p>
                    <p class="text-3xl font-black text-red-600">{{ stats.critical }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-amber-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center">
                    <AlertCircle class="w-7 h-7 text-amber-600" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Warning</p>
                    <p class="text-3xl font-black text-amber-600">{{ stats.warning }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center">
                    <Clock class="w-7 h-7 text-blue-600" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Pending</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.pending }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-green-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                    <CheckCircle class="w-7 h-7 text-green-600" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Resolved</p>
                    <p class="text-3xl font-black text-green-600">{{ stats.resolved }}</p>
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
                        placeholder="Search by tag, type, description..." 
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                    >
                </div>
                <select 
                    v-model="severityFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Severity</option>
                    <option value="critical">Critical</option>
                    <option value="warning">Warning</option>
                    <option value="info">Info</option>
                </select>
                <select 
                    v-model="statusFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>
        </div>

        <div class="space-y-4">
            <div v-for="alert in filteredAlerts" :key="alert.id" 
                class="bg-white rounded-xl shadow-sm border-l-4 p-4 transition-all hover:shadow-md"
                :class="{
                    'border-l-red-500': alert.severity === 'critical',
                    'border-l-amber-500': alert.severity === 'warning',
                    'border-l-blue-500': alert.severity === 'info',
                }"
            >
                <div class="flex items-start justify-between">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                            :class="{
                                'bg-red-100': alert.severity === 'critical',
                                'bg-amber-100': alert.severity === 'warning',
                                'bg-blue-100': alert.severity === 'info',
                            }"
                        >
                            <component :is="getSeverityIcon(alert.severity)" 
                                class="w-5 h-5"
                                :class="{
                                    'text-red-600': alert.severity === 'critical',
                                    'text-amber-600': alert.severity === 'warning',
                                    'text-blue-600': alert.severity === 'info',
                                }"
                            />
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-gray-900">{{ alert.type }}</span>
                                <span class="px-2 py-0.5 rounded text-xs font-medium" :class="getSeverityClass(alert.severity)">
                                    {{ alert.severity.toUpperCase() }}
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="getStatusClass(alert.status)">
                                    {{ alert.status.replace('_', ' ') }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2">{{ alert.description }}</p>
                            <div class="flex items-center gap-4 text-xs text-gray-500">
                                <span class="flex items-center gap-1">
                                    <span class="font-medium">Tag:</span> {{ alert.tag_no }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="font-medium">Category:</span> {{ alert.category }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <span class="font-medium">Date:</span> {{ alert.date }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <select 
                            v-model="alert.status"
                            @change="updateStatus(alert, alert.status)"
                            class="px-2 py-1 rounded border border-gray-200 text-xs bg-white outline-none"
                        >
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                        </select>
                        <button @click="viewAlert(alert)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                            <Eye class="w-4 h-4" />
                        </button>
                        <button @click="deleteAlert(alert)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
            
            <div v-if="filteredAlerts.length === 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                <Bell class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                <p class="text-gray-500">No health alerts found</p>
            </div>
        </div>
    </div>
</template>
