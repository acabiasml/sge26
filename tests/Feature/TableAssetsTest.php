<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Blade;
use Rappasoft\LaravelLivewireTables\Mechanisms\RappasoftFrontendAssets;
use Tests\TestCase;

class TableAssetsTest extends TestCase
{
    public function test_table_asset_routes_serve_the_files_from_the_installed_package(): void
    {
        $prefix = rtrim(config('livewire-tables.script_base_path'), '/');
        foreach ([
            'core.min.css' => 'css/laravel-livewire-tables.min.css',
            'core.min.js' => 'js/laravel-livewire-tables.min.js',
            'thirdparty.css' => 'css/laravel-livewire-tables-thirdparty.min.css',
            'thirdparty.min.js' => 'js/laravel-livewire-tables-thirdparty.min.js',
        ] as $route => $file) {
            $response = $this->get($prefix.'/'.$route)->assertOk();
            $served = $response->baseResponse->getFile()->getPathname();
            $expected = base_path('vendor/rappasoft/laravel-livewire-tables/resources/'.$file);
            $this->assertSame(hash_file('sha256', $expected), hash_file('sha256', $served));
        }

        $this->assertStringContainsString('.lwt-toolbar', file_get_contents(base_path('vendor/rappasoft/laravel-livewire-tables/resources/css/laravel-livewire-tables.min.css')));
    }

    public function test_table_asset_tags_and_routes_support_the_sge_subdirectory(): void
    {
        config(['livewire-tables.script_base_path' => '/sge/rappasoft/laravel-livewire-tables']);
        (new RappasoftFrontendAssets)->boot();

        $html = Blade::render('@rappasoftTableStyles');
        $this->assertStringContainsString('href="/sge/rappasoft/laravel-livewire-tables/core.min.css"', $html);
        $this->get('/sge/rappasoft/laravel-livewire-tables/core.min.css')->assertOk();
        $this->get('/sge/rappasoft/laravel-livewire-tables/core.min.js')->assertOk();

        config(['app.dir' => 'sge']);
        (new AppServiceProvider($this->app))->boot();
        $this->assertStringContainsString('src="/sge/livewire/livewire.js?', Blade::render('@livewireScripts'));
        $this->get('/sge/livewire/livewire.js')->assertOk();
    }
}
