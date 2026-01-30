<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Tests\Feature;

use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use GaiaTools\FulcrumSettings\Tests\TestCase;

class MaskingTest extends TestCase
{
    public function test_masked_setting_returns_masked_value_by_default()
    {
        // Create a masked setting using a migration-like approach
        $migration = new class extends SettingMigration
        {
            public function up(): void
            {
                $this->createSetting('masked_test')
                    ->type('string')
                    ->default('masked test value')
                    ->description('Test masked setting')
                    ->masked()
                    ->save();
            }
        };
        $migration->up();

        // Verify the setting is created and masked
        $value = Fulcrum::get('masked_test');

        // This is expected to fail currently based on the issue description
        $this->assertInstanceOf(MaskedValue::class, $value);
        $this->assertEquals('********', (string) $value);
    }

    public function test_masked_setting_can_be_revealed_if_authorized()
    {
        // Create a masked setting
        $migration = new class extends SettingMigration
        {
            public function up(): void
            {
                $this->createSetting('masked_test_reveal')
                    ->type('string')
                    ->default('secret value')
                    ->masked()
                    ->save();
            }
        };
        $migration->up();

        // 1. Straight request should be masked
        $this->assertInstanceOf(MaskedValue::class, Fulcrum::get('masked_test_reveal'));

        // 2. Explicit reveal request WITHOUT permission should be unmasked because tests run in CLI
        $value = Fulcrum::reveal()->get('masked_test_reveal');
        $this->assertEquals('secret value', $value);

        // 3. Explicit reveal request WITH permission should be unmasked
        $user = new class extends \Illuminate\Foundation\Auth\User {};
        $this->actingAs($user);

        \Illuminate\Support\Facades\Gate::define('viewSettingValue', function () {
            return true;
        });

        $value = Fulcrum::reveal()->get('masked_test_reveal');
        $this->assertEquals('secret value', $value);
    }

    public function test_reveal_is_not_sticky_if_we_reset_context()
    {
        $migration = new class extends SettingMigration
        {
            public function up(): void
            {
                $this->createSetting('masked_test_sticky')
                    ->type('string')
                    ->default('secret')
                    ->masked()
                    ->save();
            }
        };
        $migration->up();

        $user = new class extends \Illuminate\Foundation\Auth\User {};
        $this->actingAs($user);

        \Illuminate\Support\Facades\Gate::define('viewSettingValue', function () {
            return true;
        });

        $this->assertEquals('secret', Fulcrum::reveal()->get('masked_test_sticky'));

        // Manually clear reveal flag (usually happens at start of request)
        \GaiaTools\FulcrumSettings\Support\FulcrumContext::reveal(false);

        $this->assertInstanceOf(MaskedValue::class, Fulcrum::get('masked_test_sticky'));
    }
}
