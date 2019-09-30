<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\Value;

use webignition\BasilModel\Value\ObjectValueInterface;
use webignition\BasilModel\Value\ObjectValueType;
use webignition\BasilTranspiler\Model\TranspilableSource;
use webignition\BasilTranspiler\Model\TranspilableSourceInterface;
use webignition\BasilTranspiler\Model\UseStatementCollection;
use webignition\BasilTranspiler\Model\VariablePlaceholderCollection;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\TranspilerInterface;
use webignition\BasilTranspiler\VariableNames;

class EnvironmentParameterValueTranspiler implements TranspilerInterface
{
    public static function createTranspiler(): EnvironmentParameterValueTranspiler
    {
        return new EnvironmentParameterValueTranspiler();
    }

    public function handles(object $model): bool
    {
        return $model instanceof ObjectValueInterface && ObjectValueType::ENVIRONMENT_PARAMETER === $model->getType();
    }

    /**
     * @param object $model
     *
     * @return TranspilableSourceInterface
     *
     * @throws NonTranspilableModelException
     */
    public function transpile(object $model): TranspilableSourceInterface
    {
        if ($this->handles($model) && $model instanceof ObjectValueInterface) {
            $variablePlaceholders = new VariablePlaceholderCollection();
            $environmentVariableArrayPlaceholder = $variablePlaceholders->create(
                VariableNames::ENVIRONMENT_VARIABLE_ARRAY
            );

            $content = sprintf(
                (string) $environmentVariableArrayPlaceholder . '[\'%s\']',
                $model->getProperty()
            );

            return new TranspilableSource(
                [$content],
                new UseStatementCollection(),
                $variablePlaceholders
            );
        }

        throw new NonTranspilableModelException($model);
    }
}
