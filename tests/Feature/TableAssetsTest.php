<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\URL;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
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
        URL::forceRootUrl('https://ctjj.org/sge');
        URL::forceScheme('https');
        $styles = view('layouts.partials.table-styles')->render();
        $scripts = view('layouts.partials.table-scripts')->render();
        $this->assertStringContainsString('href="https://ctjj.org/sge/rappasoft/laravel-livewire-tables/core.min.css"', $styles);
        $this->assertStringContainsString('src="https://ctjj.org/sge/rappasoft/laravel-livewire-tables/core.min.js"', $scripts);
        $this->assertStringNotContainsString('/sge/sge/', $styles.$scripts);
    }

    public function test_table_css_loads_when_the_front_controller_is_mounted_under_sge(): void
    {
        $routes = new RouteCollection;
        foreach ($this->app['router']->getRoutes() as $route) {
            if (! str_contains($route->uri(), 'rappasoft/laravel-livewire-tables')) {
                $routes->add($route);
            }
        }
        $this->app['router']->setRoutes($routes);
        (new RappasoftFrontendAssets)->boot();
        foreach ([
            '/rappasoft/laravel-livewire-tables/core.min.css',
            '/rappasoft/laravel-livewire-tables/core.min.js',
            '/rappasoft/laravel-livewire-tables/thirdparty.css',
            '/rappasoft/laravel-livewire-tables/thirdparty.min.js',
            '/'.app(FrontendAssets::class)->javaScriptRoute->uri(),
        ] as $path) {
            $request = Request::create('https://ctjj.org/sge'.$path, 'GET', [], [], [], [
                'SCRIPT_NAME' => '/sge/index.php',
                'PHP_SELF' => '/sge/index.php',
                'SCRIPT_FILENAME' => base_path('index.php'),
            ]);
            $this->assertSame('/sge', $request->getBaseUrl());
            $response = $this->app->make(Kernel::class)->handle($request);
            $this->assertSame(200, $response->getStatusCode(), $path);
        }
    }
}
