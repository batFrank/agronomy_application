---
name: code-analysis
description: Static analysis and code quality checking for PHP using PHPStan and PHP-CS-Fixer
license: MIT
compatibility: opencode
---

## What I do

- Run PHPStan static analysis at level 8 (strictest)
- Check and fix code style with PHP-CS-Fixer
- Ensure code follows PSR-12 + Symfony standards
- Verify PHPDoc is used only where it adds value
- Check for type safety and declaration of strict types

## When to use me

Use this skill when you need to:
- Verify code passes static analysis
- Auto-fix code style issues
- Check code quality before committing
- Ensure PHPDoc follows conventions
- Validate type hints and declarations

## Commands

### PHPStan Analysis (Level 8)
```bash
docker compose exec -T symfony-base-php php vendor/bin/phpstan analyse --level=max src/
```
Checks for type errors, undefined variables, and other static analysis issues at the strictest level.

### PHP-CS-Fixer Check (Dry Run)
```bash
docker compose exec -T symfony-base-php php vendor/bin/php-cs-fixer fix --dry-run --diff
```
Shows what would be fixed without making changes.

### PHP-CS-Fixer Auto-Fix
```bash
docker compose exec -T symfony-base-php php vendor/bin/php-cs-fixer fix
```
Automatically fixes code style issues.

## Code Quality Standards

### File Headers
Every PHP file must start with:
```php
<?php

declare(strict_types=1);

namespace App\...;
```

### PHPDoc Usage

✅ **Use PHPDoc for**:
- Arrays with element types: `@param array<string, User> $data`
- Exceptions thrown: `@throws UserNotFoundException`
- Generic types: `@extends Collection<User>`

❌ **Don't use PHPDoc for**:
- Types already in function signature
- Return types with proper typehints
- Obvious parameter types

### Type Hints
- All parameters must have type hints
- All return types must be specified
- Use strict types: `declare(strict_types=1)`
- No nullable types unless necessary

### Logical Operators
- Use `&&`, `||` (not `and`, `or`)
- Use `!` (not `not`)

### String Concatenation
- Use spacing around `.` operator: `$a . ' ' . $b`

## Common Issues Fixed by Code-Analysis

- Missing type declarations
- Incorrect PHPDoc formatting
- Code style violations (spacing, imports, etc.)
- Unnecessary use statements
- Missing declare(strict_types=1)
- Inconsistent naming conventions

## Common PHPStan Issues and Solutions

| Issue | Solution |
|-------|----------|
| **Nullable return types** | Always check for null before using optional returns (e.g., `if ($result !== null)`) or throw an exception |
| **Undefined properties** | Check property names match declared properties; common typos in getters (e.g., `$this->lastName` vs `$this->name`) |
| **Missing type hints** | Add type declarations to all parameters and return types (see "Type Hints" section above) |
| **Array type mismatch** | Use proper array types: `array<string, User>` for associative arrays, `array<User>` for indexed arrays |
| **Mixed types** | Avoid returning different types; use union types (PHP 8.0+): `User|null` instead of `?User` when needed |

See `/docs/code/CONVENTIONS.md` for detailed examples and best practices.

## Quality Gate Checklist

Before committing code:
- [ ] PHPStan passes: `docker compose exec -T symfony-base-php php vendor/bin/phpstan analyse --level=max src/`
- [ ] PHP-CS-Fixer passes: `docker compose exec -T symfony-base-php php vendor/bin/php-cs-fixer fix --dry-run --diff`
- [ ] Tests pass: `docker compose exec -T symfony-base-php php bin/phpunit`

## Reference

See `/docs/code/CONVENTIONS.md` for detailed code style guidelines.
