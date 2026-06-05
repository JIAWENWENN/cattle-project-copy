<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { 
    FileText, Clock, CheckCircle, User, 
    Calendar, Truck, Shield, Download, Eye
} from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Transfer Details', parent: 'Transfer', parentUrl: '/transfer/ctv' }, () => page)
});

const page = usePage();
const document = computed(() => page.props.document || {});

const workflowSteps = [
    { id: 'issued', label: 'Issued', role: 'SAPP Integration' },
    { id: 'approved', label: 'Approved', role: 'Livestock Manager' },
    { id: 'transported', label: 'Transported', role: 'Driver' },
    { id: 'witness_transit', label: 'Witness (T)', role: 'Livestock Staff' },
    { id: 'verified_transit', label: 'Verified (T)', role: 'Security' },
    { id: 'witness_receive', label: 'Witness (R)', role: 'Livestock Staff' },
    { id: 'received', label: 'Received', role: 'Supervisor' },
    { id: 'completed', label: 'Completed', role: 'Administrator' }
];

const getStepIndex = (step) => {
    const steps = ['issued', 'approved', 'transported', 'witness_transit', 'verified_transit', 'witness_receive', 'received', 'completed'];
    return steps.indexOf(step);
};

const currentStepIndex = computed(() => {
    return getStepIndex(document.value.current_step || 'issued');
});

const getTypeClass = (type) => {
    const classes = {
        'CTV': 'bg-blue-100 text-blue-800',
        'Receival': 'bg-purple-100 text-purple-800',
        'SIV': 'bg-green-100 text-green-800',
    };
    return classes[type] || 'bg-gray-100 text-gray-800';
};

const getStatusClass = (status) => {
    const classes = {
        'completed': 'bg-[#1f5c19] text-white border-[#1f5c19]',
        'pending': 'bg-orange-100 text-orange-800',
        'in_progress': 'bg-blue-100 text-blue-800',
        'rejected': 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const formatStatusLabel = (status) => {
    if (!status) return 'Pending';
    const normalized = String(status).replace(/_/g, ' ').toLowerCase();
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-GB', { 
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' 
    });
};

const formatCurrency = (value) => {
    if (!value) return '-';
    return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(value);
};

const downloadDocument = (id, stepIndex) => {
    window.open(`/transfer/${id}/download-endorsement/${stepIndex}`, '_blank');
};
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="mb-6">
            <div class="flex items-center gap-3">
                <code class="bg-primary text-white px-4 py-2 rounded-lg font-bold text-lg">{{ document.document_no }}</code>
                <span :class="['px-3 py-1 rounded-full text-sm font-medium', getTypeClass(document.type)]">{{ document.type }}</span>
                <span :class="['px-3 py-1 rounded-full text-sm font-medium border', getStatusClass(document.status)]">{{ formatStatusLabel(document.status) }}</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight mt-3">Transfer Document Details</h1>
            <p class="text-sm text-gray-500 mt-0.5">Complete audit trail and document information.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <FileText class="w-5 h-5 text-primary" />
                            Document Information
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide">Type</p>
                                <p class="font-medium text-gray-900">{{ document.type || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide">Date</p>
                                <p class="font-medium text-gray-900">{{ document.date ? new Date(document.date).toLocaleDateString() : '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide">From</p>
                                <p class="font-medium text-gray-900">{{ document.from_location || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide">To</p>
                                <p class="font-medium text-gray-900">{{ document.to_location || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide">Vehicle</p>
                                <p class="font-medium text-gray-900">{{ document.vehicle_no || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide">Driver</p>
                                <p class="font-medium text-gray-900">{{ document.driver_name || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide">Customer</p>
                                <p class="font-medium text-gray-900">{{ document.customer_name || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase tracking-wide">Total Cattle</p>
                                <p class="font-medium text-gray-900">{{ document.total_cattle || 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Truck class="w-5 h-5 text-primary" />
                            Livestock Details
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead>
                                <tr class="bg-primary text-white text-sm">
                                    <th class="p-4 font-semibold">No.</th>
                                    <th class="p-4 font-semibold">Tag No.</th>
                                    <th class="p-4 font-semibold">Category</th>
                                    <th class="p-4 font-semibold">Colour</th>
                                    <th class="p-4 font-semibold">Weight</th>
                                    <th class="p-4 font-semibold">Condition</th>
                                    <th class="p-4 font-semibold">Purpose</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-sm text-gray-700">
                                <tr v-for="(item, index) in document.livestock" :key="index" class="hover:bg-gray-50">
                                    <td class="p-4">{{ index + 1 }}</td>
                                    <td class="p-4 font-medium">{{ item.tag_no || '-' }}</td>
                                    <td class="p-4">{{ item.category || '-' }}</td>
                                    <td class="p-4">{{ item.colour || '-' }}</td>
                                    <td class="p-4">{{ item.weight ? item.weight + ' KG' : '-' }}</td>
                                    <td class="p-4">
                                        <span v-if="item.condition_good" class="text-green-600">Good</span>
                                        <span v-else-if="item.condition_not_good" class="text-red-600">Not Good</span>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="p-4">{{ item.purpose || '-' }}</td>
                                </tr>
                                <tr v-if="!document.livestock?.length">
                                    <td colspan="7" class="p-8 text-center text-gray-400 italic">No livestock records found</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Clock class="w-5 h-5 text-primary" />
                            Workflow Progress
                        </h3>
                    </div>
                    <div class="p-4">
                        <div class="space-y-3">
                            <div v-for="(step, index) in workflowSteps" :key="step.id" class="flex items-center gap-3">
                                <div 
                                    :class="[
                                        'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold',
                                        index < currentStepIndex ? 'bg-green-500 text-white' : 
                                        index === currentStepIndex ? 'bg-primary text-white ring-4 ring-primary/20' : 
                                        'bg-gray-200 text-gray-400'
                                    ]"
                                >
                                    <CheckCircle v-if="index < currentStepIndex" class="w-4 h-4" />
                                    <span v-else>{{ index + 1 }}</span>
                                </div>
                                <div class="flex-1">
                                    <p :class="['text-sm', index <= currentStepIndex ? 'font-medium text-gray-900' : 'text-gray-400']">
                                        {{ step.label }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ step.role }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Shield class="w-5 h-5 text-primary" />
                            Approval History
                        </h3>
                    </div>
                    <div class="p-4 max-h-64 overflow-y-auto">
                        <div class="space-y-3">
                            <div v-for="approval in document.approvals" :key="approval.id" class="flex gap-3 p-2 bg-gray-50 rounded-lg">
                                <div class="w-2 h-2 bg-primary rounded-full mt-1.5 flex-shrink-0"></div>
                                <div class="flex-1">
                                    <p class="text-sm text-gray-800">
                                        <strong>{{ approval.approver?.name || 'Unknown' }}</strong> - {{ approval.step }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ formatDate(approval.created_at) }}</p>
                                    <p v-if="approval.comments" class="text-xs text-gray-600 mt-1">{{ approval.comments }}</p>
                                </div>
                            </div>
                            <div v-if="!document.approvals?.length" class="text-center text-gray-400 text-sm py-4">
                                No approvals yet
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
