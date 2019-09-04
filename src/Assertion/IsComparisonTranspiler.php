<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\Assertion;

use webignition\BasilModel\Assertion\AssertionComparisons;
use webignition\BasilModel\Assertion\AssertionInterface;
use webignition\BasilModel\Value\AttributeValueInterface;
use webignition\BasilModel\Value\ElementValueInterface;
use webignition\BasilModel\Value\EnvironmentValueInterface;
use webignition\BasilModel\Value\LiteralValueInterface;
use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValueInterface;
use webignition\BasilTranspiler\CallFactory\AssertionCallFactory;
use webignition\BasilTranspiler\CallFactory\VariableAssignmentCallFactory;
use webignition\BasilTranspiler\CallFactory\DomCrawlerNavigatorCallFactory;
use webignition\BasilTranspiler\Model\TranspilationResultInterface;
use webignition\BasilTranspiler\Model\VariablePlaceholder;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\TranspilerInterface;

class IsComparisonTranspiler implements TranspilerInterface
{
    private $assertionCallFactory;
    private $variableAssignmentCallFactory;
    private $domCrawlerNavigatorCallFactory;
    private $assertableValueExaminer;

    public function __construct(
        AssertionCallFactory $assertionCallFactory,
        VariableAssignmentCallFactory $variableAssignmentCallFactory,
        DomCrawlerNavigatorCallFactory $domCrawlerNavigatorCallFactory,
        AssertableValueExaminer $assertableValueExaminer
    ) {
        $this->assertionCallFactory = $assertionCallFactory;
        $this->variableAssignmentCallFactory = $variableAssignmentCallFactory;
        $this->domCrawlerNavigatorCallFactory = $domCrawlerNavigatorCallFactory;
        $this->assertableValueExaminer = $assertableValueExaminer;
    }

    public static function createTranspiler(): IsComparisonTranspiler
    {
        return new IsComparisonTranspiler(
            AssertionCallFactory::createFactory(),
            VariableAssignmentCallFactory::createFactory(),
            DomCrawlerNavigatorCallFactory::createFactory(),
            AssertableValueExaminer::create()
        );
    }

    public function handles(object $model): bool
    {
        if (!$model instanceof AssertionInterface) {
            return false;
        }

        return in_array($model->getComparison(), [
            AssertionComparisons::IS,
            AssertionComparisons::IS_NOT,
        ]);
    }

    /**
     * @param object $model
     *
     * @return TranspilationResultInterface
     *
     * @throws NonTranspilableModelException
     */
    public function transpile(object $model): TranspilationResultInterface
    {
        if (!$model instanceof AssertionInterface) {
            throw new NonTranspilableModelException($model);
        }

        $isHandledComparison = in_array($model->getComparison(), [
            AssertionComparisons::IS,
            AssertionComparisons::IS_NOT,
        ]);

        if (false === $isHandledComparison) {
            throw new NonTranspilableModelException($model);
        }

        $examinedValue = $model->getExaminedValue();
        if (!$this->assertableValueExaminer->isAssertableExaminedValue($examinedValue)) {
            throw new NonTranspilableModelException($model);
        }

        $expectedValue = $model->getExpectedValue();
        if (!$this->assertableValueExaminer->isAssertableExpectedValue($expectedValue)) {
            throw new NonTranspilableModelException($model);
        }

        $transpiledExaminedValue = null;
        $examinedValuePlaceholder = new VariablePlaceholder('EXAMINED_VALUE');

        if ($examinedValue instanceof ElementValueInterface) {
            $transpiledExaminedValue = $this->variableAssignmentCallFactory->createForElementCollectionValue(
                $examinedValue->getIdentifier(),
                $examinedValuePlaceholder
            );
        }

        if ($examinedValue instanceof AttributeValueInterface) {
            $transpiledExaminedValue = $this->variableAssignmentCallFactory->createForAttributeValue(
                $examinedValue->getIdentifier(),
                $examinedValuePlaceholder
            );
        }

        if ($examinedValue instanceof EnvironmentValueInterface) {
            $transpiledExaminedValue = $this->variableAssignmentCallFactory->createForScalar(
                $examinedValue,
                new VariablePlaceholder('ENVIRONMENT_VARIABLE')
            );
        }

        if ($examinedValue instanceof ObjectValueInterface) {
            if (ObjectNames::BROWSER === $examinedValue->getObjectName()) {
                $transpiledExaminedValue = $this->variableAssignmentCallFactory->createForScalar(
                    $examinedValue,
                    new VariablePlaceholder('BROWSER_VARIABLE')
                );
            }

            if (ObjectNames::PAGE === $examinedValue->getObjectName()) {
                $transpiledExaminedValue = $this->variableAssignmentCallFactory->createForScalar(
                    $examinedValue,
                    new VariablePlaceholder('PAGE_VARIABLE')
                );
            }
        }

        $transpiledExpectedValue = null;
        $expectedValuePlaceholder = new VariablePlaceholder('EXPECTED_VALUE');

        if ($expectedValue instanceof LiteralValueInterface) {
            $transpiledExpectedValue = $this->variableAssignmentCallFactory->createForScalar(
                $expectedValue,
                $expectedValuePlaceholder
            );
        }

        if ($expectedValue instanceof ElementValueInterface) {
            $transpiledExpectedValue = $this->variableAssignmentCallFactory->createForElementCollectionValue(
                $expectedValue->getIdentifier(),
                $expectedValuePlaceholder
            );
        }

        if ($expectedValue instanceof AttributeValueInterface) {
            $transpiledExpectedValue = $this->variableAssignmentCallFactory->createForAttributeValue(
                $expectedValue->getIdentifier(),
                $expectedValuePlaceholder
            );
        }

        if ($expectedValue instanceof EnvironmentValueInterface) {
            $transpiledExpectedValue = $this->variableAssignmentCallFactory->createForScalar(
                $expectedValue,
                new VariablePlaceholder('ENVIRONMENT_VARIABLE')
            );
        }

        if ($expectedValue instanceof ObjectValueInterface) {
            if (ObjectNames::BROWSER === $expectedValue->getObjectName()) {
                $transpiledExpectedValue = $this->variableAssignmentCallFactory->createForScalar(
                    $expectedValue,
                    new VariablePlaceholder('BROWSER_VARIABLE')
                );
            }

            if (ObjectNames::PAGE === $expectedValue->getObjectName()) {
                $transpiledExpectedValue = $this->variableAssignmentCallFactory->createForScalar(
                    $expectedValue,
                    new VariablePlaceholder('PAGE_VARIABLE')
                );
            }
        }

        // ...
        // create expected value from further value types
        // env|browser|page object
        // ...

        return $this->assertionCallFactory->createValuesAreEqualAssertionCall(
            $transpiledExpectedValue,
            $transpiledExaminedValue
        );
    }
}
