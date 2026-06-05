<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { 
    Activity, Plus, Search, Filter, ChevronRight, Eye, Edit, Trash2,
    TrendingUp, TrendingDown, AlertTriangle, CheckCircle, Clock, Users,
    BarChart3, Calendar, MapPin
} from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Disease Tracking', parent: 'Health', parentUrl: '/health' }, () => page)
});

const page = usePage();
const searchQuery = ref('');
const diseaseFilter = ref('');
const statusFilter = ref('');
const periodFilter = ref('this_month');

const stats = computed(() => ({
    totalCases: cases.value.length,
    activeCases: cases.value.filter(c => c.status === 'active').length,
    recovered: cases.value.filter(c => c.status === 'recovered').length,
    mortality: cases.value.filter(c => c.status === 'mortality').length,
    recoveryRate: Math.round((cases.value.filter(c => c.status === 'recovered').length / cases.value.filter(c => c.status !== 'active').length * 100) || 0),
}));

const cases = ref([
    { id: 1, disease: 'Foot and Mouth Disease', date_onset: '02/01/26', tag_no: 'BF 24/01', category: 'Anak', location: 'Barn 3', affected_count: 1, recovered_count: 0, status: 'active', severity: 'high', treatment: 'Supportive care + antibiotics', notes: 'Isolated immediately' },
    { id: 2, disease: 'Bloat', date_onset: '05/01/26', tag_no: 'BF 24/02', category: 'W/n', location: 'Barn 1', affected_count: 1, recovered_count: 1, status: 'recovered', severity: 'critical', treatment: 'Trocarization + oil administration', notes: 'Recovered after 2 days' },
    { id: 3, disease: 'Mastitis', date_onset: '08/01/26', tag_no: 'IPEG 23/11', category: 'Cow', location: 'Milking Parlor', affected_count: 1, recovered_count: 0, status: 'active', severity: 'medium', treatment: 'Antibiotic infusion', notes: 'Under treatment' },
    { id: 4, disease: 'Diarrhea (Neonatal)', date_onset: '10/01/26', tag_no: 'BF 24/03', category: 'Anak', location: 'Calf Pen A', affected_count: 3, recovered_count: 2, status: 'active', severity: 'high', treatment: 'Fluid therapy + electrolytes', notes: 'Outbreak under control' },
    { id: 5, disease: 'Tick Fever (Anaplasmosis)', date_onset: '12/01/26', tag_no: 'SMP 24 5156', category: 'W/n', location: 'Barn 2', affected_count: 1, recovered_count: 1, status: 'recovered', severity: 'medium', treatment: 'Oxytetracycline + supportive care', notes: 'Recovered fully' },
    { id: 6, disease: 'Respiratory Infection', date_onset: '14/01/26', tag_no: 'BF 24/04', category: 'Anak', location: 'Barn 3', affected_count: 2, recovered_count: 0, status: 'active', severity: 'medium', treatment: 'Antibiotics + anti-inflammatories', notes: 'Monitoring closely' },
]);

const diseases = [
    'Foot and Mouth Disease', 'Bloat', 'Mastitis', 'Diarrhea (Neonatal)', 
    'Tick Fever (Anaplasmosis)', 'Respiratory Infection', 'Blackleg', 
    'Anthrax', 'Brucellosis', 'Rabies', 'Other'
];

const diseaseStats = computed(() => {
    const stats = {};
    cases.value.forEach(c => {
        if (!stats[c.disease]) {
            stats[c.disease] = { name: c.disease, total: 0, active: 0, recovered: 0 };
        }
        stats[c.disease].total++;
        if (c.status === 'active') stats[c.disease].active++;
        if (c.status === 'recovered') stats[c.disease].recovered++;
    });
    return Object.values(stats).sort((a, b) => b.total - a.total);
});

const filteredCases = computed(() => {
    let result = [...cases.value];
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(c => 
            c.disease.toLowerCase().includes(query) ||
            c.tag_no.toLowerCase().includes(query) ||
            c.location.toLowerCase().includes(query)
        );
    }
    
    if (diseaseFilter.value) {
        result = result.filter(c => c.disease === diseaseFilter.value);
    }
    
    if (statusFilter.value) {
        result = result.filter(c => c.status === statusFilter.value);
    }
    
    return result;
});

const getStatusClass = (status) => {
    const classes = {
        'active': 'bg-red-100 text-red-700',
        'recovered': 'bg-green-100 text-green-700',
        'mortality': 'bg-gray-100 text-gray-700',
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
};

const getSeverityClass = (severity) => {
    const classes = {
        'critical': 'bg-red-100 text-red-700 border-red-200',
        'high': 'bg-amber-100 text-amber-700 border-amber-200',
        'medium': 'bg-blue-100 text-blue-700 border-blue-200',
        'low': 'bg-green-100 text-green-700 border-green-200',
    };
    return classes[severity] || 'bg-gray-100 text-gray-700 border-gray-200';
};

const viewCase = (caseItem) => {
    router.visit(`/health/disease/${caseItem.id}`);
};

const editCase = (caseItem) => {
    router.visit(`/health/disease/${caseItem.id}/edit`);
};

const deleteCase = (caseItem) => {
    if (confirm('Are you sure you want to delete this case?')) {
        const index = cases.value.findIndex(c => c.id === caseItem.id);
        if (index !== -1) {
            cases.value.splice(index, 1);
        }
    }
};

const exportReport = () => {
    alert('Exporting disease tracking report...');
};
</script>

<template>
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Disease Tracking</h1>
                <p class="text-sm text-gray-500 mt-1">Monitor and track disease outbreaks across all cattle.</p>
            </div>
            <div class="flex items-center gap-3">
                <button @click="exportReport" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    Export Report
                </button>
                <button @click="$inertia.visit('/health/disease/create')" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors">
                    <Plus class="w-4 h-4" />
                    Report Case
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-[#34554a]/10 rounded-full flex items-center justify-center">
                    <Activity class="w-7 h-7 text-[#34554a]" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Total Cases</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.totalCases }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-red-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center">
                    <AlertTriangle class="w-7 h-7 text-red-600" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Active Cases</p>
                    <p class="text-3xl font-black text-red-600">{{ stats.activeCases }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-green-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                    <CheckCircle class="w-7 h-7 text-green-600" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Recovered</p>
                    <p class="text-3xl font-black text-green-600">{{ stats.recovered }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center">
                    <Users class="w-7 h-7 text-gray-600" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Mortality</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.mortality }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-blue-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center">
                    <TrendingUp class="w-7 h-7 text-blue-600" />
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Recovery Rate</p>
                    <p class="text-3xl font-black text-blue-600">{{ stats.recoveryRate }}%</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
            <div class="lg:col-span-3">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                    <div class="p-4 flex flex-wrap gap-3 items-center">
                        <div class="relative flex-1 min-w-[200px]">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" />
                            <input 
                                v-model="searchQuery"
                                type="text" 
                                placeholder="Search by disease, tag, location..." 
                                class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                            >
                        </div>
                        <select 
                            v-model="diseaseFilter"
                            class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                        >
                            <option value="">All Diseases</option>
                            <option v-for="disease in diseases" :key="disease" :value="disease">{{ disease }}</option>
                        </select>
                        <select 
                            v-model="statusFilter"
                            class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="recovered">Recovered</option>
                            <option value="mortality">Mortality</option>
                        </select>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead>
                                <tr class="bg-[#34554a] text-white text-sm">
                                    <th class="p-4 font-semibold">Disease</th>
                                    <th class="p-4 font-semibold">Onset Date</th>
                                    <th class="p-4 font-semibold">Tag No.</th>
                                    <th class="p-4 font-semibold">Location</th>
                                    <th class="p-4 font-semibold">Affected</th>
                                    <th class="p-4 font-semibold">Severity</th>
                                    <th class="p-4 font-semibold">Status</th>
                                    <th class="p-4 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-sm text-gray-700">
                                <tr v-for="caseItem in filteredCases" :key="caseItem.id" class="hover:bg-gray-50 transition-colors">
                                    <td class="p-4">
                                        <span class="font-medium text-gray-900">{{ caseItem.disease }}</span>
                                    </td>
                                    <td class="p-4 text-gray-600">{{ caseItem.date_onset }}</td>
                                    <td class="p-4 font-medium text-gray-900">{{ caseItem.tag_no }}</td>
                                    <td class="p-4 text-gray-600">
                                        <div class="flex items-center gap-1">
                                            <MapPin class="w-3 h-3 text-gray-400" />
                                            {{ caseItem.location }}
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <span class="font-medium text-gray-900">{{ caseItem.affected_count }}</span>
                                        <span v-if="caseItem.recovered_count > 0" class="text-xs text-green-600 ml-1">({{ caseItem.recovered_count }} recovered)</span>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-medium border" :class="getSeverityClass(caseItem.severity)">
                                            {{ caseItem.severity }}
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-medium" :class="getStatusClass(caseItem.status)">
                                            {{ caseItem.status }}
                                        </span>
                                    </td>
                                    <td class="p-4 text-right flex justify-end gap-2">
                                        <button @click="viewCase(caseItem)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                                            <Eye class="w-4 h-4" />
                                        </button>
                                        <button @click="editCase(caseItem)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                                            <Edit class="w-4 h-4" />
                                        </button>
                                        <button @click="deleteCase(caseItem)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredCases.length === 0">
                                    <td colspan="8" class="p-8 text-center text-gray-400 italic">
                                        <Activity class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                        <p class="text-sm">No disease cases found</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                            <BarChart3 class="w-4 h-4 text-[#34554a]" />
                            Disease Distribution
                        </h3>
                    </div>
                    <div class="p-4 space-y-4">
                        <div v-for="stat in diseaseStats" :key="stat.name" class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-gray-600 font-medium">{{ stat.name }}</span>
                                <span class="text-xs text-gray-500">{{ stat.total }} cases</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-[#34554a] h-2 rounded-full" :style="{ width: `${(stat.total / stats.totalCases) * 100}%` }"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-400">
                                <span>{{ stat.active }} active</span>
                                <span>{{ stat.recovered }} recovered</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-900">Quick Actions</h3>
                    </div>
                    <div class="p-4 space-y-2">
                        <button @click="$inertia.visit('/health/disease/create')" class="w-full py-2 px-3 text-left text-sm text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                            Report New Case
                        </button>
                        <button class="w-full py-2 px-3 text-left text-sm text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                            View Outbreak Map
                        </button>
                        <button class="w-full py-2 px-3 text-left text-sm text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
                            Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
