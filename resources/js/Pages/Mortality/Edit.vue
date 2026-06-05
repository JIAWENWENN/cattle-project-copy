<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Save, ArrowLeft, Settings, Plus, Pencil, Trash2, Check, X } from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Edit Mortality Record', parent: 'Mortality', parentUrl: '/mortality/records' }, () => page)
});

const props = defineProps({
    case: {
        type: Object,
        required: true
    },
    customFields: {
        type: Object,
        default: () => ({})
    },
    estates: {
        type: Array,
        default: () => []
    },
    blocks: {
        type: Array,
        default: () => []
    }
});

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role || '');
const derivedFieldClass = 'w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-100 text-sm text-gray-600 cursor-not-allowed';

const categoryOptions = computed(() => props.customFields?.category || []);
const preliminaryCauseOptions = computed(() => props.customFields?.preliminary_cause || []);

const pm = computed(() => props.case?.postmortem_examination || {});

const toDateInput = (value) => {
    if (!value) return '';
    const str = String(value);
    return str.length >= 10 ? str.slice(0, 10) : str;
};

const toTimeInput = (value) => {
    if (!value) return '';
    const str = String(value).trim();
    const match = str.match(/^(\d{1,2}:\d{2})(?::\d{2})?/);
    if (!match) return str;
    return match[0].length === 5 ? `${match[0]}:00` : match[0];
};

const form = useForm({
    category: props.case?.category || '',
    location: props.case?.location || props.case?.cattle?.operating_unit || '',
    block: props.case?.block || props.case?.cattle?.location_block || '',
    death_date: toDateInput(props.case?.death_date),
    additional_info: props.case?.initial_notes || '',
    reported_by: props.case?.reported_by || '',
    time_of_death: toTimeInput(props.case?.time_of_death),
    cause_of_death: props.case?.cause_of_death || '',
    treatment: props.case?.treatment || '',
    pm_examination_date: toDateInput(pm.value.examination_date),
    pm_examination_time: toTimeInput(pm.value.examination_time),
    pm_external_skin: pm.value.external_skin || '',
    pm_external_eyes: pm.value.external_eyes || '',
    pm_external_mouth: pm.value.external_mouth || '',
    pm_external_nostrils: pm.value.external_nostrils || '',
    pm_external_ears: pm.value.external_ears || '',
    pm_external_limbs: pm.value.external_limbs || '',
    pm_external_anus: pm.value.external_anus || '',
    pm_external_genital: pm.value.external_genital || '',
    pm_external_general: pm.value.external_general || '',
    pm_subcutaneous: pm.value.subcutaneous_findings || '',
    pm_heart: pm.value.heart_findings || '',
    pm_trachea: pm.value.trachea_findings || '',
    pm_lung: pm.value.lung_floating_test || '',
    pm_diaphragma: pm.value.diaphragma_test || '',
    pm_kidney: pm.value.kidney_findings || '',
    pm_reproductive_organ: pm.value.reproductive_organ_findings || '',
    pm_joint: pm.value.joint_findings || '',
    pm_bladder: pm.value.urinary_bladder_findings || '',
    pm_liver: pm.value.liver_findings || '',
    pm_spleen: pm.value.spleen_findings || '',
    pm_stomachrumen: pm.value.rumen_findings || '',
    pm_stomachreticulum: pm.value.reticulum_findings || '',
    pm_stomachabomasum: pm.value.abomasum_findings || '',
    pm_intestine_small: pm.value.small_intestine_findings || '',
    pm_intestine_colon: pm.value.colon_findings || '',
});

const submitForm = () => {
    form.put(`/mortality/${props.case.id}`, {
        onSuccess: () => {
            alert('Record updated successfully!');
            router.visit('/mortality/records');
        },
        onError: (errors) => {
            console.error(errors);
            const errorMessage = Object.entries(errors)
                .map(([key, value]) => `${key}: ${value}`)
                .join('\n');
            alert('Validation Errors:\n' + errorMessage);
        }
    });
};

const goBack = () => {
    window.history.back();
};
</script>

<template>
    <div class="w-full max-w-6xl mx-auto">
        <div class="mb-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="goBack" class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                    <ArrowLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Edit mortality record</h1>
                    <p class="text-sm text-gray-500 mt-1">Update mortality case details - {{ props.case?.lmc_no || 'LMC-' + props.case?.id }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" @click="goBack" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="button" @click="submitForm" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors disabled:opacity-50" :disabled="form.processing">
                    <Save class="w-4 h-4" />
                    {{ form.processing ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </div>

        <form @submit.prevent>
            <!-- Case Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Case Information</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">LMC No.</label>
                        <input type="text" :value="props.case?.lmc_no || 'LMC-' + props.case?.id" readonly class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-100 text-sm cursor-not-allowed" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tag No.</label>
                        <input type="text" :value="props.case?.cattle?.tag_no || '-'" readonly class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-100 text-sm cursor-not-allowed" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Date *</label>
                        <input type="date" v-model="form.death_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Time of Death</label>
                        <input type="time" step="1" v-model="form.time_of_death" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Reported By</label>
                        <input type="text" v-model="form.reported_by" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Cause of Death</label>
                        <input type="text" v-model="form.cause_of_death" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Treatment</label>
                        <input type="text" v-model="form.treatment" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Category *</label>
                        <input
                            v-model="form.category"
                            type="text"
                            readonly
                            :class="derivedFieldClass"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Location (Estate)</label>
                        <input type="text" v-model="form.location" readonly :class="derivedFieldClass" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Block</label>
                        <input type="text" v-model="form.block" readonly :class="derivedFieldClass" />
                    </div>
                </div>
            </div>

            <!-- Additional Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Additional Info</h3>
                </div>
                <div class="p-6">
                    <textarea v-model="form.additional_info" rows="4" placeholder="Additional info..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a] resize-none"></textarea>
                </div>
            </div>

            <!-- Post-Mortem Examination -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 bg-[#34554a] border-b border-gray-100">
                    <h3 class="text-sm font-bold text-white">Post-Mortem Examination</h3>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Examination Date</label>
                        <input type="date" v-model="form.pm_examination_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>
                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Examination Time</label>
                        <input type="time" step="1" v-model="form.pm_examination_time" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                    </div>

                    <h4 class="text-sm font-bold text-gray-800 mb-4 pb-2 border-b-2 border-[#34554a]">External Findings</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Skin</label>
                            <input type="text" v-model="form.pm_external_skin" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Eyes</label>
                            <input type="text" v-model="form.pm_external_eyes" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Mouth</label>
                            <input type="text" v-model="form.pm_external_mouth" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Nostrils</label>
                            <input type="text" v-model="form.pm_external_nostrils" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Ears</label>
                            <input type="text" v-model="form.pm_external_ears" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Limbs</label>
                            <input type="text" v-model="form.pm_external_limbs" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Anus</label>
                            <input type="text" v-model="form.pm_external_anus" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Genital</label>
                            <input type="text" v-model="form.pm_external_genital" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">General</label>
                            <input type="text" v-model="form.pm_external_general" placeholder="General external observations..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                    </div>

                    <h4 class="text-sm font-bold text-gray-800 mb-4 pb-2 border-b-2 border-[#34554a]">Organ Findings</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Subcutaneous</label>
                            <input type="text" v-model="form.pm_subcutaneous" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Heart</label>
                            <input type="text" v-model="form.pm_heart" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Trachea</label>
                            <input type="text" v-model="form.pm_trachea" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Lung</label>
                            <input type="text" v-model="form.pm_lung" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Diaphragma</label>
                            <input type="text" v-model="form.pm_diaphragma" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Kidney</label>
                            <input type="text" v-model="form.pm_kidney" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Reproductive Organ</label>
                            <input type="text" v-model="form.pm_reproductive_organ" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Joint</label>
                            <input type="text" v-model="form.pm_joint" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Bladder</label>
                            <input type="text" v-model="form.pm_bladder" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Liver</label>
                            <input type="text" v-model="form.pm_liver" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Spleen</label>
                            <input type="text" v-model="form.pm_spleen" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                    </div>

                    <h4 class="text-sm font-bold text-gray-800 mb-4 pb-2 border-b-2 border-[#34554a]">Stomach</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Rumen</label>
                            <input type="text" v-model="form.pm_stomachrumen" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Reticulum</label>
                            <input type="text" v-model="form.pm_stomachreticulum" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Abomasum</label>
                            <input type="text" v-model="form.pm_stomachabomasum" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                    </div>

                    <h4 class="text-sm font-bold text-gray-800 mb-4 pb-2 border-b-2 border-[#34554a]">Intestine</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Small Intestine</label>
                            <input type="text" v-model="form.pm_intestine_small" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Colon</label>
                            <input type="text" v-model="form.pm_intestine_colon" placeholder="Findings..." class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]" />
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="form.hasErrors" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-600 font-medium">Form has errors:</p>
                <pre class="text-xs text-red-500 mt-1">{{ JSON.stringify(form.errors, null, 2) }}</pre>
            </div>
            <div v-if="form.recentlySuccessful" class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-sm text-green-600 font-medium">Record saved successfully!</p>
            </div>
        </form>
    </div>
</template>
