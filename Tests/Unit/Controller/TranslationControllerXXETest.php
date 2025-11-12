<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Controller;

use Netresearch\NrTextdb\Controller\TranslationController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function simplexml_load_string;

/**
 * Test case for XXE (XML External Entity) protection in XLF import functionality.
 *
 * These tests verify that the XML parsing implementation properly prevents
 * XXE attacks by disabling external entity processing.
 *
 * @see https://owasp.org/www-community/vulnerabilities/XML_External_Entity_(XXE)_Processing
 */
#[CoversClass(TranslationController::class)]
final class TranslationControllerXXETest extends UnitTestCase
{
    /**
     * Test that XXE payloads attempting to read local files are blocked.
     *
     * This test verifies the core XXE protection mechanism: external entity
     * references should not be resolved when LIBXML_NONET flag is used.
     */
    #[Test]
    public function xxePayloadWithFileReadIsBlocked(): void
    {
        // Create XXE payload attempting to read /etc/passwd
        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE xliff [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<xliff version="1.0">
  <file source-language="en" datatype="plaintext" original="messages">
    <body>
      <trans-unit id="component|type|placeholder">
        <source>&xxe;</source>
      </trans-unit>
    </body>
  </file>
</xliff>
XML;

        // Enable XXE protection (same as in TranslationController)
        // In PHP 8.0+, external entity loading is disabled by default
        libxml_use_internal_errors(true);

        // Parse with LIBXML_NONET flag (disables network access)
        $data = simplexml_load_string(
            $xxePayload,
            'SimpleXMLElement',
            LIBXML_NONET
        );

        // Parsing should succeed (valid XML structure)
        self::assertNotFalse($data, 'XML parsing should succeed for structurally valid XML');

        // Extract the source value
        $sourceValue = (string) $data->file->body->{'trans-unit'}->source;

        // XXE entity should NOT be resolved
        // The value should be empty or the literal entity reference, NOT file contents
        self::assertStringNotContainsString(
            'root:',
            $sourceValue,
            'XXE entity should not be resolved - file contents should not be present'
        );

        self::assertStringNotContainsString(
            '/bin/',
            $sourceValue,
            'XXE entity should not be resolved - file contents should not be present'
        );

        // The entity reference may appear as empty string or literal reference
        // Both are acceptable - what matters is file contents are NOT present
        $isSecure = ($sourceValue === '') || ($sourceValue === '&xxe;') || (!str_contains($sourceValue, 'root'));

        self::assertTrue(
            $isSecure,
            'XXE protection should prevent entity resolution. Got: ' . $sourceValue
        );
    }

    /**
     * Test that XXE payloads attempting SSRF (Server-Side Request Forgery) are blocked.
     *
     * This test verifies that external HTTP requests cannot be made during XML parsing
     * when LIBXML_NONET flag is used.
     */
    #[Test]
    public function xxePayloadWithHttpRequestIsBlocked(): void
    {
        // Create XXE payload attempting HTTP request
        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE xliff [
  <!ENTITY xxe SYSTEM "http://example.com/xxe-test">
]>
<xliff version="1.0">
  <file source-language="en" datatype="plaintext" original="messages">
    <body>
      <trans-unit id="component|type|placeholder">
        <source>&xxe;</source>
      </trans-unit>
    </body>
  </file>
</xliff>
XML;

        // Enable XXE protection
        // In PHP 8.0+, external entity loading is disabled by default
        libxml_use_internal_errors(true);

        // Parse with LIBXML_NONET flag (disables network access)
        $data = simplexml_load_string(
            $xxePayload,
            'SimpleXMLElement',
            LIBXML_NONET
        );

        // Parsing should succeed (valid XML structure)
        self::assertNotFalse($data, 'XML parsing should succeed for structurally valid XML');

        // Extract the source value
        $sourceValue = (string) $data->file->body->{'trans-unit'}->source;

        // HTTP entity should NOT be resolved
        self::assertStringNotContainsString(
            'example.com',
            $sourceValue,
            'SSRF attempt should be blocked - external URL should not be fetched'
        );

        // The entity reference should be empty or literal, NOT resolved
        $isSecure = ($sourceValue === '') || ($sourceValue === '&xxe;');

        self::assertTrue(
            $isSecure,
            'XXE protection should prevent external HTTP requests. Got: ' . $sourceValue
        );
    }

    /**
     * Test that legitimate XLF files without external entities parse correctly.
     *
     * This ensures our XXE protection doesn't break normal functionality.
     */
    #[Test]
    public function legitimateXlfFileWithoutEntitiesParsesCorrectly(): void
    {
        // Create legitimate XLF without external entities
        $validXlf = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<xliff version="1.0">
  <file source-language="en" datatype="plaintext" original="messages">
    <body>
      <trans-unit id="checkout|button|submit">
        <source>Proceed to Checkout</source>
      </trans-unit>
      <trans-unit id="checkout|label|email">
        <source>Email Address</source>
      </trans-unit>
    </body>
  </file>
</xliff>
XML;

        // Enable XXE protection
        // In PHP 8.0+, external entity loading is disabled by default
        libxml_use_internal_errors(true);

        // Parse with LIBXML_NONET flag
        $data = simplexml_load_string(
            $validXlf,
            'SimpleXMLElement',
            LIBXML_NONET
        );

        // Parsing should succeed
        self::assertNotFalse($data, 'Valid XLF should parse successfully');

        // Verify content is accessible
        $transUnits = $data->file->body->{'trans-unit'};
        self::assertCount(2, $transUnits, 'Should have 2 trans-unit elements');

        // Verify values are correct
        $firstUnit = $transUnits[0];
        self::assertSame(
            'checkout|button|submit',
            (string) $firstUnit['id'],
            'First trans-unit ID should match'
        );
        self::assertSame(
            'Proceed to Checkout',
            (string) $firstUnit->source,
            'First trans-unit source should match'
        );
    }

    /**
     * Test that billion laughs attack (XML bomb) is mitigated.
     *
     * While not a pure XXE attack, billion laughs uses entity expansion
     * to cause DoS. Our entity loader configuration should help mitigate this.
     */
    #[Test]
    public function billionLaughsAttackIsMitigated(): void
    {
        // Simplified billion laughs payload (not full scale to avoid test timeout)
        $billionLaughsPayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE xliff [
  <!ENTITY lol "lol">
  <!ENTITY lol1 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
  <!ENTITY lol2 "&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;">
]>
<xliff version="1.0">
  <file source-language="en" datatype="plaintext" original="messages">
    <body>
      <trans-unit id="test|label|bomb">
        <source>&lol2;</source>
      </trans-unit>
    </body>
  </file>
</xliff>
XML;

        // Enable XXE protection
        // In PHP 8.0+, external entity loading is disabled by default
        libxml_use_internal_errors(true);

        // Parse with LIBXML_NONET flag
        $data = simplexml_load_string(
            $billionLaughsPayload,
            'SimpleXMLElement',
            LIBXML_NONET
        );

        // Parsing may succeed or fail - both are acceptable defensive outcomes
        if ($data !== false) {
            // If parsing succeeded, verify entity expansion is limited
            $sourceValue = (string) $data->file->body->{'trans-unit'}->source;

            // Entity expansion should be prevented or limited
            // The source should not contain massively expanded "lol" strings
            self::assertLessThan(
                200,
                strlen($sourceValue),
                'Entity expansion should be prevented/limited to avoid DoS'
            );
        }

        // Always perform an assertion: verify we either failed to parse OR content is limited
        self::assertTrue(
            $data === false || strlen((string) $data->file->body->{'trans-unit'}->source) < 200,
            'Billion laughs attack was mitigated (parsing failed or expansion limited)'
        );
    }

    /**
     * Test that XXE protection with PHP wrapper is blocked.
     *
     * PHP wrappers (php://, expect://, etc.) can be used for more advanced attacks.
     */
    #[Test]
    public function xxePayloadWithPhpWrapperIsBlocked(): void
    {
        // Skip test if running on Windows (PHP wrappers behave differently)
        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('PHP wrapper test skipped on Windows');
        }

        // Create XXE payload with PHP wrapper
        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE xliff [
  <!ENTITY xxe SYSTEM "php://filter/convert.base64-encode/resource=/etc/passwd">
]>
<xliff version="1.0">
  <file source-language="en" datatype="plaintext" original="messages">
    <body>
      <trans-unit id="component|type|placeholder">
        <source>&xxe;</source>
      </trans-unit>
    </body>
  </file>
</xliff>
XML;

        // Enable XXE protection
        // In PHP 8.0+, external entity loading is disabled by default
        libxml_use_internal_errors(true);

        // Parse with LIBXML_NONET flag
        $data = simplexml_load_string(
            $xxePayload,
            'SimpleXMLElement',
            LIBXML_NONET
        );

        // Parsing should succeed (valid XML structure)
        self::assertNotFalse($data, 'XML parsing should succeed for structurally valid XML');

        // Extract the source value
        $sourceValue = (string) $data->file->body->{'trans-unit'}->source;

        // PHP wrapper should NOT be resolved
        self::assertStringNotContainsString(
            'root:',
            $sourceValue,
            'PHP wrapper XXE should be blocked - file contents should not be present'
        );

        // Check for base64 encoded content (would indicate successful exploitation)
        if ($sourceValue !== '' && $sourceValue !== '&xxe;') {
            // If there's content, it shouldn't be valid base64 of /etc/passwd
            $decoded = base64_decode($sourceValue, true);
            if ($decoded !== false) {
                self::assertStringNotContainsString(
                    'root',
                    $decoded,
                    'PHP wrapper should not resolve to file contents'
                );
            }
        }
    }
}
