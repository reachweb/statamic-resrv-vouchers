import VouchersIndex from './pages/Vouchers/Index.vue';
import VouchersScan from './pages/Vouchers/Scan.vue';
import VouchersWidget from './components/widgets/Vouchers.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('resrv-vouchers::Vouchers/Index', VouchersIndex);
    Statamic.$inertia.register('resrv-vouchers::Vouchers/Scan', VouchersScan);
    Statamic.component('resrv-vouchers-widget', VouchersWidget);
});
