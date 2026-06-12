<?php

namespace Reach\StatamicResrvVouchers\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use MarcoRieser\Livewire\ServiceProvider as StatamicLivewireServiceProvider;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrv\Models\Customer;
use Reach\StatamicResrv\Models\Reservation;
use Reach\StatamicResrv\StatamicResrvServiceProvider;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\StatamicResrvVouchersServiceProvider;
use Statamic\Addons\Manifest;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Support\Str;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    use RefreshDatabase;

    protected string $addonServiceProvider = StatamicResrvVouchersServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutExceptionHandling();

        Site::setSites([
            'en' => [
                'name' => 'English',
                'url' => 'http://localhost/',
                'locale' => 'en_US',
                'lang' => 'en',
            ],
        ]);
    }

    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [
            LivewireServiceProvider::class,
            StatamicLivewireServiceProvider::class,
            StatamicResrvServiceProvider::class,
        ]);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $manifest = $app->make(Manifest::class);
        $manifest->manifest = array_merge($manifest->manifest, [
            'reachweb/statamic-resrv' => [
                'id' => 'reachweb/statamic-resrv',
                'slug' => 'statamic-resrv',
                'version' => 'dev-main',
                'namespace' => 'Reach\\StatamicResrv',
                'autoload' => 'src/',
                'provider' => StatamicResrvServiceProvider::class,
            ],
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('statamic.editions.pro', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate');
    }

    protected function signInAdmin()
    {
        $user = User::make();
        $user->id(1)->email('test@test.com')->makeSuper();
        $this->be($user);

        return $user;
    }

    protected function signInUserWithoutPermissions()
    {
        $user = User::make();
        $user->id(2)->email('plain@test.com');
        $this->be($user);

        return $user;
    }

    protected function signInWithPermissions(array $permissions)
    {
        $role = Role::make('role_'.Str::random(8))->addPermission($permissions)->save();

        $user = User::make()
            ->id('user-'.Str::random(8))
            ->email(Str::random(8).'@test.com')
            ->assignRole($role);

        $this->be($user);

        return $user;
    }

    protected function ensureCollectionExists(string $handle, string $route = '/{slug}'): \Statamic\Contracts\Entries\Collection
    {
        if ($existing = Collection::findByHandle($handle)) {
            return $existing;
        }

        $collection = Collection::make($handle)->routes($route);
        $collection->save();

        return $collection;
    }

    protected function makeStatamicItem(array $data = [], string $collection = 'pages'): \Statamic\Contracts\Entries\Entry
    {
        $this->ensureCollectionExists($collection);

        $slug = $data['slug'] ?? Str::random(6);

        Entry::make()
            ->collection($collection)
            ->slug($slug)
            ->data(array_merge(['title' => 'Test Item'], $data))
            ->save();

        return Entry::query()->where('slug', $slug)->first();
    }

    protected function makeConfirmedReservation(array $overrides = []): Reservation
    {
        $entry = $overrides['entry'] ?? $this->makeStatamicItem([], $overrides['collection'] ?? 'pages');

        \Reach\StatamicResrv\Models\Entry::firstOrCreate(
            ['item_id' => $entry->id()],
            [
                'title' => $entry->get('title') ?? 'Test Item',
                'enabled' => true,
                'collection' => $entry->collection()->handle(),
                'handle' => $entry->collection()->handle(),
            ]
        );

        $customer = $overrides['customer'] ?? Customer::create([
            'email' => 'guest@example.com',
            'data' => ['first_name' => 'Test', 'last_name' => 'Guest'],
        ]);

        $attributes = array_merge([
            'status' => 'confirmed',
            'reference' => Str::upper(Str::random(6)),
            'item_id' => $entry->id(),
            'date_start' => now()->addDay(),
            'date_end' => now()->addDays(3),
            'price' => 200,
            'payment' => 50,
            'payment_id' => 'test',
            'customer_id' => $customer->id,
        ], array_diff_key($overrides, array_flip(['entry', 'collection', 'customer'])));

        $reservation = new Reservation;
        foreach ($attributes as $key => $value) {
            $reservation->{$key} = $value;
        }
        $reservation->save();

        return $reservation->fresh();
    }

    protected function makeIssuedVoucher(): Voucher
    {
        $reservation = $this->makeConfirmedReservation();
        ReservationConfirmed::dispatch($reservation);

        return Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();
    }
}
