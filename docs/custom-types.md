# Setting Types & Custom Handlers

Laravel Fulcrum supports both built-in data types and custom value objects through a flexible type handling system.

## Built-in Types

Fulcrum supports the following types out of the box:

- `string`
- `boolean`
- `integer`
- `float`
- `json` (arrays/objects)
- `array`
- `carbon` (automatically registered)

## Custom Type System

You can extend Fulcrum to support any PHP value object (e.g., `Money`, `Address`, `Carbon`) by creating a Custom Type Handler.

### 1. Create a Handler Class

Your handler must extend `GaiaTools\FulcrumSettings\Types\SettingTypeHandler`.

```php
namespace App\Settings\Types;

use GaiaTools\FulcrumSettings\Types\SettingTypeHandler;
use Brick\Money\Money;

class MoneyHandler extends SettingTypeHandler
{
    public function get(mixed $value): Money
    {
        // Convert stored string/number back to Money object
        return Money::of($value, 'USD');
    }

    public function set(mixed $value): string
    {
        // Convert Money object to storable format
        return (string) $value->getAmount();
    }

    public function validate(mixed $value): bool
    {
        return $value instanceof Money;
    }
}
```

### 2. Register the Custom Type

Register your handler in `config/fulcrum.php`:

```php
'types' => [
    // ...
    'money' => App\Settings\Types\MoneyHandler::class,
],
```

### 3. Use the Custom Type

Now you can use this type in your migrations and settings classes (as accessors).

**In a Migration:**

```php
$this->createSetting('product_price')
    ->type('money')
    ->default(Money::of(99.00, 'USD'))
    ->save();
```

**In a Settings Class:**

```php
use GaiaTools\FulcrumSettings\Attributes\SettingProperty;
use GaiaTools\FulcrumSettings\Support\Settings\FulcrumSettings;
use Brick\Money\Money;

class StoreSettings extends FulcrumSettings
{
    #[SettingProperty(key: 'product_price', cast: 'money')]
    protected Money $productPrice;
}
```

## Complete Example: Money Type

Here's a full implementation using the `brick/money` library for proper currency handling:

### 1. Install Dependencies

```bash
composer require brick/money
```

### 2. Create the Money Handler

```php
namespace App\Settings\Types;

use GaiaTools\FulcrumSettings\Types\SettingTypeHandler;
use Brick\Money\Money;

class MoneyHandler extends SettingTypeHandler
{
    public function get(mixed $value): Money
    {
        // Stored as "USD 9900" (amount in cents)
        [$currency, $amount] = explode(' ', $value);
        return Money::ofMinor($amount, $currency);
    }

    public function set(mixed $value): string
    {
        if (! $value instanceof Money) {
            throw new \InvalidArgumentException("Value must be an instance of Brick\Money\Money");
        }

        return $value->getCurrency()->getCurrencyCode() . ' ' . $value->getMinorAmount()->toInt();
    }

    public function validate(mixed $value): bool
    {
        return $value instanceof Money;
    }
}
```

### 3. Location-Based Pricing with Custom Type

```php
use GaiaTools\FulcrumSettings\Database\Migrations\SettingMigration;
use Brick\Money\Money;

return new class extends SettingMigration
{
    public function up(): void
    {
        $this->createSetting('base_subscription_price')
            ->type('money')
            ->default(Money::of(29.00, 'USD'))
            ->save();
            
        $this->addRule('base_subscription_price', function ($rule) {
            $rule->name('India pricing')
                ->whenType('geocoding', 'country', 'equals', 'IN')
                ->then(Money::of(10.00, 'USD'));
        });
    }
};
```

### 4. Usage

```php
use GaiaTools\FulcrumSettings\Facades\Fulcrum;

$price = Fulcrum::get('base_subscription_price');
echo $price->formatTo('en_US'); // "$29.00" or "$10.00" based on location
```

## Next Steps

- [Class-Based Settings](class-based-settings) - Use custom types in your settings classes.
- [Targeting Rules](targeting-rules) - Combine custom types with complex rules.
