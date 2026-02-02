<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use Illuminate\Contracts\Auth\Authenticatable;

interface RuleEvaluator
{
    /**
     * Evaluate if a rule passes for the given scope.
     * All conditions within the rule must pass (AND logic).
     */
    public function evaluateRule(SettingRule $rule, mixed $scope = null): bool;

    /**
     * Evaluate a single condition for the given scope.
     */
    public function evaluateCondition(SettingRuleCondition $condition, mixed $scope = null): bool;

    /**
     * Set the authenticated user for segment evaluation.
     */
    public function setUser(?Authenticatable $user): static;
}
