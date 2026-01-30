<?php

namespace GaiaTools\FulcrumSettings\Builders;

// src/Support/Builders/RuleConditionBuilder.php

namespace GaiaTools\FulcrumSettings\Support\Builders;

use GaiaTools\FulcrumSettings\Enums\ComparisonOperator;

class RuleConditionBuilder
{
    protected array $conditions = [];

    protected string $boolean = 'and'; // for future OR support

    /**
     * Add a condition (AND logic).
     */
    public function where(
        string $attribute,
        string|ComparisonOperator $operator,
        mixed $value = null,
        ?string $type = null
    ): self {
        // Allow shorthand: where('user.active', 'is_true')
        if ($value === null && is_string($operator)) {
            $value = null;
            $operator = ComparisonOperator::from($operator);
        } else {
            $operator = is_string($operator)
                ? ComparisonOperator::from($operator)
                : $operator;
        }

        $this->conditions[] = [
            'type' => $type,
            'attribute' => $attribute,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'and',
        ];

        return $this;
    }

    /**
     * Add a typed condition (AND logic).
     */
    public function whereType(
        string $type,
        string $attribute,
        string|ComparisonOperator $operator,
        mixed $value = null
    ): self {
        return $this->where($attribute, $operator, $value, $type);
    }

    /**
     * Get all conditions.
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }
}
