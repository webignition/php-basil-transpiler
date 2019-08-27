<?php
/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace webignition\BasilTranspiler\Tests\Unit\Value;

use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilModel\Value\ValueTypes;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\Tests\DataProvider\Value\BrowserObjectValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\ElementValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\EnvironmentParameterValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\LiteralCssSelectorValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\LiteralStringValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\LiteralXpathExpressionValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\PageObjectValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\UnhandledValueDataProviderTrait;
use webignition\BasilTranspiler\UnknownObjectPropertyException;
use webignition\BasilTranspiler\Value\PageObjectValueTranspiler;

class PageObjectValueTranspilerTest extends \PHPUnit\Framework\TestCase
{
    use BrowserObjectValueDataProviderTrait;
    use ElementValueDataProviderTrait;
    use EnvironmentParameterValueDataProviderTrait;
    use LiteralCssSelectorValueDataProviderTrait;
    use LiteralStringValueDataProviderTrait;
    use LiteralXpathExpressionValueDataProviderTrait;
    use PageObjectValueDataProviderTrait;
    use UnhandledValueDataProviderTrait;

    /**
     * @var PageObjectValueTranspiler
     */
    private $transpiler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transpiler = PageObjectValueTranspiler::createTranspiler();
    }

    /**
     * @dataProvider pageObjectValueDataProvider
     */
    public function testHandlesDoesHandle(ValueInterface $value)
    {
        $this->assertTrue($this->transpiler->handles($value));
    }

    /**
     * @dataProvider browserObjectValueDataProvider
     * @dataProvider elementValueDataProvider
     * @dataProvider environmentParameterValueDataProvider
     * @dataProvider literalCssSelectorValueDataProvider
     * @dataProvider literalStringValueDataProvider
     * @dataProvider literalXpathExpressionValueDataProvider
     * @dataProvider unhandledValueDataProvider
     */
    public function testHandlesDoesNotHandle(ValueInterface $value)
    {
        $this->assertFalse($this->transpiler->handles($value));
    }

    public function testTranspileNonTranspilableModel()
    {
        $this->expectException(NonTranspilableModelException::class);
        $this->expectExceptionMessage('Non-transpilable model "webignition\BasilModel\Value\ObjectValue"');

        $value = new ObjectValue(ValueTypes::DATA_PARAMETER, '', '', '');

        $this->transpiler->transpile($value);
    }

    public function testTranspileThrowsUnknownObjectPropertyException()
    {
        $value = new ObjectValue(
            ValueTypes::PAGE_OBJECT_PROPERTY,
            '$page.foo',
            ObjectNames::PAGE,
            'foo'
        );

        $this->expectException(UnknownObjectPropertyException::class);
        $this->expectExceptionMessage('Unknown object property "foo"');

        $this->transpiler->transpile($value);
    }
}
