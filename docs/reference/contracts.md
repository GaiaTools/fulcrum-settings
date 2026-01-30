---
title: Contracts
description: Interfaces and contracts provided by Fulcrum
---

# Contracts

Fulcrum exposes interfaces for drivers, resolvers, and core services.

## BucketCalculator

```php
public function calculate(string $identifier, string $salt, int $buckets = 100_000): int;
```

## DistributionStrategy

```php
public function findVariantForBucket(SettingRule $rule, int $bucket): ?SettingRuleRolloutVariant;
```

## GeoResolver

```php
public function resolve(Request|string|array|null $input = null): array;
```

## HolidayResolver

```php
public function isHoliday(Carbon $date, string|array|null $region = null): bool;
```

## RuleEvaluator

```php
public function evaluateRule(SettingRule $rule, mixed $scope = null): bool;
public function evaluateCondition(SettingRuleCondition $condition, mixed $scope = null): bool;
public function setUser(?Authenticatable $user): static;
```

## ConditionTypeHandler

```php
public function resolve(string $field, mixed $scope = null, ?Authenticatable $user = null): AttributeValue;
```

`AttributeValue` is a value object with:
- `exists` (bool): whether the attribute was present in the resolved context
- `value` (mixed): the resolved value used for comparison

### Built-in Condition Type Handlers

- `GaiaTools\FulcrumSettings\Conditions\UserConditionTypeHandler`
- `GaiaTools\FulcrumSettings\Conditions\GeocodingConditionTypeHandler`
- `GaiaTools\FulcrumSettings\Conditions\UserAgentConditionTypeHandler`
- `GaiaTools\FulcrumSettings\Conditions\DateTimeConditionTypeHandler`

## SegmentDriver

```php
public function isInSegment(Authenticatable $user, string $segment): bool;
public function getUserSegments(Authenticatable $user): array;
```

## SettingResolver

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

## SettingTypeHandler

```php
public function get(mixed $value): mixed;
public function set(mixed $value): mixed;
public function validate(mixed $value): bool;
public function getDatabaseType(): string;
```

## TenantResolver

```php
public function resolve(): ?string;
```

## UserAgentResolver

```php
public function resolve(mixed $scope = null): array;
```

## Data Portability Responses

These contracts extend `Illuminate\Contracts\Support\Responsable`:

- `GaiaTools\FulcrumSettings\Contracts\DataPortability\ExportResponse`
- `GaiaTools\FulcrumSettings\Contracts\DataPortability\ExportViewResponse`
- `GaiaTools\FulcrumSettings\Contracts\DataPortability\ImportResponse`
- `GaiaTools\FulcrumSettings\Contracts\DataPortability\ImportViewResponse`
