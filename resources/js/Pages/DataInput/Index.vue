<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Plus, Pencil, Trash2, Save, FileDown, Loader2, X } from 'lucide-vue-next';

const props = defineProps({
    estates: Array,
    herds: Array,
    blocks: Array,
    selectedEstateId: Number,
    savedData: Object
});

// --- State Management ---
const estates = ref(props.estates?.length > 0 ? props.estates : []);

const herds = ref(props.herds?.length > 0 ? props.herds : []);

const blocks = ref(props.blocks?.length > 0 ? props.blocks : [
    { id: "Block 1", area: 0, actual: 0, achievement: 0, rate: 11.09 },
]);

const selectedEstateId = ref(props.selectedEstateId || 0);
const selectedHerdName = ref('');
// Form Fields
const form = useForm({
    estate_id: selectedEstateId.value,
    herd: '',
    month: new Date().toISOString().slice(0, 7),
    allocated_area: estates.value[0]?.area || 0,
    rotation_period: 62,
    days_in_month: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).getDate(),
    current_month_ha: 0,
    rate_per_ha: 11.09,
    deduction_percent: 0,
    deduction_amount: 0,
    to_date_ha: 0,
    total_budget: 0,
    ytd_claim: 0,
    blocks: blocks.value
});

// Load saved data if available
onMounted(() => {
    if (props.savedData) {
        Object.assign(form, props.savedData);
        isExistingRecord.value = true;
    }
});

// Computed Fields
const selectedEstate = computed(() => estates.value[selectedEstateId.value] || { name: '', area: 0 });

const dailyCapacity = computed(() => {
    return form.rotation_period > 0 ? (form.allocated_area / form.rotation_period).toFixed(2) : '0.00';
});

const currentMonthPercent = computed(() => {
    return form.allocated_area > 0 ? ((form.current_month_ha / form.allocated_area) * 100).toFixed(1) + '%' : '0.0%';
});

const toDatePercent = computed(() => {
    return form.allocated_area > 0 ? ((form.to_date_ha / form.allocated_area) * 100).toFixed(1) + '%' : '0.0%';
});

const grossClaim = computed(() => {
    return form.current_month_ha * form.rate_per_ha;
});

const netClaim = computed(() => {
    return grossClaim.value - form.deduction_amount;
});

const budgetRemaining = computed(() => {
    return form.total_budget - form.ytd_claim;
});

// Block Totals
const blockTotals = computed(() => {
    let totalArea = 0;
    let totalActual = 0;
    let totalAchievement = 0;
    let totalMoney = 0;

    blocks.value.forEach(b => {
        totalArea += parseFloat(b.area) || 0;
        totalActual += parseFloat(b.actual) || 0;
        totalAchievement += parseFloat(b.achievement) || 0;
        totalMoney += ((parseFloat(b.achievement) || 0) * (parseFloat(b.rate) || 0));
    });

    const avgPercent = totalArea > 0 ? (totalActual / totalArea) * 100 : 0;

    return {
        totalArea,
        totalActual,
        avgPercent: avgPercent.toFixed(1) + '%',
        totalAchievement,
        totalMoney
    };
});

const variance = computed(() => {
    return blockTotals.value.totalAchievement - form.current_month_ha;
});

// Format Currency
const fmtMoney = (num) => {
    return "RM " + parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const toTitleCase = (value) => {
    if (!value) return '';
    return String(value)
        .toLowerCase()
        .split(' ')
        .filter(Boolean)
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

// Loading state
const isLoading = ref(false);
const isExistingRecord = ref(false);

// Default form values for new records
const getDefaultFormValues = (estateArea = 0) => ({
    allocated_area: estateArea,
    rotation_period: 62,
    days_in_month: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).getDate(),
    current_month_ha: 0,
    rate_per_ha: 11.09,
    deduction_percent: 0,
    deduction_amount: 0,
    to_date_ha: 0,
    total_budget: 0,
    ytd_claim: 0,
});

// Fetch estate data from API
const fetchEstateData = async (estateIndex, month = null) => {
    if (!estates.value[estateIndex]) return;

    isLoading.value = true;
    try {
        const params = new URLSearchParams();
        if (month) params.append('month', month);

        const url = route('data-input.estate.data', estateIndex) + (params.toString() ? '?' + params.toString() : '');
        const response = await fetch(url);
        const data = await response.json();

        if (data.savedData) {
            // Load saved data (don't update month/herd as they triggered this fetch)
            isExistingRecord.value = true;
            form.allocated_area = data.savedData.allocated_area;
            form.rotation_period = data.savedData.rotation_period;
            form.days_in_month = data.savedData.days_in_month;
            form.current_month_ha = data.savedData.current_month_ha;
            form.rate_per_ha = data.savedData.rate_per_ha;
            form.deduction_percent = data.savedData.deduction_percent;
            form.deduction_amount = data.savedData.deduction_amount;
            form.to_date_ha = data.savedData.to_date_ha;
            form.total_budget = data.savedData.total_budget;
            form.ytd_claim = data.savedData.ytd_claim;

            // Load blocks
            if (data.blocks && data.blocks.length > 0) {
                blocks.value = data.blocks;
            } else {
                blocks.value = [{ id: "Block 1", area: 0, actual: 0, achievement: 0, rate: form.rate_per_ha }];
            }
        } else {
            // Reset to defaults for new record
            isExistingRecord.value = false;
            const estateArea = data.estate?.area || estates.value[estateIndex]?.area || 0;
            const defaults = getDefaultFormValues(estateArea);
            Object.assign(form, defaults);
            blocks.value = [{ id: "Block 1", area: 0, actual: 0, achievement: 0, rate: 11.09 }];
            recalculateCoverage();
        }
    } catch (error) {
        console.error('Error fetching estate data:', error);
    } finally {
        isLoading.value = false;
    }
};

// Methods
const selectEstate = (id) => {
    if (!estates.value[id]) return;
    selectedEstateId.value = id;
    form.estate_id = id;
    // Fetch data for this estate with current month and herd
    fetchEstateData(id, form.month);
};

const recalculateCoverage = () => {
    const dailyCap = form.rotation_period > 0 ? form.allocated_area / form.rotation_period : 0;
    form.current_month_ha = parseFloat((dailyCap * form.days_in_month).toFixed(2));
};

// Update estate area in grid card when allocated area changes
const updateEstateArea = () => {
    if (estates.value[selectedEstateId.value]) {
        estates.value[selectedEstateId.value].area = form.allocated_area;
    }
};

const updateDeductionFromPercent = () => {
    form.deduction_amount = parseFloat((grossClaim.value * (form.deduction_percent / 100)).toFixed(2));
};

const updateDeductionFromAmount = () => {
    if (grossClaim.value > 0) {
        form.deduction_percent = parseFloat(((form.deduction_amount / grossClaim.value) * 100).toFixed(2));
    }
};

const applyGlobalRate = () => {
    if (confirm("Apply Rate RM " + form.rate_per_ha + " to all blocks?")) {
        blocks.value.forEach(b => b.rate = form.rate_per_ha);
    }
};

const addBlockRow = () => {
    blocks.value.push({ id: "", area: 0, actual: 0, achievement: 0, rate: form.rate_per_ha });
};

const removeBlock = (index) => {
    blocks.value.splice(index, 1);
};

const getBlockPercent = (block) => {
    return block.area > 0 ? ((block.actual / block.area) * 100).toFixed(1) + '%' : '0.0%';
};

const getBlockTotal = (block) => {
    return (parseFloat(block.achievement) || 0) * (parseFloat(block.rate) || 0);
};

// Estate CRUD Modal State
const showEstateModal = ref(false);
const estateModalMode = ref('add'); // 'add' or 'edit'
const editingEstateIndex = ref(null);
const estateForm = useForm({
    name: '',
    area: '',
    latitude: '',
    longitude: '',
    place_name: ''
});

// Google Maps State
const showMapModal = ref(false);
const mapLatitude = ref('');
const mapLongitude = ref('');
const mapMarker = ref(null);
let mapInstance = null;

const openAddEstateModal = () => {
    estateModalMode.value = 'add';
    estateForm.reset();
    mapLatitude.value = '';
    mapLongitude.value = '';
    showEstateModal.value = true;
};

const openEditEstateModal = (e, index) => {
    e.stopPropagation();
    estateModalMode.value = 'edit';
    editingEstateIndex.value = index;
    const estate = estates.value[index];
    estateForm.name = estate.name;
    estateForm.area = estate.area;
    estateForm.latitude = estate.latitude || '';
    estateForm.longitude = estate.longitude || '';
    estateForm.place_name = estate.place_name || '';
    mapLatitude.value = estate.latitude || '';
    mapLongitude.value = estate.longitude || '';
    showEstateModal.value = true;
};

const saveEstate = () => {
    if (estateModalMode.value === 'add') {
        estateForm.post(route('data-input.estates.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showEstateModal.value = false;
                estateForm.reset();
            }
        });
    } else {
        const estate = estates.value[editingEstateIndex.value];
        estateForm.put(route('data-input.estates.update', estate.db_id), {
            preserveScroll: true,
            onSuccess: () => {
                showEstateModal.value = false;
                estateForm.reset();
            }
        });
    }
};

const deleteEstate = (e, index) => {
    e.stopPropagation();
    if (confirm("Are you sure you want to delete " + estates.value[index].name + "?")) {
        const estate = estates.value[index];
        if (estate.db_id) {
            router.delete(route('data-input.estates.destroy', estate.db_id), {
                preserveScroll: true
            });
        }
    }
};

const openEstateDetailModal = (estate, index) => {
    selectedEstateForDetail.value = estate;
    selectedEstateIndexForDetail.value = index;
    showEstateDetailModal.value = true;
};

const openGoogleMapsDirections = (estate) => {
    if (estate.latitude && estate.longitude) {
        const url = `https://www.google.com/maps/dir/?api=1&destination=${estate.latitude},${estate.longitude}`;
        window.open(url, '_blank');
    }
};

const openEditEstateFromDetail = () => {
    showEstateDetailModal.value = false;
    
    let index = selectedEstateIndexForDetail.value;
    
    // Fallback: find index by matching estate data
    if ((index === null || index === undefined) && selectedEstateForDetail.value) {
        index = estates.value.findIndex(e => 
            e.name === selectedEstateForDetail.value.name && 
            e.area === selectedEstateForDetail.value.area
        );
    }
    
    if (index !== null && index !== undefined && index >= 0) {
        const mockEvent = { stopPropagation: () => {} };
        openEditEstateModal(mockEvent, index);
    } else {
        console.error('Could not find estate index');
    }
};

const getGoogleMapsUrl = (lat, lng) => {
    if (!lat || !lng) return '#';
    return `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
};

// Herd CRUD Modal State
const showHerdModal = ref(false);
const showEstateDetailModal = ref(false);
const selectedEstateForDetail = ref(null);
const selectedEstateIndexForDetail = ref(null);
const herdModalMode = ref('add'); // 'add' or 'edit'
const editingHerdId = ref(null);
const herdForm = useForm({
    name: ''
});

const openAddHerdModal = () => {
    herdModalMode.value = 'add';
    herdForm.reset();
    showHerdModal.value = true;
};

const openEditHerdModal = () => {
    const currentHerd = herds.value.find(h => h.name === selectedHerdName.value);
    if (!currentHerd) return;
    herdModalMode.value = 'edit';
    editingHerdId.value = currentHerd.id;
    herdForm.name = currentHerd.name;
    showHerdModal.value = true;
};

const saveHerd = () => {
    if (herdModalMode.value === 'add') {
        herdForm.post(route('data-input.herds.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showHerdModal.value = false;
                herdForm.reset();
            }
        });
    } else {
        herdForm.put(route('data-input.herds.update', editingHerdId.value), {
            preserveScroll: true,
            onSuccess: () => {
                showHerdModal.value = false;
                herdForm.reset();
            }
        });
    }
};

const deleteHerd = () => {
    const currentHerd = herds.value.find(h => h.name === selectedHerdName.value);
    if (!currentHerd) return;
    if (confirm("Delete herd: " + currentHerd.name + "?")) {
        router.delete(route('data-input.herds.destroy', currentHerd.id), {
            preserveScroll: true,
            onSuccess: () => {
                selectedHerdName.value = herds.value[0]?.name || '';
            }
        });
    }
};

// OpenStreetMap Functions (Free - No API Key Required)
let currentMapType = 'satellite';

const loadLeaflet = () => {
    return new Promise((resolve, reject) => {
        if (typeof L !== 'undefined') {
            resolve();
            return;
        }
        
        const existingCss = document.getElementById('leaflet-css');
        if (!existingCss) {
            const link = document.createElement('link');
            link.id = 'leaflet-css';
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);
        }
        
        const existingScript = document.getElementById('leaflet-js');
        if (existingScript) {
            existingScript.addEventListener('load', resolve);
            existingScript.addEventListener('error', reject);
            return;
        }
        
        const script = document.createElement('script');
        script.id = 'leaflet-js';
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.async = true;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
};

// Reverse geocoding to get place name from coordinates (free using Nominatim)
const getPlaceName = async (lat, lng) => {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=14&addressdetails=1`);
        const data = await response.json();
        if (data && data.display_name) {
            return data.display_name;
        }
        return `${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
    } catch (error) {
        console.error('Geocoding error:', error);
        return `${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
    }
};

const openMapPicker = async () => {
    mapLatitude.value = estateForm.latitude || '';
    mapLongitude.value = estateForm.longitude || '';
    currentMapType = 'satellite';
    showMapModal.value = true;
    
    try {
        await loadLeaflet();
        setTimeout(() => {
            initLeafletMap();
        }, 100);
    } catch (error) {
        console.error('Failed to load map:', error);
        alert('Failed to load map. Please check your internet connection.');
    }
};

const initLeafletMap = () => {
    const mapContainer = document.getElementById('estate-map');
    if (!mapContainer) return;
    
    const lat = mapLatitude.value ? parseFloat(mapLatitude.value) : 5.9788;
    const lng = mapLongitude.value ? parseFloat(mapLongitude.value) : 116.0753;
    
    if (mapInstance) {
        mapInstance.remove();
    }
    
    mapInstance = L.map('estate-map').setView([lat, lng], 12);
    
    updateTileLayer();
    
    if (mapLatitude.value && mapLongitude.value) {
        updateMarker(lat, lng);
    }
    
    mapInstance.on('click', async (e) => {
        const { lat, lng } = e.latlng;
        mapLatitude.value = lat.toFixed(7);
        mapLongitude.value = lng.toFixed(7);
        
        updateMarker(lat, lng);
        
        // Get place name
        const placeName = await getPlaceName(lat, lng);
        document.getElementById('selected-place-name').textContent = placeName;
        document.getElementById('selected-place-name').classList.remove('hidden');
    });
};

const updateTileLayer = () => {
    if (!mapInstance) return;
    
    mapInstance.eachLayer((layer) => {
        if (layer instanceof L.TileLayer) {
            mapInstance.removeLayer(layer);
        }
    });
    
    let tileUrl, attribution;
    
    if (currentMapType === 'satellite') {
        tileUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
        attribution = 'Satellite &copy; Esri';
    } else if (currentMapType === 'street') {
        tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        attribution = '&copy; OpenStreetMap contributors';
    } else if (currentMapType === 'hybrid') {
        tileUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
        attribution = 'Satellite &copy; Esri';
        
        // Add labels layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '',
            pane: 'tilePane'
        }).addTo(mapInstance);
    }
    
    L.tileLayer(tileUrl, {
        attribution: attribution
    }).addTo(mapInstance);
};

const switchMapType = (type) => {
    currentMapType = type;
    updateTileLayer();
    
    // Update button styles
    document.querySelectorAll('.map-type-btn').forEach(btn => {
        btn.classList.remove('bg-green-600', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
    });
    document.getElementById(`btn-${type}`).classList.remove('bg-gray-200', 'text-gray-700');
    document.getElementById(`btn-${type}`).classList.add('bg-green-600', 'text-white');
};

const updateMarker = async (lat, lng) => {
    if (mapMarker) {
        mapMarker.setLatLng([lat, lng]);
    } else {
        mapMarker = L.marker([lat, lng]).addTo(mapInstance);
    }
    
    mapMarker.bindPopup('Loading...').openPopup();
    
    const placeName = await getPlaceName(lat, lng);
    
    mapMarker.setPopupContent(`
        <div class="text-center min-w-[200px]">
            <strong class="block text-sm text-green-700 mb-1">Estate Location</strong>
            <span class="text-xs text-gray-600">${placeName}</span>
            <div class="text-xs text-gray-500 mt-1">
                ${parseFloat(lat).toFixed(7)}, ${parseFloat(lng).toFixed(7)}
            </div>
        </div>
    `);
};

const clearMapLocation = () => {
    mapLatitude.value = '';
    mapLongitude.value = '';
    document.getElementById('selected-place-name').textContent = '';
    document.getElementById('selected-place-name').classList.add('hidden');
    
    if (mapMarker) {
        mapMarker.remove();
        mapMarker = null;
    }
};

const saveMapLocation = async () => {
    estateForm.latitude = mapLatitude.value;
    estateForm.longitude = mapLongitude.value;
    
    if (mapLatitude.value && mapLongitude.value) {
        const placeName = await getPlaceName(mapLatitude.value, mapLongitude.value);
        estateForm.place_name = placeName;
    }
    
    showMapModal.value = false;
};

// Get place name for existing coordinates
const getLocationDisplay = async (lat, lng) => {
    if (!lat || !lng) return null;
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16&addressdetails=1`);
        const data = await response.json();
        if (data && data.display_name) {
            // Shorten the display name
            const parts = data.display_name.split(', ');
            if (parts.length > 3) {
                return parts.slice(0, 3).join(', ');
            }
            return data.display_name;
        }
        return `${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
    } catch (error) {
        return `${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}`;
    }
};

// Generate static map URL using OpenStreetMap
const getStaticMapUrl = (lat, lng, width = 200, height = 100) => {
    if (!lat || !lng) return null;
    // Use OpenStreetMap static map
    const zoom = width > 300 ? 14 : 12;
    return `https://static-maps.yandex.ru/1.x/?lang=en-US&ll=${lng},${lat}&z=${zoom}&size=${width},${height}&l=sat`;
};

// Save Data
const saveData = () => {
    form.blocks = blocks.value;
    form.herd = '';
    form.post(route('data-input.save'), {
        preserveScroll: true,
        onSuccess: () => {}
    });
};

// Watchers
watch(() => form.allocated_area, (newArea) => {
    recalculateCoverage();
    updateEstateArea();
});
watch(() => form.rotation_period, recalculateCoverage);
watch(() => form.days_in_month, recalculateCoverage);
watch(() => form.deduction_percent, updateDeductionFromPercent);

// Watch for month changes - fetch data for new month
watch(() => form.month, (newMonth, oldMonth) => {
    if (newMonth !== oldMonth && estates.value[selectedEstateId.value]) {
        fetchEstateData(selectedEstateId.value, newMonth);
    }
});

// Watch for props changes (after CRUD operations)
watch(() => props.estates, (newEstates) => {
    if (newEstates) {
        estates.value = newEstates;
        // Select first estate if current selection is invalid
        if (selectedEstateId.value >= newEstates.length) {
            selectedEstateId.value = 0;
        }
        if (newEstates.length > 0 && newEstates[selectedEstateId.value]) {
            form.allocated_area = newEstates[selectedEstateId.value].area;
        }
    }
}, { deep: true });

// Watch for herds props changes
watch(() => props.herds, (newHerds) => {
    if (newHerds) {
        herds.value = newHerds;
        // Select first herd if current selection is invalid
        const currentHerdExists = newHerds.some(h => h.name === selectedHerdName.value);
        if (!currentHerdExists && newHerds.length > 0) {
            selectedHerdName.value = newHerds[0].name;
        }
    }
}, { deep: true });
</script>

<template>
    <Head title="Grazing Data Input" />
    <AppLayout title="Grazing Data Input" parent="Data Management">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- Header Section -->
            <div class="mb-8 flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Grazing Data Input</h1>
                    <p class="text-sm text-gray-500 mt-1">Select an operating unit and month, then enter grazing data.</p>
                </div>
                <div v-if="estates.length > 0" class="flex items-center gap-2">
                    <button type="button" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                        <FileDown class="w-4 h-4" />
                        Export
                    </button>
                </div>
            </div>

            <!-- Estate Selection Cards -->
            <section class="mb-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <!-- Estate Cards -->
                    <div v-for="(estate, index) in estates" :key="estate.id"
                        @click="selectEstate(index)"
                        :class="[
                            'estate-card relative cursor-pointer bg-white p-5 rounded-xl border-2 shadow-sm hover:shadow-md transition-all text-center group',
                            selectedEstateId === index ? 'border-[#34554a] ring-1 ring-[#34554a]' : 'border-transparent hover:border-slate-300'
                        ]">
                        <div :class="['text-sm font-bold group-hover:text-[#34554a] mb-1', selectedEstateId === index ? 'text-[#34554a]' : 'text-gray-800']">
                            {{ toTitleCase(estate.name) }}
                        </div>
                        <div class="text-xs text-gray-500 font-medium">{{ estate.area }} Ha</div>
                        
                        <!-- Location indicator with place name -->
                        <div v-if="estate.latitude && estate.longitude" class="flex items-center gap-1 mt-1 text-xs text-gray-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="10" r="3"/>
                                <path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"/>
                            </svg>
                            <span>{{ estate.place_name ? estate.place_name.substring(0, 20) + (estate.place_name.length > 20 ? '...' : '') : 'Located' }}</span>
                        </div>

                        <!-- Small Map Preview with coordinates - Clickable for directions -->
                        <div v-if="estate.latitude && estate.longitude" class="mt-2">
                            <a 
                                :href="`https://www.google.com/maps/dir/?api=1&destination=${estate.latitude},${estate.longitude}`" 
                                target="_blank"
                                class="block group"
                                title="Click to get directions"
                            >
                                <img 
                                    :src="getStaticMapUrl(estate.latitude, estate.longitude, 120, 60)" 
                                    :alt="estate.name + ' location'"
                                    class="w-full h-12 object-cover rounded border border-gray-200 group-hover:ring-2 group-hover:ring-[#34554a] transition-all cursor-pointer"
                                    @error="$event.target.style.display='none'"
                                />
                                <div class="text-[9px] text-gray-400 mt-0.5 text-center flex items-center justify-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                                    </svg>
                                    {{ parseFloat(estate.latitude).toFixed(4) }}, {{ parseFloat(estate.longitude).toFixed(4) }}
                                </div>
                            </a>
                        </div>

                        <!-- Action Buttons -->
                        <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button 
                                @click="openEstateDetailModal(estate, index)" 
                                class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-600 p-1.5 rounded-full cursor-pointer shadow-sm"
                                title="View Details"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                            <button @click="openEditEstateModal($event, index)" class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-600 p-1.5 rounded-full cursor-pointer shadow-sm" title="Edit">
                                <Pencil class="w-3 h-3" />
                            </button>
                            <button @click="deleteEstate($event, index)" class="text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-500 p-1.5 rounded-full cursor-pointer shadow-sm" title="Delete">
                                <Trash2 class="w-3 h-3" />
                            </button>
                        </div>
                    </div>

                    <!-- Add New Estate Card -->
                    <div @click="openAddEstateModal" class="cursor-pointer bg-white p-5 rounded-xl border-2 border-dashed border-gray-300 hover:border-[#34554a] hover:bg-gray-50 transition-all text-center flex flex-col justify-center items-center h-full min-h-[80px] group">
                        <div class="w-10 h-10 rounded-full bg-gray-50 text-gray-400 group-hover:bg-[#34554a] group-hover:text-white flex items-center justify-center mb-2 transition-all">
                            <Plus class="w-5 h-5" />
                        </div>
                        <div class="text-xs font-medium text-gray-500 group-hover:text-[#34554a] font-semibold">Add Estate</div>
                    </div>
                </div>
            </section>

            <form @submit.prevent="saveData" class="space-y-6 relative">

                <!-- Loading Overlay -->
                <div v-if="isLoading" class="absolute inset-0 bg-white/70 z-10 flex items-center justify-center rounded-xl">
                    <div class="flex items-center gap-3 bg-white px-6 py-3 rounded-lg shadow-lg border border-gray-200">
                        <Loader2 class="w-5 h-5 animate-spin text-[#34554a]" />
                        <span class="text-sm font-medium text-gray-600">Loading data...</span>
                    </div>
                </div>

                <!-- Basic Info -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b border-gray-100 pb-2 flex justify-between items-center">
                        <span>Basic Information</span>
                        <span v-if="selectedEstate.name" class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded font-medium">{{ toTitleCase(selectedEstate.name) }}</span>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Operating Unit</label>
                            <div class="flex gap-2">
                                <select
                                    v-model="selectedEstateId"
                                    @change="selectEstate(selectedEstateId)"
                                    class="flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] cursor-pointer">
                                    <option v-if="estates.length === 0" value="" disabled>No estates - Add one first</option>
                                    <option v-for="(estate, index) in estates" :key="estate.db_id" :value="index">
                                        {{ toTitleCase(estate.name) }} ({{ estate.area }} Ha)
                                    </option>
                                </select>
                                <button
                                    v-if="estates.length > 0"
                                    type="button"
                                    @click="openEditEstateModal($event, selectedEstateId)"
                                    class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-500 rounded-lg px-2.5 py-2 transition-colors shadow-sm cursor-pointer hover:text-gray-700"
                                    title="Edit Selected Estate">
                                    <Pencil class="w-3 h-3" />
                                </button>
                                <button
                                    v-if="estates.length > 0"
                                    type="button"
                                    @click="deleteEstate($event, selectedEstateId)"
                                    class="bg-white hover:bg-gray-50 border border-gray-200 text-gray-500 rounded-lg px-2.5 py-2 transition-colors shadow-sm cursor-pointer"
                                    title="Delete Selected Estate">
                                    <Trash2 class="w-3 h-3" />
                                </button>
                                <button
                                    type="button"
                                    @click="openAddEstateModal"
                                    class="bg-[#34554a] hover:bg-[#2a443b] text-white rounded-lg px-3 py-2 transition-colors shadow-md cursor-pointer"
                                    title="Add New Estate">
                                    <Plus class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Month</label>
                            <input type="month" v-model="form.month" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a]">
                        </div>
                    </div>
                </div>

                <!-- Coverage & Financials -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-6 border-b border-gray-100 pb-2">
                        <span>Monthly Grazing Coverage & Financials</span>
                    </h3>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-6">

                        <!-- Left: Coverage Params -->
                        <div class="space-y-5">
                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-600 font-medium">Allocated Area (Ha)</label>
                                <input type="number" v-model="form.allocated_area" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] text-right font-medium">
                            </div>
                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-600 font-medium">Rotation Period (Days)</label>
                                <input type="number" v-model="form.rotation_period" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] text-right font-medium">
                            </div>
                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-600 font-medium">Days in Month</label>
                                <input type="number" v-model="form.days_in_month" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] text-right font-medium">
                            </div>
                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-500 font-medium">Daily Capacity (Ha/Day)</label>
                                <input type="text" :value="dailyCapacity" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm outline-none text-gray-600 text-right font-bold" readonly>
                            </div>

                            <div class="border-t border-gray-100 my-2"></div>

                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-900 font-semibold">Current Month (Ha)</label>
                                <input type="number" v-model="form.current_month_ha" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] text-right font-bold text-gray-800">
                            </div>
                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-600 font-medium">% Current Month</label>
                                <input type="text" :value="currentMonthPercent" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm outline-none text-gray-600 text-right font-medium" readonly>
                            </div>
                        </div>

                        <!-- Right: Financials -->
                        <div class="space-y-5">
                            <div class="grid grid-cols-2 gap-4 items-center">
                                <div class="flex items-center gap-1">
                                    <label class="text-sm text-gray-600 font-medium">Pay/Ha (RM)</label>
                                    <button type="button" @click="applyGlobalRate" class="text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded border border-gray-200 hover:bg-gray-200 cursor-pointer font-medium" title="Apply this rate to all blocks">Apply to All</button>
                                </div>
                                <input type="number" v-model="form.rate_per_ha" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] text-right font-medium">
                            </div>
                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-600 font-medium">Deduction %</label>
                                <div class="relative">
                                    <input type="number" v-model="form.deduction_percent" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] text-right font-medium">
                                    <span class="absolute right-8 top-2 text-gray-400 text-sm">%</span>
                                </div>
                            </div>

                            <div class="border-t border-gray-100 my-2"></div>

                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-600 font-medium">To Date (Ha)</label>
                                <input type="number" v-model="form.to_date_ha" step="0.01" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] text-right font-medium">
                            </div>
                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-600 font-medium">% To Date</label>
                                <input type="text" :value="toDatePercent" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm outline-none text-gray-600 text-right font-medium" readonly>
                            </div>

                            <div class="border-t border-gray-100 my-2"></div>

                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-600 font-medium">Gross MTD Claim (RM)</label>
                                <input type="text" :value="fmtMoney(grossClaim)" class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm outline-none text-gray-700 text-right font-bold" readonly>
                            </div>
                            <div class="grid grid-cols-2 gap-4 items-center">
                                <label class="text-sm text-gray-600 font-medium">Deduction Amount (RM)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-400 text-sm">RM</span>
                                    <input type="number" v-model="form.deduction_amount" @input="updateDeductionFromAmount" class="w-full rounded-lg border border-gray-200 bg-white pl-10 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] text-right font-medium text-gray-700">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4 items-center bg-gray-50 p-2 rounded-lg border border-gray-200">
                                <label class="text-sm text-gray-800 font-bold">Net MTD Claim (RM)</label>
                                <input type="text" :value="fmtMoney(netClaim)" class="bg-white border-gray-200 text-gray-700 w-full rounded-md border px-3 py-2 text-right font-bold shadow-sm" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Calculation Note -->
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-100 text-xs text-gray-500">
                        <p class="font-medium mb-1 text-gray-700">Note:</p>
                        <p>Current Month (Ha) defaults to formula: (Allocated Area / Rotation Period) x Days in Month.
                        You can manually edit the Current Month (Ha) value or the Deduction Amount if needed.</p>
                    </div>
                </div>

                <!-- Block Level Data Table -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <h3 class="text-lg font-semibold text-gray-700">Block Level Data</h3>
                        <button type="button" @click="addBlockRow" class="text-sm text-[#34554a] hover:text-[#2a443b] font-medium flex items-center gap-1 transition-colors">
                            <Plus class="w-4 h-4" /> Add Block
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-[#34554a] text-white text-sm">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">Block</th>
                                    <th class="px-6 py-3 font-semibold text-right">Area (Ha)</th>
                                    <th class="px-4 py-3 text-center font-semibold bg-[#2a443b]"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                                <tr v-for="(block, index) in blocks" :key="index" class="hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0">
                                    <td class="px-6 py-3 border-b border-gray-100">
                                        <input type="text" v-model="block.id" placeholder="Block ID *" required class="w-full bg-transparent border-none focus:ring-0 font-bold text-gray-900 placeholder-gray-300">
                                    </td>
                                    <td class="px-6 py-3 border-b border-gray-100">
                                        <input type="number" v-model="block.area" class="w-full rounded px-2 py-1 text-right text-xs border border-gray-200 focus:ring-2 focus:ring-[#34554a]">
                                    </td>
                                    <td class="px-4 py-3 border-b border-gray-100 text-center">
                                        <button type="button" @click="removeBlock(index)" class="text-gray-400 hover:text-gray-700 transition-colors cursor-pointer">
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold text-gray-700 text-sm border-t border-gray-200">
                                <tr>
                                    <td class="px-6 py-3">Totals</td>
                                    <td class="px-6 py-3 text-right">{{ blockTotals.totalArea }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Budget Status -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b border-gray-100 pb-2">Budget Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Total Budget</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-gray-400 text-sm">RM</span>
                                <input type="number" v-model="form.total_budget" class="w-full rounded-lg border border-gray-200 bg-white pl-10 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] font-medium">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Ytd Claimed</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-gray-400 text-sm">RM</span>
                                <input type="number" v-model="form.ytd_claim" class="w-full rounded-lg border border-gray-200 bg-white pl-10 pr-3 py-2 text-sm outline-none focus:ring-2 focus:ring-[#34554a] font-medium">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Budget Remaining</label>
                            <input type="text" :value="fmtMoney(budgetRemaining)" class="w-full rounded-lg border border-gray-200 bg-gray-50 pl-3 pr-3 py-2 text-sm outline-none font-bold text-gray-700" readonly>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <!-- Error Display -->
                <div v-if="Object.keys(form.errors).length > 0" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <p class="text-sm font-medium text-gray-700 mb-2">Please fix the following errors:</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                        <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>

                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 pt-4 border-t border-gray-200">
                    <button type="submit" :disabled="form.processing" class="w-full sm:w-auto px-8 py-2.5 rounded-lg bg-[#34554a] text-white hover:bg-[#2a443b] font-medium shadow-md shadow-[#34554a]/20 transition-all text-sm disabled:opacity-75 flex items-center justify-center gap-2">
                        <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                        <Save v-else class="w-4 h-4" />
                        {{ form.processing ? 'Saving...' : 'Save Data' }}
                    </button>
                </div>

            </form>

            <!-- Estate Modal -->
            <div v-if="showEstateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showEstateModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-lg shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">
                            {{ estateModalMode === 'add' ? 'Add New Estate' : 'Edit Estate' }}
                        </h3>
                        <button @click="showEstateModal = false" class="text-gray-400 hover:text-gray-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="saveEstate" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Estate Name</label>
                            <input
                                v-model="estateForm.name"
                                type="text"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                placeholder="Enter estate name"
                            />
                            <p v-if="estateForm.errors.name" class="mt-1 text-sm text-gray-600">{{ estateForm.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Area (Hectares)</label>
                            <input
                                v-model="estateForm.area"
                                type="number"
                                step="0.01"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                placeholder="Enter area in hectares"
                            />
                            <p v-if="estateForm.errors.area" class="mt-1 text-sm text-gray-600">{{ estateForm.errors.area }}</p>
                        </div>

                        <!-- Location Section -->
                        <div class="border-t border-gray-200 pt-4 mt-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Location (Optional)</h4>
                            
                            <div class="grid grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Latitude</label>
                                    <input
                                        v-model="estateForm.latitude"
                                        type="text"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                        placeholder="e.g., 5.9788"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Longitude</label>
                                    <input
                                        v-model="estateForm.longitude"
                                        type="text"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                        placeholder="e.g., 116.0753"
                                    />
                                </div>
                            </div>
                            
                            <button
                                type="button"
                                @click="openMapPicker"
                                class="w-full px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] transition-colors flex items-center justify-center gap-2"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="10" r="3"/>
                                    <path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"/>
                                </svg>
                                Pick Location on Map
                            </button>
                            
                            <!-- Show current location preview -->
                            <div v-if="estateForm.latitude && estateForm.longitude" class="mt-2">
                                <div class="text-xs text-gray-600 text-center font-medium mb-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="10" r="3"/>
                                        <path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"/>
                                    </svg>
                                    Location Set
                                </div>
                                <div class="text-[10px] text-gray-500 text-center">
                                    {{ estateForm.latitude }}, {{ estateForm.longitude }}
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                @click="showEstateModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="estateForm.processing"
                                class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 flex items-center gap-2">
                                <Loader2 v-if="estateForm.processing" class="w-4 h-4 animate-spin" />
                                <Save v-else class="w-4 h-4" />
                                {{ estateModalMode === 'add' ? 'Add Estate' : 'Save Changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Estate Detail Modal -->
            <div v-if="showEstateDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showEstateDetailModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-lg shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#34554a]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <path d="M3 9h18"/>
                                <path d="M9 21V9"/>
                            </svg>
                            Estate Details
                        </h3>
                        <button @click="showEstateDetailModal = false" class="text-gray-400 hover:text-gray-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <div v-if="selectedEstateForDetail" class="space-y-4">
                        <!-- Estate Name & Area -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-xl font-bold text-gray-900">{{ selectedEstateForDetail.name }}</h4>
                            <p class="text-sm text-gray-500 mt-1">{{ selectedEstateForDetail.area }} Hectares</p>
                        </div>

                        <!-- Location Info -->
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                                <span class="text-xs font-semibold text-gray-700 uppercase">Location Information</span>
                            </div>
                            <div class="p-4 space-y-3">
                                <div v-if="selectedEstateForDetail.place_name" class="flex items-start gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    <div>
                                        <div class="text-xs text-gray-500">Address</div>
                                        <div class="text-sm text-gray-800">{{ selectedEstateForDetail.place_name }}</div>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line x1="2" y1="12" x2="22" y2="12"/>
                                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                                    </svg>
                                    <div>
                                        <div class="text-xs text-gray-500">Coordinates</div>
                                        <div class="text-sm text-gray-800 font-mono">
                                            {{ selectedEstateForDetail.latitude ? parseFloat(selectedEstateForDetail.latitude).toFixed(7) : '-' }}, 
                                            {{ selectedEstateForDetail.longitude ? parseFloat(selectedEstateForDetail.longitude).toFixed(7) : '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Map Preview -->
                        <div v-if="selectedEstateForDetail.latitude && selectedEstateForDetail.longitude" class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 flex justify-between items-center">
                                <span class="text-xs font-semibold text-gray-700 uppercase">Map Preview</span>
                            </div>
                            <div class="relative">
                                <img 
                                    :src="getStaticMapUrl(selectedEstateForDetail.latitude, selectedEstateForDetail.longitude, 450, 200)" 
                                    :alt="selectedEstateForDetail.name + ' location'"
                                    class="w-full h-40 object-cover"
                                    @error="$event.target.style.display='none'"
                                />
                                <div class="absolute bottom-2 right-2">
                                    <button 
                                        @click="openGoogleMapsDirections(selectedEstateForDetail)"
                                        class="px-3 py-1.5 bg-[#34554a] text-white text-xs font-medium rounded-lg shadow-lg hover:bg-[#2a443b] transition-colors flex items-center gap-1"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                                        </svg>
                                        Get Directions
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3 pt-2">
                            <button 
                                @click="openGoogleMapsDirections(selectedEstateForDetail)"
                                :disabled="!selectedEstateForDetail.latitude || !selectedEstateForDetail.longitude"
                                class="flex-1 px-4 py-2.5 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 font-medium"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                                </svg>
                                Open in Google Maps
                            </button>
                            <button 
                                @click="openEditEstateFromDetail"
                                class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 flex items-center justify-center gap-2 font-medium"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Edit Estate
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Herd Modal -->
            <div v-if="showHerdModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showHerdModal = false">
                <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">
                            {{ herdModalMode === 'add' ? 'Add New Herd' : 'Edit Herd' }}
                        </h3>
                        <button @click="showHerdModal = false" class="text-gray-400 hover:text-gray-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <form @submit.prevent="saveHerd" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Herd Name</label>
                            <input
                                v-model="herdForm.name"
                                type="text"
                                required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#34554a] focus:border-transparent"
                                placeholder="e.g., Herd A - Main Breeding"
                            />
                            <p v-if="herdForm.errors.name" class="mt-1 text-sm text-gray-600">{{ herdForm.errors.name }}</p>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="showHerdModal = false"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="herdForm.processing"
                                class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] disabled:opacity-50 flex items-center gap-2">
                                <Loader2 v-if="herdForm.processing" class="w-4 h-4 animate-spin" />
                                <Save v-else class="w-4 h-4" />
                                {{ herdModalMode === 'add' ? 'Add Herd' : 'Save Changes' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- OpenStreetMap Modal -->
            <div v-if="showMapModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50" @click.self="showMapModal = false">
                <div class="bg-white rounded-xl p-4 w-full max-w-4xl shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="10" r="3"/>
                                <path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 7 8 11.7z"/>
                            </svg>
                            Select Estate Location
                        </h3>
                        <button @click="showMapModal = false" class="text-gray-400 hover:text-gray-600">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Map Type Selector -->
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-xs text-gray-500 font-medium">Map View:</span>
                        <button
                            id="btn-satellite"
                            type="button"
                            @click="switchMapType('satellite')"
                            class="map-type-btn px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 text-white transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                            Satellite
                        </button>
                        <button
                            id="btn-hybrid"
                            type="button"
                            @click="switchMapType('hybrid')"
                            class="map-type-btn px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-200 text-gray-700 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                                <polyline points="2 17 12 22 22 17"/>
                                <polyline points="2 12 12 17 22 12"/>
                            </svg>
                            Hybrid
                        </button>
                        <button
                            id="btn-street"
                            type="button"
                            @click="switchMapType('street')"
                            class="map-type-btn px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-200 text-gray-700 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 inline mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <path d="M3 9h18"/>
                                <path d="M9 21V9"/>
                            </svg>
                            Street Map
                        </button>
                    </div>

                    <!-- Place Name Display -->
                    <div id="selected-place-name" class="hidden mb-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-start gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-600 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <div>
                                <div class="text-xs font-semibold text-green-700">Selected Location</div>
                                <div class="text-xs text-green-600 mt-0.5">Loading...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Coordinate Inputs -->
                    <div class="mb-3">
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Latitude</label>
                                <input
                                    v-model="mapLatitude"
                                    type="text"
                                    placeholder="Click map or enter coordinates"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 text-sm"
                                />
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Longitude</label>
                                <input
                                    v-model="mapLongitude"
                                    type="text"
                                    placeholder="Click map or enter coordinates"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 text-sm"
                                />
                            </div>
                            <div class="flex items-end">
                                <button
                                    type="button"
                                    @click="clearMapLocation"
                                    class="px-3 py-2 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 text-sm"
                                >
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="estate-map" class="w-full h-96 rounded-lg border border-gray-300 mb-4"></div>

                    <!-- Instructions -->
                    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-start gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 16v-4"/>
                                <path d="M12 8h.01"/>
                            </svg>
                            <div class="text-xs text-blue-700">
                                <strong>Instructions:</strong> Click anywhere on the map to select the estate location. The marker will show the exact address. You can switch between Satellite, Hybrid, or Street views.
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            @click="showMapModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="saveMapLocation"
                            :disabled="!mapLatitude || !mapLongitude"
                            class="px-4 py-2 bg-[#34554a] text-white rounded-lg hover:bg-[#2a443b] flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Save Location
                        </button>
                    </div>
                </div>
            </div>

        </div>

    </AppLayout>
</template>
