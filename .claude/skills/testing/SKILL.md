---
name: testing
description: Comprehensive testing patterns and workflows for PHP/Symfony projects using Mother pattern, DataProviders, and AAA pattern
license: MIT
compatibility: opencode
---

## What I do

- Guide test creation using AAA pattern (Arrange, Act, Assert)
- Ensure DataProviders use generators with `yield`, never arrays
- Help write transparent tests with inline expected data
- Apply Mother pattern for test data creation
- Order tests following code execution flow
- Verify tests pass and code quality checks pass

## When to use me

Use this skill when you need to:
- Write new tests for endpoints or domain logic
- Refactor existing tests to follow patterns
- Debug failing tests
- Ensure test code quality and coverage

## Key Patterns

### AAA Pattern
```php
// ARRANGE - Setup data and state
$entity = EntityMother::create(title: 'Test');

// ACT - Execute the action
$response = $this->makeRequest(method: 'GET', uri: '/api/v1/endpoint');

// ASSERT - Verify results
$this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
```

### DataProviders with Generators
```php
public static function casesProvider(): \Generator
{
    yield 'descriptive case name' => [
        $inputData,
        $expectedResult,
    ];
}
```

### Inline Expected Data
Always show all expected data explicitly, never use helper functions that mask it:
```php
yield 'filter by title' => [
    new ScenarioCollection([$scenario]),
    ['title' => 'Test'],
    [
        [
            'id' => $scenario->getId()->value(),
            'title' => 'Test',
            'description' => 'Test description',
        ],
    ],
];
```

### Mother Pattern
```php
$user = UserMother::create(email: 'test@example.com', name: 'Test User');
$scenario = ScenarioMother::create(title: 'Phishing');
```

### Helper Traits for Functional Tests

**Location**: `tests/Functional/HelperTrait/[Domain]/[Entity]HelperTrait.php`  
**Example**: `tests/Functional/HelperTrait/Scenario/ScenarioHelperTrait.php`

#### When to USE Helper Traits
- Complex test data creation that's reused across **multiple test classes**
- Setup logic with multiple steps (e.g., creating entity + related entities + fixtures)
- Data builders that encapsulate business rules (e.g., `createScenarioWithAllCompetencyLevels()`)
- Conditional logic for different test scenarios

#### When NOT to use (use Mother Pattern instead)
- Simple, one-off test data creation (use `Mother::create()` directly)
- Single entity instantiation with parameters
- Data creation needed in just **one test class** (define as private method or use Mother)
- Mother Pattern already provides enough flexibility with named parameters

#### Example Usage
```php
trait ScenarioHelperTrait
{
    private function createScenarioWithCompetencyLevels(string $title = 'Phishing'): Scenario
    {
        // Complex multi-step setup encapsulated
        $scenario = ScenarioMother::create(title: $title);
        $this->attachCompetencyLevels($scenario, ['Level1', 'Level2']);
        $this->persistScenarioData($scenario);
        return $scenario;
    }
}
```

#### Key Rule
Helper Traits **complement** Mother Pattern—don't replace it. Use Mothers for simple entity creation, Traits for complex multi-step setup shared across tests.

## Commands

### Run All Tests
```bash
docker compose exec -T symfony-base-php php bin/phpunit
```

### Run Specific Test
```bash
docker compose exec -T symfony-base-php php bin/phpunit tests/Functional/Scenario/GetScenariosFunctionalTest.php
```

### Run Tests by Pattern
```bash
docker compose exec -T symfony-base-php php bin/phpunit --filter testGetScenarios
```

### Run with Coverage
```bash
docker compose exec -T symfony-base-php php bin/phpunit --coverage-html coverage
```

## Test Conventions

- Unit tests: `tests/Domain/[Entity]/`
- Integration tests: `tests/Application/`
- Functional tests: `tests/Functional/`
- Factories: `tests/Mother/Domain/[Entity]/[Entity]Mother.php`
- Test names: Follow execution order (exceptions first, then early returns, then success cases)

## Reference

See `/docs/code/CONVENTIONS.md` for detailed patterns and workflows.
