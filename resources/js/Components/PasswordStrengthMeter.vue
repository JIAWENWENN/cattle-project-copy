<script setup>
import { computed } from 'vue';

const props = defineProps({
    password: { type: String, default: '' },
});

const criteria = computed(() => [
    { label: 'At least 8 characters', met: props.password.length >= 8 },
    { label: 'Has uppercase letter', met: /[A-Z]/.test(props.password) },
    { label: 'Has lowercase letter', met: /[a-z]/.test(props.password) },
    { label: 'Has digit (0-9)', met: /\d/.test(props.password) },
    { label: 'Has special character (!@#$%^&*)', met: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?`~]/.test(props.password) },
    { label: 'At least 5 unique characters', met: new Set(props.password).size >= 5 },
]);

const score = computed(() => criteria.value.filter(c => c.met).length);

const strengthLabel = computed(() => {
    if (score.value <= 2) return { text: 'Weak', color: 'bg-red-500' };
    if (score.value <= 4) return { text: 'Fair', color: 'bg-orange-500' };
    if (score.value === 5) return { text: 'Good', color: 'bg-yellow-500' };
    return { text: 'Strong', color: 'bg-green-500' };
});

const progressPercent = computed(() => (score.value / 6) * 100);

const isVisible = computed(() => props.password.length > 0);
</script>

<template>
    <div v-if="isVisible" class="mt-2 space-y-2">
        <div class="flex items-center gap-2">
            <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                <div
                    :class="strengthLabel.color"
                    class="h-full rounded-full transition-all duration-300"
                    :style="{ width: progressPercent + '%' }"
                ></div>
            </div>
            <span class="text-xs font-medium text-gray-600">{{ strengthLabel.text }}</span>
        </div>
        <ul class="space-y-1">
            <li
                v-for="item in criteria"
                :key="item.label"
                class="flex items-center gap-2 text-xs"
                :class="item.met ? 'text-green-600' : 'text-gray-400'"
            >
                <span v-if="item.met" class="text-green-500">&#10003;</span>
                <span v-else class="text-gray-300">&#9679;</span>
                {{ item.label }}
            </li>
        </ul>
    </div>
</template>
