<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { Head } from '@statamic/cms/inertia';
import { Badge, Button, CardPanel, Field, Header, Heading, Input } from '@statamic/cms/ui';

const props = defineProps({
    lookupUrl: { type: String, required: true },
    markUsedUrl: { type: String, required: true },
    unMarkUrl: { type: String, required: true },
});

const scanner = ref(null);
const scannerEl = ref(null);
const cameras = ref([]);
const currentCameraIndex = ref(0);
const scannerError = ref('');

const state = reactive({
    token: '',
    result: null,
    pending: false,
    flash: '',
});

const statusVariant = (status) => ({
    issued: 'success',
    used: 'warning',
    invalidated: 'danger',
    expired: 'danger',
}[status] ?? 'default');

async function startScanner() {
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
    if (state.pending || decoded === state.token) {
        return;
    }
    state.token = decoded;
    await lookup();
}

async function lookup() {
    if (!state.token) {
        state.flash = 'Enter or scan a token first.';
        return;
    }
    state.pending = true;
    state.flash = '';
    try {
        const { data } = await axios.post(props.lookupUrl, { token: state.token });
        state.result = data;
    } catch (e) {
        state.result = null;
        state.flash = e?.response?.data?.message ?? 'Lookup failed.';
    } finally {
        state.pending = false;
    }
}

async function markUsed() {
    state.pending = true;
    try {
        await axios.patch(props.markUsedUrl, { token: state.token });
        await lookup();
    } catch (e) {
        state.flash = e?.response?.data?.message ?? 'Could not mark used.';
    } finally {
        state.pending = false;
    }
}

async function unMark() {
    state.pending = true;
    try {
        await axios.patch(props.unMarkUrl, { token: state.token });
        await lookup();
    } catch (e) {
        state.flash = e?.response?.data?.message ?? 'Could not un-mark.';
    } finally {
        state.pending = false;
    }
}

function scanAnother() {
    state.token = '';
    state.result = null;
    state.flash = '';
}

onMounted(() => {
    startScanner();
});

onBeforeUnmount(() => {
    stopScanner();
});
</script>

<template>
    <div>
        <Head title="Scan voucher" />
        <Header>
            <Heading>Scan voucher</Heading>
        </Header>

        <CardPanel>
            <div id="voucher-scanner-viewport" ref="scannerEl" class="mb-4 w-full max-w-sm" />

            <p v-if="scannerError" class="text-sm text-orange-600">{{ scannerError }}</p>

            <Button v-if="cameras.length > 1" variant="ghost" @click="switchCamera">Switch camera</Button>

            <Field label="Or paste a token">
                <Input v-model="state.token" placeholder="paste / type token" />
            </Field>

            <div class="mt-3 flex gap-2">
                <Button :disabled="state.pending" @click="lookup">Validate</Button>
                <Button variant="ghost" @click="scanAnother">Scan another</Button>
            </div>

            <p v-if="state.flash" class="mt-3 text-sm text-orange-600">{{ state.flash }}</p>
        </CardPanel>

        <CardPanel v-if="state.result" class="mt-4">
            <div class="flex items-center justify-between">
                <Heading>Voucher {{ state.result.voucher.id }}</Heading>
                <Badge :variant="statusVariant(state.result.status)">{{ state.result.status }}</Badge>
            </div>

            <p v-if="state.result.status_banner" class="mt-2 text-sm">{{ state.result.status_banner }}</p>

            <dl v-if="state.result.reservation" class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <dt class="font-medium">Reference</dt>
                <dd>{{ state.result.reservation.reference }}</dd>
                <dt class="font-medium">Guest</dt>
                <dd>
                    {{ state.result.reservation.customer?.data?.first_name }}
                    {{ state.result.reservation.customer?.data?.last_name }}
                </dd>
                <dt class="font-medium">Dates</dt>
                <dd>{{ state.result.reservation.date_start }} – {{ state.result.reservation.date_end }}</dd>
                <dt class="font-medium">Quantity</dt>
                <dd>{{ state.result.reservation.quantity }}</dd>
            </dl>

            <div class="mt-4 flex gap-2">
                <Button v-if="state.result.status === 'issued'" :disabled="state.pending" @click="markUsed">
                    Mark as used
                </Button>
                <Button v-if="state.result.status === 'used'" variant="ghost" :disabled="state.pending" @click="unMark">
                    Un-mark
                </Button>
                <Button variant="ghost" @click="scanAnother">Scan another</Button>
            </div>
        </CardPanel>
    </div>
</template>
