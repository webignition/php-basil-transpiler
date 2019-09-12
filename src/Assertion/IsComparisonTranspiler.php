<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\Assertion;

use webignition\BasilModel\Assertion\IsAssertion;
use webignition\BasilModel\Assertion\IsNotAssertion;
use webignition\BasilModel\Assertion\ValueComparisonAssertionInterface;
use webignition\BasilModel\Exception\InvalidAssertionExaminedValueException;
use webignition\BasilModel\Exception\InvalidAssertionExpectedValueException;
use webignition\BasilTranspiler\CallFactory\AssertionCallFactory;
use webignition\BasilTranspiler\CallFactory\VariableAssignmentCallFactory;
use webignition\BasilTranspiler\Model\Call\VariableAssignmentCall;
use webignition\BasilTranspiler\Model\TranspilationResultInterface;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\TranspilerInterface;

class IsComparisonTranspiler extends AbstractValueComparisonAssertionTranspiler implements TranspilerInterface
{
    public static function createTranspiler(): IsComparisonTranspiler
    {
        return new IsComparisonTranspiler(
            AssertionCallFactory::createFactory(),
            VariableAssignmentCallFactory::createFactory()
        );
    }

    public function handles(object $model): bool
    {
        return $model instanceof IsAssertion || $model instanceof IsNotAssertion;
    }

    /**
     * @param object $model
     *
     * @return TranspilationResultInterface
     *
     * @throws NonTranspilableModelException
     * @throws InvalidAssertionExaminedValueException
     * @throws InvalidAssertionExpectedValueException
     */
    public function transpile(object $model): TranspilationResultInterface
    {
        if (!($model instanceof IsAssertion || $model instanceof IsNotAssertion)) {
            throw new NonTranspilableModelException($model);
        }

        return $this->doTranspile($model);
    }

    protected function getAssertionCall(
        ValueComparisonAssertionInterface $assertion,
        VariableAssignmentCall $examinedValue,
        VariableAssignmentCall $expectedValue
    ): TranspilationResultInterface {
        return $assertion instanceof IsAssertion
            ? $this->assertionCallFactory->createValuesAreEqualAssertionCall($examinedValue, $expectedValue)
            : $this->assertionCallFactory->createValuesAreNotEqualAssertionCall($examinedValue, $expectedValue);
    }
}
