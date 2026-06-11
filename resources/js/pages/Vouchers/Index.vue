<script setup>
import { Head } from '@statamic/cms/inertia';
import { Badge, Header, Listing } from '@statamic/cms/ui';

const props = defineProps({
    filters: { type: Array, default: () => [] },
    listUrl: { type: String, required: true },
});

const statusColor = (status) => ({
    issued: 'green',
    used: 'amber',
    invalidated: 'red',
    expired: 'red',
}[status] ?? 'default');
</script>

<template>
    <div class="max-w-page mx-auto">
        <Head :title="__('Vouchers')" />

        <Header :title="__('Vouchers')" />

        <Listing
            :url="props.listUrl"
            :filters="props.filters"
            sort-column="created_at"
            sort-direction="desc"
            preferences-prefix="resrv-vouchers.vouchers"
            push-query
        >
            <template #cell-status="{ row: voucher }">
                <Badge :color="statusColor(voucher.status)" :text="voucher.status.toUpperCase()" />
            </template>

            <template #cell-customer="{ row: voucher }">
                <a v-if="voucher.customer?.email" :href="`mailto:${voucher.customer.email}`">
                    {{ voucher.customer.email }}
                </a>
            </template>
        </Listing>
    </div>
</template>
