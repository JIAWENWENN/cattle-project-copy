<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { 
    Truck, Plus, Search, Filter, MoreVertical,
    Edit, Trash2, Eye, Calendar, MapPin,
    Phone, Mail, User, FileText,
    ChevronDown, X, Save, CheckCircle, ChevronLeft, ChevronRight
} from 'lucide-vue-next';

const props = defineProps({
    drivers: {
        type: Array,
        default: () => []
    }
});

const searchQuery = ref('');
const statusFilter = ref('');
const showAddModal = ref(false);
const showDetailsModal = ref(false);
const showDeleteModal = ref(false);
const selectedDriver = ref(null);
const isEditing = ref(false);
const currentPage = ref(1);
const itemsPerPage = 10;

const driverForm = useForm({
    name: '',
    phone: '',
    email: '',
    license_number: '',
    license_expiry: '',
    vehicle_assigned: '',
    address: '',
    emergency_contact: '',
    status: 'active',
    notes: ''
});

const filteredDrivers = computed(() => {
    return props.drivers.filter(driver => {
        const matchesSearch = !searchQuery.value || 
            driver.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            driver.email.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            driver.phone.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            driver.license_number.toLowerCase().includes(searchQuery.value.toLowerCase());
        
        const matchesStatus = !statusFilter.value || driver.status === statusFilter.value;
        
        return matchesSearch && matchesStatus;
    });
});

const totalPages = computed(() => {
    return Math.ceil(filteredDrivers.value.length / itemsPerPage);
});

const paginatedDrivers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredDrivers.value.slice(start, end);
});

const pageNumbers = computed(() => {
    const pages = [];
    const maxVisible = 5;

    if (totalPages.value <= maxVisible) {
        for (let i = 1; i <= totalPages.value; i++) {
            pages.push(i);
        }
    } else if (currentPage.value <= 3) {
        pages.push(1, 2, 3, '...', totalPages.value);
    } else if (currentPage.value >= totalPages.value - 2) {
        pages.push(1, '...', totalPages.value - 2, totalPages.value - 1, totalPages.value);
    } else {
        pages.push(1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value);
    }

    return pages;
});

const getStatusClass = (status) => {
    const classes = {
        'active': 'bg-green-100 text-green-700 border-green-200',
        'inactive': 'bg-red-100 text-red-700 border-red-200',
        'on_leave': 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'suspended': 'bg-orange-100 text-orange-700 border-orange-200'
    };
    return classes[status] || 'bg-gray-100 text-gray-700 border-gray-200';
};

const formatStatus = (status) => {
    const normalized = String(status || '').replace(/_/g, ' ').toLowerCase();
    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const openAddModal = () => {
    // This is no longer used for adding new drivers
    selectedDriver.value = null;
    isEditing.value = false;
    driverForm.reset();
    showAddModal.value = true;
};

const openEditModal = (driver) => {
    selectedDriver.value = driver;
    isEditing.value = true;
    driverForm.name = driver.name;
    driverForm.phone = driver.phone;
    driverForm.email = driver.email;
    driverForm.license_number = driver.license_number;
    driverForm.license_expiry = driver.license_expiry;
    driverForm.vehicle_assigned = driver.vehicle_assigned;
    driverForm.address = driver.address;
    driverForm.emergency_contact = driver.emergency_contact;
    driverForm.status = driver.status;
    driverForm.notes = driver.notes;
    showAddModal.value = true;
};

const openDetailsModal = (driver) => {
    selectedDriver.value = driver;
    showDetailsModal.value = true;
};

const openDeleteModal = (driver) => {
    selectedDriver.value = driver;
    showDeleteModal.value = true;
};

const closeModals = () => {
    showAddModal.value = false;
    showDetailsModal.value = false;
    showDeleteModal.value = false;
    selectedDriver.value = null;
    driverForm.reset();
};

const saveDriver = () => {
    if (isEditing.value && selectedDriver.value) {
        driverForm.put(route('driver.update', selectedDriver.value.id), {
            onSuccess: () => closeModals(),
        });
    }
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const previousPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
};

const nextPage = () => {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
};

watch([searchQuery, statusFilter], () => {
    currentPage.value = 1;
});

watch(totalPages, (newTotal) => {
    if (newTotal === 0) {
        currentPage.value = 1;
        return;
    }

    if (currentPage.value > newTotal) {
        currentPage.value = newTotal;
    }
});

</script>

<template>
    <Head title="Driver Management" />

    <AppLayout title="Driver Management" parent="Driver" parentUrl="/driver">
        <!-- Page Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Driver Management</h1>
                <p class="text-sm text-gray-500 mt-1">Manage all drivers, track deliveries, and monitor performance.</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Total Drivers</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1">{{ filteredDrivers.length }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-[#34554a]/10 flex items-center justify-center">
                        <svg class="w-8 h-8" viewBox="0 0 295.326 295.326" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <g>
                                <g><path style="fill:#34631C;" d="M127.011,181.972h41.269c0,0,0.938,17.467-19.681,17.467S126.98,186.18,127.011,181.972z"></path></g>
                                <g><rect x="130.834" y="159.326" style="fill:#DDC29B;" width="34" height="51"></rect></g>
                                <g><path style="fill:#E8D8BF;" d="M225.309,107.248c0-6.457-3.983-11.976-9.623-14.257c0.202-2.248,0.31-4.474,0.31-6.666c0-37.646-30.518-51.123-68.164-51.123S79.669,48.68,79.669,86.325c0,2.192,0.108,4.418,0.31,6.666c-5.64,2.281-9.623,7.8-9.623,14.257c0,8.351,11.241,19.728,19.693,20.112c12.054,24.255,33.425,44.17,57.784,44.17s45.73-19.915,57.784-44.17C214.068,126.976,225.309,115.599,225.309,107.248z"></path></g>
                                <g><path style="fill:#F9B65F;" d="M77.776,92.393c0,0,4.389,11.102,11.619,25.82c0,0-5.422-18.848,0-29.692"></path></g>
                                <g><path style="fill:#F9B65F;" d="M216.801,92.393c0,0-2.711,16.266-14.436,27.051c0,0,8.239-20.079,2.817-30.924"></path></g>
                                <g><path style="fill:#C63C22;" d="M254.693,222.113c-2.925-13.486-13.435-24.034-26.91-27.008l-49.316-10.885l-10.188-2.249c0,11.481-9,17.483-20.481,17.483s-20.788-6.002-20.789-17.483l-9.816,2.166l-49.688,10.967c-13.475,2.974-23.985,13.522-26.91,27.008l-9.126,42.076c13.14,8.612,28.709,15.764,46.016,21.034c21.242,6.469,45.104,10.102,70.326,10.102c25.092,0,48.839-3.595,69.999-10.001c17.31-5.241,32.886-12.364,46.047-20.945L254.693,222.113z"></path></g>
                                <g><path style="fill:#F9B65F;" d="M175.701,62.065c0,0-8.632,11.188-25.007,11.674l2.274-8.269c0,0-22.744,21.403-38.665,26.267v-8.756c0,0-9.021,16.007-31.855,20.384c0,0-13.481-20.709-2.715-51.516c9.115-26.084,50.643-50.114,72.022-38.565c0,0,35.784-6.673,57.828,22.026c18.553,24.154,11.09,52.756,4.682,65.668C214.265,100.98,188.882,93.683,175.701,62.065z"></path></g>
                                <g><path style="fill:#79302A;" d="M168.28,181.972c0,0,2.32,19.597-20.446,19.597c-21.484,0-20.824-19.597-20.824-19.597l3.824-8.354c0,9.389,7.611,13.695,17,13.695s17-4.306,17-13.695L168.28,181.972z"></path></g>
                                <g><rect x="143.813" y="197.334" style="fill:#79302A;" width="8" height="97.992"></rect></g>
                                <g><path style="fill:#CC823A;" d="M232.997,246.732l25.518-7l-3.821-17.619c-0.513-2.363-1.271-4.629-2.224-6.787l-19.472,7L232.997,246.732L232.997,246.732z"></path></g>
                                <g><path style="fill:#79302A;" d="M73.514,193.747c3.336,7.894,11.514,30.363,10.085,58.079c0,0-7.772-42.043-17.41-53.854c-1.336-1.637-0.821-4.08,1.092-4.98l1.905-0.895C70.838,191.32,72.804,192.065,73.514,193.747z"></path></g>
                                <g><path style="fill:#79302A;" d="M221.817,193.747c-3.336,7.894-11.514,30.363-10.085,58.079c0,0,7.772-42.043,17.411-53.854c1.336-1.637,0.821-4.08-1.092-4.98l-1.905-0.895C224.494,191.32,222.528,192.065,221.817,193.747z"></path></g>
                                <g><rect x="88.557" y="254.826" style="fill:#79302A;" width="39.88" height="8.9"></rect></g>
                                <g><path style="fill:#D3862A;" d="M174.575,240.226h17.886c1.943,0,3.518-1.575,3.518-3.518v-1.864c0-1.943-1.575-3.518-3.518-3.518h-17.886c-1.943,0-3.518,1.575-3.518,3.518v1.864C171.057,238.651,172.632,240.226,174.575,240.226z"></path></g>
                                <g><path style="fill:#D3862A;" d="M174.575,253.226h17.886c1.943,0,3.518-1.575,3.518-3.518v-1.864c0-1.943-1.575-3.518-3.518-3.518h-17.886c-1.943,0-3.518,1.575-3.518,3.518v1.864C171.057,251.651,172.632,253.226,174.575,253.226z"></path></g>
                                <g><rect x="88.557" y="241.826" style="fill:#79302A;" width="39.88" height="8.9"></rect></g>
                                <g>
                                    <g><path style="fill:#79302A;" d="M147.813,0c-47.23,0-85.517,21.749-85.517,68.979s38.287,102.055,85.517,102.055s85.517-54.825,85.517-102.055S195.042,0,147.813,0zM199.765,128.716c-10.635,10.635-93.228,10.677-103.906,0c-5.486-18.773-9.453-37.733-11.982-56.542c8.377,8.377,119.098,8.772,127.87,0C209.218,90.983,205.251,109.943,199.765,128.716z"></path></g>
                                    <g><path style="fill:#C63C22;" d="M228.71,43.008c-4.874,18.753-16.962,29.166-16.962,29.166c-2.478,18.427-6.667,38.146-11.982,56.542l0,0c7.633-2.876,17.652-12.089,26.188-22.132c4.739-12.307,7.377-25.217,7.377-37.605C233.33,59.251,231.702,50.607,228.71,43.008z"></path></g>
                                    <path style="fill:#B85627;" d="M147.813,0c-2.676,0-5.319,0.08-7.933,0.22c-3.296,17.771-12.113,55.77-30.052,77.033c22.227,1.756,53.82,1.819,76.04,0.082c-17.987-21.247-26.823-59.32-30.124-77.115C153.132,0.08,150.489,0,147.813,0z"></path>
                                    <g><path style="fill:#C63C22;" d="M83.878,72.174c0,0-12.088-10.413-16.962-29.166c-2.992,7.599-4.62,16.243-4.62,25.971c0,12.388,2.638,25.298,7.377,37.605c8.535,10.044,18.555,19.256,26.188,22.132l0,0C90.544,110.32,86.356,90.601,83.878,72.174z"></path></g>
                                </g>
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Active Drivers</p>
                        <p class="text-3xl font-bold text-green-600 mt-1">
                            {{ filteredDrivers.filter(d => d.status === 'active').length }}
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                        <svg class="w-8 h-8" viewBox="0 0 295.326 295.326" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <g>
                                <g><path style="fill:#34631C;" d="M127.011,181.972h41.269c0,0,0.938,17.467-19.681,17.467S126.98,186.18,127.011,181.972z"></path></g>
                                <g><rect x="130.834" y="159.326" style="fill:#DDC29B;" width="34" height="51"></rect></g>
                                <g><path style="fill:#E8D8BF;" d="M225.309,107.248c0-6.457-3.983-11.976-9.623-14.257c0.202-2.248,0.31-4.474,0.31-6.666c0-37.646-30.518-51.123-68.164-51.123S79.669,48.68,79.669,86.325c0,2.192,0.108,4.418,0.31,6.666c-5.64,2.281-9.623,7.8-9.623,14.257c0,8.351,11.241,19.728,19.693,20.112c12.054,24.255,33.425,44.17,57.784,44.17s45.73-19.915,57.784-44.17C214.068,126.976,225.309,115.599,225.309,107.248z"></path></g>
                                <g><path style="fill:#02041d;" d="M77.776,92.393c0,0,4.389,11.102,11.619,25.82c0,0-5.422-18.848,0-29.692"></path></g>
                                <g><path style="fill:#02041d;" d="M216.801,92.393c0,0-2.711,16.266-14.436,27.051c0,0,8.239-20.079,2.817-30.924"></path></g>
                                <g><path style="fill:#0b0958;" d="M254.693,222.113c-2.925-13.486-13.435-24.034-26.91-27.008l-49.316-10.885l-10.188-2.249c0,11.481-9,17.483-20.481,17.483s-20.788-6.002-20.789-17.483l-9.816,2.166l-49.688,10.967c-13.475,2.974-23.985,13.522-26.91,27.008l-9.126,42.076c13.14,8.612,28.709,15.764,46.016,21.034c21.242,6.469,45.104,10.102,70.326,10.102c25.092,0,48.839-3.595,69.999-10.001c17.31-5.241,32.886-12.364,46.047-20.945L254.693,222.113z"></path></g>
                                <g><path style="fill:#02041d;" d="M175.701,62.065c0,0-8.632,11.188-25.007,11.674l2.274-8.269c0,0-22.744,21.403-38.665,26.267v-8.756c0,0-9.021,16.007-31.855,20.384c0,0-13.481-20.709-2.715-51.516c9.115-26.084,50.643-50.114,72.022-38.565c0,0,35.784-6.673,57.828,22.026c18.553,24.154,11.09,52.756,4.682,65.668C214.265,100.98,188.882,93.683,175.701,62.065z"></path></g>
                                <g><path style="fill:#2a5379;" d="M168.28,181.972c0,0,2.32,19.597-20.446,19.597c-21.484,0-20.824-19.597-20.824-19.597l3.824-8.354c0,9.389,7.611,13.695,17,13.695s17-4.306,17-13.695L168.28,181.972z"></path></g>
                                <g><rect x="143.813" y="197.334" style="fill:#2a5379;" width="8" height="97.992"></rect></g>
                                <g><path style="fill:#c5280d;" d="M232.997,246.732l25.518-7l-3.821-17.619c-0.513-2.363-1.271-4.629-2.224-6.787l-19.472,7L232.997,246.732L232.997,246.732z"></path></g>
                                <g><path style="fill:#2a5379;" d="M73.514,193.747c3.336,7.894,11.514,30.363,10.085,58.079c0,0-7.772-42.043-17.41-53.854c-1.336-1.637-0.821-4.08,1.092-4.98l1.905-0.895C70.838,191.32,72.804,192.065,73.514,193.747z"></path></g>
                                <g><path style="fill:#2a5379;" d="M221.817,193.747c-3.336,7.894-11.514,30.363-10.085,58.079c0,0,7.772-42.043,17.411-53.854c1.336-1.637,0.821-4.08-1.092-4.98l-1.905-0.895C224.494,191.32,222.528,192.065,221.817,193.747z"></path></g>
                                <g><rect x="88.557" y="254.826" style="fill:#2a5379;" width="39.88" height="8.9"></rect></g>
                                <g><path style="fill:#ff0000;" d="M174.575,240.226h17.886c1.943,0,3.518-1.575,3.518-3.518v-1.864c0-1.943-1.575-3.518-3.518-3.518h-17.886c-1.943,0-3.518,1.575-3.518,3.518v1.864C171.057,238.651,172.632,240.226,174.575,240.226z"></path></g>
                                <g><path style="fill:#ff0000;" d="M174.575,253.226h17.886c1.943,0,3.518-1.575,3.518-3.518v-1.864c0-1.943-1.575-3.518-3.518-3.518h-17.886c-1.943,0-3.518,1.575-3.518,3.518v1.864C171.057,251.651,172.632,253.226,174.575,253.226z"></path></g>
                                <g><rect x="88.557" y="241.826" style="fill:#2a5379;" width="39.88" height="8.9"></rect></g>
                                <g>
                                    <g><path style="fill:#2a5379;" d="M147.813,0c-47.23,0-85.517,21.749-85.517,68.979s38.287,102.055,85.517,102.055s85.517-54.825,85.517-102.055S195.042,0,147.813,0z M199.765,128.716c-10.635,10.635-93.228,10.677-103.906,0c-5.486-18.773-9.453-37.733-11.982-56.542c8.377,8.377,119.098,8.772,127.87,0C209.218,90.983,205.251,109.943,199.765,128.716z"></path></g>
                                    <g><path style="fill:#0b0958;" d="M228.71,43.008c-4.874,18.753-16.962,29.166-16.962,29.166c-2.478,18.427-6.667,38.146-11.982,56.542l0,0c7.633-2.876,17.652-12.089,26.188-22.132c4.739-12.307,7.377-25.217,7.377-37.605C233.33,59.251,231.702,50.607,228.71,43.008z"></path></g>
                                    <path style="fill:#2c3668;" d="M147.813,0c-2.676,0-5.319,0.08-7.933,0.22c-3.296,17.771-12.113,55.77-30.052,77.033c22.227,1.756,53.82,1.819,76.04,0.082c-17.987-21.247-26.823-59.32-30.124-77.115C153.132,0.08,150.489,0,147.813,0z"></path>
                                    <g><path style="fill:#0b0958;" d="M83.878,72.174c0,0-12.088-10.413-16.962-29.166c-2.992,7.599-4.62,16.243-4.62,25.971c0,12.388,2.638,25.298,7.377,37.605c8.535,10.044,18.555,19.256,26.188,22.132l0,0C90.544,110.32,86.356,90.601,83.878,72.174z"></path></g>
                                </g>
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Total Deliveries</p>
                        <p class="text-3xl font-bold text-blue-600 mt-1">
                            {{ filteredDrivers.reduce((sum, d) => sum + d.total_deliveries, 0) }}
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                        <svg class="w-8 h-8" viewBox="0 0 128 128" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <g>
                                <path d="M57.99 53.73s-.53-1.53-1.68-2.06c-1.14-.53-3.05-.61-3.05-.61l-3.2 37.85l2.6 18.68s.76 2.21 2.75 2.29c1.98.08 18.47 0 19.92 0c1.45 0 1.91-1.91 1.98-2.75c.08-.84.05-2.25.05-2.25l7.04-.03s.41 3-.27 4.89c-.59 1.64-1.12 2.95-1.11 3.51c.01.8 1.07 1.6 2.29 1.53c1.22-.08 3.05-1.68 4.27-2.67c1.22-.99 20.78-.76 20.78-.76s.94 1.74 1.99 2.79c.63.63 1.65.94 2.57.94c.92 0 2.06-1.22 1.83-2.06c-.23-.84-1.27-2.77-1.45-4.65c-.08-.85.02-3.22.44-3.61c.1-.09 1.62-.04 3.04-.19c2.42-.25 4.84-3.38 5.14-5.82c.31-2.44.08-10.15.08-10.15L57.99 53.73z" fill="#b3afaf"></path>
                                <path d="M52.02 96.11h72.03s.17 2.02-.18 3.32a5.612 5.612 0 0 1-.93 1.91l-70.51-.01l-.41-5.22z" fill="#9a9a9a"></path>
                                <path d="M26.75 92.84L11.5 97.56l-7.62-2.63s.27-14.34.36-15.79c.09-1.45.45-2.72 1.82-3.63c1.36-.91 5.17-3.18 5.17-3.18s11.71-18.79 12.71-20.15c1-1.36 2.23-3.02 5.14-3.29s20.87-.05 22.5.04c1.63.09 2.85.83 2.85 4.37s-.09 30.56-.18 31.92c-.09 1.36-6.9 6.35-6.9 6.35l-20.6 1.27z" fill="#e0e0e0"></path>
                                <path d="M48.95 110.03s1.46.04 2.47-.1s2.53-1.15 2.6-3.54c.04-1.42.23-21.17.23-21.17l-27.2.06l-8.8 9.63l-14.37.02s-.06 5.65-.02 7.16c.04 1.52 7.86 4.83 7.86 4.83l37.23 3.11z" fill="#dc0d28"></path>
                                <path d="M3.86 102.09h16.38s3.51-7 13.6-6.88c14.82.18 15.12 14.81 15.12 14.81h-38.6c-2.17 0-5.27-.72-6.06-3.46c-.67-2.3-.44-4.47-.44-4.47z" fill="#b10a1b"></path>
                                <path d="M13.89 76.26s0-1.63-1.07-1.69s-2.25.61-3.32 1.52c-1.13.96-1.69 1.83-1.91 2.59c-.34 1.13-.06 2.14 1.69 2.19c2.14.07 36.12-.15 37.69-.28c1.97-.17 3.15-1.01 3.09-2.48c-.05-1.41-1.07-1.86-5.29-1.91s-30.88.06-30.88.06z" fill="#ffffff"></path>
                                <path d="M4.2 81.03s10.21-.32 10.43-.07c.31.37.37 5.19.26 5.92c-.11.73-1.07 1.24-2.25 1.29s-8.61.17-8.61.17l.17-7.31z" fill="#fdedc5"></path>
                                <path d="M46.24 74.51c1.58 0 2.14-1.8 2.14-3.04V55.78c0-1.24-.79-1.91-3.26-2.03c-2.48-.11-13.78.02-15.02.06c-1.91.06-3.21.9-4.95 3.32c-1.13 1.57-8.1 13.11-8.66 14.06c-.56.96-1.24 3.49 1.69 3.54c3.03.06 28.06-.22 28.06-.22z" fill="#5f6369"></path>
                                <path d="M19.63 71.87h25.2c.51 0 .96-.56.96-1.24s.04-11.3.06-11.93c.06-1.74-1.24-2.14-2.36-2.08c-1.13.06-12.51.02-13.18.05c-1.29.06-2.57 1.79-3.69 3.5c-1.35 2.07-6.99 11.7-6.99 11.7z" fill="#afe3fb"></path>
                                <path d="M20.02 73.73c.11 2.53 4.16 4.61 5.18 4.67c1.01.06 1.63-1.91 1.74-4.56S25.99 69 25.14 69c-.84 0-5.21 2.59-5.12 4.73z" fill="#5f6369"></path>
                                <path d="M20.9 111.62c.08 5 4.06 12.19 12.72 12.24c8.66.05 13.16-6.59 12.88-13.4c-.28-6.92-5.65-11.81-13.38-11.59c-7.23.22-12.33 5.84-12.22 12.75z" fill="#4e433d"></path>
                                <path d="M27.09 111.33c.04 2.63 2.13 6.43 6.69 6.46s6.76-3.27 6.62-6.86c-.15-3.65-3.06-6.37-6.88-6.31c-3.8.05-6.48 3.06-6.43 6.71z" fill="#c8c8c8"></path>
                                <path d="M86.9 111.62c.08 5 4.06 12.19 12.72 12.24c8.66.05 13.16-6.59 12.88-13.4c-.28-6.92-5.65-11.81-13.38-11.59c-7.23.22-12.33 5.84-12.22 12.75z" fill="#4e433d"></path>
                                <path d="M93.09 111.33c.04 2.63 2.13 6.43 6.69 6.46c4.55.03 6.76-3.27 6.62-6.86c-.15-3.65-3.06-6.37-6.88-6.31c-3.8.05-6.48 3.06-6.43 6.71z" fill="#c8c8c8"></path>
                                <path d="M56.87 28.62c0-2.62 1.15-3.67 3.25-3.56c2.1.1 59.65-.21 61.53-.21s2.62 1.99 2.62 3.14c0 1.15-.36 59.49-.28 60.62c.07 1.13-.24 2.7-2.23 2.91s-61.01.1-62.27 0s-2.83-1.15-2.83-2.62c0-1.48.21-58.92.21-60.28z" fill="#d16e1d"></path>
                                <path d="M62.11 30.92c0 1.36-.42 52.31-.42 53.57c0 1.26.63 2.73 2.31 2.73c1.68 0 53.25.1 54.3 0c1.05-.1 1.68-.73 1.78-2.41c.1-1.68.1-52.83.1-54.51c0-1.68-1.68-1.78-3.46-1.78s-52.2.1-52.94.21c-.73.09-1.67.83-1.67 2.19z" fill="#f5b03a"></path>
                                <path d="M74.45 89c-.06-18.45-.19-61.48.02-62.65l1.48.27l1.49.18c-.14 1.65-.07 38.09.01 62.19l-3 .01z" fill="#d16e1d"></path>
                                <path fill="#d16e1d" d="M89.75 26.52h3V89.1h-3z"></path>
                                <path fill="#d16e1d" d="M104.89 26.57h3v62.27h-3z"></path>
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1 relative">
                    <input 
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search drivers by name, phone, or license..."
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                    >
                    <Search class="w-4 h-4 text-gray-400 absolute left-3 top-3" />
                </div>
                <div class="flex gap-2">
                    <select 
                        v-model="statusFilter"
                        class="px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none bg-white"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Driver Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left whitespace-nowrap">
                    <thead>
                        <tr class="bg-[#34554a] text-white text-sm">
                            <th class="px-6 py-4 font-semibold">Driver</th>
                            <th class="px-6 py-4 font-semibold">License</th>
                            <th class="px-6 py-4 font-semibold">Vehicle Assigned</th>
                            <th class="px-6 py-4 font-semibold">Deliveries</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                        <tr 
                            v-for="driver in paginatedDrivers" 
                            :key="driver.id"
                            class="hover:bg-gray-50 transition-colors group"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ driver.name }}</p>
                                        <p class="text-xs text-gray-500">{{ driver.phone || 'No phone' }}</p>
                                        <p class="text-xs text-[#34554a] font-medium">{{ driver.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900">{{ driver.license_number }}</p>
                                <p class="text-sm text-gray-500">Expires: {{ driver.license_expiry }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-gray-900">{{ driver.vehicle_assigned }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-gray-900">{{ driver.total_deliveries }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium border', getStatusClass(driver.status)]">
                                    {{ formatStatus(driver.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button 
                                        @click="openDetailsModal(driver)"
                                        class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors"
                                        title="View Details"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <button 
                                        @click="openEditModal(driver)"
                                        class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors"
                                        title="Edit"
                                    >
                                        <Edit class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredDrivers.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400 italic">
                                <p class="text-sm">No driver records found</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-center p-4 border-t border-gray-100 bg-gray-50">
                <div class="flex items-center gap-2 mb-4 md:mb-0">
                    <button
                        @click="previousPage"
                        :disabled="currentPage === 1"
                        :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                        class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>

                    <button
                        v-for="page in pageNumbers"
                        :key="page"
                        @click="page !== '...' && goToPage(page)"
                        :class="[
                            page === currentPage ? 'bg-[#34554a] text-white' : 'text-gray-600 hover:bg-white',
                            page === '...' ? 'cursor-default' : ''
                        ]"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-bold transition-colors"
                    >
                        {{ page }}
                    </button>

                    <button
                        @click="nextPage"
                        :disabled="currentPage === totalPages || totalPages === 0"
                        :class="currentPage === totalPages || totalPages === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-white'"
                        class="w-8 h-8 flex items-center justify-center border border-gray-200 rounded-lg text-gray-500"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>

                <div class="text-sm text-gray-500">
                    Showing <span class="font-semibold text-gray-800">{{ filteredDrivers.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0 }}-{{ Math.min(currentPage * itemsPerPage, filteredDrivers.length) }}</span> of <span class="font-semibold text-gray-800">{{ filteredDrivers.length }}</span> records
                </div>
            </div>
        </div>

        <!-- Add/Edit Driver Modal -->
        <Teleport to="body">
            <div 
                v-if="showAddModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="closeModals"
            >
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
                    <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Truck class="w-5 h-5 text-[#34554a]" />
                            {{ isEditing ? 'Edit Driver' : 'Add New Driver' }}
                        </h3>
                        <button 
                            @click="closeModals"
                            class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 transition-colors"
                        >
                            ✕
                        </button>
                    </div>
                    <form @submit.prevent="saveDriver" class="flex flex-col max-h-[90vh]">
                        <div class="px-6 py-4 overflow-y-auto flex-1 custom-scrollbar">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name (System)</label>
                                    <input 
                                        v-model="driverForm.name"
                                        type="text"
                                        disabled
                                        class="w-full px-4 py-2.5 border border-gray-100 bg-gray-50 text-gray-500 rounded-lg text-sm cursor-not-allowed outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number</label>
                                    <input 
                                        v-model="driverForm.phone"
                                        type="tel"
                                        placeholder="+6012-3456789"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address (System)</label>
                                    <input 
                                        v-model="driverForm.email"
                                        type="email"
                                        disabled
                                        class="w-full px-4 py-2.5 border border-gray-100 bg-gray-50 text-gray-500 rounded-lg text-sm cursor-not-allowed outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">License Number</label>
                                    <input 
                                        v-model="driverForm.license_number"
                                        type="text"
                                        required
                                        placeholder="DL123456"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">License Expiry</label>
                                    <input 
                                        v-model="driverForm.license_expiry"
                                        type="date"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Vehicle Assigned</label>
                                    <input 
                                        v-model="driverForm.vehicle_assigned"
                                        type="text"
                                        placeholder="Enter vehicle details (e.g. Truck 001 - Volvo FH16)"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Address</label>
                                    <textarea 
                                        v-model="driverForm.address"
                                        rows="2"
                                        placeholder="Enter full address"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none resize-none"
                                    ></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Emergency Contact</label>
                                    <input 
                                        v-model="driverForm.emergency_contact"
                                        type="text"
                                        placeholder="Name - Phone number"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Status (System)</label>
                                    <select 
                                        v-model="driverForm.status"
                                        disabled
                                        class="w-full px-4 py-2.5 border border-gray-100 bg-gray-50 text-gray-500 rounded-lg text-sm cursor-not-allowed outline-none"
                                    >
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="on_leave">On Leave</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                                    <textarea 
                                        v-model="driverForm.notes"
                                        rows="3"
                                        placeholder="Additional notes about the driver"
                                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#34554a] focus:border-transparent outline-none resize-none"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 flex justify-end gap-3 border-t border-gray-100 bg-gray-50">
                            <button 
                                type="button"
                                @click="closeModals"
                                class="px-5 py-2.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 font-medium"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-5 py-2.5 bg-[#34554a] text-white rounded-lg font-medium hover:bg-[#2c463d] flex items-center gap-2"
                            >
                                <Save class="w-4 h-4" />
                                Update Driver info
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Driver Details Modal -->
        <Teleport to="body">
            <div 
                v-if="showDetailsModal && selectedDriver"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
                @click.self="closeModals"
            >
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
                    <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <Eye class="w-5 h-5 text-[#34554a]" />
                            Driver Details
                        </h3>
                        <button 
                            @click="closeModals"
                            class="text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 transition-colors"
                        >
                            ✕
                        </button>
                    </div>
                    <div class="flex flex-col max-h-[90vh]">
                        <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                            <div class="flex items-center gap-4 mb-6">
                                <div>
                                    <h4 class="text-xl font-bold text-gray-900">{{ selectedDriver.name }}</h4>
                                    <span :class="['px-3 py-1 rounded-full text-xs font-medium border mt-1 inline-block', getStatusClass(selectedDriver.status)]">
                                        {{ formatStatus(selectedDriver.status) }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                                        <Phone class="w-3 h-3" /> Phone
                                    </p>
                                    <p class="font-medium text-gray-900">{{ selectedDriver.phone }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                                        <Mail class="w-3 h-3" /> Email
                                    </p>
                                    <p class="font-medium text-gray-900">{{ selectedDriver.email }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                                        <FileText class="w-3 h-3" /> License No.
                                    </p>
                                    <p class="font-medium text-gray-900">{{ selectedDriver.license_number }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                                        <Calendar class="w-3 h-3" /> License Expiry
                                    </p>
                                    <p class="font-medium text-gray-900">{{ selectedDriver.license_expiry }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                                        <Truck class="w-3 h-3" /> Vehicle Assigned
                                    </p>
                                    <p class="font-medium text-gray-900">{{ selectedDriver.vehicle_assigned }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                                        <MapPin class="w-3 h-3" /> Total Deliveries
                                    </p>
                                    <p class="font-medium text-gray-900">{{ selectedDriver.total_deliveries }}</p>
                                </div>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                <p class="text-xs text-gray-500 mb-1">Address</p>
                                <p class="font-medium text-gray-900">{{ selectedDriver.address }}</p>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                <p class="text-xs text-gray-500 mb-1">Emergency Contact</p>
                                <p class="font-medium text-gray-900">{{ selectedDriver.emergency_contact }}</p>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4">
                                <p class="text-xs text-gray-500 mb-1">Notes</p>
                                <p class="font-medium text-gray-900">{{ selectedDriver.notes }}</p>
                            </div>
                        </div>

                        <div class="px-6 py-4 flex justify-end gap-3 border-t border-gray-100 bg-gray-50">
                            <button 
                                @click="openEditModal(selectedDriver); showDetailsModal = false"
                                class="px-5 py-2.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 font-medium flex items-center gap-2"
                            >
                                <Edit class="w-4 h-4" />
                                Edit Driver
                            </button>
                            <button 
                                @click="closeModals"
                                class="px-5 py-2.5 bg-[#34554a] text-white rounded-lg font-medium hover:bg-[#2c463d]"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
