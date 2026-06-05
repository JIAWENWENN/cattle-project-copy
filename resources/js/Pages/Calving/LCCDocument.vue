<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Download, ArrowLeft } from 'lucide-vue-next';

defineOptions({
    layout: (h, page) => h({ render: () => page })
});

const props = defineProps({
    calvingRecord: {
        type: Object,
        required: true
    }
});

const record = computed(() => props.calvingRecord || {});

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return '';
    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const formatDateOrDash = (dateString) => formatDate(dateString) || '-';
const checked = (condition) => (condition ? '☑' : '☐');
const textOrDash = (value) => (value && String(value).trim() ? String(value) : '-');

const isMale = computed(() => String(record.value.sex || '').toUpperCase() === 'MC');
const isFemale = computed(() => String(record.value.sex || '').toUpperCase() === 'FC');
const isNormal = computed(() => String(record.value.general_condition || '').toLowerCase() === 'normal');
const isAbnormal = computed(() => !isNormal.value && !!String(record.value.general_condition || '').trim());

const calfColours = ['Kelabu (Grey)', 'Hitam (Black)', 'Madu (Honey)', 'Berjalur (Stripe)', 'Merah (Red)'];
const isCalfColour = (label) => String(record.value.colour || '').trim().toLowerCase() === label.toLowerCase();
const isDamColour = (label) => String(record.value.dam_colour || '').trim().toLowerCase() === label.toLowerCase();

const downloadPDF = () => window.print();
const goBack = () => router.visit('/calving');
</script>

<template>
    <div class="page-wrap">
        <div class="no-print toolbar">
            <button @click="goBack" class="btn btn-light">
                <ArrowLeft class="w-4 h-4" />
                Back to List
            </button>
            <button @click="downloadPDF" class="btn btn-primary">
                <Download class="w-4 h-4" />
                Download LCC
            </button>
        </div>

        <div class="sheet">
            <div class="header center">
                <div class="brand">Sawit Kinabalu</div>
                <div class="company">SAWIT KINABALU FARM PRODUCTS SDN BHD (Co. No. 465571-P)</div>
                <div class="company">Wholly owned by Sawit Kinabalu Sdn Bhd (Co. No. 403109-V)</div>
                <div class="title">SIJIL KELAHIRAN TERNAKAN</div>
                <div class="subtitle">(LIVESTOCK CALVING CERTIFICATE)</div>
            </div>

            <div class="lcc-row">
                <span class="lcc-label">LCC NO :</span>
                <span class="lcc-value">{{ textOrDash(record.lcc_running_number) }}</span>
            </div>

            <div class="section-title">BUTIRAN ANAK (Case Detail) :</div>
            <table class="detail-table">
                <tr>
                    <td class="label">No. Pengenalan (Identification Tag) :</td>
                    <td class="value">{{ textOrDash(record.tag_no) }}</td>
                </tr>
                <tr>
                    <td class="label">Cattle Number Request Form :</td>
                    <td class="value">{{ textOrDash(record.cattle_no_request_form) }}</td>
                </tr>
                <tr>
                    <td class="label">Tarikh Lahir (Date of Birth) :</td>
                    <td class="value">{{ formatDateOrDash(record.calving_date) }}</td>
                </tr>
                <tr>
                    <td class="label">Jantina (Gender) :</td>
                    <td class="value">{{ checked(isMale) }} Male (Jantan) &nbsp;&nbsp;&nbsp; {{ checked(isFemale) }} Female (Betina)</td>
                </tr>
                <tr>
                    <td class="label">Warna (Coat Colour) :</td>
                    <td class="value checkbox-grid">
                        <div>{{ checked(isCalfColour(calfColours[0])) }} {{ calfColours[0] }}</div>
                        <div>{{ checked(isCalfColour(calfColours[1])) }} {{ calfColours[1] }}</div>
                        <div>{{ checked(isCalfColour(calfColours[2])) }} {{ calfColours[2] }}</div>
                        <div>{{ checked(isCalfColour(calfColours[3])) }} {{ calfColours[3] }}</div>
                        <div>{{ checked(isCalfColour(calfColours[4])) }} {{ calfColours[4] }}</div>
                    </td>
                </tr>
                <tr>
                    <td class="label">Kondisi Anak Sapi (Cattle Condition) :</td>
                    <td class="value">{{ checked(isNormal) }} Normal &nbsp;&nbsp;&nbsp; {{ checked(isAbnormal) }} Abnormal</td>
                </tr>
            </table>

            <div class="section-title">BUTIRAN INDUK (Breeder's Details) :</div>
            <table class="detail-table">
                <tr>
                    <td class="label">No. Pengenalan Indung (Dam's Identification Tag) :</td>
                    <td class="value">{{ textOrDash(record.dam_tag_no) }}</td>
                </tr>
                <tr>
                    <td class="label">Baka Indung (Dam's Breed) :</td>
                    <td class="value">Brahman</td>
                </tr>
                <tr>
                    <td class="label">Warna (Coat Colour) :</td>
                    <td class="value checkbox-grid">
                        <div>{{ checked(isDamColour(calfColours[0])) }} {{ calfColours[0] }}</div>
                        <div>{{ checked(isDamColour(calfColours[1])) }} {{ calfColours[1] }}</div>
                        <div>{{ checked(isDamColour(calfColours[2])) }} {{ calfColours[2] }}</div>
                        <div>{{ checked(isDamColour(calfColours[3])) }} {{ calfColours[3] }}</div>
                        <div>{{ checked(isDamColour(calfColours[4])) }} {{ calfColours[4] }}</div>
                    </td>
                </tr>
            </table>

            <div class="section-title">BUTIRAN LAIN (Other Details) :</div>
            <table class="detail-table">
                <tr>
                    <td class="label">Nama Pekerja (Worker's Name) :</td>
                    <td class="value">{{ textOrDash(record.worker_name) }}</td>
                </tr>
                <tr>
                    <td class="label">Unit Operasi (Operating Unit) :</td>
                    <td class="value">{{ textOrDash(record.operating_unit) }}</td>
                </tr>
                <tr>
                    <td class="label">Lokasi (Location) :</td>
                    <td class="value">Block : {{ textOrDash(record.location_block) }} &nbsp;&nbsp;&nbsp; Phase : {{ textOrDash(record.location_phase) }}</td>
                </tr>
            </table>

            <div class="section-title">Approval Section</div>
            <table class="approval-table">
                <tr>
                    <th>Issued by :</th>
                    <th>Verified by :</th>
                    <th>Checked by :</th>
                    <th>Witness by :</th>
                    <th>Approved by :</th>
                </tr>
                <tr class="signature-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td>{{ textOrDash(record.issued_by_name) }}</td>
                    <td>{{ textOrDash(record.verified_by_name) }}</td>
                    <td>{{ textOrDash(record.checked_by_name) }}</td>
                    <td>{{ textOrDash(record.witnessed_by_name) }}</td>
                    <td>{{ textOrDash(record.approved_by_name) }}</td>
                </tr>
                <tr>
                    <td>Sr. Asst. Livestock</td>
                    <td>Sr. Assistant Security</td>
                    <td>Supervisor Livestock</td>
                    <td>Estate Management</td>
                    <td>Livestock Manager / OIC</td>
                </tr>
                <tr>
                    <td>Date: {{ formatDate(record.issued_at || record.issued_by_date) }}</td>
                    <td>Date: {{ formatDate(record.verified_at || record.verified_by_date) }}</td>
                    <td>Date: {{ formatDate(record.checked_at || record.checked_by_date) }}</td>
                    <td>Date: {{ formatDate(record.witnessed_at || record.witnessed_by_date) }}</td>
                    <td>Date: {{ formatDate(record.approved_at || record.approved_by_date) }}</td>
                </tr>
            </table>

            <div class="footer-copies">
                <div>Original Copy : SKFP Office</div>
                <div>2nd Copy : SAL</div>
                <div>3rd Copy : Issuing Office</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-wrap {
    min-height: 100vh;
    background: #f3f4f6;
    padding: 24px;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    margin-bottom: 16px;
}

.btn {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}

.btn-light {
    border: 1px solid #d1d5db;
    background: #fff;
    color: #4b5563;
}

.btn-primary {
    border: 1px solid #34554a;
    background: #34554a;
    color: #fff;
}

.sheet {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    background: #fff;
    border: 1px solid #111827;
    padding: 12mm 10mm;
    font-family: "Times New Roman", serif;
    font-size: 12px;
    color: #111827;
}

.center {
    text-align: center;
}

.brand {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 2px;
}

.company {
    font-size: 12px;
    line-height: 1.25;
}

.title {
    font-size: 18px;
    margin-top: 8px;
    font-weight: 700;
}

.subtitle {
    font-size: 12px;
    margin-bottom: 10px;
}

.lcc-row {
    text-align: right;
    font-weight: 700;
    margin-bottom: 8px;
}

.lcc-value {
    display: inline-block;
    min-width: 70px;
    text-align: left;
    margin-left: 6px;
}

.section-title {
    font-weight: 700;
    margin-top: 8px;
    margin-bottom: 4px;
    text-transform: none;
}

.detail-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 6px;
}

.detail-table td {
    vertical-align: top;
    padding: 3px 2px;
}

.detail-table .label {
    width: 45%;
    font-weight: 500;
}

.detail-table .value {
    width: 55%;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 2px 12px;
}

.approval-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6px;
    font-size: 11px;
}

.approval-table th,
.approval-table td {
    border: 1px solid #1f2937;
    padding: 4px 5px;
    vertical-align: top;
}

.approval-table th {
    text-align: left;
    font-weight: 700;
}

.signature-row td {
    height: 24px;
}

.footer-copies {
    margin-top: 10px;
    font-size: 11px;
    line-height: 1.4;
}

@page {
    size: A4 portrait;
    margin: 8mm;
}

@media print {
    .page-wrap {
        padding: 0;
        background: #fff;
    }

    .no-print {
        display: none !important;
    }

    .sheet {
        width: 100%;
        min-height: auto;
        border: 1px solid #111827;
        margin: 0;
        box-shadow: none;
        page-break-inside: avoid;
    }

    * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
