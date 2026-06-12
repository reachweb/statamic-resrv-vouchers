<script setup>
import { onBeforeUnmount, reactive, ref } from 'vue';
import axios from 'axios';
import { Head } from '@statamic/cms/inertia';
import { Alert, Badge, Button, CardPanel, Field, Header, Heading, Input } from '@statamic/cms/ui';

const props = defineProps({
    lookupUrl: { type: String, required: true },
    markUsedUrl: { type: String, required: true },
});

const scanner = ref(null);
const scannerEl = ref(null);
const scannerActive = ref(false);
const cameras = ref([]);
const currentCameraIndex = ref(0);
const scannerError = ref('');

const state = reactive({
    query: '',
    token: '',
    result: null,
    pending: false,
    flash: '',
});

const statusColor = (status) => ({
    issued: 'green',
    used: 'amber',
    invalidated: 'red',
    expired: 'red',
}[status] ?? 'default');

const bannerVariant = (tone) => ({
    success: 'success',
    warning: 'warning',
    danger: 'error',
}[tone] ?? 'default');

async function startScanner() {
    scannerError.value = '';
    try {
        const { Html5Qrcode } = await import('html5-qrcode');
        cameras.value = await Html5Qrcode.getCameras();

        if (!cameras.value.length) {
            scannerError.value = 'No camera devices available — use the manual entry field below.';
            return;
        }

        scanner.value = new Html5Qrcode(scannerEl.value.id);
        await scanner.value.start(
            cameras.value[currentCameraIndex.value].id,
            { fps: 10, qrbox: { width: 240, height: 240 } },
            (decoded) => onDecoded(decoded),
            () => {},
        );
        scannerActive.value = true;
    } catch (e) {
        scannerError.value = e?.message ?? 'Unable to access the camera.';
    }
}

async function stopScanner() {
    if (scanner.value && scanner.value.isScanning) {
        try {
            await scanner.value.stop();
        } catch (e) {
            // ignore stop errors during teardown
        }
    }
    scannerActive.value = false;
}

async function switchCamera() {
    if (cameras.value.length < 2) {
        return;
    }
    await stopScanner();
    currentCameraIndex.value = (currentCameraIndex.value + 1) % cameras.value.length;
    await startScanner();
}

async function onDecoded(decoded) {
    if (state.pending || decoded === state.query) {
        return;
    }
    state.query = decoded;
    await lookup();
}

async function lookup() {
    const query = state.query.trim();
    if (!query) {
        state.flash = 'Enter a reservation code, booking reference, or token first.';
        return;
    }
    state.pending = true;
    state.flash = '';
    try {
        const { data } = await axios.post(props.lookupUrl, { query });
        state.result = data;
        state.token = data.token;
    } catch (e) {
        state.result = null;
        state.token = '';
        state.flash = e?.response?.data?.message ?? 'Lookup failed.';
    } finally {
        state.pending = false;
    }
}

async function markUsed() {
    state.pending = true;
    state.flash = '';
    try {
        const { data } = await axios.patch(props.markUsedUrl, { token: state.token });
        state.result = data;
    } catch (e) {
        state.flash = e?.response?.data?.message ?? 'Could not mark used.';
    } finally {
        state.pending = false;
    }
}

function scanAnother() {
    state.query = '';
    state.token = '';
    state.result = null;
    state.flash = '';
}

onBeforeUnmount(() => {
    stopScanner();
});
</script>

<template>
    <div class="max-w-page mx-auto">
        <Head :title="__('Scan voucher')" />

        <Header :title="__('Scan voucher')" />

        <CardPanel>
            <div id="voucher-scanner-viewport" ref="scannerEl" class="mb-4 w-full max-w-sm" />

            <p v-if="scannerError" class="text-sm text-orange-600">{{ scannerError }}</p>

            <div class="mb-4 flex gap-2">
                <Button v-if="!scannerActive" @click="startScanner">{{ __('Start camera') }}</Button>
                <Button v-else variant="ghost" @click="stopScanner">{{ __('Stop camera') }}</Button>
                <Button v-if="scannerActive && cameras.length > 1" variant="ghost" @click="switchCamera">
                    {{ __('Switch camera') }}
                </Button>
            </div>

            <Field :label="__('Or find by reservation code, booking reference, or token')">
                <Input v-model="state.query" placeholder="e.g. 1052 or AB12CD" @keyup.enter="lookup" />
            </Field>

            <div class="mt-3 flex gap-2">
                <Button :disabled="state.pending" @click="lookup">{{ __('Find voucher') }}</Button>
                <Button variant="ghost" @click="scanAnother">{{ __('Clear') }}</Button>
            </div>

            <p v-if="state.flash" class="mt-3 text-sm text-orange-600">{{ state.flash }}</p>
        </CardPanel>

        <CardPanel v-if="state.result" class="mt-4">
            <div class="flex items-center justify-between">
                <Heading>Voucher {{ state.result.voucher.id }}</Heading>
                <Badge :color="statusColor(state.result.status)" :text="state.result.status" />
            </div>

            <Alert
                v-if="state.result.status_banner"
                class="mt-3"
                :variant="bannerVariant(state.result.status_banner.tone)"
                :text="state.result.status_banner.message"
            />

            <dl v-if="state.result.reservation" class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <template v-if="state.result.entry_title">
                    <dt class="font-medium">{{ __('Entry') }}</dt>
                    <dd>{{ state.result.entry_title }}</dd>
                </template>
                <template v-if="state.result.rate">
                    <dt class="font-medium">{{ __('Rate') }}</dt>
                    <dd>{{ state.result.rate }}</dd>
                </template>
                <dt class="font-medium">{{ __('Reference') }}</dt>
                <dd>{{ state.result.reservation.reference }}</dd>
                <dt class="font-medium">{{ __('Guest') }}</dt>
                <dd>
                    {{ state.result.reservation.customer?.data?.first_name }}
                    {{ state.result.reservation.customer?.data?.last_name }}
                </dd>
                <dt class="font-medium">{{ __('Dates') }}</dt>
                <dd>{{ state.result.dates?.start }} – {{ state.result.dates?.end }}</dd>
                <dt class="font-medium">{{ __('Quantity') }}</dt>
                <dd>{{ state.result.reservation.quantity }}</dd>
            </dl>

            <div class="mt-4 flex gap-2">
                <Button v-if="state.result.status === 'issued'" variant="primary" :disabled="state.pending" @click="markUsed">
                    {{ __('Mark as used') }}
                </Button>
                <Button variant="primary" @click="scanAnother">{{ __('Scan another') }}</Button>
            </div>
        </CardPanel>
    </div>
</template>
