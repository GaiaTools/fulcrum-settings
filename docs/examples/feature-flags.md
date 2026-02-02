# Example: Basic Feature Flags

This example demonstrates how to set up and use a simple boolean feature flag in Laravel Fulcrum.

## Scenario
We want to release a new "Dark Mode" feature, but only for users who have opted into our "Beta" program.

## 1. Define the Setting

Create a migration to define the `dark_mode` setting.

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('dark_mode')
            ->description('Enable the dark mode theme')
            ->boolean()
            ->default(false)
            ->save();
    }
};
```

## 2. Add a Targeting Rule

Now, let's add a rule that enables `dark_mode` for beta users. You can do this in a seeder or through an admin interface.

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->addRule('dark_mode', function ($rule) {
            $rule->name('Beta users')
                ->whereInSegment('segment', 'beta')
                ->then(true);
        });
    }
};
```

## 3. Check the Flag in a Blade View

You can use the `Fulcrum` facade directly in your Blade templates.

```html
<body class="{{ Fulcrum::isActive('dark_mode') ? 'dark-theme' : 'light-theme' }}">
    <!-- ... -->
</body>
```

## 4. Check the Flag in a Controller

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

public function index()
{
    if (Fulcrum::isActive('dark_mode')) {
        // Log that the user is using the new feature
        logger()->info('User viewed page in dark mode', ['user_id' => auth()->id()]);
    }

    return view('welcome');
}
```

## Summary
By using a targeting rule, we've enabled the feature for beta users without having to write any conditional logic in our migrations or change the default value for everyone else.

## Next Steps
- [Example: Advanced Targeting](advanced-targeting) - Learn how to add more complex rules.
- [Targeting Rules](../targeting-rules) - Deep dive into the rules engine.
