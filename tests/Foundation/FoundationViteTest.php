<?php

namespace Illuminate\Tests\Foundation;

use Illuminate\Foundation\Vite;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Illuminate\Support\Str;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class FoundationViteTest extends TestCase
{
    protected function setUp(): void
    {
        app()->instance('url', tap(
            m::mock(UrlGenerator::class),
            fn ($url) => $url
                ->shouldReceive('asset')
                ->andReturnUsing(fn ($value) => "https://example.com{$value}")
        ));

        app()->singleton(Vite::class);
        Facade::setFacadeApplication(app());
    }

    protected function tearDown(): void
    {
        $this->cleanViteManifest();
        $this->cleanViteHotFile();
        Facade::clearResolvedInstances();
        m::close();
    }

    public function testViteWithJsOnly()
    {
        $this->makeViteManifest();

        $result = app(Vite::class)('resources/js/app.js');

        $this->assertSame('<script type="module" src="https://example.com/build/assets/app.versioned.js"></script>', $result->toHtml());
    }

    public function testViteWithCssAndJs()
    {
        $this->makeViteManifest();

        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame(
            '<link rel="stylesheet" href="https://example.com/build/assets/app.versioned.css" />'
            .'<script type="module" src="https://example.com/build/assets/app.versioned.js"></script>',
            $result->toHtml()
        );
    }

    public function testViteWithCssImport()
    {
        $this->makeViteManifest();

        $result = app(Vite::class)('resources/js/app-with-css-import.js');

        $this->assertSame(
            '<link rel="stylesheet" href="https://example.com/build/assets/imported-css.versioned.css" />'
            .'<script type="module" src="https://example.com/build/assets/app-with-css-import.versioned.js"></script>',
            $result->toHtml()
        );
    }

    public function testViteWithSharedCssImport()
    {
        $this->makeViteManifest();

        $result = app(Vite::class)(['resources/js/app-with-shared-css.js']);

        $this->assertSame(
            '<link rel="stylesheet" href="https://example.com/build/assets/shared-css.versioned.css" />'
            .'<script type="module" src="https://example.com/build/assets/app-with-shared-css.versioned.js"></script>',
            $result->toHtml()
        );
    }

    public function testViteHotModuleReplacementWithJsOnly()
    {
        $this->makeViteHotFile();

        $result = app(Vite::class)('resources/js/app.js');

        $this->assertSame(
            '<script type="module" src="http://localhost:3000/@vite/client"></script>'
            .'<script type="module" src="http://localhost:3000/resources/js/app.js"></script>',
            $result->toHtml()
        );
    }

    public function testViteHotModuleReplacementWithJsAndCss()
    {
        $this->makeViteHotFile();

        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame(
            '<script type="module" src="http://localhost:3000/@vite/client"></script>'
            .'<link rel="stylesheet" href="http://localhost:3000/resources/css/app.css" />'
            .'<script type="module" src="http://localhost:3000/resources/js/app.js"></script>',
            $result->toHtml()
        );
    }

    public function testItCanGenerateCspNonceWithHotFile()
    {
        Str::createRandomStringsUsing(fn ($length) => "random-string-with-length:{$length}");
        $this->makeViteHotFile();

        $nonce = ViteFacade::useCspNonce();
        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame('random-string-with-length:40', $nonce);
        $this->assertSame('random-string-with-length:40', ViteFacade::cspNonce());
        $this->assertSame(
            '<script type="module" src="http://localhost:3000/@vite/client" nonce="random-string-with-length:40"></script>'
            .'<link rel="stylesheet" href="http://localhost:3000/resources/css/app.css" nonce="random-string-with-length:40" />'
            .'<script type="module" src="http://localhost:3000/resources/js/app.js" nonce="random-string-with-length:40"></script>',
            $result->toHtml()
        );

        Str::createRandomStringsNormally();
    }

    public function testItCanGenerateCspNonceWithManifest()
    {
        Str::createRandomStringsUsing(fn ($length) => "random-string-with-length:{$length}");
        $this->makeViteManifest();

        $nonce = ViteFacade::useCspNonce();
        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame('random-string-with-length:40', $nonce);
        $this->assertSame('random-string-with-length:40', ViteFacade::cspNonce());
        $this->assertSame(
            '<link rel="stylesheet" href="https://example.com/build/assets/app.versioned.css" nonce="random-string-with-length:40" />'
            .'<script type="module" src="https://example.com/build/assets/app.versioned.js" nonce="random-string-with-length:40"></script>',
            $result->toHtml()
        );

        Str::createRandomStringsNormally();
    }

    public function testItCanSpecifyCspNonceWithHotFile()
    {
        $this->makeViteHotFile();

        $nonce = ViteFacade::useCspNonce('expected-nonce');
        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame('expected-nonce', $nonce);
        $this->assertSame('expected-nonce', ViteFacade::cspNonce());
        $this->assertSame(
            '<script type="module" src="http://localhost:3000/@vite/client" nonce="expected-nonce"></script>'
            .'<link rel="stylesheet" href="http://localhost:3000/resources/css/app.css" nonce="expected-nonce" />'
            .'<script type="module" src="http://localhost:3000/resources/js/app.js" nonce="expected-nonce"></script>',
            $result->toHtml()
        );
    }

    public function testItCanSpecifyCspNonceWithManifest()
    {
        $this->makeViteManifest();

        $nonce = ViteFacade::useCspNonce('expected-nonce');
        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame('expected-nonce', $nonce);
        $this->assertSame('expected-nonce', ViteFacade::cspNonce());
        $this->assertSame(
            '<link rel="stylesheet" href="https://example.com/build/assets/app.versioned.css" nonce="expected-nonce" />'
            .'<script type="module" src="https://example.com/build/assets/app.versioned.js" nonce="expected-nonce"></script>',
            $result->toHtml()
        );
    }

    public function testItCanInjectIntegrityWhenPresentInManifest()
    {
        $buildDir = Str::random();
        $this->makeViteManifest([
            'resources/js/app.js' => [
                'file' => 'assets/app.versioned.js',
                'integrity' => 'expected-app.js-integrity',
            ],
            'resources/css/app.css' => [
                'file' => 'assets/app.versioned.css',
                'integrity' => 'expected-app.css-integrity',
            ],
        ], $buildDir);

        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js'], $buildDir);

        $this->assertSame(
            '<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" integrity="expected-app.css-integrity" />'
            .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js" integrity="expected-app.js-integrity"></script>',
            $result->toHtml()
        );

        unlink(public_path("{$buildDir}/manifest.json"));
        rmdir(public_path($buildDir));
    }

    public function testItCanInjectIntegrityWhenPresentInManifestForImportedCss()
    {
        $buildDir = Str::random();
        $this->makeViteManifest([
            'resources/js/app.js' => [
                'file' => 'assets/app.versioned.js',
                'imports' => [
                    '_import.versioned.js',
                ],
                'integrity' => 'expected-app.js-integrity',
            ],
            '_import.versioned.js' => [
                'file' => 'assets/import.versioned.js',
                'css' => [
                    'assets/imported-css.versioned.css',
                ],
                'integrity' => 'expected-import.js-integrity',
            ],
            'imported-css.css' => [
                'file' => 'assets/imported-css.versioned.css',
                'integrity' => 'expected-imported-css.css-integrity',
            ],
        ], $buildDir);

        $result = app(Vite::class)('resources/js/app.js', $buildDir);

        $this->assertSame(
            '<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/imported-css.versioned.css" integrity="expected-imported-css.css-integrity" />'
            .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js" integrity="expected-app.js-integrity"></script>',
            $result->toHtml()
        );

        unlink(public_path("{$buildDir}/manifest.json"));
        rmdir(public_path($buildDir));
    }

    public function testItCanSpecifyIntegrityKey()
    {
        $buildDir = Str::random();
        $this->makeViteManifest([
            'resources/js/app.js' => [
                'file' => 'assets/app.versioned.js',
                'different-integrity-key' => 'expected-app.js-integrity',
            ],
            'resources/css/app.css' => [
                'file' => 'assets/app.versioned.css',
                'different-integrity-key' => 'expected-app.css-integrity',
            ],
        ], $buildDir);
        ViteFacade::useIntegrityKey('different-integrity-key');

        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js'], $buildDir);

        $this->assertSame(
            '<link rel="stylesheet" href="https://example.com/'.$buildDir.'/assets/app.versioned.css" integrity="expected-app.css-integrity" />'
            .'<script type="module" src="https://example.com/'.$buildDir.'/assets/app.versioned.js" integrity="expected-app.js-integrity"></script>',
            $result->toHtml()
        );

        unlink(public_path("{$buildDir}/manifest.json"));
        rmdir(public_path($buildDir));
    }

    public function testItCanSpecifyArbitraryAttributesForScriptTagsWhenBuilt()
    {
        $this->makeViteManifest();
        ViteFacade::useScriptTagAttributes([
            'general' => 'attribute',
        ]);
        ViteFacade::useScriptTagAttributes(function ($src, $url, $chunk, $manifest) {
            $this->assertSame('resources/js/app.js', $src);
            $this->assertSame('https://example.com/build/assets/app.versioned.js', $url);
            $this->assertSame(['file' => 'assets/app.versioned.js'], $chunk);
            $this->assertSame([
                'resources/js/app.js' => [
                    'file' => 'assets/app.versioned.js',
                ],
                'resources/js/app-with-css-import.js' => [
                    'file' => 'assets/app-with-css-import.versioned.js',
                    'css' => [
                        'assets/imported-css.versioned.css',
                    ],
                ],
                'resources/css/imported-css.css' => [
                    'file' => 'assets/imported-css.versioned.css',
                ],
                'resources/js/app-with-shared-css.js' => [
                    'file' => 'assets/app-with-shared-css.versioned.js',
                    'imports' => [
                        '_someFile.js',
                    ],
                ],
                'resources/css/app.css' => [
                    'file' => 'assets/app.versioned.css',
                ],
                '_someFile.js' => [
                    'css' => [
                        'assets/shared-css.versioned.css',
                    ],
                ],
                'resources/css/shared-css' => [
                    'file' => 'assets/shared-css.versioned.css',
                ],
            ], $manifest);

            return [
                'crossorigin',
                'data-persistent-across-pages' => 'YES',
                'remove-me' => false,
                'keep-me' => true,
                'null' => null,
                'empty-string' => '',
                'zero' => 0,
            ];
        });

        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame(
            '<link rel="stylesheet" href="https://example.com/build/assets/app.versioned.css" />'
            .'<script type="module" src="https://example.com/build/assets/app.versioned.js" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me empty-string="" zero="0"></script>',
            $result->toHtml()
        );
    }

    public function testItCanSpecifyArbitraryAttributesForStylesheetTagsWhenBuild()
    {
        $this->makeViteManifest();
        ViteFacade::useStyleTagAttributes([
            'general' => 'attribute',
        ]);
        ViteFacade::useStyleTagAttributes(function ($src, $url, $chunk, $manifest) {
            $this->assertSame('resources/css/app.css', $src);
            $this->assertSame('https://example.com/build/assets/app.versioned.css', $url);
            $this->assertSame(['file' => 'assets/app.versioned.css'], $chunk);
            $this->assertSame([
                'resources/js/app.js' => [
                    'file' => 'assets/app.versioned.js',
                ],
                'resources/js/app-with-css-import.js' => [
                    'file' => 'assets/app-with-css-import.versioned.js',
                    'css' => [
                        'assets/imported-css.versioned.css',
                    ],
                ],
                'resources/css/imported-css.css' => [
                    'file' => 'assets/imported-css.versioned.css',
                ],
                'resources/js/app-with-shared-css.js' => [
                    'file' => 'assets/app-with-shared-css.versioned.js',
                    'imports' => [
                        '_someFile.js',
                    ],
                ],
                'resources/css/app.css' => [
                    'file' => 'assets/app.versioned.css',
                ],
                '_someFile.js' => [
                    'css' => [
                        'assets/shared-css.versioned.css',
                    ],
                ],
                'resources/css/shared-css' => [
                    'file' => 'assets/shared-css.versioned.css',
                ],
            ], $manifest);

            return [
                'crossorigin',
                'data-persistent-across-pages' => 'YES',
                'remove-me' => false,
                'keep-me' => true,
            ];
        });

        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame(
            '<link rel="stylesheet" href="https://example.com/build/assets/app.versioned.css" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me />'
            .'<script type="module" src="https://example.com/build/assets/app.versioned.js"></script>',
            $result->toHtml()
        );
    }

    public function testItCanSpecifyArbitraryAttributesForScriptTagsWhenHotModuleReloading()
    {
        $this->makeViteHotFile();
        ViteFacade::useScriptTagAttributes([
            'general' => 'attribute',
        ]);
        $expectedArguments = [
            ['src' => '@vite/client', 'url' => 'http://localhost:3000/@vite/client'],
            ['src' => 'resources/js/app.js', 'url' => 'http://localhost:3000/resources/js/app.js'],
        ];
        ViteFacade::useScriptTagAttributes(function ($src, $url, $chunk, $manifest) use (&$expectedArguments) {
            $args = array_shift($expectedArguments);

            $this->assertSame($args['src'], $src);
            $this->assertSame($args['url'], $url);
            $this->assertNull($chunk);
            $this->assertNull($manifest);

            return [
                'crossorigin',
                'data-persistent-across-pages' => 'YES',
                'remove-me' => false,
                'keep-me' => true,
            ];
        });

        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame(
            '<script type="module" src="http://localhost:3000/@vite/client" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me></script>'
            .'<link rel="stylesheet" href="http://localhost:3000/resources/css/app.css" />'
            .'<script type="module" src="http://localhost:3000/resources/js/app.js" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me></script>',
            $result->toHtml()
        );
    }

    public function testItCanSpecifyArbitraryAttributesForStylesheetTagsWhenHotModuleReloading()
    {
        $this->makeViteHotFile();
        ViteFacade::useStyleTagAttributes([
            'general' => 'attribute',
        ]);
        ViteFacade::useStyleTagAttributes(function ($src, $url, $chunk, $manifest) {
            $this->assertSame('resources/css/app.css', $src);
            $this->assertSame('http://localhost:3000/resources/css/app.css', $url);
            $this->assertNull($chunk);
            $this->assertNull($manifest);

            return [
                'crossorigin',
                'data-persistent-across-pages' => 'YES',
                'remove-me' => false,
                'keep-me' => true,
            ];
        });

        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame(
            '<script type="module" src="http://localhost:3000/@vite/client"></script>'
            .'<link rel="stylesheet" href="http://localhost:3000/resources/css/app.css" general="attribute" crossorigin data-persistent-across-pages="YES" keep-me />'
            .'<script type="module" src="http://localhost:3000/resources/js/app.js"></script>',
            $result->toHtml()
        );
    }

    public function testItCanOverrideAllAttributes()
    {
        $this->makeViteManifest();
        ViteFacade::useStyleTagAttributes([
            'rel' => 'expected-rel',
            'href' => 'expected-href',
        ]);
        ViteFacade::useScriptTagAttributes([
            'type' => 'expected-type',
            'src' => 'expected-src',
        ]);

        $result = app(Vite::class)(['resources/css/app.css', 'resources/js/app.js']);

        $this->assertSame(
            '<link rel="expected-rel" href="expected-href" />'
            .'<script type="expected-type" src="expected-src"></script>',
            $result->toHtml()
        );
    }

    protected function makeViteManifest($contents = null, $path = 'build')
    {
        app()->singleton('path.public', fn () => __DIR__);

        if (! file_exists(public_path($path))) {
            mkdir(public_path($path));
        }

        $manifest = json_encode($contents ?? [
            'resources/js/app.js' => [
                'file' => 'assets/app.versioned.js',
            ],
            'resources/js/app-with-css-import.js' => [
                'file' => 'assets/app-with-css-import.versioned.js',
                'css' => [
                    'assets/imported-css.versioned.css',
                ],
            ],
            'resources/css/imported-css.css' => [
                'file' => 'assets/imported-css.versioned.css',
            ],
            'resources/js/app-with-shared-css.js' => [
                'file' => 'assets/app-with-shared-css.versioned.js',
                'imports' => [
                    '_someFile.js',
                ],
            ],
            'resources/css/app.css' => [
                'file' => 'assets/app.versioned.css',
            ],
            '_someFile.js' => [
                'css' => [
                    'assets/shared-css.versioned.css',
                ],
            ],
            'resources/css/shared-css' => [
                'file' => 'assets/shared-css.versioned.css',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents(public_path("{$path}/manifest.json"), $manifest);
    }

    protected function cleanViteManifest()
    {
        if (file_exists(public_path('build/manifest.json'))) {
            unlink(public_path('build/manifest.json'));
        }

        if (file_exists(public_path('build'))) {
            rmdir(public_path('build'));
        }
    }

    protected function makeViteHotFile()
    {
        app()->singleton('path.public', fn () => __DIR__);

        file_put_contents(public_path('hot'), 'http://localhost:3000');
    }

    protected function cleanViteHotFile()
    {
        if (file_exists(public_path('hot'))) {
            unlink(public_path('hot'));
        }
    }
}
