# User Activity Voter Infrastructure

This infrastructure provides a flexible way to determine if a user is "active" based on various criteria using Symfony's SecurityVoter pattern.

## Components

### UserActivityInterface
Defines the contract for activity checkers with methods:
- `isUserActive(UserInterface $user): bool` - Check if user is active
- `getActivityDescription(): string` - Human-readable description
- `getPriority(): int` - Priority for multiple checkers (higher = more important)

### UserActivityVoter
The main voter that orchestrates activity checking using the attribute `IS_ACTIVE_USER`.

### Built-in Activity Checkers

#### LastLoginActivityChecker
- **Criteria**: User has logged in within a configurable threshold
- **Default**: 30 days (configurable via `user_activity_last_login_threshold_days`)
- **Priority**: 100 (high)

#### ClaimedStatementsActivityChecker  
- **Criteria**: User has claimed statements/segments with recent activity
- **Default**: 90 days (configurable via `user_activity_claimed_statements_threshold_days`)
- **Priority**: 75 (medium-high)

## Usage Examples

### In Controllers
```php
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MyController
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker
    ) {}
    
    public function someAction(User $user)
    {
        if ($this->authorizationChecker->isGranted('IS_ACTIVE_USER', $user)) {
            // User is considered active
            return $this->render('active_user_view.html.twig');
        }
        
        // User is inactive
        return $this->render('inactive_user_view.html.twig');
    }
}
```

### In Twig Templates
```twig
{% if is_granted('IS_ACTIVE_USER', user) %}
    <span class="badge badge-success">Active User</span>
{% else %}
    <span class="badge badge-warning">Inactive User</span>
{% endif %}
```

### In Services
```php
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class UserAnalyticsService
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker
    ) {}
    
    public function getActiveUserCount(array $users): int
    {
        return count(array_filter($users, fn($user) => 
            $this->authorizationChecker->isGranted('IS_ACTIVE_USER', $user)
        ));
    }
}
```

## Configuration

### Parameters (config/parameters_default.yml)
```yaml
parameters:
    # Threshold for last login activity checker (days)
    user_activity_last_login_threshold_days: 180
    
    # Threshold for claimed statements activity checker (days)
    user_activity_claimed_statements_threshold_days: 180
```

### Services (config/services.yml)
Activity checkers are automatically registered with the `user_activity_checker` tag and injected into the voter via tagged iterators.

## Creating Custom Activity Checkers

1. Implement `UserActivityInterface`
2. Register as a service with the `user_activity_checker` tag
3. Define priority to control evaluation order

Example:
```php
class CustomActivityChecker implements UserActivityInterface
{
    public function isUserActive(UserInterface $user): bool
    {
        // Your custom logic here
        return true;
    }
    
    public function getActivityDescription(): string
    {
        return 'Custom activity criteria';
    }
    
    public function getPriority(): int
    {
        return 50; // Medium priority
    }
}
```

Service registration:
```yaml
services:
    App\Logic\CustomActivityChecker:
        tags:
            - { name: user_activity_checker }
```

## Behavior

- **Multiple Checkers**: Uses OR logic - user is active if ANY checker considers them active
- **Priority**: Higher priority checkers are evaluated first
- **No Checkers**: If no activity checkers are configured, all users are considered active
- **Performance**: Checkers are sorted by priority once per request
