# Use Cases

Discover how Laravel Fulcrum can solve common configuration and feature delivery challenges in your application.

## 1. Beta Testing & Gradual Rollouts
Release new features safely by enabling them for a small percentage of users first.
- **Example**: Enable the "New Search Engine" for 10% of users to monitor performance before a full release.
- **Feature**: Percentage-based rollouts in [Targeting Rules](targeting-rules).

## 2. Multi-Tenant SaaS Configuration
Provide different settings for different customers (tenants) while maintaining global defaults.
- **Example**: Set different "max_users" limits or "branded_colors" for each organization using your software.
- **Feature**: Built-in [Multi-Tenancy](multi-tenancy) support.

## 3. Geographic & Regional Targeting
Tailor your application experience based on the user's location.
- **Example**: Show a "Winter Sale" banner only to users in the Northern Hemisphere, or enable "GDPR Compliance" features only for users in the EU.
- **Feature**: Geo-targeting rules.

## 4. Time-Based Campaigns & Promotions
Schedule features or configuration changes to go live and expire automatically.
- **Example**: Automatically activate a "Black Friday" discount at midnight and disable it when the sale ends.
- **Feature**: Time-based conditions with [Carbon Integration](integrations/carbon-integration).

## 5. Emergency Kill Switches
Quickly disable problematic features without redeploying your code.
- **Example**: If a new integration is causing errors, immediately set its "enabled" flag to `false` via the Artisan command.
- **Feature**: Instant updates via `php artisan fulcrum:set`.

## 6. Role & Permission Based Features
Gate features based on user roles or specific segments.
- **Example**: Enable "Advanced Analytics" only for users with the "Premium" subscription or "Admin" role.
- **Feature**: Segment-based rules.

## 7. A/B Testing
Test different configuration values to see which performs better.
- **Example**: Test two different "call_to_action_text" values to see which one leads to more signups.
- **Feature**: Consistent variant assignment.

## 8. Dynamic Pricing
Adjust pricing or discounts dynamically based on user context.
- **Example**: Offer a "Loyalty Discount" to users who have been with you for more than a year.
- **Feature**: Custom types (like Money) combined with rules.
