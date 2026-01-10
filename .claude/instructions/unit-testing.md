# Unit Testing Instructions for Spinsearch

## Core Principle
**For every new business logic component, create comprehensive unit tests BEFORE marking the feature complete.**

## When to Create Unit Tests

### Always Create Unit Tests For:
1. **New Service Classes** - Any class in `app/Services/`
2. **New Support Classes** - Any class in `app/Support/`
3. **New Background Jobs** - Any class in `app/Jobs/`
4. **New Job Traits** - Any trait in `app/Jobs/Concerns/`
5. **Complex Controller Methods** - Methods with >10 lines of business logic
6. **New Model Methods** - Any computed attributes, accessors, or business logic methods

### When Feature Tests Are Sufficient:
- Simple CRUD controllers (Create, Read, Update, Delete)
- Standard Laravel authentication flows (already tested by Breeze)
- Route definitions (covered by RouteTest)

## Unit Test Standards

### Test Coverage Requirements:
- ✅ **Normal/Happy Path** - Standard usage scenarios
- ✅ **Edge Cases** - Boundary conditions (empty, null, zero, max values)
- ✅ **Error Cases** - Invalid inputs, missing data, exceptions
- ✅ **Data Integrity** - Relationship loading, cascading effects
- ✅ **Special Characters** - Unicode, HTML, SQL injection attempts

### Test File Organization:
```php
<?php

namespace Tests\Unit\[ComponentType];

use App\[Namespace]\[ClassName];
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class [ClassName]Test extends TestCase
{
    use RefreshDatabase; // For database-dependent tests

    /** @test */
    public function descriptive_test_name_in_snake_case()
    {
        // Arrange - Set up test data

        // Act - Execute the method

        // Assert - Verify results
    }
}
```

### Naming Conventions:
- **Test Files**: `[ClassName]Test.php`
- **Test Methods**: Use snake_case with descriptive names
  - ✅ `truncate_description_returns_null_for_empty_input`
  - ✅ `artist_json_ld_includes_all_same_as_links`
  - ❌ `test1`, `testBasic`, `testMethod`

### Test Quality Checklist:
- [ ] Each test method tests ONE behavior
- [ ] Test names describe WHAT is being tested and EXPECTED result
- [ ] All edge cases identified and tested
- [ ] Null safety verified for all parameters
- [ ] Relationship loading states tested (loaded vs not loaded)
- [ ] No hardcoded IDs or magic numbers (use factories)
- [ ] No test interdependencies (each test can run independently)

## Component-Specific Guidelines

### Service Classes (e.g., SeoService)
**Test Coverage:** 95%+ of all public methods

**Required Tests:**
1. All public methods with normal inputs
2. Null/empty input handling
3. Special character encoding (Unicode, HTML, URLs)
4. Truncation/formatting edge cases
5. Optional field omission
6. Relationship loading scenarios

**Example:**
```php
/** @test */
public function truncate_description_returns_null_for_null_input()
{
    $result = SeoService::truncateDescription(null);

    $this->assertNull($result);
}

/** @test */
public function truncate_description_truncates_at_exact_boundary()
{
    $text = str_repeat('a', 160);
    $result = SeoService::truncateDescription($text);

    $this->assertEquals($text, $result);
}
```

### Support Classes (e.g., AdminJobManager)
**Test Coverage:** 90%+ of all public methods

**Required Tests:**
1. Job dispatch with valid parameters
2. Job dispatch with missing/invalid parameters
3. Multi-driver support (Redis vs Database)
4. Error handling and exceptions
5. Data aggregation algorithms
6. Memory efficiency (large datasets)

**Example:**
```php
/** @test */
public function dispatch_job_returns_error_for_unknown_job_key()
{
    $manager = new AdminJobManager();

    $result = $manager->dispatchJob('invalid_key');

    $this->assertFalse($result['dispatched']);
    $this->assertEquals('Unknown job type', $result['message']);
}
```

### Background Jobs
**Test Coverage:** 80%+ of handle() method logic

**Required Tests:**
1. Successful execution path
2. API rate limit handling (429 responses)
3. Network timeout handling
4. Malformed API responses
5. Database constraint violations
6. Idempotency verification
7. Resumability (cursor/checkpoint logic)

**Example:**
```php
/** @test */
public function handle_creates_artist_from_wikidata_response()
{
    Http::fake([
        'query.wikidata.org/*' => Http::response([
            'results' => [
                'bindings' => [
                    [
                        'artist' => ['value' => 'http://www.wikidata.org/entity/Q1299'],
                        'name' => ['value' => 'The Beatles'],
                    ],
                ],
            ],
        ]),
    ]);

    $job = new WikidataSeedArtistIds();
    $job->handle();

    $this->assertDatabaseHas('artists', [
        'wikidata_qid' => 'Q1299',
        'name' => 'The Beatles',
    ]);
}
```

### Job Traits/Concerns
**Test Coverage:** 85%+ of all trait methods

**Required Tests:**
1. State transitions (pending → running → success/failed)
2. Metrics tracking (increment counters)
3. Cursor persistence across failures
4. Rate limit detection and retry logic
5. Heartbeat recording

**Example:**
```php
/** @test */
public function start_job_run_creates_running_status()
{
    $job = new class {
        use TracksJobMetrics;
        protected function jobRunName(): string { return 'TestJob'; }
    };

    $run = $job->startJobRun();

    $this->assertEquals('running', $run->status);
    $this->assertNotNull($run->started_at);
}
```

### Controllers
**Test Coverage:** 80%+ for complex business logic methods

**Only test private/complex methods that:**
- Contain algorithmic logic (sorting, grouping, filtering)
- Perform data transformations
- Have multiple edge cases
- Are critical for SEO or UX

**Do NOT unit test:**
- Simple passthrough methods
- Standard Laravel resource controllers
- Methods fully covered by feature tests

**Example:**
```php
/** @test */
public function deduplicate_links_prioritizes_official_links()
{
    $artist = Artist::factory()->create();
    ArtistLink::factory()->create([
        'artist_id' => $artist->id,
        'type' => 'spotify',
        'url' => 'https://open.spotify.com/artist/unofficial',
        'is_official' => false,
    ]);
    ArtistLink::factory()->create([
        'artist_id' => $artist->id,
        'type' => 'spotify',
        'url' => 'https://open.spotify.com/artist/official',
        'is_official' => true,
    ]);

    $controller = new ArtistController();
    $result = $controller->deduplicateLinks($artist->links);

    $this->assertCount(1, $result['spotify']);
    $this->assertStringContainsString('official', $result['spotify'][0]->url);
}
```

## Testing Workflow

### For New Features:
1. **Write tests first** (TDD) or **immediately after** implementation
2. Run tests: `php artisan test --filter=[ClassName]Test`
3. Verify 100% of new code is covered
4. Fix any failing tests
5. Commit tests WITH implementation (not separately)

### For Bug Fixes:
1. **Write failing test** that reproduces the bug
2. Fix the bug
3. Verify test now passes
4. Commit test + fix together

### Before Committing:
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test --filter=SeoServiceTest

# Check coverage (if configured)
php artisan test --coverage --min=85
```

## Common Testing Patterns

### Mock HTTP Requests:
```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'api.example.com/*' => Http::response(['data' => 'value'], 200),
    'api.example.com/error' => Http::response([], 429),
]);
```

### Mock Queue Dispatching:
```php
use Illuminate\Support\Facades\Queue;

Queue::fake();

// ... code that dispatches jobs

Queue::assertPushed(SomeJob::class, function ($job) {
    return $job->someProperty === 'expected value';
});
```

### Test Database Transactions:
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class SomeTest extends TestCase
{
    use RefreshDatabase; // Rolls back after each test

    /** @test */
    public function creates_record()
    {
        $model = Model::factory()->create();

        $this->assertDatabaseHas('models', ['id' => $model->id]);
    }
}
```

### Test Relationship Loading:
```php
/** @test */
public function includes_data_when_relationship_loaded()
{
    $artist = Artist::factory()->create();
    $artist->load('genres');

    $result = SomeService::processArtist($artist);

    $this->assertArrayHasKey('genres', $result);
}

/** @test */
public function omits_data_when_relationship_not_loaded()
{
    $artist = Artist::factory()->create(); // Without load()

    $result = SomeService::processArtist($artist);

    $this->assertArrayNotHasKey('genres', $result);
}
```

## Anti-Patterns to Avoid

### ❌ Don't Do This:
```php
// 1. Testing multiple behaviors in one test
public function test_everything()
{
    // Tests 5 different methods
}

// 2. Using magic numbers
public function test_something()
{
    $artist = Artist::find(123); // Hardcoded ID
}

// 3. No assertions
public function test_no_error()
{
    $service->doSomething(); // No assertion!
}

// 4. Testing framework code
public function test_eloquent_save()
{
    $model = new Model();
    $model->save();
    // Don't test Laravel's save() method
}
```

### ✅ Do This Instead:
```php
// 1. One behavior per test
public function truncates_long_descriptions()
{
    $text = str_repeat('a', 200);
    $result = Service::truncate($text);
    $this->assertEquals(160, mb_strlen($result));
}

// 2. Use factories
public function processes_artist_data()
{
    $artist = Artist::factory()->create();
    // ...
}

// 3. Always assert
public function creates_job_run()
{
    $service->execute();
    $this->assertDatabaseHas('job_runs', ['status' => 'success']);
}

// 4. Test YOUR code
public function calculates_quality_score_correctly()
{
    $artist = Artist::factory()->create([
        'wikipedia_url' => 'https://en.wikipedia.org/wiki/Test',
        'spotify_artist_id' => 'abc123',
    ]);

    $score = $artist->computeQualityScore();
    $this->assertEquals(25, $score); // Wikipedia (15) + Spotify (10)
}
```

## Test Coverage Goals

| Component Type | Minimum Coverage | Target Coverage |
|----------------|------------------|-----------------|
| Services | 90% | 95% |
| Support Classes | 85% | 95% |
| Background Jobs | 75% | 85% |
| Job Concerns | 80% | 90% |
| Controllers | 70% | 85% |
| Models | 85% | 95% |

## When in Doubt:
- **Ask:** "If this method breaks, will tests catch it?"
- **Ask:** "Can I confidently refactor this code with these tests?"
- **Ask:** "Do these tests document expected behavior?"

If the answer is NO to any question, add more tests.

---

## Quick Reference: Test Command Cheat Sheet

```bash
# Run all tests
php artisan test

# Run specific file
php artisan test --filter=SeoServiceTest

# Run specific test method
php artisan test --filter=SeoServiceTest::truncate_description_returns_null_for_null_input

# Run tests in parallel (faster)
php artisan test --parallel

# Run with coverage report
php artisan test --coverage

# Run with minimum coverage enforcement
php artisan test --coverage --min=85

# Watch mode (re-run on file changes)
php artisan test --watch
```

---

**Last Updated:** 2026-01-10
**Applies To:** All new features, bug fixes, and refactoring work
