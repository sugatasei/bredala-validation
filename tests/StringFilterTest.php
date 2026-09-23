<?php

use Bredala\Validation\Filters\StringFilter;
use Bredala\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class StringFieldTest extends TestCase
{
    // -------------------------------------------------------------------------
    // input / text
    // -------------------------------------------------------------------------

    public function testInputIsSingleLineAndTextIsMultiline()
    {
        self::assertSame('a b', StringFilter::input("a\nb"));
        self::assertSame("a\nb", StringFilter::text("a\nb"));
    }

    /**
     * @dataProvider emptyProvider
     */
    public function testEmptyValuesBecomeNull(mixed $input)
    {
        self::assertNull(StringFilter::input($input));
        self::assertNull(StringFilter::text($input));
    }

    public static function emptyProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'spaces' => ['   '],
            'tab' => ["\t"],
            'newline' => ["\n"],
            'non breaking space' => ["\xC2\xA0"],
        ];
    }

    /**
     * @dataProvider numericProvider
     */
    public function testNumericValuesAreCastToString(mixed $input, string $expected)
    {
        // Numerics short-circuit the whole sanitizing pipeline.
        self::assertSame($expected, StringFilter::input($input));
    }

    public static function numericProvider(): array
    {
        return [
            'int' => [42, '42'],
            'zero' => [0, '0'],
            'float' => [1.5, '1.5'],
            'numeric string' => ['42', '42'],
            'negative' => [-7, '-7'],
        ];
    }

    /**
     * @dataProvider nonStringProvider
     */
    public function testNonStringNonNumericValuesAreRejected(mixed $input)
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('type');

        StringFilter::input($input);
    }

    public static function nonStringProvider(): array
    {
        return [
            'array' => [[]],
            'true' => [true],
            'object' => [new stdClass()],
        ];
    }

    // -------------------------------------------------------------------------
    // Whitespace normalization
    // -------------------------------------------------------------------------

    public function testValueIsTrimmed()
    {
        self::assertSame('abc', StringFilter::input('   abc   '));
    }

    public function testConsecutiveSpacesAreCollapsed()
    {
        self::assertSame('a b', StringFilter::input('a     b'));
    }

    public function testInputTurnsEveryWhitespaceIntoASpace()
    {
        self::assertSame('a b c d', StringFilter::input("a\tb\nc\r\nd"));
    }

    public function testTextKeepsUpToTwoConsecutiveNewlines()
    {
        self::assertSame("a\n\nb", StringFilter::text("a\n\n\n\n\nb"));
    }

    public function testTextKeepsASingleNewline()
    {
        self::assertSame("a\nb", StringFilter::text("a\nb"));
    }

    public function testTextTrimsEachLine()
    {
        self::assertSame("a\nb", StringFilter::text("  a  \n  b  "));
    }

    public function testExoticSpacesBecomeRegularSpaces()
    {
        // U+2003 EM SPACE and U+00A0 NBSP are normalized, then collapsed.
        self::assertSame('a b', StringFilter::input("a\u{2003}\u{00A0}b"));
    }

    public function testNonPrintableCharactersAreRemoved()
    {
        self::assertSame('ab', StringFilter::input("a\x00\x07\x1Fb"));
    }

    public function testUnicodeReplacementCharacterIsRemoved()
    {
        self::assertSame('ab', StringFilter::input("a\u{FFFD}b"));
    }

    public function testAccentedCharactersArePreserved()
    {
        self::assertSame('éàü Français', StringFilter::input('  éàü   Français  '));
    }

    public function testEmojiArePreserved()
    {
        self::assertSame('hi 👋', StringFilter::input('hi 👋'));
    }

    // -------------------------------------------------------------------------
    // HTML handling
    // -------------------------------------------------------------------------

    public function testHtmlTagsAreStripped()
    {
        self::assertSame('bold', StringFilter::input('<b>bold</b>'));
    }

    public function testScriptContentSurvivesButTheTagsDoNot()
    {
        // strip_tags() removes the markup, not the text inside it.
        self::assertSame('alert(1)', StringFilter::input('<script>alert(1)</script>'));
    }

    public function testHtmlEntitiesAreDecoded()
    {
        self::assertSame('a&b', StringFilter::input('a&amp;b'));
    }

    public function testQuotesAreDecoded()
    {
        self::assertSame('it\'s "x"', StringFilter::input('it&#039;s &quot;x&quot;'));
    }

    public function testLessThanFollowedByADigitIsPreserved()
    {
        // stripTags() shields '<' + digit with a uniqid sentinel so a comparison
        // is not mistaken for a tag.
        self::assertSame('a <3 b', StringFilter::input('a <3 b'));
    }

    public function testStripTagsIsDeterministicDespiteUsingUniqid()
    {
        self::assertSame(
            StringFilter::stripTags('a <3 b <i>c</i>'),
            StringFilter::stripTags('a <3 b <i>c</i>')
        );
    }

    public function testStripTagsLeavesTheSentinelOutOfTheOutput()
    {
        self::assertStringNotContainsString('~', StringFilter::stripTags('a <3 b'));
    }

    // -------------------------------------------------------------------------
    // sanitizeUrl / sanitizeEmail / sanitizeType
    // -------------------------------------------------------------------------

    public function testSanitizeUrlStripsIllegalCharacters()
    {
        self::assertSame('http://example.test/a', StringFilter::sanitizeUrl('http://exa mple.test/a'));
    }

    public function testSanitizeEmailStripsIllegalCharacters()
    {
        // Only the illegal characters go; the letters between them stay.
        self::assertSame('ax@b.test', StringFilter::sanitizeEmail('a(x)@b.test'));
    }

    /**
     * @dataProvider falsyProvider
     */
    public function testSanitizeTypeReturnsNullForFalsyOrNonStringInput(mixed $input)
    {
        // Note: no 'type' error here, unlike sanitize() -- these filters just
        // return null, so a wrong type passes validation silently.
        self::assertNull(StringFilter::sanitizeUrl($input));
        self::assertNull(StringFilter::sanitizeEmail($input));
    }

    public static function falsyProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'zero' => [0],
            'zero string' => ['0'],
            'false' => [false],
            'array' => [[]],
            'int' => [42],
        ];
    }

    public function testSanitizeTypeAcceptsAnyFilterConstant()
    {
        self::assertSame(
            'a&#60;b&#62;c',
            StringFilter::sanitizeType('a<b>c', FILTER_SANITIZE_SPECIAL_CHARS)
        );
    }

    public function testSanitizeTypeReturnsNullWhenTheFilterFails()
    {
        // $type is passed as filter_var()'s filter id, not as a flag -- OR-ing a
        // flag into it makes the call fail and the method returns null, silently.
        self::assertNull(
            StringFilter::sanitizeType('abc', FILTER_SANITIZE_SPECIAL_CHARS | FILTER_FLAG_STRIP_LOW)
        );
    }
}
