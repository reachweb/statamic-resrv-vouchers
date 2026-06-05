<script setup>
import { Head } from '@statamic/cms/inertia';
import { Header, Heading, Listing } from '@statamic/cms/ui';

const props = defineProps({
    listUrl: { type: String, required: true },
    resendUrl: { type: String, required: true },
    statuses: { type: Array, default: () => [] },
    defaultPerPage: { type: Number, default: 25 },
});

const columns = [
    { handle: 'id', label: 'ID' },
    { handle: 'status', label: 'Status' },
    { handle: 'reservation.reference', label: 'Reservation' },
    { handle: 'expires_at', label: 'Expires' },
    { handle: 'used_at', label: 'Used at' },
    { handle: 'created_at', label: 'Issued at' },
];

const filters = [
    { handle: 'status', label: 'Status', type: 'select', options: props.statuses },
];
</script>

<template>
    <div>
        <Head title="Vouchers" />
        <Header>
            <Heading>Vouchers</Heading>
        </Header>
        <Listing
            :url="props.listUrl"
            :columns="columns"
            :filters="filters"
            sort-column="created_at"
            sort-direction="desc"
            preferences-prefix="resrv-vouchers.vouchers"
            push-query
        />
    </div>
</template>
