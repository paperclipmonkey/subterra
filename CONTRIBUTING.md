# Contributing to Subterra

Thank you for your interest in contributing to Subterra! This document provides guidelines and information for contributors.

## Code of Conduct

This project follows a standard code of conduct. Please be respectful and constructive in all interactions.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally
3. Set up the development environment (see [README.md](README.md))
4. Create a feature branch for your changes
5. Make your changes and test them
6. Submit a pull request

## Development Workflow

### Branch Naming
Use descriptive branch names that indicate the type of change:
- `feature/add-cave-search` - New features
- `fix/trip-validation-bug` - Bug fixes
- `docs/api-documentation` - Documentation updates
- `refactor/image-processing` - Code refactoring

### Commit Messages
Write clear, descriptive commit messages:
- Use present tense ("Add feature" not "Added feature")
- Keep the first line under 50 characters
- Include additional details in the body if needed

Good examples:
```
Add cave search functionality

- Implement search by name and location
- Add filters for cave type and difficulty
- Include pagination for results
```

## Code Standards

### Backend (Laravel)

#### Code Style
- Follow PSR-12 coding standards
- Use Laravel Pint for code formatting: `vendor/bin/pint`
- Run static analysis with PHPStan: `vendor/bin/phpstan analyse`

#### PHP Standards
- Use strict typing: `declare(strict_types=1);`
- Add proper type hints for all method parameters and return types
- Use meaningful variable and method names
- Follow Laravel naming conventions

#### Architecture
- Use Form Request classes for validation
- Implement authorization via Laravel Policies
- Extract complex business logic into Service classes
- Use Eloquent relationships instead of manual queries where possible
- Fire events for significant actions (trip creation, medal awards, etc.)

#### Example Controller
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveRequest;
use App\Http\Resources\CaveResource;
use App\Models\Cave;
use App\Services\ImageProcessingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CaveController extends Controller
{
    public function __construct(
        private readonly ImageProcessingService $imageProcessingService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return CaveResource::collection(Cave::orderBy('name')->get());
    }

    public function store(StoreCaveRequest $request): CaveResource
    {
        $cave = Cave::create($request->validated());
        
        return new CaveResource($cave);
    }
}
```

### Frontend (Vue.js)

#### Code Style
- Use Prettier for code formatting
- Follow Vue.js style guide
- Use TypeScript where possible
- Use Vuetify components consistently

#### Component Standards
- Use Composition API for new components
- Keep components focused and single-purpose
- Use proper prop types and validation
- Emit events for parent-child communication

### Database

#### Migrations
- Use descriptive migration names
- Include rollback logic in `down()` methods
- Add proper indexes for performance
- Use foreign key constraints where appropriate

#### Models
- Use proper relationship definitions
- Add type casts for attributes
- Implement soft deletes where appropriate
- Use scopes for common query patterns

Example:
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cave extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'cave_system_id',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function system(): BelongsTo
    {
        return $this->belongsTo(CaveSystem::class);
    }
}
```

## Testing

### Backend Testing
- Write tests for all new functionality
- Use feature tests for API endpoints
- Use unit tests for services and complex logic
- Maintain test coverage above 80%

#### Test Structure
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Cave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_caves(): void
    {
        $user = User::factory()->create(['approved' => true]);
        $cave = Cave::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/caves');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description']
                ]
            ]);
    }

    public function test_admin_can_create_cave(): void
    {
        $admin = User::factory()->create(['admin' => true]);

        $response = $this->actingAs($admin)
            ->postJson('/api/caves', [
                'name' => 'Test Cave',
                'description' => 'A test cave',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('caves', ['name' => 'Test Cave']);
    }
}
```

### Frontend Testing
- Write unit tests for components
- Write integration tests for user flows
- Use Jest for unit testing
- Use Cypress or similar for E2E testing

## Documentation

### API Documentation
- Document all public endpoints in `docs/API.md`
- Include request/response examples
- Document authentication requirements
- Update documentation when endpoints change

### Code Documentation
- Use PHPDoc for PHP methods and classes
- Document complex business logic
- Explain non-obvious code decisions
- Keep comments up-to-date with code changes

### README Updates
- Update setup instructions when dependencies change
- Keep feature list current
- Update deployment instructions as needed

## Security

### General Security
- Never commit secrets or credentials
- Use environment variables for configuration
- Validate all user input
- Use HTTPS in production

### Laravel Security
- Use Laravel's built-in security features
- Implement proper authorization checks
- Use CSRF protection for state-changing operations
- Sanitize user-generated content

### Authentication
- Use Laravel Sanctum for API authentication
- Implement proper session management
- Use secure password policies
- Implement rate limiting for sensitive endpoints

## Performance

### Database Performance
- Use database indexes appropriately
- Avoid N+1 queries with eager loading
- Use database transactions for data integrity
- Consider caching for expensive queries

### Application Performance
- Use Laravel's caching mechanisms
- Optimize image processing and storage
- Use queues for long-running tasks
- Monitor performance with tools like Laravel Telescope

## Pull Request Process

1. **Before Submitting**
   - Ensure all tests pass
   - Run code style checks
   - Update documentation if needed
   - Test your changes manually

2. **Pull Request Description**
   - Describe what the change does and why
   - Reference any related issues
   - Include screenshots for UI changes
   - List any breaking changes

3. **Review Process**
   - Address reviewer feedback promptly
   - Keep discussions constructive
   - Update the PR as needed
   - Ensure CI checks pass

## Release Process

The project uses semantic versioning:
- `MAJOR.MINOR.PATCH`
- Major: Breaking changes
- Minor: New features (backward compatible)
- Patch: Bug fixes (backward compatible)

## Getting Help

- **Questions**: Open a GitHub Discussion
- **Bugs**: Open a GitHub Issue with reproduction steps
- **Features**: Open a GitHub Issue with detailed requirements
- **Security**: Email security issues privately

## Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Documentation](https://vuejs.org/guide/)
- [Vuetify Documentation](https://vuetifyjs.com/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)

Thank you for contributing to Subterra!