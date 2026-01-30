<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature;

use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\DataPortability\ExportManager;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\JsonFormatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;
use GaiaTools\FulcrumSettings\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DataPortabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('fulcrum.portability.routes.enabled', true);
        $app['config']->set('fulcrum.portability.routes.middleware', []);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->app['config']->set('fulcrum.portability.export_ability', 'exportFulcrumSettings');
        $this->app['config']->set('fulcrum.portability.import_ability', 'importFulcrumSettings');

        $this->app['auth']->resolveUsersUsing(fn () => new \Illuminate\Foundation\Auth\User);

        Gate::define('exportFulcrumSettings', fn () => true);
        Gate::define('importFulcrumSettings', fn () => true);
    }

    public function test_api_export_endpoint()
    {
        Setting::create([
            'key' => 'api_setting',
            'type' => 'string',
        ]);

        $response = $this->postJson(route('fulcrum.portability.export', ['format' => 'json']));

        $response->assertStatus(200);
    }

    public function test_show_export_form()
    {
        $response = $this->get(route('fulcrum.portability.export.create'));

        $response->assertStatus(200);
        $response->assertViewIs('fulcrum::export.form');
        $response->assertViewHas('supportedFormats', ['json', 'csv', 'xml']);
    }

    public function test_show_import_form()
    {
        $response = $this->get(route('fulcrum.portability.import.create'));

        $response->assertStatus(200);
        $response->assertViewIs('fulcrum::import.form');
        $response->assertViewHas('supportedFormats', ['json', 'csv', 'xml']);
    }

    public function test_api_import_endpoint()
    {
        $data = [['key' => 'api_imported', 'type' => 'string']];
        $file = UploadedFile::fake()->createWithContent('import.json', json_encode($data));

        $response = $this->postJson(route('fulcrum.portability.import'), [
            'file' => $file,
            'format' => 'json',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Settings imported successfully']);
        $this->assertDatabaseHas('settings', ['key' => 'api_imported']);
    }

    public function test_web_import_endpoint_redirects_back_on_success()
    {
        $data = [['key' => 'web_imported', 'type' => 'string']];
        $file = UploadedFile::fake()->createWithContent('import.json', json_encode($data));

        $response = $this->from('/previous-page')->post(route('fulcrum.portability.import'), [
            'file' => $file,
            'format' => 'json',
        ]);

        $response->assertRedirect('/previous-page');
        $response->assertSessionHas('success', 'Settings imported successfully');
        $this->assertDatabaseHas('settings', ['key' => 'web_imported']);
    }

    public function test_web_import_endpoint_redirects_back_on_error()
    {
        // No file provided
        $response = $this->from('/previous-page')->post(route('fulcrum.portability.import'), []);

        $response->assertRedirect('/previous-page');
        $response->assertSessionHasErrors();
    }

    public function test_web_export_unauthorized_redirects_back()
    {
        Gate::define('exportFulcrumSettings', fn () => false);

        $response = $this->from('/previous-page')->post(route('fulcrum.portability.export'));

        $response->assertRedirect('/previous-page');
        $response->assertSessionHasErrors();
    }

    public function test_api_export_unauthorized_throws_exception()
    {
        Gate::define('exportFulcrumSettings', fn () => false);

        $response = $this->postJson(route('fulcrum.portability.export'));

        $response->assertStatus(403);
    }

    public function test_api_import_unauthorized_throws_exception()
    {
        Gate::define('importFulcrumSettings', fn () => false);

        $response = $this->postJson(route('fulcrum.portability.import'));

        $response->assertStatus(403);
    }

    public function test_web_import_unauthorized_redirects_back()
    {
        Gate::define('importFulcrumSettings', fn () => false);

        $response = $this->from('/previous-page')->post(route('fulcrum.portability.import'));

        $response->assertRedirect('/previous-page');
        $response->assertSessionHasErrors();
    }

    public function test_export_response_download()
    {
        Setting::create([
            'key' => 'download_test',
            'type' => 'string',
        ]);

        $response = $this->post(route('fulcrum.portability.export', ['format' => 'json']));

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('Content-Disposition'));
        $this->assertStringContainsString('attachment; filename=', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.json', $response->headers->get('Content-Disposition'));
    }

    public function test_export_response_failure_redirects_back()
    {
        // We need to bypass the pipeline or force a failure that still reaches the response
        // ExportResponse is called with exportData from the pipeline.

        $response = new \GaiaTools\FulcrumSettings\Http\Responses\ExportResponse(['file_path' => null]);
        $result = $response->toResponse(request());

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $result);
        $this->assertTrue(session()->has('errors'));
    }

    public function test_can_export_settings_to_json()
    {
        Setting::create([
            'key' => 'site_name',
            'type' => 'string',
            'description' => 'Name of the site',
        ])->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => 1,
            'value' => 'My App',
        ]);

        $manager = new ExportManager;
        $path = $manager->export(new JsonFormatter, [
            'filename' => 'export.json',
        ]);

        $this->assertStringContainsString('export.json', $path);
        Storage::disk('local')->assertExists('export.json');

        $content = Storage::disk('local')->get('export.json');
        $data = json_decode($content, true);

        $this->assertCount(1, $data);
        $this->assertEquals('site_name', $data[0]['key']);
        $this->assertEquals('My App', $data[0]['default_value']);
    }

    public function test_can_import_settings_from_json()
    {
        $data = [
            [
                'key' => 'site_name',
                'type' => 'string',
                'description' => 'Name of the site',
                'default_value' => 'My App',
            ],
        ];
        Storage::disk('local')->put('import.json', json_encode($data));
        $fullPath = Storage::disk('local')->path('import.json');

        $manager = new ImportManager;
        $result = $manager->import(new JsonFormatter, $fullPath);

        $this->assertTrue($result);
        $this->assertDatabaseHas('settings', ['key' => 'site_name']);

        $setting = Setting::where('key', 'site_name')->first();
        $this->assertEquals('My App', $setting->getDefaultValue());
    }

    public function test_import_can_upsert_settings()
    {
        Setting::create([
            'key' => 'site_name',
            'type' => 'string',
            'description' => 'Old description',
        ]);

        $data = [
            [
                'key' => 'site_name',
                'type' => 'string',
                'description' => 'New description',
                'default_value' => 'My App',
            ],
        ];
        Storage::disk('local')->put('import.json', json_encode($data));
        $fullPath = Storage::disk('local')->path('import.json');

        $manager = new ImportManager;
        $manager->import(new JsonFormatter, $fullPath, ['mode' => 'upsert']);

        $this->assertDatabaseHas('settings', [
            'key' => 'site_name',
            'description' => 'New description',
        ]);

        $setting = Setting::where('key', 'site_name')->first();
        $this->assertEquals('My App', $setting->getDefaultValue());
    }

    public function test_import_with_truncate()
    {
        Setting::create([
            'key' => 'old_setting',
            'type' => 'string',
        ]);

        $data = [
            [
                'key' => 'new_setting',
                'type' => 'string',
            ],
        ];
        Storage::disk('local')->put('import.json', json_encode($data));
        $fullPath = Storage::disk('local')->path('import.json');

        $manager = new ImportManager;
        $manager->import(new JsonFormatter, $fullPath, ['truncate' => true]);

        $this->assertDatabaseMissing('settings', ['key' => 'old_setting']);
        $this->assertDatabaseHas('settings', ['key' => 'new_setting']);
    }

    public function test_export_with_anonymize()
    {
        Setting::create([
            'key' => 'secret_setting',
            'type' => 'string',
            'description' => 'A very secret setting',
        ]);

        $manager = new ExportManager;
        $path = $manager->export(new JsonFormatter, [
            'filename' => 'anonymized.json',
            'anonymize' => true,
        ]);

        $content = Storage::disk('local')->get('anonymized.json');
        $data = json_decode($content, true);

        $this->assertEquals('Anonymized description', $data[0]['description']);
        $this->assertArrayNotHasKey('created_at', $data[0]);
        $this->assertArrayNotHasKey('updated_at', $data[0]);
    }

    public function test_export_with_decrypt()
    {
        $setting = Setting::create([
            'key' => 'masked_setting',
            'type' => 'string',
            'masked' => true,
        ]);

        $setting->defaultValue()->create([
            'valuable_type' => Setting::class,
            'valuable_id' => $setting->id,
            'value' => 'secret-value', // This will be encrypted by the model
        ]);

        $manager = new ExportManager;

        // Without decrypt
        $path = $manager->export(new JsonFormatter, [
            'filename' => 'encrypted.json',
            'decrypt' => false,
        ]);
        $data = json_decode(Storage::disk('local')->get('encrypted.json'), true);
        $this->assertNotEquals('secret-value', $data[0]['default_value']);

        // With decrypt
        $path = $manager->export(new JsonFormatter, [
            'filename' => 'decrypted.json',
            'decrypt' => true,
        ]);
        $data = json_decode(Storage::disk('local')->get('decrypted.json'), true);
        $this->assertEquals('secret-value', $data[0]['default_value']);
    }

    public function test_export_command()
    {
        Setting::create([
            'key' => 'command_test',
            'type' => 'string',
        ]);

        $this->artisan('fulcrum:export', [
            '--format' => 'json',
            '--filename' => 'command_export.json',
        ])->assertExitCode(0);

        Storage::disk('local')->assertExists('command_export.json');
    }

    public function test_import_command()
    {
        $data = [['key' => 'imported_via_command', 'type' => 'string']];
        Storage::disk('local')->put('command_import.json', json_encode($data));
        $fullPath = Storage::disk('local')->path('command_import.json');

        $this->artisan('fulcrum:import', [
            'path' => $fullPath,
            '--format' => 'json',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('settings', ['key' => 'imported_via_command']);
    }
}
