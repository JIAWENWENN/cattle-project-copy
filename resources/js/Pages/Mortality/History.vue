<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { 
    Skull, History, Calendar, Eye, 
    FileSignature, CheckCircle, ClipboardCheck, Shield, Stamp,
    ChevronRight, Trash2, Edit, FileText
} from 'lucide-vue-next';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Mortality History', parent: 'Mortality', parentUrl: '/mortality/records' }, () => page)
});

const page = usePage();
const openMenus = ref(['mortality']);

const userRole = computed(() => page.props.auth?.user?.role || '');

const mortalityPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Mortality Records'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasMortalityPermission = (action) => {
    if (String(userRole.value).toLowerCase() === 'admin') return true;
    const perms = mortalityPermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canViewMortality = computed(() => hasMortalityPermission('view'));
const canCreateMortality = computed(() => hasMortalityPermission('create'));
const canEditMortality = computed(() => hasMortalityPermission('edit'));
const canDeleteMortality = computed(() => hasMortalityPermission('delete'));

const showModal = ref(false);
const selectedRecord = ref(null);
const showDeleteModal = ref(false);
const recordToDelete = ref(null);
const dateFrom = ref('');
const dateTo = ref('');

const props = defineProps({
    mortalityCases: Object,
    filters: Object,
});

const mortalityRecords = computed(() => {
    return props.mortalityCases?.data || [];
});

const workflow = [
    { id: 'issued', label: 'Issued', role: 'estate', icon: FileSignature },
    { id: 'verified', label: 'Verified', role: 'veterinary', icon: CheckCircle },
    { id: 'checked', label: 'Checked', role: 'supervisor', icon: ClipboardCheck },
    { id: 'witness', label: 'Witness', role: 'manager', icon: Shield },
    { id: 'approved', label: 'Approved', role: 'security', icon: Stamp }
];

const filteredRecords = computed(() => {
    let records = mortalityRecords.value;
    if (dateFrom.value) {
        records = records.filter(record => new Date(record.death_date) >= new Date(dateFrom.value));
    }
    if (dateTo.value) {
        records = records.filter(record => new Date(record.death_date) <= new Date(dateTo.value));
    }
    return records;
});

const applyDateFilter = () => {
    // Computed property handles filtering automatically based on dateFrom and dateTo
};

const clearDateFilter = () => {
    dateFrom.value = '';
    dateTo.value = '';
};

const getStatus = (record) => {
    return {
        label: record.status || 'Pending',
        isCompleted: record.status === 'completed'
    };
};

const getCauseOfDeath = (record) => {
    return record.postmortem_examination?.confirmed_cause_of_death || record.cause_of_death || 'Unknown';
};

const viewDetail = (record) => {
    selectedRecord.value = record;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedRecord.value = null;
};


const confirmDelete = (record) => {
    recordToDelete.value = record;
    showDeleteModal.value = true;
};

const editRecord = (record) => {
    router.visit(`/mortality/${record.id}/edit`);
};

const viewWorkflow = (record) => {
    router.visit(`/mortality/${record.id}/workflow`);
};

const deleteRecord = () => {
    if (!recordToDelete.value) return;
    
    router.delete(`/mortality/${recordToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            recordToDelete.value = null;
        },
        onError: (errors) => {
            console.error('Delete failed:', errors);
            alert('Failed to delete record. Please try again.');
        }
    });
};
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Mortality History</h1>
            <p class="text-sm text-gray-500 mt-1">Complete audit trail of all mortality cases.</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 mb-6">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex items-center gap-2">
                    <Calendar class="w-4 h-4 text-[#34554a]" />
                    <span class="text-sm font-medium text-gray-700">Date Range Filter:</span>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">From Date</label>
                    <input 
                        v-model="dateFrom"
                        type="date" 
                        class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                    >
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">To Date</label>
                    <input 
                        v-model="dateTo"
                        type="date" 
                        class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                    >
                </div>
                <button 
                    @click="applyDateFilter"
                    class="px-4 py-2 bg-[#34554a] text-white rounded-lg text-sm font-medium hover:bg-opacity-90 transition-colors flex items-center gap-2">
                    <Eye class="w-4 h-4" />
                    Apply Filter
                </button>
                <button 
                    @click="clearDateFilter"
                    class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-2">
                    <History class="w-4 h-4" />
                    Clear
                </button>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th class="p-4 font-semibold">LMC No.</th>
                            <th class="p-4 font-semibold">Date</th>
                            <th class="p-4 font-semibold">Tag No.</th>
                            <th class="p-4 font-semibold">Category</th>
                            <th class="p-4 font-semibold">Cause of Death</th>
                            <th class="p-4 font-semibold">Status</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                        <tr v-for="record in filteredRecords" :key="record.id" class="hover:bg-gray-50 transition-colors">
                            <td class="p-4">
                                <code class="bg-[#34554a]/10 text-[#34554a] px-2 py-1 rounded font-bold text-xs">{{ record.lmc_no || ('LMC-' + record.id) }}</code>
                            </td>
                            <td class="p-4">{{ record.death_date ? new Date(record.death_date).toISOString().split('T')[0] : '-' }}</td>
                            <td class="p-4 font-medium">{{ record.cattle?.tag_no || '-' }}</td>
                            <td class="p-4">
                                <span class="px-2.5 py-0.5 bg-gray-100 rounded text-xs font-medium">{{ record.category || '-' }}</span>
                            </td>
                            <td class="p-4 text-gray-600">{{ getCauseOfDeath(record) }}</td>
                            <td class="p-4">
                                <span 
                                    class="px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                    :class="getStatus(record).isCompleted ? 'bg-[#1f5c19] text-white border-[#1f5c19]' : 'bg-gray-100 text-gray-800 border-gray-200'"
                                >
                                    {{ getStatus(record).label }}
                                </span>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center gap-1">
                                    <button 
                                        v-if="canEditMortality && !getStatus(record).isCompleted"
                                        @click="editRecord(record)"
                                        class="w-8 h-8 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center transition-colors"
                                        title="Edit Record">
                                        <Edit class="w-4 h-4" />
                                    </button>
                                    <button 
                                        v-if="canViewMortality"
                                        @click="viewWorkflow(record)"
                                        class="w-8 h-8 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center transition-colors"
                                        title="Workflow">
                                        <FileSignature class="w-4 h-4" />
                                    </button>
                                    <button 
                                        v-if="canViewMortality"
                                        @click="viewDetail(record)"
                                        class="w-8 h-8 text-gray-400 hover:text-[#34554a] hover:bg-[#34554a]/10 rounded flex items-center justify-center transition-colors"
                                        title="View Details">
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <button 
                                        v-if="canDeleteMortality && !getStatus(record).isCompleted"
                                        @click="confirmDelete(record)"
                                        class="w-8 h-8 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded flex items-center justify-center transition-colors"
                                        title="Delete Record">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredRecords.length === 0">
                            <td colspan="7" class="p-8 text-center text-gray-400 italic">No records found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Teleport to="body">
            <div 
                v-if="showModal" 
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto"
                @click.self="closeModal">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-5xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col">
                    <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50 sticky top-0">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Skull class="w-5 h-5 text-[#34554a]" />
                            <span>Document Details - {{ selectedRecord?.id }}</span>
                        </h3>
                        <button 
                            @click="closeModal" 
                            class="text-gray-400 hover:text-gray-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200">
                            ✕
                        </button>
                    </div>
                    <div class="p-6 overflow-y-auto flex-1">
                        <div class="flex justify-center gap-2 flex-wrap p-4 bg-gray-50 rounded-xl mb-6">
                            <template v-for="(step, index) in workflow" :key="step.id">
                                <div class="flex flex-col items-center min-w-[70px]">
                                    <div 
                                        class="w-10 h-10 rounded-full flex items-center justify-center text-sm mb-1"
                                        :class="selectedRecord?.current_step >= workflow.length 
                                            ? 'bg-[#34554a] text-white' 
                                            : selectedRecord?.current_step >= index + 1 
                                                ? 'bg-[#34554a] text-white' 
                                                : 'bg-gray-200 text-gray-400'"
                                    >
                                        <CheckCircle v-if="selectedRecord?.current_step >= index + 1" class="w-5 h-5" />
                                        <span v-else>{{ index + 1 }}</span>
                                    </div>
                                    <span 
                                        class="text-[10px] text-center"
                                        :class="selectedRecord?.current_step >= index + 1 ? 'text-gray-800 font-semibold' : 'text-gray-400'"
                                    >{{ step.label }}</span>
                                </div>
                                <div v-if="index < workflow.length - 1" class="text-gray-300 pb-5 flex items-center">
                                    <ChevronRight class="w-4 h-4" />
                                </div>
                            </template>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-5">
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <History class="w-4 h-4 text-[#34554a]" />
                                        Case Information
                                    </h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">LMC Number</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.id }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Date Reported</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.date }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Tag Number</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.tag_no }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Category</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.category }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Location</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.location }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5 border-b border-gray-200">
                                            <span class="text-gray-500">Herd</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.herd || '-' }}</span>
                                        </div>
                                        <div class="flex justify-between py-1.5">
                                            <span class="text-gray-500">Reported By</span>
                                            <span class="font-semibold text-gray-900">{{ selectedRecord?.reported_by }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <Calendar class="w-4 h-4 text-[#34554a]" />
                                        Case Timeline
                                    </h4>
                                    <div class="space-y-4 max-h-48 overflow-y-auto">
                                        <div 
                                            v-for="(step, index) in selectedRecord?.steps?.filter(s => s.completed)" 
                                            :key="index"
                                            class="flex gap-3">
                                            <div class="w-3 h-3 bg-[#34554a] rounded-full mt-1 flex-shrink-0"></div>
                                            <div class="flex-1 pb-4">
                                                <div class="flex justify-between items-center">
                                                    <span class="font-semibold text-gray-800 text-sm">{{ step.step_id }}</span>
                                                    <span class="text-xs text-gray-400">{{ step.date }}</span>
                                                </div>
                                                <div class="text-xs text-gray-500 mb-2">By {{ step.user }}</div>
                                            </div>
                                        </div>
                                        <p v-if="!selectedRecord?.steps?.filter(s => s.completed)?.length" class="text-gray-400 italic text-sm">
                                            No steps completed
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-5">
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <Eye class="w-4 h-4 text-[#34554a]" />
                                        Findings Summary
                                    </h4>
                                    <div class="space-y-3">
                                        <div>
                                            <span class="text-xs text-gray-500 uppercase tracking-wide">Cause of Death</span>
                                            <p class="font-semibold text-gray-900">{{ selectedRecord?.cause_of_death || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500 uppercase tracking-wide">Clinical Signs</span>
                                            <p class="font-semibold text-gray-900 text-xs">{{ selectedRecord?.clinical_signs || '-' }}</p>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-500 uppercase tracking-wide">Final Diagnosis</span>
                                            <p class="font-semibold text-green-600">
                                                {{ selectedRecord?.pm_findings?.final_diagnosis || (getStatus(selectedRecord).isCompleted ? 'Completed' : 'Pending') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                                        <ClipboardCheck class="w-4 h-4 text-[#34554a]" />
                                        Audit Log
                                    </h4>
                                    <div class="max-h-48 overflow-y-auto space-y-2">
                                        <div 
                                            v-for="(audit, index) in selectedRecord?.audit" 
                                            :key="index"
                                            class="flex gap-2 py-2 border-b border-gray-100 last:border-0">
                                            <div class="w-2 h-2 bg-[#34554a] rounded-full mt-1.5 flex-shrink-0"></div>
                                            <div class="flex-1">
                                                <div class="text-sm text-gray-800">
                                                    <strong>{{ audit.user }}</strong> - {{ audit.action }}
                                                </div>
                                                <div class="text-xs text-gray-400">{{ audit.time }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete Mortality Case"
            :message="`Are you sure you want to delete mortality case`"
            :itemName="recordToDelete?.id || ''"
            @close="showDeleteModal = false"
            @confirm="deleteRecord"
        />
    </div>
</template>
