<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { 
    FileText, Download, Upload, CheckCircle, Clock, 
    Eye, Calendar, User, Filter, ChevronRight
} from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Document Endorsement', parent: 'Mortality', parentUrl: '/mortality/records' }, () => page)
});

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role || '');
const userName = computed(() => page.props.auth?.user?.name || '');

const props = defineProps({
    documents: Object,
    pendingDocuments: Array,
    workflowSteps: Object,
    userRole: String,
    canDownload: Boolean,
    canUpload: Boolean,
});

const showUploadModal = ref(false);
const selectedDocument = ref(null);
const uploadForm = ref({
    name: '',
    date: '',
    signed_document: null,
});

const workflowLabels = {
    0: 'Issued',
    1: 'Verified',
    2: 'Checked',
    3: 'Witnessed',
    4: 'Approved',
};

const workflowRoleNames = {
    0: 'Sr. Assistant Livestock',
    1: 'Sr. Assistant Security',
    2: 'Supervisor Livestock',
    3: 'Estate Management',
    4: 'Livestock Manager/OIC',
};

const statusClass = (status) => {
    const classes = {
        'pending': 'bg-yellow-100 text-yellow-800',
        'in_progress': 'bg-blue-100 text-blue-800',
        'completed': 'bg-[#1f5c19] text-white border-[#1f5c19]',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const statusLabel = (status) => {
    if (!status) return 'Pending';
    const normalized = String(status).replace(/_/g, ' ').toLowerCase();
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const openUploadModal = (doc) => {
    selectedDocument.value = doc;
    uploadForm.value = {
        name: userName.value,
        date: new Date().toISOString().split('T')[0],
        signed_document: null,
    };
    showUploadModal.value = true;
};

const closeUploadModal = () => {
    showUploadModal.value = false;
    selectedDocument.value = null;
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        uploadForm.value.signed_document = file;
    }
};

    const submitUpload = () => {
        if (!uploadForm.value.signed_document || !uploadForm.value.name || !uploadForm.value.date) {
            alert('Please fill in all fields and upload the signed document');
            return;
        }

        const formData = new FormData();
        formData.append('signed_document', uploadForm.value.signed_document);
        formData.append('name', uploadForm.value.name);
        formData.append('date', uploadForm.value.date);

        // Create a temporary form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/document-endorsement/${selectedDocument.value.id}/upload`;
        form.enctype = 'multipart/form-data';
        form.style.display = 'none';

        // Add CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken.getAttribute('content');
            form.appendChild(csrfInput);
        }

        // Add file
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = 'signed_document';
        if (uploadForm.value.signed_document) {
            const dt = new DataTransfer();
            dt.items.add(uploadForm.value.signed_document);
            fileInput.files = dt.files;
        } else {
            fileInput.files = null;
        }
        form.appendChild(fileInput);

        // Add name
        const nameInput = document.createElement('input');
        nameInput.type = 'hidden';
        nameInput.name = 'name';
        nameInput.value = uploadForm.value.name;
        form.appendChild(nameInput);

        // Add date
        const dateInput = document.createElement('input');
        dateInput.type = 'hidden';
        dateInput.name = 'date';
        dateInput.value = uploadForm.value.date;
        form.appendChild(dateInput);

        document.body.appendChild(form);
        form.submit();
    };

const getDocumentStatus = (doc) => {
    const step = doc.current_step;
    const field = props.workflowSteps[step]?.field;
    
    if (doc.status === 'completed') {
        return { label: 'Completed', class: 'bg-[#1f5c19] text-white border-[#1f5c19]' };
    }
    
    const docField = field + '_document';
    if (doc[docField]) {
        return { label: 'Signed', class: 'bg-blue-100 text-blue-800' };
    }
    
    return { label: 'Pending', class: 'bg-yellow-100 text-yellow-800' };
};

const canUserDownload = (doc) => {
    if (page.props.auth?.user?.role === 'admin') return true;
    
    const userStep = Object.keys(props.workflowSteps).find(
        key => props.workflowSteps[key].role === page.props.auth?.user?.role
    );
    
    if (userStep === undefined) return false;
    
    return doc.current_step >= parseInt(userStep) && doc.status !== 'completed';
};

const canUserUpload = (doc) => {
    if (page.props.auth?.user?.role === 'admin') return false;
    
    const userStep = Object.keys(props.workflowSteps).find(
        key => props.workflowSteps[key].role === page.props.auth?.user?.role
    );
    
    if (userStep === undefined) return false;
    
    return doc.current_step === parseInt(userStep) && doc.status !== 'completed';
};
</script>

<template>
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Document Endorsement</h1>
            <p class="text-sm text-gray-500 mt-1">Download, sign, and upload PM examination documents</p>
        </div>

        <!-- Pending Actions Section -->
        <div v-if="pendingDocuments && pendingDocuments.length > 0" class="bg-blue-50 border border-blue-200 rounded-xl p-6 mb-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <Clock class="w-5 h-5 text-blue-600" />
                </div>
                <div>
                    <h3 class="font-bold text-gray-900">Your Pending Actions</h3>
                    <p class="text-sm text-gray-600">Documents waiting for your endorsement</p>
                </div>
                <span class="ml-auto px-3 py-1 bg-blue-600 text-white rounded-full text-sm font-bold">
                    {{ pendingDocuments.length }} Pending
                </span>
            </div>
            
            <div class="grid gap-3">
                <div 
                    v-for="doc in pendingDocuments" 
                    :key="doc.id"
                    class="bg-white rounded-lg p-4 border border-blue-100 flex items-center gap-4"
                >
                    <div class="flex-1">
                        <code class="bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold">{{ doc.lmc_no }}</code>
                        <span class="ml-2 text-sm text-gray-600">{{ doc.tag_no }} ({{ doc.category }})</span>
                    </div>
                    <button 
                        @click="openUploadModal(doc)"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 flex items-center gap-2"
                    >
                        <Upload class="w-4 h-4" />
                        Sign & Upload
                    </button>
                </div>
            </div>
        </div>

        <!-- Document List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <div class="p-4 border-b border-gray-100 bg-gray-50 flex items-center gap-4">
                <FileText class="w-5 h-5 text-[#34554a]" />
                <h3 class="font-bold text-gray-900">All Documents</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th class="p-4 font-semibold">LMC No.</th>
                            <th class="p-4 font-semibold">Tag No.</th>
                            <th class="p-4 font-semibold">Category</th>
                            <th class="p-4 font-semibold">Cause of Death</th>
                            <th class="p-4 font-semibold">Current Step</th>
                            <th class="p-4 font-semibold">Status</th>
                            <th class="p-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                        <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-gray-50">
                            <td class="p-4">
                                <code class="bg-[#34554a]/10 text-[#34554a] px-2 py-1 rounded font-bold text-xs">{{ doc.lmc_no }}</code>
                            </td>
                            <td class="p-4 font-medium">{{ doc.tag_no }}</td>
                            <td class="p-4">
                                <span class="px-2.5 py-0.5 bg-gray-100 rounded text-xs font-medium">{{ doc.category }}</span>
                            </td>
                            <td class="p-4 text-gray-600">{{ doc.confirmed_cause_of_death || doc.preliminary_diagnosis || '-' }}</td>
                            <td class="p-4">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-0.5 bg-[#34554a] text-white rounded-full text-xs font-medium">
                                        Step {{ doc.current_step + 1 }}: {{ workflowLabels[doc.current_step] }}
                                    </span>
                                </div>
                            </td>
                            <td class="p-4">
                                <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium border', statusClass(doc.status)]">
                                    {{ statusLabel(doc.status) }}
                                </span>
                            </td>
                            <td class="p-4 text-right flex justify-end gap-2">
                                <a 
                                    :href="`/document-endorsement/${doc.id}/download`"
                                    :class="[
                                        'w-8 h-8 rounded flex items-center justify-center transition-colors',
                                        canUserDownload(doc) 
                                            ? 'text-gray-500 hover:text-[#34554a] hover:bg-[#34554a]/10' 
                                            : 'text-gray-300 cursor-not-allowed'
                                    ]"
                                    :title="canUserDownload(doc) ? 'Download Document' : 'Not your turn yet'"
                                    target="_blank"
                                >
                                    <Download class="w-4 h-4" />
                                </a>
                                <button 
                                    @click="openUploadModal(doc)"
                                    :class="[
                                        'w-8 h-8 rounded flex items-center justify-center transition-colors',
                                        canUserUpload(doc) 
                                            ? 'text-gray-500 hover:text-green-600 hover:bg-green-50' 
                                            : 'text-gray-300 cursor-not-allowed'
                                    ]"
                                    :title="canUserUpload(doc) ? 'Upload Signed Document' : 'Not your turn yet'"
                                    :disabled="!canUserUpload(doc)"
                                >
                                    <Upload class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="documents.data.length === 0">
                            <td colspan="7" class="p-8 text-center text-gray-400 italic">No documents found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div v-if="documents.last_page > 1" class="p-4 border-t border-gray-100 flex justify-center gap-2">
                <Link 
                    v-for="pageNum in documents.last_page" 
                    :key="pageNum"
                    :href="`/document-endorsement?page=${pageNum}`"
                    :class="[
                        'px-3 py-1 rounded text-sm',
                        documents.current_page === pageNum 
                            ? 'bg-[#34554a] text-white' 
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                >
                    {{ pageNum }}
                </Link>
            </div>
        </div>

        <!-- Upload Modal -->
        <Teleport to="body">
            <div 
                v-if="showUploadModal" 
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5"
                @click.self="closeUploadModal"
            >
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-auto overflow-hidden">
                    <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Upload class="w-5 h-5 text-[#34554a]" />
                            Sign & Upload Document
                        </h3>
                        <button 
                            @click="closeUploadModal"
                            class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200"
                        >
                            ✕
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <div class="flex items-center gap-2 mb-2">
                                <FileText class="w-4 h-4 text-[#34554a]" />
                                <span class="font-bold text-gray-900">{{ selectedDocument?.lmc_no }}</span>
                            </div>
                            <p class="text-sm text-gray-600">
                                {{ selectedDocument?.tag_no }} - {{ selectedDocument?.category }}
                            </p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                                    Your Name *
                                </label>
                                <input 
                                    v-model="uploadForm.name"
                                    type="text" 
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    placeholder="Enter your name"
                                >
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                                    Date *
                                </label>
                                <input 
                                    v-model="uploadForm.date"
                                    type="date" 
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                >
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5">
                                    Signed Document (PDF) *
                                </label>
                                <input 
                                    type="file" 
                                    accept="application/pdf"
                                    @change="handleFileUpload"
                                    class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                >
                                <p class="text-xs text-gray-500 mt-1">Upload the signed PDF document (max 10MB)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 p-4 border-t border-gray-100 bg-gray-50">
                        <button 
                            @click="closeUploadModal"
                            class="px-4 py-2 border border-gray-200 rounded-lg text-gray-600 font-medium hover:bg-gray-100"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="submitUpload"
                            :disabled="!uploadForm.signed_document || !uploadForm.name || !uploadForm.date"
                            class="px-6 py-2 bg-[#34554a] text-white rounded-lg font-medium hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                        >
                            <Upload class="w-4 h-4" />
                            Upload Signed Document
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
