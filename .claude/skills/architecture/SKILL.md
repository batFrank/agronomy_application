---
name: architecture
description: DDD and Hexagonal Architecture patterns for PHP with CQRS and Domain-Driven Design
license: MIT
compatibility: opencode
---

## What I do

- Guide implementation following Hexagonal Architecture (3-layer pattern)
- Ensure proper Domain/Application/Infrastructure separation
- Validate CQRS pattern (Commands, Queries, Handlers)
- Check correct naming conventions for entities, services, and handlers
- Ensure no framework dependencies leak into Domain layer
- Verify repository pattern implementation

## When to use me

Use this skill when you need to:
- Create new commands or queries
- Implement domain entities and value objects
- Build API endpoints and controllers
- Create repository implementations
- Ensure proper layer separation
- Validate architecture compliance

## Architecture Layers

### Domain Layer (`src/Domain/`)
Pure business logic, zero framework dependencies.

**Contains**:
- Entities (extend `LifeCycleEntity`)
- Value Objects
- Repository interfaces
- Domain-specific exceptions
- Collections (extend `EntityWithIdCollection<[Entity]>`)

**Example structure**:
```
src/Domain/
└── User/
    ├── User.php
    ├── UserRepository.php (interface)
    └── Exception/
        └── UserNotFoundException.php
```

### Application Layer (`src/Application/`)
CQRS pattern with Commands, Queries, and Handlers. Orchestrates use cases.

**Contains**:
- Commands (write operations)
- CommandHandlers
- Queries (read operations)
- QueryHandlers
- DTOs (data transfer objects)

**Example structure**:
```
src/Application/
└── User/
    ├── Command/
    │   ├── CreateUserCommand.php
    │   └── CreateUserCommandHandler.php
    ├── Query/
    │   ├── GetUserQuery.php
    │   └── GetUserQueryHandler.php
    └── DTO/
        └── UserDTO.php
```

### Infrastructure Layer (`src/Infrastructure/`)
Framework-specific code, external integrations, and implementations.

**Contains**:
- Controllers (API endpoints)
- Repository implementations (DBAL, InMemory)
- External service clients
- Security/Authentication
- Response transformers

**Example structure**:
```
src/Infrastructure/
├── Controller/
│   ├── CreateUserController.php
│   └── GetUserController.php
├── Persistence/
│   └── User/
│       ├── DBALUserRepository.php
│       └── InMemoryUserRepository.php
└── Response/
    └── UserTransformer.php
```

## Naming Conventions

### Commands (Write Operations)
```
[Action][Entity]Command.php
Examples: CreateUserCommand, UpdateScenarioCommand, DeleteCompetencyCommand
```

### Queries (Read Operations)
```
Get[Entity]Query.php or Get[Entity]sQuery.php
Examples: GetUserQuery, GetScenariosQuery, GetCompetencyQuery
```

### Handlers
```
[Action][Entity]CommandHandler.php
[Action][Entity]QueryHandler.php
Examples: CreateUserCommandHandler, GetUserQueryHandler
```

### Repositories
**Domain (Interface)**:
```
[Entity]RepositoryInterface.php
Example: UserRepositoryInterface.php
```

**Infrastructure (Implementation)**:
```
DBAL[Entity]Repository.php (Doctrine DBAL)
InMemory[Entity]Repository.php (Testing)
Examples: DBALUserRepository.php, InMemoryUserRepository.php
```

### Controllers
```
[Action][Entity]Controller.php
Examples: CreateUserController, GetUsersController
```

### Collections
```
[Entity]Collection.php (extends EntityWithIdCollection<[Entity]>)
Example: UserCollection.php
```

### DTOs
```
[Name]DTO.php
Examples: UserDTO.php, CreateUserDTO.php
```

### Tests
```
[Class]Test.php
Examples: UserTest.php, CreateUserCommandHandlerTest.php
```

### Test Factories (Mother Pattern)
```
[Entity]Mother.php
Example: UserMother.php
```

### CriteriaTransformers
```
[Entity]CriteriaTransformer.php en Infrastructure/Request/[Entity]/
Examples: UserCriteriaTransformer.php, ScenarioCriteriaTransformer.php
```
Transforms HTTP query params to Criteria objects for filtering and pagination.

## Key Rules

### Domain Layer
- ✅ Pure business logic
- ✅ Typehinted properties and methods
- ✅ Value Objects for complex types
- ✅ Collections for multiple entities
- ❌ No Symfony imports
- ❌ No database queries
- ❌ No external service calls

### Application Layer
- ✅ Commands for writes
- ✅ Queries for reads
- ✅ DTOs for input/output
- ✅ Orchestrates domain and infrastructure
- ✅ Handles business use cases
- ❌ No direct database access

### Infrastructure Layer
- ✅ Controllers expose HTTP endpoints
- ✅ Repositories implement domain interfaces
- ✅ External service calls
- ✅ Security and authentication
- ✅ Response formatting
- ❌ No business logic

## Soft Deletes

All entities use soft deletes with `deletedAt` field:
```php
#[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $deletedAt = null;
```

Repositories automatically filter: `WHERE deleted_at IS NULL`

## Entity Lifecycle

Entities extend `LifeCycleEntity` which provides:
- `id` - UUID identifier
- `createdAt` - Creation timestamp
- `updatedAt` - Last update timestamp
- `deletedAt` - Soft delete timestamp

## Important: markAsUpdated()

⚠️ **CRITICAL**: Only call `markAsUpdated()` in **persistence layer**, never in domain logic:

```php
// ❌ WRONG - In domain
public function setTitle(string $title): void
{
    $this->title = $title;
    $this->markAsUpdated(); // NO!
}

// ✅ CORRECT - In persistence layer
public function put(User $user): void
{
    // ... save to database
    $user->markAsUpdated(); // Only here
}
```

## Reference

See `/docs/code/CONVENTIONS.md` for detailed conventions and patterns.
