<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal.vue';
import { ref, computed, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import { 
    Phone, Mail, MapPin, Plus, Search, Eye, Edit, Trash2,
    User, Building, Clock, Star, Stethoscope, X, Save, Upload, Image as ImageIcon,
    ChevronLeft, ChevronRight
} from 'lucide-vue-next';

defineOptions({ 
    layout: (h, page) => h(AppLayout, { title: 'Veterinary Contact', parent: 'Health', parentUrl: '/health' }, () => page)
});

const props = defineProps({
    contacts: Array
});
const page = usePage();

const searchQuery = ref('');
const typeFilter = ref('');
const currentPage = ref(1);
const itemsPerPage = 10;
const showModal = ref(false);
const showViewModal = ref(false);
const showDeleteModal = ref(false);
const isEditing = ref(false);
const selectedContact = ref(null);
const contactToDelete = ref(null);

const stats = computed(() => ({
    totalContacts: props.contacts.length,
    vets: props.contacts.filter(c => c.type === 'veterinarian').length,
    clinics: props.contacts.filter(c => c.type === 'clinic').length,
    suppliers: props.contacts.filter(c => c.type === 'supplier').length,
}));

const form = useForm({
    id: null,
    name: '',
    type: 'veterinarian',
    position: '',
    organization: '',
    phone: '',
    alt_phone: '',
    email: '',
    address: '',
    availability: '',
    emergency: false,
    notes: '',
    profile_photo: null,
});

const profilePreview = ref(null);
const userRole = computed(() => String(page.props.auth?.user?.role || '').toLowerCase());
const contactPermissions = computed(() => {
    const perms = page.props.auth?.permissions?.['Veterinary Contact'];
    return Array.isArray(perms) ? perms : ['no-access'];
});
const hasContactPermission = (action) => {
    if (userRole.value === 'admin') return true;
    const perms = contactPermissions.value;
    return perms.includes('full') || perms.includes(action);
};
const canViewContact = computed(() => hasContactPermission('view'));
const canCreateContact = computed(() => hasContactPermission('create'));
const canEditContact = computed(() => hasContactPermission('edit'));
const canDeleteContact = computed(() => hasContactPermission('delete'));
const malaysiaPhonePattern = '^(?:\\+?60|0)(?:1\\d{8,9}|[3-9]\\d{7,8})$';

const handleImageUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.profile_photo = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            profilePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removeImage = () => {
    form.profile_photo = null;
    profilePreview.value = null;
};

const filteredContacts = computed(() => {
    let result = [...props.contacts];
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(c => 
            c.name.toLowerCase().includes(query) ||
            (c.organization && c.organization.toLowerCase().includes(query)) ||
            c.phone.includes(query) ||
            (c.address && c.address.toLowerCase().includes(query))
        );
    }
    
    if (typeFilter.value) {
        result = result.filter(c => c.type === typeFilter.value);
    }
    
    return result;
});

const totalPages = computed(() => {
    return Math.ceil(filteredContacts.value.length / itemsPerPage);
});

const paginatedContacts = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredContacts.value.slice(start, end);
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

const getTypeClass = (type) => {
    const classes = {
        'veterinarian': 'bg-[#34554a]/10 text-[#34554a]',
        'clinic': 'bg-blue-100 text-blue-700',
        'supplier': 'bg-green-100 text-green-700',
    };
    return classes[type] || 'bg-gray-100 text-gray-700';
};

const getTypeIcon = (type) => {
    const icons = {
        'veterinarian': Stethoscope,
        'clinic': Building,
        'supplier': Building,
    };
    return icons[type] || Building;
};

const formatTypeLabel = (type) => {
    if (!type) return '';
    return type.charAt(0).toUpperCase() + type.slice(1).toLowerCase();
};

const openAdd = () => {
    isEditing.value = false;
    form.reset();
    form.clearErrors();
    profilePreview.value = null;
    showModal.value = true;
};

const openEdit = (contact) => {
    isEditing.value = true;
    form.clearErrors();
    form.id = contact.id;
    form.name = contact.name;
    form.type = contact.type;
    form.position = contact.position;
    form.organization = contact.organization;
    form.phone = contact.phone;
    form.alt_phone = contact.alt_phone;
    form.email = contact.email;
    form.address = contact.address;
    form.availability = contact.availability;
    form.emergency = contact.emergency;
    form.notes = contact.notes;
    profilePreview.value = contact.photo_url;
    showModal.value = true;
};

const viewContact = (contact) => {
    selectedContact.value = contact;
    showViewModal.value = true;
};

const submitForm = () => {
    if (isEditing.value) {
        // Use POST with _method: PUT because PUT doesn't support multipart/form-data (files)
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(`/health/contact/${form.id}`, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post('/health/contact', {
            onSuccess: () => closeModal(),
        });
    }
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    form.clearErrors();
};

const closeViewModal = () => {
    showViewModal.value = false;
    selectedContact.value = null;
};

const deleteContact = (contact) => {
    contactToDelete.value = contact;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (!contactToDelete.value) return;
    router.delete(`/health/contact/${contactToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteModal.value = false;
            contactToDelete.value = null;
        },
        onError: () => {
            showDeleteModal.value = false;
            contactToDelete.value = null;
        }
    });
};

const callContact = (phone) => {
    window.location.href = `tel:${phone}`;
};

watch([searchQuery, typeFilter], () => {
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
    <div class="w-full">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Veterinary Contact</h1>
                <p class="text-sm text-gray-500 mt-1">Directory of veterinarians, clinics, and suppliers.</p>
            </div>
            <button v-if="canCreateContact" @click="openAdd" class="flex items-center gap-2 bg-[#34554a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#2a443b] shadow-sm transition-colors">
                <Plus class="w-4 h-4" />
                Add Contact
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-gray-100 rounded-full border border-gray-200 flex items-center justify-center">
                    <svg class="w-11 h-11" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M18,3H4A1,1,0,0,0,3,4V20a1,1,0,0,0,1,1H18a1,1,0,0,0,1-1V4A1,1,0,0,0,18,3ZM14,16H8V15a3,3,0,0,1,3-3,2,2,0,1,1,2-2,2,2,0,0,1-2,2,3,3,0,0,1,3,3Z" fill="#2ca9bc"></path>
                        <path d="M21,9H19V7h2Zm0,4H19v2h2ZM11,8a2,2,0,1,0,2,2A2,2,0,0,0,11,8Zm3,7a3,3,0,0,0-3-3h0a3,3,0,0,0-3,3v1h6Zm5,5V4a1,1,0,0,0-1-1H4A1,1,0,0,0,3,4V20a1,1,0,0,0,1,1H18A1,1,0,0,0,19,20Z" fill="none" stroke="#000000" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Total Contacts</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.totalContacts }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-gray-100 rounded-full border border-gray-200 flex items-center justify-center">
                    <svg class="w-11 h-11" viewBox="0 0 512.001 512.001" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path style="fill:#B6EDAA;" d="M155.259,74.199l-3.71,25.096c-0.768,5.198,3.063,9.891,8.076,9.89l192.759-0.063 c5.013-0.002,8.841-4.697,8.069-9.895l-4.889-32.923c-3.901-26.265-25.455-45.629-50.78-45.621l-97.616,0.031 c-19.068,0.006-35.991,10.993-44.847,27.634C162.319,48.349,158.197,54.327,155.259,74.199z"></path>
                        <path style="fill:#92D182;" d="M360.452,99.227l-4.889-32.923c-3.901-26.265-25.455-45.629-50.78-45.621l-21.48,0.007 c25.197,0.143,46.598,19.452,50.483,45.614l4.889,32.923c0.772,5.198-3.057,9.893-8.069,9.895h21.777 C357.395,109.12,361.224,104.425,360.452,99.227z"></path>
                        <polygon style="fill:#FED9A8;" points="256,383.681 335,309.21 300.896,293.981 295.762,263.143 256.702,250.36 256.702,249.877 256,250.119 255.3,249.877 255.3,250.36 216.238,263.143 211.105,293.981 177,309.21 "></polygon>
                        <path style="fill:#37CC8F;" d="M495.401,392.655c-2.705-30.585-24.408-56.138-54.152-63.758l-99.966-25.609l-85.282,80.392 l-85.282-80.392l-99.966,25.609c-29.744,7.62-51.447,33.173-54.152,63.757l-8.728,98.663h247.077h2.1h247.077L495.401,392.655z"></path>
                        <path style="fill:#2AA86F;" d="M495.401,392.655c-2.705-30.585-24.408-56.138-54.152-63.758l-99.966-25.609l-5.08,4.789 l81.273,20.82c29.744,7.62,51.447,33.173,54.152,63.758l8.727,98.663h23.773L495.401,392.655z"></path>
                        <path style="fill:#FED9A8;" d="M291.509,272.324l-1.052,0.274c-23.021,6.008-47.099,5.974-70.105-0.098l0,0 c-35.409-9.344-60.236-42.754-60.248-81.075l-0.027-82.241l191.853-0.063l0.026,82.02 C351.969,229.566,327.035,263.053,291.509,272.324z"></path>
                        <path style="fill:#F7CA94;" d="M351.93,109.122l-23.773,0.007l0.027,82.013c0.013,38.424-24.922,71.911-60.448,81.18l-1.052,0.274 c-7.566,1.975-15.247,3.283-22.966,3.952c15.63,1.352,31.417,0.046,46.739-3.952l1.052-0.274c35.525-9.27,60.46-42.757,60.448-81.18 L351.93,109.122z"></path>
                        <path style="fill:#B6EDAA;" d="M322.68,190.965c-44.747,7.506-88.523,7.518-133.275,0.043c-2.547,19.225-5.093,38.452-7.641,57.677 c10.172,11.357,23.408,19.806,38.6,23.815l0,0c23.007,6.071,47.084,6.104,70.105,0.098l1.052-0.274 c15.28-3.987,28.592-12.463,38.812-23.877C327.782,229.285,325.231,210.125,322.68,190.965z"></path>
                        <path style="fill:#333333;" d="M205.174,157.117c-4.348,0-7.873-3.525-7.873-7.873v-6.309c0-4.348,3.525-7.873,7.873-7.873 s7.873,3.525,7.873,7.873v6.309C213.047,153.592,209.522,157.117,205.174,157.117z"></path>
                        <path style="fill:#333333;" d="M306.827,157.117c-4.348,0-7.873-3.525-7.873-7.873v-6.309c0-4.348,3.525-7.873,7.873-7.873 s7.873,3.525,7.873,7.873v6.309C314.7,153.592,311.175,157.117,306.827,157.117z"></path>
                        <circle style="fill:#FEE570;" cx="255.996" cy="91.929" r="28.999"></circle>
                        <path style="fill:#FEC373;" d="M361.086,166.624l-9.139,0.003l-0.016-49.253l9.139-0.003c7.787-0.002,14.103,6.308,14.105,14.096 l0.007,21.052C375.184,160.306,368.873,166.621,361.086,166.624z"></path>
                        <path style="fill:#FEC373;" d="M150.931,166.695l9.139-0.003l-0.016-49.253l-9.139,0.003c-7.787,0.002-14.098,6.317-14.096,14.105 l0.007,21.052C136.829,160.387,143.143,166.697,150.931,166.695z"></path>
                        <path d="M511.97,490.625l-8.727-98.663c-2.994-33.85-27.123-62.258-60.04-70.691l-99.967-25.609 c-2.617-0.671-5.391,0.046-7.355,1.898l-2.464,2.322l-25.451-11.366l-2.136-12.829c0.24-0.104,0.478-0.216,0.717-0.322 c0.502-0.223,1.005-0.443,1.501-0.675c0.457-0.213,0.908-0.438,1.361-0.659c30.358-14.81,50.431-46.791,50.419-82.891l-0.005-16.643 h1.264c5.869-0.002,11.387-2.29,15.535-6.441c4.149-4.152,6.433-9.67,6.431-15.54l-0.007-21.051 c-0.003-10.583-7.529-19.44-17.512-21.509c2.346-3.443,3.328-7.679,2.703-11.886l-4.889-32.923 c-4.505-30.332-29.125-52.338-58.55-52.338c-0.005,0-0.015,0-0.021,0l-97.616,0.031c-21.511,0.007-41.357,12.195-51.794,31.809 c-2.043,3.839-0.588,8.606,3.251,10.649c3.838,2.043,8.606,0.588,10.649-3.251c7.698-14.466,22.22-23.455,37.9-23.46l97.616-0.031 c0.005,0,0.009,0,0.015,0c21.554,0,39.628,16.36,42.975,38.904l1.343,9.044l-59.632,0.02c-5.847-12.656-18.653-21.464-33.485-21.464 c-0.003,0-0.008,0-0.012,0c-14.836,0.005-27.642,8.821-33.483,21.485l-59.638,0.02l0.18-1.214c0.636-4.301-2.336-8.304-6.638-8.94 c-4.299-0.632-8.305,2.336-8.94,6.638l-3.71,25.096c-0.621,4.206,0.362,8.439,2.708,11.88c-4.174,0.855-8.011,2.904-11.092,5.987 c-4.149,4.152-6.433,9.67-6.431,15.54l0.007,21.052c0.004,12.112,9.861,21.965,21.973,21.966c0.001,0,0.006,0,0.007,0h1.291 l0.005,16.859c0.013,37.327,21.549,70.296,53.707,84.348c0.067,0.029,0.134,0.062,0.202,0.091l-2.106,12.651l-25.451,11.366 l-2.464-2.322c-1.964-1.851-4.739-2.569-7.355-1.898l-99.963,25.608c-32.917,8.433-57.046,36.842-60.04,70.691l-8.727,98.663 c-0.195,2.2,0.544,4.381,2.035,6.01c1.491,1.629,3.599,2.557,5.807,2.557h88.001c0.004,0,0.007,0,0.012,0c0.003,0,0.007,0,0.011,0 h320.208c0.003,0,0.007,0,0.01,0c0.004,0,0.007,0,0.012,0h88.001c2.209,0,4.316-0.928,5.807-2.557 C511.426,495.005,512.165,492.825,511.97,490.625z M253.677,70.943c0.059-0.006,0.115-0.02,0.175-0.026 c0.706-0.071,1.423-0.109,2.149-0.109c0.722,0,1.436,0.038,2.14,0.108c0.099,0.01,0.195,0.033,0.294,0.043 c0.612,0.07,1.22,0.155,1.814,0.278c0.01,0.002,0.02,0.005,0.03,0.007c8.217,1.697,14.704,8.181,16.407,16.396 c0.002,0.013,0.006,0.024,0.009,0.037c0.122,0.592,0.206,1.197,0.277,1.806c0.012,0.101,0.035,0.199,0.045,0.301 c0.071,0.704,0.109,1.418,0.109,2.14c0,0.669-0.039,1.328-0.1,1.981c-0.016,0.176-0.042,0.351-0.062,0.526 c-0.059,0.499-0.132,0.992-0.226,1.479c-0.03,0.163-0.063,0.325-0.098,0.487c-0.12,0.554-0.259,1.101-0.422,1.639 c-0.022,0.072-0.04,0.146-0.062,0.218c-0.401,1.277-0.922,2.499-1.546,3.659c-0.043,0.08-0.09,0.157-0.134,0.236 c-0.266,0.48-0.551,0.947-0.852,1.402c-0.081,0.122-0.164,0.242-0.247,0.362c-3.035,4.388-7.68,7.58-13.08,8.7 c-0.025,0.005-0.049,0.011-0.073,0.016c-0.648,0.132-1.307,0.232-1.974,0.302c-0.08,0.008-0.16,0.014-0.239,0.021 c-0.657,0.062-1.321,0.101-1.993,0.102c-0.005,0-0.012,0-0.017,0h-0.001c-0.679,0-1.35-0.039-2.012-0.102 c-0.074-0.007-0.148-0.012-0.222-0.019c-0.674-0.071-1.338-0.172-1.991-0.305c-0.017-0.003-0.033-0.006-0.049-0.011 c-5.41-1.117-10.065-4.315-13.104-8.71c-0.077-0.111-0.153-0.223-0.229-0.336c-0.309-0.465-0.599-0.942-0.871-1.431 c-0.039-0.071-0.082-0.141-0.121-0.211c-0.628-1.162-1.15-2.388-1.553-3.667c-0.022-0.069-0.039-0.141-0.06-0.211 c-0.164-0.54-0.303-1.089-0.424-1.645c-0.035-0.162-0.066-0.323-0.098-0.486c-0.093-0.487-0.167-0.98-0.227-1.479 c-0.021-0.175-0.046-0.35-0.063-0.526c-0.061-0.653-0.101-1.312-0.101-1.98c0-0.722,0.037-1.437,0.108-2.142 c0.009-0.099,0.031-0.194,0.043-0.292c0.07-0.613,0.155-1.222,0.277-1.817c0.002-0.007,0.004-0.016,0.005-0.023 C237.135,78.799,244.536,71.951,253.677,70.943z M167.952,117.056l30.136-0.01l30.913-0.01c0.303,0.325,0.62,0.638,0.935,0.951 c0.004,0.004,0.008,0.008,0.013,0.014c0.357,0.356,0.722,0.702,1.093,1.042c0.103,0.094,0.205,0.19,0.309,0.283 c0.293,0.262,0.59,0.519,0.89,0.771c0.206,0.174,0.414,0.346,0.624,0.515c0.216,0.174,0.434,0.345,0.654,0.514 c0.322,0.249,0.648,0.491,0.978,0.729c0.123,0.088,0.246,0.176,0.37,0.263c0.452,0.318,0.913,0.626,1.38,0.924 c0.014,0.008,0.026,0.017,0.04,0.026c5.707,3.626,12.466,5.737,19.712,5.737c0,0,0.012,0,0.013,0 c7.252-0.002,14.015-2.117,19.723-5.75c0.01-0.006,0.021-0.014,0.031-0.02c0.472-0.301,0.939-0.613,1.396-0.935 c0.114-0.08,0.229-0.163,0.342-0.244c0.341-0.246,0.679-0.498,1.012-0.755c0.207-0.16,0.411-0.32,0.615-0.484 c0.225-0.182,0.447-0.366,0.667-0.553c0.284-0.24,0.567-0.483,0.844-0.732c0.122-0.11,0.242-0.221,0.362-0.334 c0.352-0.324,0.699-0.653,1.039-0.992c0.025-0.026,0.05-0.052,0.077-0.078c0.3-0.301,0.604-0.599,0.894-0.911l61.044-0.02v0.344 c0,0.012-0.002,0.022-0.002,0.034l0.015,45.563l-24.759,20.586c-21.2,3.444-42.516,5.206-63.393,5.206 c-20.795,0-42.031-1.748-63.155-5.166l-24.799-20.572L167.952,117.056z M190.059,246.02l6.092-45.982 c20.005,2.948,40.073,4.439,59.769,4.439c19.784,0,39.935-1.505,60.019-4.478l6.096,45.786c-5.025,5.101-10.696,9.37-16.836,12.712 c-0.166,0.089-0.331,0.181-0.497,0.269c-0.533,0.284-1.07,0.561-1.609,0.831c-0.316,0.157-0.634,0.311-0.952,0.464 c-0.455,0.219-0.909,0.44-1.368,0.649c-0.769,0.35-1.545,0.689-2.329,1.011c-0.235,0.097-0.473,0.185-0.71,0.279 c-0.651,0.259-1.306,0.513-1.967,0.754c-0.257,0.093-0.515,0.184-0.775,0.275c-0.61,0.214-1.225,0.417-1.842,0.614 c-0.275,0.088-0.55,0.182-0.826,0.266c-0.005,0.002-0.011,0.003-0.016,0.005c-0.921,0.281-1.848,0.548-2.785,0.793l-1.053,0.275 c-10.802,2.819-21.927,4.221-33.052,4.205c-9.548-0.014-19.094-1.079-28.436-3.18c-1.542-0.346-3.079-0.715-4.608-1.118 c-0.05-0.014-0.1-0.029-0.15-0.043c-0.271-0.072-0.547-0.156-0.82-0.233c-0.527-0.147-1.053-0.294-1.575-0.452 c-0.035-0.01-0.069-0.023-0.105-0.034c-0.547-0.169-1.095-0.35-1.642-0.532c-0.259-0.086-0.519-0.172-0.777-0.261 c-0.484-0.168-0.967-0.338-1.447-0.518c-0.276-0.103-0.55-0.212-0.825-0.318C205.73,258.892,197.236,253.301,190.059,246.02z M335.453,227.244l-4.394-33.006l13.022-10.827l0.002,7.735C344.087,204.117,340.989,216.442,335.453,227.244z M367.309,152.521 c0,1.663-0.647,3.228-1.822,4.404c-1.176,1.177-2.74,1.825-4.403,1.826h-1.264l-0.01-33.505h1.266c3.433,0,6.226,2.792,6.227,6.225 L367.309,152.521z M352.664,100.384c0.084,0.566-0.245,0.847-0.284,0.865l-60.695,0.02c0.101-0.385,0.176-0.777,0.263-1.165 c0.063-0.278,0.132-0.553,0.189-0.834c0.068-0.337,0.123-0.677,0.182-1.016c0.065-0.378,0.128-0.756,0.182-1.138 c0.043-0.307,0.082-0.613,0.118-0.922c0.052-0.451,0.093-0.906,0.129-1.363c0.02-0.251,0.043-0.501,0.057-0.753 c0.035-0.599,0.054-1.203,0.059-1.809l58.593-0.019L352.664,100.384z M219.138,92.293c0.006,0.607,0.025,1.209,0.06,1.809 c0.015,0.254,0.039,0.506,0.058,0.76c0.036,0.452,0.077,0.903,0.129,1.35c0.036,0.313,0.076,0.625,0.12,0.936 c0.052,0.372,0.114,0.741,0.178,1.11c0.06,0.35,0.118,0.7,0.188,1.047c0.048,0.239,0.108,0.473,0.162,0.711 c0.096,0.426,0.181,0.857,0.291,1.279l-60.248,0.02c-0.029,0-0.059,0.004-0.088,0.004h-0.352c-0.052-0.022-0.381-0.303-0.297-0.87 l1.203-8.135L219.138,92.293z M150.928,158.822h-0.001c-3.433,0-6.226-2.793-6.227-6.225l-0.007-21.052 c0-1.663,0.647-3.228,1.822-4.404c1.176-1.177,2.74-1.824,4.403-1.826h1.267l0.01,33.506L150.928,158.822z M167.974,183.458 l13.055,10.83l-4.399,33.206c-5.543-10.798-8.649-23.115-8.653-36.072L167.974,183.458z M214.315,301.17 c2.411-1.077,4.122-3.291,4.556-5.895l2.402-14.424c0.236,0.057,0.473,0.105,0.71,0.161c0.802,0.19,1.606,0.368,2.41,0.545 c0.663,0.145,1.327,0.288,1.991,0.424c0.849,0.173,1.701,0.338,2.552,0.497c0.592,0.11,1.184,0.216,1.777,0.319 c0.943,0.164,1.886,0.318,2.832,0.462c0.472,0.072,0.945,0.14,1.417,0.208c1.07,0.153,2.14,0.296,3.212,0.426 c0.309,0.037,0.618,0.07,0.927,0.105c1.228,0.14,2.458,0.268,3.689,0.377c0.099,0.008,0.197,0.016,0.295,0.024 c4.157,0.359,8.328,0.555,12.503,0.555c0.001,0,0.001,0,0.001,0c0.002,0,0.004,0,0.006,0c0.002,0,0.004,0,0.005,0s0,0,0.001,0 c5.944,0,11.883-0.376,17.784-1.102c0.029-0.004,0.06-0.006,0.089-0.01c1.117-0.139,2.232-0.298,3.346-0.462 c0.417-0.061,0.835-0.117,1.251-0.181c0.889-0.139,1.776-0.294,2.662-0.449c0.638-0.111,1.278-0.219,1.915-0.34 c0.751-0.142,1.499-0.296,2.249-0.449c0.758-0.155,1.516-0.311,2.271-0.478c0.687-0.152,1.371-0.314,2.055-0.476 c0.489-0.115,0.982-0.219,1.47-0.34l2.432,14.61c0.435,2.606,2.145,4.819,4.556,5.897l23.319,10.414l-65.005,61.278l-65.005-61.278 L214.315,301.17z M141.838,374.14c5.966,0,10.82,4.854,10.82,10.82c0,5.966-4.854,10.82-10.82,10.82s-10.82-4.854-10.82-10.82 C131.018,378.995,135.873,374.14,141.838,374.14z M423.319,483.445l-6.095-67.478c-0.392-4.33-4.219-7.515-8.55-7.133 c-4.33,0.392-7.524,4.219-7.133,8.55l5.967,66.061H104.493l1.99-22.031c0.392-4.33-2.803-8.159-7.133-8.55 c-4.333-0.381-8.158,2.803-8.55,7.133l-2.117,23.449H16.474l7.968-90.096c2.407-27.209,21.803-50.045,48.264-56.824l61.259-15.693 v38.754c-10.817,3.363-18.693,13.466-18.693,25.375c0,14.648,11.918,26.566,26.566,26.566s26.566-11.918,26.566-26.566 c0-11.909-7.876-22.011-18.693-25.375v-42.787l18.76-4.806l79.655,75.088v49.654c0,4.348,3.525,7.873,7.873,7.873 c4.348,0,7.873-3.525,7.873-7.873v-49.654l79.655-75.088l10.1,2.588v45.797c-15.567,0.67-28.026,13.538-28.026,29.266v66.847 c0,2.867,1.559,5.508,4.069,6.893l14.665,8.094c3.805,2.101,8.597,0.718,10.697-3.088c2.101-3.806,0.718-8.597-3.088-10.697 l-10.596-5.848v-62.199c0-7.473,6.079-13.552,13.552-13.552h13.2c7.473,0,13.552,6.079,13.552,13.552v62.199l-10.596,5.848 c-3.806,2.101-5.19,6.891-3.088,10.697c1.436,2.601,4.126,4.07,6.9,4.07c1.287,0,2.591-0.316,3.797-0.982l14.665-8.094 c2.51-1.386,4.069-4.026,4.069-6.893v-66.849c0-15.729-12.459-28.597-28.026-29.266v-41.763l69.92,17.912 c26.461,6.779,45.857,29.615,48.264,56.824l7.968,90.096H423.319z"></path>
                        <path d="M103.327,408.834c-4.341-0.385-8.158,2.803-8.55,7.133l-1.646,18.222c-0.392,4.33,2.803,8.159,7.133,8.55 c0.241,0.022,0.481,0.033,0.718,0.033c4.028,0,7.463-3.076,7.832-7.166l1.646-18.222 C110.851,413.053,107.657,409.226,103.327,408.834z"></path>
                        <path d="M205.175,157.118c4.348,0,7.873-3.525,7.873-7.873v-6.309c0-4.348-3.525-7.873-7.873-7.873s-7.873,3.525-7.873,7.873v6.309 C197.302,153.593,200.827,157.118,205.175,157.118z"></path>
                        <path d="M306.827,157.118c4.348,0,7.873-3.525,7.873-7.873v-6.309c0-4.348-3.525-7.873-7.873-7.873s-7.873,3.525-7.873,7.873v6.309 C298.954,153.593,302.479,157.118,306.827,157.118z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Veterinarians</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.vets }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-gray-100 rounded-full border border-gray-200 flex items-center justify-center">
                    <svg class="w-11 h-11" viewBox="0 0 356.725 356.725" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect y="6.309" style="fill:#6EBEFF;" width="356.725" height="344.107"></rect>
                        <rect x="10.864" y="122.473" style="fill:#FFFFFF;" width="139.502" height="227.943"></rect>
                        <rect x="17.866" y="129.476" style="fill:#2D4151;" width="59.441" height="220.94"></rect>
                        <rect x="84.308" y="129.476" style="fill:#2D4151;" width="59.441" height="220.94"></rect>
                        <rect x="157.866" y="122.473" style="fill:#FFFFFF;" width="189" height="211.003"></rect>
                        <rect x="163.866" y="129.476" style="fill:#2D4151;" width="85" height="197.5"></rect>
                        <rect x="255.866" y="129.476" style="fill:#2D4151;" width="85" height="197.5"></rect>
                        <path style="opacity:0.2;fill:#FCFAFA;" d="M246.301,190.676V324.66h-76.646 C169.655,324.659,236.912,303.143,246.301,190.676z"></path>
                        <path style="opacity:0.2;fill:#FCFAFA;" d="M139.635,253.363v93.296H86.264 C86.264,346.66,133.097,331.677,139.635,253.363z"></path>
                        <path style="opacity:0.2;fill:#FCFAFA;" d="M73.635,253.363v93.296H20.264 C20.264,346.66,67.097,331.677,73.635,253.363z"></path>
                        <path style="opacity:0.2;fill:#FCFAFA;" d="M338.301,190.676V324.66h-76.646 C261.655,324.659,328.912,303.143,338.301,190.676z"></path>
                        <rect x="64.307" y="241.976" style="fill:#FFFFFF;" width="5" height="18"></rect>
                        <rect x="92.307" y="241.976" style="fill:#FFFFFF;" width="5" height="18"></rect>
                        <rect x="163.866" y="129.476" style="fill:#839BAA;" width="85" height="143"></rect>
                        <rect x="255.866" y="129.476" style="fill:#839BAA;" width="85" height="143"></rect>
                        <rect x="10.864" y="18.309" style="fill:#FFFFFF;" width="336.002" height="96.167"></rect>
                        <path style="fill:#F93030;" d="M297.032,28.976c-20.987,0-38,17.013-38,38s17.013,38,38,38s38-17.013,38-38 S318.019,28.976,297.032,28.976z M306.033,75.976v21.333h-18V75.976H266.7v-18h21.333V36.643h18v21.333h21.333v18H306.033z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Clinics</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.clinics }}</p>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 flex items-center gap-5 shadow-sm">
                <div class="w-14 h-14 bg-gray-100 rounded-full border border-gray-200 flex items-center justify-center">
                    <svg class="w-11 h-11" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M558.4 511.9v-31.2c0-4.7-3.8-8.5-8.5-8.5h-56.4c-4.7 0-8.5 3.8-8.5 8.5v27.9c0 12.9-10.5 23.4-23.4 23.4h-26.9c-4.7 0-8.5 3.8-8.5 8.5v56.4c0 4.7 3.8 8.5 8.5 8.5h16.1c5.5 0 14.2 4.5 14.2 10l1 41.7c0 10.3 3.1 18.6 13.4 18.6h13.1c4.2 0 8.1 1.4 11.2-0.6 0.4-0.2 0.8-0.4 1.2-0.4h41.7c6.4 0 11.7-5.2 11.7-11.7v-35.6c0-12.2 9.8-22 22-22H617c4.7 0 8.5-3.8 8.5-8.5v-66.8c0-8.9-7.2-16.1-16.1-16.1h-48.8c-1.2 0.1-2.2-0.9-2.2-2.1z" fill="#F9C0C0"></path>
                        <path d="M855.4 289.3c15.1 0 29.4 6 40.2 16.8s16.8 25.1 16.8 40.2V750c0 15.1-6 29.4-16.8 40.2-10.7 10.8-25 16.8-40.2 16.8H170c-15.1 0-29.4-6-40.2-16.8S113 765.1 113 750V346.3c0-15.1 6-29.4 16.8-40.2 10.8-10.8 25.1-16.8 40.2-16.8h685.4m0-15H170c-39.6 0-72 32.4-72 72V750c0 39.6 32.4 72 72 72h685.4c39.6 0 72-32.4 72-72V346.3c0-39.6-32.4-72-72-72z" fill="#999999"></path>
                        <path d="M630.7 213.3v98.8h-236v-98.8h236m3-15h-242c-6.6 0-12 5.4-12 12v104.8c0 6.6 5.4 12 12 12h242c6.6 0 12-5.4 12-12V210.3c0-6.6-5.4-12-12-12zM275.3 259.3V274h-61.7v-14.7h61.7m3-15h-67.7c-6.6 0-12 5.4-12 12V277c0 6.6 5.4 12 12 12h67.7c6.6 0 12-5.4 12-12v-20.7c0-6.6-5.4-12-12-12zM811.8 259.3V274h-61.7v-14.7h61.7m3-15h-67.7c-6.6 0-12 5.4-12 12V277c0 6.6 5.4 12 12 12h67.7c6.6 0 12-5.4 12-12v-20.7c0-6.6-5.4-12-12-12zM513.3 409.4c40.8 0 79.1 15.9 108 44.7 28.8 28.8 44.7 67.2 44.7 108s-15.9 79.1-44.7 108c-28.8 28.8-67.2 44.7-108 44.7s-79.1-15.9-108-44.7c-28.8-28.8-44.7-67.2-44.7-108s15.9-79.1 44.7-108c28.9-28.8 67.2-44.7 108-44.7m0-15c-92.6 0-167.7 75.1-167.7 167.7s75.1 167.7 167.7 167.7S681 654.7 681 562.1s-75.1-167.7-167.7-167.7z" fill="#999999"></path>
                        <path d="M198.6 289h91.7v518h-91.7zM735.2 289h91.7v518h-91.7z" fill="#F9C0C0"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Suppliers</p>
                    <p class="text-3xl font-black text-gray-900">{{ stats.suppliers }}</p>
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
                        placeholder="Search by name, organization, phone..." 
                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 outline-none text-sm focus:ring-2 focus:ring-[#34554a]"
                    >
                </div>
                <select 
                    v-model="typeFilter"
                    class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm outline-none focus:ring-2 focus:ring-[#34554a]"
                >
                    <option value="">All Types</option>
                    <option value="veterinarian">Veterinarian</option>
                    <option value="clinic">Clinic</option>
                    <option value="supplier">Supplier</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="contact in paginatedContacts" :key="contact.id" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-4 border-b border-gray-100 flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div v-if="contact.photo_url" class="w-12 h-12 rounded-full overflow-hidden border border-gray-100">
                            <img :src="contact.photo_url" alt="Profile" class="w-full h-full object-cover" />
                        </div>
                        <div v-else-if="contact.type !== 'veterinarian'" class="w-12 h-12 rounded-full flex items-center justify-center" :class="getTypeClass(contact.type)">
                            <component :is="getTypeIcon(contact.type)" class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900">{{ contact.name }}</h3>
                            <p class="text-sm text-gray-500">{{ contact.position }} {{ contact.position && contact.organization ? 'at' : '' }} {{ contact.organization }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <span v-if="contact.emergency" class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 border-2 border-red-300 flex items-center gap-1.5">
                            <svg class="w-4 h-4" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path style="fill:#E7B3A8;" d="M147.429,234.468L125.598,256.3c1.019,1.869,2.029,3.886,3.175,5.923 c10.393,18.741,24.553,44.386,51.541,71.374c27.056,27.047,52.712,41.304,71.454,51.689c1.97,1.12,3.258,1.577,5.101,2.595 l19.248-20.48l2.122-2.122c16.226-16.226,42.533-16.226,58.759,0l43.353,43.353c16.317,16.317,16.212,42.803-0.233,58.99 l-8.664,8.528c-7.103,5.554-15.364,10.265-24.175,13.716c-8.415,3.19-16.472,5.23-24.492,6.112 c-64.427,7.792-123.32-21.643-203.232-101.522C9.135,284.008,17.439,194.999,17.869,191.154c0.985-8.349,2.983-16.364,6.139-24.517 c3.417-8.744,8.199-17.077,13.753-24.146l8.522-8.667c16.185-16.46,42.683-16.572,59.005-0.249l42.138,42.138 C163.652,191.936,163.654,218.243,147.429,234.468z"></path>
                                <path style="fill:#4C213A;" d="M369.328,205.587c-8.178,0-14.811-6.632-14.811-14.811V97.963c0-8.178,6.632-14.811,14.811-14.811 s14.811,6.632,14.811,14.811v92.813C384.139,198.955,377.507,205.587,369.328,205.587z"></path>
                                <path style="fill:#4C213A;" d="M415.734,159.18h-92.81c-8.178,0-14.811-6.632-14.811-14.811s6.632-14.811,14.811-14.811h92.81 c8.178,0,14.811,6.632,14.811,14.811S423.913,159.18,415.734,159.18z"></path>
                            </svg>
                            24/7
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="getTypeClass(contact.type)">
                            {{ contact.type }}
                        </span>
                    </div>
                </div>
                <div class="p-4 space-y-2">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <Phone class="w-4 h-4 text-gray-400" />
                        <span class="font-medium">{{ contact.phone }}</span>
                        <span v-if="contact.alt_phone" class="text-gray-400">| {{ contact.alt_phone }}</span>
                        <button @click="callContact(contact.phone)" class="ml-auto text-[#34554a] font-medium text-xs hover:underline">Call</button>
                    </div>
                    <div v-if="contact.email" class="flex items-center gap-2 text-sm text-gray-600">
                        <Mail class="w-4 h-4 text-gray-400" />
                        <span>{{ contact.email }}</span>
                    </div>
                    <div class="flex items-start gap-2 text-sm text-gray-600">
                        <MapPin class="w-4 h-4 text-gray-400 mt-0.5" />
                        <span>{{ contact.address }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <Clock class="w-4 h-4 text-gray-400" />
                        <span>{{ contact.availability }}</span>
                    </div>
                </div>
                <div class="p-3 bg-gray-50 border-t border-gray-100 flex justify-end items-center">
                    <div class="flex items-center gap-1">
                        <button v-if="canViewContact" @click="viewContact(contact)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                            <Eye class="w-4 h-4" />
                        </button>
                        <button v-if="canEditContact" @click="openEdit(contact)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                            <Edit class="w-4 h-4" />
                        </button>
                        <button v-if="canDeleteContact" @click="deleteContact(contact)" class="w-8 h-8 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded flex items-center justify-center transition-colors">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
            
            <div v-if="filteredContacts.length === 0" class="col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                <Phone class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                <p class="text-gray-500">No contacts found</p>
            </div>
        </div>

        <div class="mt-6 flex flex-col md:flex-row justify-between items-center p-4 border border-gray-200 rounded-xl bg-gray-50">
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
                Showing <span class="font-semibold text-gray-800">{{ filteredContacts.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0 }}-{{ Math.min(currentPage * itemsPerPage, filteredContacts.length) }}</span> of <span class="font-semibold text-gray-800">{{ filteredContacts.length }}</span> records
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showModal" class="fixed inset-y-0 right-0 left-0 md:left-64 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-5 overflow-y-auto" @click.self="closeModal">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl mx-auto overflow-hidden border border-gray-100 max-h-[90vh] flex flex-col">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-[#34554a] text-white">
                    <h3 class="text-lg font-bold">{{ isEditing ? 'Edit Contact' : 'Add New Contact' }}</h3>
                    <button @click="closeModal" class="text-white opacity-70 hover:opacity-100"><X class="w-6 h-6" /></button>
                </div>
                <div class="p-6 overflow-y-auto space-y-4">
                    <div v-if="Object.keys(form.errors || {}).length" class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                        Please fix the highlighted fields and try again.
                    </div>
                    <!-- PROFILE PICTURE UPLOAD -->
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Contact Profile Picture</h3>
                        <div class="flex items-start gap-4">
                            <div class="w-24 h-24 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center bg-white overflow-hidden">
                                <img v-if="profilePreview" :src="profilePreview" alt="Preview" class="w-full h-full object-cover" />
                                <ImageIcon v-else class="w-10 h-10 text-gray-300" />
                            </div>

                            <div class="flex-1">
                                <input
                                    type="file"
                                    @change="handleImageUpload"
                                    accept="image/*"
                                    class="hidden"
                                    id="profile-upload"
                                />
                                <label
                                    for="profile-upload"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 cursor-pointer transition-all">
                                    <Upload class="w-4 h-4" />
                                    Upload Picture
                                </label>
                                <button
                                    v-if="profilePreview"
                                    @click="removeImage"
                                    type="button"
                                    class="ml-2 px-4 py-2 bg-red-50 text-red-600 rounded-lg text-sm font-medium hover:bg-red-100 transition-all">
                                    Remove
                                </button>
                                <p class="text-[10px] text-gray-500 mt-2 font-medium">JPG, PNG (max 2MB)</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Full Name *</label>
                            <input v-model="form.name" type="text" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Type *</label>
                            <select v-model="form.type" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] bg-white outline-none">
                                <option value="veterinarian">Veterinarian</option>
                                <option value="clinic">Clinic</option>
                                <option value="supplier">Supplier</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Position</label>
                            <input v-model="form.position" type="text" placeholder="e.g. Farm Vet" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Organization</label>
                            <input v-model="form.organization" type="text" placeholder="Clinic or Company" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Phone *</label>
                            <input v-model="form.phone" type="tel" inputmode="numeric" :pattern="malaysiaPhonePattern" title="Enter valid Malaysia phone number, e.g. 0123456789 or +60123456789" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] outline-none">
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Alt Phone</label>
                            <input v-model="form.alt_phone" type="tel" inputmode="numeric" :pattern="malaysiaPhonePattern" title="Enter valid Malaysia phone number, e.g. 0123456789 or +60123456789" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] outline-none">
                            <p v-if="form.errors.alt_phone" class="mt-1 text-xs text-red-600">{{ form.errors.alt_phone }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                            <input v-model="form.email" type="email" maxlength="255" autocomplete="email" title="Enter a valid email address, e.g. name@example.com" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] outline-none">
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Address</label>
                            <textarea v-model="form.address" rows="2" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] outline-none resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Availability</label>
                            <input v-model="form.availability" type="text" placeholder="e.g. Mon-Fri 8am-5pm" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] outline-none">
                        </div>
                        <div class="flex items-center pt-5">
                            <input v-model="form.emergency" type="checkbox" id="modal-emergency" class="w-4 h-4 text-[#34554a] rounded focus:ring-[#34554a] cursor-pointer mr-2">
                            <label for="modal-emergency" class="text-sm font-semibold text-gray-700 cursor-pointer">Emergency Service (24/7)</label>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="2" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#34554a] outline-none resize-none"></textarea>
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
                    <button @click="closeModal" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-bold text-gray-600 hover:bg-white transition-all">Cancel</button>
                    <button @click="submitForm" :disabled="form.processing" class="flex items-center gap-2 bg-[#34554a] text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-[#2a443b] transition-all disabled:opacity-50 shadow-md">
                        <Save class="w-4 h-4" />
                        {{ form.processing ? 'Saving...' : (isEditing ? 'Update Contact' : 'Save Contact') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- View Modal -->
        <div v-if="showViewModal && selectedContact" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" @click.self="closeViewModal">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col border border-gray-100">
                <div class="p-6 flex flex-col items-center bg-gray-50 border-b relative">
                    <button @click="closeViewModal" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600 transition-colors"><X class="w-6 h-6" /></button>
                    <div v-if="selectedContact.photo_url" class="w-24 h-24 rounded-full overflow-hidden mb-4 shadow-md border-2 border-white">
                        <img :src="selectedContact.photo_url" alt="Profile" class="w-full h-full object-cover" />
                    </div>
                    <div v-else-if="selectedContact.type !== 'veterinarian'" class="w-20 h-20 rounded-full flex items-center justify-center mb-4 shadow-inner" :class="getTypeClass(selectedContact.type)">
                        <component :is="getTypeIcon(selectedContact.type)" class="w-10 h-10" />
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">{{ selectedContact.name }}</h3>
                    <p class="text-sm text-gray-500 font-medium">{{ selectedContact.position }}</p>
                </div>
                <div class="p-6 space-y-4 bg-white">
                    <div class="flex items-center justify-between font-bold text-[10px] text-gray-400">
                        <span>Details</span>
                        <span :class="getTypeClass(selectedContact.type)" class="px-2 py-0.5 rounded-full">{{ formatTypeLabel(selectedContact.type) }}</span>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-start gap-3">
                            <Building class="w-5 h-5 text-gray-300 flex-shrink-0 mt-0.5" />
                            <div>
                                <p class="text-xs text-gray-400 font-bold">Organization</p>
                                <p class="text-sm text-gray-900 font-semibold">{{ selectedContact.organization || '-' }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Phone class="w-5 h-5 text-gray-300 flex-shrink-0 mt-0.5" />
                            <div>
                                <p class="text-xs text-gray-400 font-bold">Phone Numbers</p>
                                <p class="text-sm text-gray-900 font-semibold">{{ selectedContact.phone }} <span v-if="selectedContact.alt_phone" class="text-gray-400 ml-1">| {{ selectedContact.alt_phone }}</span></p>
                            </div>
                        </div>
                        <div v-if="selectedContact.email" class="flex items-start gap-3">
                            <Mail class="w-5 h-5 text-gray-300 flex-shrink-0 mt-0.5" />
                            <div>
                                <p class="text-xs text-gray-400 font-bold">Email</p>
                                <p class="text-sm text-gray-900 font-semibold">{{ selectedContact.email }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <MapPin class="w-5 h-5 text-gray-300 flex-shrink-0 mt-0.5" />
                            <div>
                                <p class="text-xs text-gray-400 font-bold">Address</p>
                                <p class="text-sm text-gray-900 font-semibold">{{ selectedContact.address || '-' }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Clock class="w-5 h-5 text-gray-300 flex-shrink-0 mt-0.5" />
                            <div>
                                <p class="text-xs text-gray-400 font-bold">Availability</p>
                                <p class="text-sm text-gray-900 font-semibold">{{ selectedContact.availability || '-' }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-if="selectedContact.notes" class="bg-gray-50 p-4 rounded-xl border border-gray-100 mt-2">
                        <p class="text-[10px] text-gray-400 font-bold mb-1">Notes</p>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ selectedContact.notes }}</p>
                    </div>
                </div>
                <div class="p-4 bg-gray-50 border-t flex gap-3">
                    <button @click="callContact(selectedContact.phone)" class="flex-1 bg-[#34554a] text-white py-2 rounded-lg text-sm font-bold shadow-sm hover:bg-[#2a443b] transition-all">Call Now</button>
                    <button @click="closeViewModal" class="px-6 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold shadow-sm hover:bg-gray-50 transition-all text-gray-700">Close</button>
                </div>
            </div>
        </div>

        <DeleteConfirmationModal
            :show="showDeleteModal"
            title="Delete Contact"
            message="Are you sure you want to remove"
            :item-name="contactToDelete?.name || 'this contact'"
            @close="showDeleteModal = false; contactToDelete = null"
            @confirm="confirmDelete"
        />
    </div>
</template>
