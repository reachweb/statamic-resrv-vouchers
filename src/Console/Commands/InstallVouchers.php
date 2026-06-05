<?php

namespace Reach\StatamicResrvVouchers\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;

class InstallVouchers extends Command
{
    use RunsInPlease;

    protected $signature = 'resrv-vouchers:install';

    protected $description = 'Install Statamic Resrv Vouchers';

    public function handle(): int
    {
        $this->publishConfig();

        $this->runMigrations();

        if ($this->confirm('Publish the email templates? (only needed if you wish to edit them)')) {
            $this->publishEmailTemplates();
        }

        if ($this->confirm('Publish the language files? (only needed if you wish to edit them)')) {
            $this->publishLanguageFiles();
        }

        $this->newLine();
        $this->info('Installation finished.');
        $this->line('Next steps:');
        $this->line('  • Add the collection handles you want vouchers issued for to config/resrv-vouchers.php → enabled_collections.');
        $this->line('  • Ensure reachweb/statamic-resrv ships the BuildingReservationEmail event (any release after the Vouchers integration).');
        $this->line('  • Add @include(\'statamic-resrv-vouchers::email.vouchers.partials.qr\') to your published Resrv confirmed.blade.php if you want the inline QR in the email body — the PDF attachment is added regardless.');

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $this->info('Publishing configuration file');

        $this->callSilent('vendor:publish', [
            '--tag' => 'resrv-vouchers-config',
        ]);
    }

    protected function runMigrations(): void
    {
        $this->info('Running migrations');

        $this->call('migrate');
    }

    protected function publishEmailTemplates(): void
    {
        $this->info('Publishing email templates');

        $this->callSilent('vendor:publish', [
            '--tag' => 'resrv-vouchers-emails',
        ]);
    }

    protected function publishLanguageFiles(): void
    {
        $this->info('Publishing language files');

        $this->callSilent('vendor:publish', [
            '--tag' => 'resrv-vouchers-language',
        ]);
    }
}
