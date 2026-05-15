<?php

namespace Reach\StatamicResrvVouchers\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\LivewireServiceProvider;
use MarcoRieser\Livewire\ServiceProvider as StatamicLivewireServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Reach\StatamicResrv\Models\Customer;
use Reach\StatamicResrv\Models\Reservation;
use Reach\StatamicResrv\StatamicResrvServiceProvider;
use Reach\StatamicResrvVouchers\StatamicResrvVouchersServiceProvider;
use Statamic\Extend\Manifest;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Providers\StatamicServiceProvider;
use Statamic\Stache\Stores\UsersStore;
use Statamic\Statamic;
use Statamic\Support\Str;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
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
        return [
            StatamicServiceProvider::class,
            LivewireServiceProvider::class,
            StatamicLivewireServiceProvider::class,
            StatamicResrvServiceProvider::class,
            StatamicResrvVouchersServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return ['Statamic' => Statamic::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->make(Manifest::class)->manifest = [
            'reach/resrv' => [
                'id' => 'reach/resrv',
                'namespace' => 'Reach\\StatamicResrv',
            ],
            'reach/resrv-vouchers' => [
                'id' => 'reach/resrv-vouchers',
                'namespace' => 'Reach\\StatamicResrvVouchers',
            ],
        ];
    }

    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);

        $statamicConfigs = [
            'assets', 'cp', 'forms', 'routes', 'static_caching',
            'stache', 'system', 'users',
        ];

        foreach ($statamicConfigs as $config) {
            $app['config']->set("statamic.$config", require __DIR__."/../vendor/statamic/cms/config/{$config}.php");
        }

        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('mail.default', 'array');

        $app['config']->set('statamic.users.repository', 'file');
        $app['config']->set('statamic.stache.watcher', false);
        $app['config']->set('statamic.stache.stores.users', [
            'class' => UsersStore::class,
            'directory' => __DIR__.'/__fixtures__/users',
        ]);

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
}
