<?php
/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace webignition\BasilTranspiler\Tests\Functional\Value;

use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ObjectValueType;
use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilTranspiler\Model\UseStatementCollection;
use webignition\BasilTranspiler\Model\VariablePlaceholderCollection;
use webignition\BasilTranspiler\Tests\Functional\AbstractTestCase;
use webignition\BasilTranspiler\Tests\Services\ExecutableCallFactory;
use webignition\BasilTranspiler\Value\ValueTranspiler;
use webignition\BasilTranspiler\VariableNames;

class ValueTranspilerTest extends AbstractTestCase
{
    const PANTHER_CLIENT_VARIABLE_NAME = 'self::$client';
    const VARIABLE_IDENTIFIERS = [
        VariableNames::PANTHER_CLIENT => self::PANTHER_CLIENT_VARIABLE_NAME,
    ];

    /**
     * @var ValueTranspiler
     */
    private $transpiler;

    /**
     * @var ExecutableCallFactory
     */
    private $executableCallFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transpiler = ValueTranspiler::createTranspiler();
        $this->executableCallFactory = ExecutableCallFactory::createFactory();
    }

    /**
     * @dataProvider transpileDataProvider
     */
    public function testTranspile(
        string $fixture,
        ValueInterface $model,
        VariablePlaceholderCollection $expectedVariablePlaceholders,
        $expectedExecutedResult,
        array $additionalVariableIdentifiers = []
    ) {
        $transpilationResult = $this->transpiler->transpile($model);

        $this->assertEquals(new UseStatementCollection(), $transpilationResult->getUseStatements());
        $this->assertEquals($expectedVariablePlaceholders, $transpilationResult->getVariablePlaceholders());

        $executableCall = $this->executableCallFactory->createWithReturn(
            $transpilationResult,
            array_merge(
                self::VARIABLE_IDENTIFIERS,
                $additionalVariableIdentifiers
            ),
            [
                'self::$client->request(\'GET\', \'' . $fixture . '\'); ',
            ]
        );

        $this->assertEquals($expectedExecutedResult, eval($executableCall));
    }

    public function transpileDataProvider(): array
    {
        return [
            'browser property: size' => [
                'fixture' => '/basic.html',
                'model' => new ObjectValue(ObjectValueType::BROWSER_PROPERTY, '$browser.size', 'size'),
                'expectedVariablePlaceholders' => VariablePlaceholderCollection::createCollection([
                    'WEBDRIVER_DIMENSION',
                    'BROWSER_SIZE',
                    VariableNames::PANTHER_CLIENT,
                ]),
                'expectedExecutedResult' => '1200x1100',
                'additionalVariableIdentifiers' => [
                    'WEBDRIVER_DIMENSION' => '$webDriverDimension',
                    'BROWSER_SIZE' => '$browser'
                ],
            ],
            'page property: title' => [
                'fixture' => '/basic.html',
                'model' => new ObjectValue(ObjectValueType::PAGE_PROPERTY, '$page.title', 'title'),
                'expectedVariablePlaceholders' => VariablePlaceholderCollection::createCollection([
                    VariableNames::PANTHER_CLIENT,
                ]),
                'expectedExecutedResult' => 'A basic page',
            ],
            'page property: url' => [
                'fixture' => '/basic.html',
                'model' => new ObjectValue(ObjectValueType::PAGE_PROPERTY, '$page.url', 'url'),
                'expectedVariablePlaceholders' => VariablePlaceholderCollection::createCollection([
                    VariableNames::PANTHER_CLIENT,
                ]),
                'expectedExecutedResult' => 'http://127.0.0.1:9080/basic.html',
            ],
        ];
    }
}
