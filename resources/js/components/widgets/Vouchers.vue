<script setup>
import { Button, Widget } from '@statamic/cms/ui';

defineProps({
    title: { type: String, default: 'Vouchers' },
    scanUrl: { type: String, required: true },
    listUrl: { type: String, required: true },
});
</script>

<template>
    <Widget :title="title" icon="media-ticket" :href="listUrl">
        <div class="flex h-full flex-col justify-between gap-4 px-4.5 py-4">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Scan a guest voucher at the door, or browse every issued code.') }}
            </p>

            <div class="flex flex-wrap gap-2">
                <Button :href="scanUrl" variant="primary" icon="media-image-camera">
                    {{ __('Scan a voucher') }}
                </Button>
                <Button :href="listUrl" icon="layout-list">
                    {{ __('View all vouchers') }}
                </Button>
            </div>
        </div>
    </Widget>
</template>
