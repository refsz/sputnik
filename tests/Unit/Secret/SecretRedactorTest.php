<?php

declare(strict_types=1);

namespace Sputnik\Tests\Unit\Secret;

use PHPUnit\Framework\TestCase;
use Sputnik\Secret\SecretRedactor;
use Sputnik\Secret\SecretRegistry;

final class SecretRedactorTest extends TestCase
{
    private SecretRegistry $registry;

    private SecretRedactor $redactor;

    protected function setUp(): void
    {
        $this->registry = new SecretRegistry();
        $this->registry->declareSecrets(['apiToken', 'pin', 'quoted']);
        $this->redactor = new SecretRedactor($this->registry);
    }

    public function testAValueSplitByAColourCodeIsStillMasked(): void
    {
        // A tool that colours a value puts an escape sequence next to or inside
        // it, and then the characters are no longer one run for a search to
        // find. Reachable today: a pass-through task forwards `--ansi`, the tool
        // honours it, and its output goes through this redactor.
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $redacted = $this->redactor->redact("token=ghp_\033[32mabcdefghij\033[0m done");

        $this->assertStringNotContainsString('abcdefghij', $redacted);
        $this->assertStringContainsString('***', $redacted);
    }

    public function testAShortValueWithColourAroundItIsMasked(): void
    {
        // The word-boundary rule for a short value looked at the character
        // before it, and an escape sequence ends in `m` - a word character. So a
        // highlighted short secret was never redacted at all, which is the worse
        // half of this: the value arrives whole and still gets through.
        $this->registry->remember('pin', '1234');

        $redacted = $this->redactor->redact("pin: \033[32m1234\033[0m");

        $this->assertStringNotContainsString('1234', $redacted);
    }

    public function testMaskingAValueKeepsTheTerminalState(): void
    {
        // Dropping the escape sequences with the value would leave the colour
        // that follows unterminated, so they are kept.
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $redacted = $this->redactor->redact("ghp_\033[32mabcdefghij\033[0m rest");

        $this->assertStringContainsString("\033[0m", $redacted, 'The reset has to survive');
    }

    public function testAShortValueSplitByAColourCodeIsMaskedAtAWordBoundary(): void
    {
        $this->registry->remember('pin', '1234');

        $redacted = $this->redactor->redact("pin is \033[31m12\033[0m34 now");

        $this->assertStringNotContainsString('34 now', $redacted);
        $this->assertStringContainsString('***', $redacted);
    }

    public function testAPlainTextIsNotTouchedByTheColourPath(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame('token=*** done', $this->redactor->redact('token=ghp_abcdefghij done'));
    }

    public function testTextWithoutSecretsIsUnchanged(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame('nothing to see', $this->redactor->redact('nothing to see'));
    }

    public function testLongValueIsReplacedAnywhere(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame(
            'Authorization: Bearer ***',
            $this->redactor->redact('Authorization: Bearer ghp_abcdefghij'),
        );
    }

    public function testLongValueIsReplacedInsideAnotherWord(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame(
            'url=https://x/***?v=1',
            $this->redactor->redact('url=https://x/ghp_abcdefghij?v=1'),
        );
    }

    public function testShortValueIsReplacedOnlyAtWordBoundaries(): void
    {
        $this->registry->remember('pin', 'abc');

        $this->assertSame(
            'value *** stays abcdef and my-abc',
            $this->redactor->redact('value abc stays abcdef and my-abc'),
        );
    }

    public function testQuotedShellFormIsReplaced(): void
    {
        $this->registry->remember('quoted', "pa'ss word");

        $this->assertSame(
            'run --pw=***',
            $this->redactor->redact('run --pw=' . escapeshellarg("pa'ss word")),
        );
    }

    public function testLongerValueIsReplacedFirst(): void
    {
        $this->registry->declareSecrets(['outer', 'inner']);
        $this->registry->remember('outer', 'abcdefghij');
        $this->registry->remember('inner', 'abcdefgh');

        $this->assertSame('***', $this->redactor->redact('abcdefghij'));
    }

    public function testValuesWithRegexMetaCharactersAreLiteral(): void
    {
        $this->registry->declareSecrets(['weird']);
        $this->registry->remember('weird', 'a.c');

        $this->assertSame('*** but abc stays', $this->redactor->redact('a.c but abc stays'));
    }

    public function testBooleanValueIsReplacedAtWordBoundaries(): void
    {
        $this->registry->declareSecrets(['flag']);
        $this->registry->remember('flag', false);

        $this->assertSame('xdebug: ***', $this->redactor->redact('xdebug: false'));
    }

    public function testEightCharValueIsReplacedEmbedded(): void
    {
        $this->registry->declareSecrets(['boundary']);
        $this->registry->remember('boundary', 'abcdefgh');

        $this->assertSame('xx***yy', $this->redactor->redact('xxabcdefghyy'));
    }

    public function testSevenCharValueIsReplacedOnlyAtBoundary(): void
    {
        $this->registry->declareSecrets(['sevenChar', 'eightChar']);
        $this->registry->remember('sevenChar', 'abcdefg');
        $this->registry->remember('eightChar', 'abcdefgh');

        $this->assertSame(
            'xx***yy *** end',
            $this->redactor->redact('xxabcdefghyy abcdefg end'),
        );
    }

    public function testMultiLineValueIndentedAsShellExecutorStreamsItIsFullyMasked(): void
    {
        $pem = "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BA\n-----END PRIVATE KEY-----";
        $this->registry->declareSecrets(['pemKey']);
        $this->registry->remember('pemKey', $pem);

        // ShellExecutor::streamOutput() rewrites "\n" into "\n  " before the
        // text ever reaches the redactor, so the whole value no longer
        // occurs as a substring; only the per-line accumulation lets this
        // come out fully masked.
        $indented = '  ' . str_replace("\n", "\n  ", $pem);

        $this->assertSame("  ***\n  ***\n  ***", $this->redactor->redact($indented));
    }

    public function testSingleLineValueIsUnaffectedByMultiLineHandling(): void
    {
        $this->registry->remember('apiToken', 'ghp_abcdefghij');

        $this->assertSame(
            'Authorization: Bearer ***',
            $this->redactor->redact('Authorization: Bearer ghp_abcdefghij'),
        );
    }

    public function testWhitespaceOnlyLineOfAMultiLineSecretIsNeverRegisteredAsAValue(): void
    {
        $this->registry->remember('apiToken', "lineA\n  \nlineB");

        $this->assertSame('a,  ,b', $this->redactor->redact('a,  ,b'));
    }
}
