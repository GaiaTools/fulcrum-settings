# Contracts, Events & Exceptions Reference

Interfaces, event classes, and exception types in Fulcrum Settings.

## Table of Contents

1. [Contracts (Interfaces)](#contracts)
2. [Events](#events)
3. [Exceptions](#exceptions)

---

## Contracts

All contracts are in the `GaiaTools\FulcrumSettings\Contracts` namespace.

### SettingResolver

The core service behind the `Fulcrum` facade.

```php
public function resolve(string $key, mixed $scope = null): mixed;
public function isActive(string $key, mixed $scope = null): bool;
public function forUser(?Authenticatable $user): static;
public function forTenant(?string $tenantId): static;
public function get(string $key, mixed $default = null, mixed $scope = null): mixed;
public function reveal(bool $reveal = true): static;
public function set(string $key, mixed $value): void;
public function isMultiTenancyEnabled(): bool;
```

### RuleEvaluator

Evaluates targeting rules and individual conditions.

```php
public function evaluateRule(SettingRule $rule, mixed $scope = null): bool;
public function evaluateCondition(SettingRuleCondition $condition, mixed $scope = null): bool;
public function setUser(?Authenticatable $user): static;
```

### ConditionTypeHandler

Resolves a field value from the evaluation context. Returns an `AttributeValue` (with `exists: bool` and `value: mixed`).

```php
public function resolve(string $field, mixed $scope = null, ?Authenticatable $user = null): AttributeValue;
```

Built-in implementations:
- `UserConditionTypeHandler` — Resolves from authenticated user / explicit scope
- `GeocodingConditionTypeHandler` — Resolves from geo resolver
- `UserAgentConditionTypeHandler` — Resolves from user agent resolver
- `DateTimeConditionTypeHandler` — Resolves from configured clock/timezone

### SettingTypeHandler

Handles serialization, deserialization, and validation for setting types.

```php
public function get(mixed $value): mixed;       // Deserialize from storage
public function set(mixed $value): mixed;       // Serialize for storage
public function validate(mixed $value): bool;   // Type validation
public function getDatabaseType(): string;      // Underlying DB column type
```

### SegmentDriver

Resolves user segment membership.

```php
public function isInSegment(Authenticatable $user, string $segment): bool;
public function getUserSegments(Authenticatable $user): array;
```

### GeoResolver

Resolves geographic location from IP or request.

```php
public function resolve(Request|string|array|null $input = null): array;
```

### UserAgentResolver

Parses user agent data.

```php
public function resolve(mixed $scope = null): array;
```

### HolidayResolver

Checks if a date is a holiday.

```php
public function isHoliday(Carbon $date, string|array|null $region = null): bool;
```

### BucketCalculator

Consistent hashing for rollout bucketing.

```php
public function calculate(string $identifier, string $salt, int $buckets = 100_000): int;
```

### DistributionStrategy

Maps a bucket to a rollout variant.

```php
public function findVariantForBucket(SettingRule $rule, int $bucket): ?SettingRuleRolloutVariant;
```

### TenantResolver

Resolves the current tenant ID.

```php
public function resolve(): ?string;
```

### Data Portability Contracts

These extend `Illuminate\Contracts\Support\Responsable`:

- `ExportResponse`
- `ExportViewResponse`
- `ImportResponse`
- `ImportViewResponse`

---

## Events

All events are in the `GaiaTools\FulcrumSettings\Events` namespace.

### SettingResolved

Fired when a setting is resolved.

| Property | Type | Description |
|----------|------|-------------|
| `key` | string | Setting key |
| `value` | mixed | Resolved value |
| `setting` | Setting\|null | Setting model |
| `matchedRule` | SettingRule\|null | Rule that matched (null if default) |
| `rulesEvaluated` | int | Number of rules checked |
| `source` | string | Resolution source |
| `tenantId` | string\|null | Active tenant |
| `userId` | mixed | Authenticated user ID |
| `scope` | array\|null | Evaluation scope |
| `durationMs` | float | Resolution time in ms |

### VariantAssigned

Fired when a user is assigned to a rollout variant.

| Property | Type | Description |
|----------|------|-------------|
| `settingKey` | string | Setting key |
| `ruleName` | string | Rule name |
| `variantName` | string | Assigned variant name |
| `value` | mixed | Variant value |
| `identifier` | string | Rollout identifier used |
| `bucket` | int | Bucket number |
| `setting` | Setting\|null | Setting model |
| `rule` | SettingRule\|null | Rule model |
| `variant` | SettingRuleRolloutVariant\|null | Variant model |
| `tenantId` | string\|null | Active tenant |
| `context` | array | Full evaluation context |

### SettingsLoaded

Fired when settings are loaded from the store.

| Property | Type |
|----------|------|
| `settings` | array |

### SettingsSaved

Fired when settings are saved to the database.

| Property | Type |
|----------|------|
| `settings` | array |

### LoadingSettings

Fired before settings are loaded. No properties.

### SavingSettings

Fired before settings are saved.

| Property | Type |
|----------|------|
| `settings` | array |

### Listener Example

```php
// EventServiceProvider
use GaiaTools\FulcrumSettings\Events\SettingResolved;
use GaiaTools\FulcrumSettings\Events\VariantAssigned;

protected $listen = [
    SettingResolved::class => [LogSettingResolution::class],
    VariantAssigned::class => [TrackExperimentAssignment::class],
];
```

```php
// Audit logging
public function handle(SettingsSaved $event)
{
    foreach ($event->settings as $key => $value) {
        AuditLog::create([
            'user_id' => auth()->id(),
            'setting_key' => $key,
            'new_value' => json_encode($value),
        ]);
    }
}
```

```php
// Analytics integration
public function handle(VariantAssigned $event)
{
    Mixpanel::track('Experiment Assigned', [
        'experiment_key' => $event->settingKey,
        'variant' => $event->variantName,
        'identifier' => $event->identifier,
    ]);
}
```

---

## Exceptions

All exceptions are in the `GaiaTools\FulcrumSettings\Exceptions` namespace.

| Exception | When Thrown |
|-----------|------------|
| `FulcrumException` | Base exception type |
| `SettingNotFoundException` | Setting key does not exist and no default provided |
| `InvalidSettingValueException` | Value fails type validation |
| `InvalidTypeHandlerException` | Registered type handler is invalid or misconfigured |
| `MissingTypeHandlerException` | Setting type has no registered handler |
| `ImmutableSettingException` | Attempting to modify/delete an immutable setting without `--force` |
| `PennantException` | Pennant integration is misconfigured or disabled |
