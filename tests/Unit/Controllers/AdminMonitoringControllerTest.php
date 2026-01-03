<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\AdminMonitoringController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AdminMonitoringControllerTest extends TestCase
{
    private AdminMonitoringController $controller;

    private \ReflectionMethod $formatDuration;

    private \ReflectionMethod $summarizeException;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new AdminMonitoringController;

        $reflection = new ReflectionClass($this->controller);

        $this->formatDuration = $reflection->getMethod('formatDuration');
        $this->formatDuration->setAccessible(true);

        $this->summarizeException = $reflection->getMethod('summarizeException');
        $this->summarizeException->setAccessible(true);
    }

    // -------------------------------------------------------------------------
    // formatDuration Tests
    // -------------------------------------------------------------------------

    public function test_format_duration_singular_minute(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 1);

        $this->assertEquals('1 minute', $result);
    }

    public function test_format_duration_plural_minutes(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 5);

        $this->assertEquals('5 minutes', $result);
    }

    public function test_format_duration_zero_minutes(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 0);

        $this->assertEquals('0 minutes', $result);
    }

    public function test_format_duration_exactly_one_hour(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 60);

        $this->assertEquals('1 hour', $result);
    }

    public function test_format_duration_multiple_hours(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 120);

        $this->assertEquals('2 hours', $result);
    }

    public function test_format_duration_hours_and_minutes(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 90);

        $this->assertEquals('1 hour 30 mins', $result);
    }

    public function test_format_duration_hours_and_one_minute(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 61);

        $this->assertEquals('1 hour 1 min', $result);
    }

    public function test_format_duration_exactly_one_day(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 1440);

        $this->assertEquals('1 day', $result);
    }

    public function test_format_duration_multiple_days(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 2880);

        $this->assertEquals('2 days', $result);
    }

    public function test_format_duration_days_and_hours(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 1500); // 1 day and 1 hour

        $this->assertEquals('1 day 1 hour', $result);
    }

    public function test_format_duration_days_and_multiple_hours(): void
    {
        $result = $this->formatDuration->invoke($this->controller, 1560); // 1 day and 2 hours

        $this->assertEquals('1 day 2 hours', $result);
    }

    public function test_format_duration_complex_duration(): void
    {
        // 3 days and 5 hours = 3*1440 + 5*60 = 4320 + 300 = 4620
        $result = $this->formatDuration->invoke($this->controller, 4620);

        $this->assertEquals('3 days 5 hours', $result);
    }

    // -------------------------------------------------------------------------
    // summarizeException Tests
    // -------------------------------------------------------------------------

    public function test_summarize_exception_returns_null_for_null_input(): void
    {
        $result = $this->summarizeException->invoke($this->controller, null);

        $this->assertNull($result);
    }

    public function test_summarize_exception_returns_null_for_empty_string(): void
    {
        $result = $this->summarizeException->invoke($this->controller, '');

        $this->assertNull($result);
    }

    public function test_summarize_exception_returns_first_line(): void
    {
        $exception = "First line of exception\nSecond line\nThird line";

        $result = $this->summarizeException->invoke($this->controller, $exception);

        $this->assertEquals('First line of exception', $result);
    }

    public function test_summarize_exception_returns_single_line_unchanged(): void
    {
        $exception = 'Single line exception message';

        $result = $this->summarizeException->invoke($this->controller, $exception);

        $this->assertEquals('Single line exception message', $result);
    }

    public function test_summarize_exception_truncates_long_first_line(): void
    {
        // Create a string longer than 200 characters
        $longLine = str_repeat('a', 250);
        $exception = $longLine."\nSecond line";

        $result = $this->summarizeException->invoke($this->controller, $exception);

        $this->assertEquals(200, strlen($result));
        $this->assertStringEndsWith('...', $result);
        $this->assertEquals(str_repeat('a', 197).'...', $result);
    }

    public function test_summarize_exception_does_not_truncate_exactly_200_chars(): void
    {
        $line = str_repeat('b', 200);
        $exception = $line."\nSecond line";

        $result = $this->summarizeException->invoke($this->controller, $exception);

        $this->assertEquals(200, strlen($result));
        $this->assertEquals($line, $result);
    }

    public function test_summarize_exception_handles_multiline_stack_trace(): void
    {
        $exception = <<<'EOT'
App\Exceptions\SomeException: Something went wrong
#0 /var/www/app/Http/Controllers/SomeController.php(42): doSomething()
#1 /var/www/vendor/laravel/framework/src/Illuminate/Routing/Controller.php(54): callAction()
EOT;

        $result = $this->summarizeException->invoke($this->controller, $exception);

        $this->assertEquals('App\Exceptions\SomeException: Something went wrong', $result);
    }
}
