<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Secret;

use PHPUnit\Framework\TestCase;
use Sputnik\Secret\SecretRegistry;

final class SecretRegistryTest extends TestCase
{
    private SecretRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new SecretRegistry();
        $this->registry->declareSecrets(['apiToken', 'dbPassword']);
    }

    public function testDeclaredNamesAreRecognised(): void
    {
        $this->assertTrue($this->registry->isSecret('apiToken'));
        $this->assertFalse($this->registry->isSecret('dbHost'));
    }

    public function testRememberedValueIsReturned(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame(['ghp_abcdefghij'], $this->registry->values());
    }

    public function testValuesAreOrderedLongestFirst(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');
        $this->registry->remember('dbPassword', 'ghp_abc');

        $this->assertSame(['ghp_abcdefghij', 'ghp_abc'], $this->registry->values());
    }

    public function testBooleanValueIsRememberedAsRenderedText(): void
    {
        $this->registry->declareSecrets(['xdebugEnabled']);
        $this->registry->remember('xdebugEnabled', false);

        $this->assertSame(['false'], $this->registry->values());
    }

    public function testNullValueIsNotRememberedAndReportsDiagnostic(): void
    {
        $this->registry->remember('apiToken', null);

        $this->assertSame([], $this->registry->values());
        $this->assertSame(["secret 'apiToken' could not be resolved"], $this->registry->takeDiagnostics());
    }

    public function testEmptyValueIsNotRememberedAndReportsDiagnostic(): void
    {
        $this->registry->remember('apiToken', '');

        $this->assertSame([], $this->registry->values());
        $this->assertSame(
            ["secret 'apiToken' resolves to an empty value and cannot be masked"],
            $this->registry->takeDiagnostics(),
        );
    }

    public function testShortValueIsRememberedAndReportsDiagnostic(): void
    {
        $this->registry->remember('apiToken', 'abc');

        $this->assertSame(['abc'], $this->registry->values());
        $this->assertSame(
            ["secret 'apiToken' has a short value; unrelated output may be masked too"],
            $this->registry->takeDiagnostics(),
        );
    }

    public function testDiagnosticsAreReportedOncePerName(): void
    {
        $this->registry->remember('apiToken', 'abc');
        $this->registry->remember('apiToken', 'abc');

        $this->assertCount(1, $this->registry->takeDiagnostics());
    }

    public function testTakeDiagnosticsDrains(): void
    {
        $this->registry->remember('apiToken', 'abc');
        $this->registry->takeDiagnostics();

        $this->assertSame([], $this->registry->takeDiagnostics());
    }

    public function testLongValueReportsNoDiagnostic(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame([], $this->registry->takeDiagnostics());
    }

    public function testRememberingShortThenLongValueForSameNameLeavesNoDiagnostic(): void
    {
        $this->registry->remember('apiToken', 'abc');
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame([], $this->registry->takeDiagnostics());
    }

    public function testRememberingTwoDifferentValuesForSameNameReturnsBoth(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');
        $this->registry->remember('apiToken', 'ghp_other12345');

        $this->assertSame(['ghp_abcdefghij', 'ghp_other12345'], $this->registry->values());
    }

    public function testRememberingLongThenShortValueForSameNameReportsShortDiagnostic(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');
        $this->registry->remember('apiToken', 'abc');

        $this->assertSame(
            ["secret 'apiToken' has a short value; unrelated output may be masked too"],
            $this->registry->takeDiagnostics(),
        );
    }
}
