<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\CallFactory;

use webignition\BasilModel\Identifier\DomIdentifierInterface;
use webignition\BasilModel\Value\DomIdentifierValueInterface;
use webignition\BasilModel\Value\LiteralValueInterface;
use webignition\BasilModel\Value\ObjectValueType;
use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilTranspiler\Model\Call\VariableAssignmentCall;
use webignition\BasilTranspiler\Model\CompilableSource;
use webignition\BasilTranspiler\Model\CompilableSourceInterface;
use webignition\BasilTranspiler\Model\ClassDependencyCollection;
use webignition\BasilTranspiler\Model\VariablePlaceholder;
use webignition\BasilTranspiler\Model\VariablePlaceholderCollection;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\ObjectValueTypeExaminer;
use webignition\BasilTranspiler\SingleQuotedStringEscaper;
use webignition\BasilTranspiler\TranspilableSourceComposer;
use webignition\BasilTranspiler\Value\ValueTranspiler;

class VariableAssignmentCallFactory
{
    const DEFAULT_ELEMENT_LOCATOR_PLACEHOLDER_NAME = 'ELEMENT_LOCATOR';
    const DEFAULT_ELEMENT_PLACEHOLDER_NAME = 'ELEMENT';

    private $assertionCallFactory;
    private $elementLocatorCallFactory;
    private $domCrawlerNavigatorCallFactory;
    private $transpilableSourceComposer;
    private $valueTranspiler;
    private $singleQuotedStringEscaper;
    private $webDriverElementInspectorCallFactory;
    private $objectValueTypeExaminer;

    public function __construct(
        AssertionCallFactory $assertionCallFactory,
        ElementLocatorCallFactory $elementLocatorCallFactory,
        DomCrawlerNavigatorCallFactory $domCrawlerNavigatorCallFactory,
        TranspilableSourceComposer $transpilableSourceComposer,
        ValueTranspiler $valueTranspiler,
        SingleQuotedStringEscaper $singleQuotedStringEscaper,
        WebDriverElementInspectorCallFactory $webDriverElementInspectorCallFactory,
        ObjectValueTypeExaminer $objectValueTypeExaminer
    ) {
        $this->assertionCallFactory = $assertionCallFactory;
        $this->elementLocatorCallFactory = $elementLocatorCallFactory;
        $this->domCrawlerNavigatorCallFactory = $domCrawlerNavigatorCallFactory;
        $this->transpilableSourceComposer = $transpilableSourceComposer;
        $this->valueTranspiler = $valueTranspiler;
        $this->singleQuotedStringEscaper = $singleQuotedStringEscaper;
        $this->webDriverElementInspectorCallFactory = $webDriverElementInspectorCallFactory;
        $this->objectValueTypeExaminer = $objectValueTypeExaminer;
    }

    public static function createFactory(): VariableAssignmentCallFactory
    {
        return new VariableAssignmentCallFactory(
            AssertionCallFactory::createFactory(),
            ElementLocatorCallFactory::createFactory(),
            DomCrawlerNavigatorCallFactory::createFactory(),
            TranspilableSourceComposer::create(),
            ValueTranspiler::createTranspiler(),
            SingleQuotedStringEscaper::create(),
            WebDriverElementInspectorCallFactory::createFactory(),
            ObjectValueTypeExaminer::createExaminer()
        );
    }

    /**
     * @param DomIdentifierInterface $elementIdentifier
     * @param VariablePlaceholder $elementLocatorPlaceholder
     * @param VariablePlaceholder $elementPlaceholder
     *
     * @return VariableAssignmentCall
     */
    public function createForElement(
        DomIdentifierInterface $elementIdentifier,
        VariablePlaceholder $elementLocatorPlaceholder,
        VariablePlaceholder $elementPlaceholder
    ) {
        $hasCall = $this->domCrawlerNavigatorCallFactory->createHasOneCallForTranspiledArguments(
            new CompilableSource(
                [(string) $elementLocatorPlaceholder],
                new ClassDependencyCollection(),
                new VariablePlaceholderCollection(),
                new VariablePlaceholderCollection()
            )
        );

        $findCall = $this->domCrawlerNavigatorCallFactory->createFindOneCallForTranspiledArguments(
            new CompilableSource(
                [(string) $elementLocatorPlaceholder],
                new ClassDependencyCollection(),
                new VariablePlaceholderCollection(),
                new VariablePlaceholderCollection()
            )
        );

        return $this->createForElementOrCollection(
            $elementIdentifier,
            $elementLocatorPlaceholder,
            $elementPlaceholder,
            $hasCall,
            $findCall
        );
    }

    /**
     * @param ValueInterface $value
     * @param VariablePlaceholder $placeholder
     * @param string $type
     * @param string $default
     *
     * @return VariableAssignmentCall|null
     *
     * @throws NonTranspilableModelException
     */
    public function createForValue(
        ValueInterface $value,
        VariablePlaceholder $placeholder,
        string $type = 'string',
        string $default = 'null'
    ): ?VariableAssignmentCall {
        $variableAssignmentCall = null;

        $isOfScalarObjectType = $this->objectValueTypeExaminer->isOfType($value, [
            ObjectValueType::BROWSER_PROPERTY,
            ObjectValueType::ENVIRONMENT_PARAMETER,
            ObjectValueType::PAGE_PROPERTY,
        ]);

        $isScalarValue = $value instanceof LiteralValueInterface || $isOfScalarObjectType;

        if ($isScalarValue) {
            $variableAssignmentCall = $this->createForScalar($value, $placeholder, $default);
        }

        if (null === $variableAssignmentCall && $value instanceof DomIdentifierValueInterface) {
            $identifier = $value->getIdentifier();

            $variableAssignmentCall = null === $identifier->getAttributeName()
                ? $this->createForElementCollectionValue($identifier, $placeholder)
                : $this->createForAttributeValue($identifier, $placeholder);
        }

        if ($variableAssignmentCall instanceof VariableAssignmentCall) {
            $variableAssignmentCallPlaceholder = $variableAssignmentCall->getElementVariablePlaceholder();

            $typeCastStatement = sprintf(
                '%s = (%s) %s',
                (string) $variableAssignmentCallPlaceholder,
                $type,
                (string) $variableAssignmentCallPlaceholder
            );

            $variableAssignmentCall = $variableAssignmentCall->withAdditionalStatements([
                $typeCastStatement,
            ]);
        }

        return $variableAssignmentCall;
    }

    /**
     * @param ValueInterface $value
     * @param VariablePlaceholder $placeholder
     *
     * @return VariableAssignmentCall|null
     *
     * @throws NonTranspilableModelException
     */
    public function createForValueExistence(
        ValueInterface $value,
        VariablePlaceholder $placeholder
    ): ?VariableAssignmentCall {
        $isScalarValue = $this->objectValueTypeExaminer->isOfType($value, [
            ObjectValueType::BROWSER_PROPERTY,
            ObjectValueType::ENVIRONMENT_PARAMETER,
            ObjectValueType::PAGE_PROPERTY,
        ]);

        if ($isScalarValue) {
            return $this->createForScalarExistence(
                $value,
                $placeholder
            );
        }

        if ($value instanceof DomIdentifierValueInterface) {
            $identifier = $value->getIdentifier();

            return null === $identifier->getAttributeName()
                ? $this->createForElementExistence(
                    $identifier,
                    VariableAssignmentCallFactory::createElementLocatorPlaceholder(),
                    $placeholder
                )
                : $this->createForAttributeExistence($identifier, $placeholder);
        }

        return null;
    }

    /**
     * @param DomIdentifierInterface $elementIdentifier
     * @param VariablePlaceholder $elementLocatorPlaceholder
     * @param VariablePlaceholder $collectionPlaceholder
     * @return VariableAssignmentCall
     */
    public function createForElementCollection(
        DomIdentifierInterface $elementIdentifier,
        VariablePlaceholder $elementLocatorPlaceholder,
        VariablePlaceholder $collectionPlaceholder
    ) {
        $hasCall = $this->domCrawlerNavigatorCallFactory->createHasCallForTranspiledArguments(
            new CompilableSource(
                [(string) $elementLocatorPlaceholder],
                new ClassDependencyCollection(),
                new VariablePlaceholderCollection(),
                new VariablePlaceholderCollection()
            )
        );

        $findCall = $this->domCrawlerNavigatorCallFactory->createFindCallForTranspiledArguments(
            new CompilableSource(
                [(string) $elementLocatorPlaceholder],
                new ClassDependencyCollection(),
                new VariablePlaceholderCollection(),
                new VariablePlaceholderCollection()
            )
        );

        return $this->createForElementOrCollection(
            $elementIdentifier,
            $elementLocatorPlaceholder,
            $collectionPlaceholder,
            $hasCall,
            $findCall
        );
    }

    /**
     * @param DomIdentifierInterface $elementIdentifier
     * @param VariablePlaceholder $elementLocatorPlaceholder
     * @param VariablePlaceholder $elementPlaceholder
     *
     * @return VariableAssignmentCall
     */
    private function createForElementExistence(
        DomIdentifierInterface $elementIdentifier,
        VariablePlaceholder $elementLocatorPlaceholder,
        VariablePlaceholder $elementPlaceholder
    ): VariableAssignmentCall {
        $variableExports = new VariablePlaceholderCollection([
            $elementLocatorPlaceholder,
            $elementPlaceholder,
        ]);

        $hasCall = $this->domCrawlerNavigatorCallFactory->createHasCallForIdentifier($elementIdentifier);

        $assignmentStatement = $this->transpilableSourceComposer->compose(
            [
                sprintf(
                    '%s = %s',
                    (string) $elementPlaceholder,
                    (string) $hasCall
                ),
            ],
            [$hasCall],
            new ClassDependencyCollection(),
            $variableExports,
            new VariablePlaceholderCollection()
        );

        return new VariableAssignmentCall($assignmentStatement, $elementPlaceholder);
    }

    /**
     * @param DomIdentifierInterface $attributeIdentifier
     * @param VariablePlaceholder $elementLocatorPlaceholder
     * @param VariablePlaceholder $elementPlaceholder
     * @param VariablePlaceholder $attributePlaceholder
     *
     * @return VariableAssignmentCall
     */
    private function createForAttribute(
        DomIdentifierInterface $attributeIdentifier,
        VariablePlaceholder $elementLocatorPlaceholder,
        VariablePlaceholder $elementPlaceholder,
        VariablePlaceholder $attributePlaceholder
    ): VariableAssignmentCall {
        $elementAssignmentCall = $this->createForElement(
            $attributeIdentifier,
            $elementLocatorPlaceholder,
            $elementPlaceholder
        );

        $variableExports = new VariablePlaceholderCollection();
        $variableExports = $variableExports->withAdditionalItems([
            $attributePlaceholder,
        ]);

        $elementPlaceholder = $elementAssignmentCall->getElementVariablePlaceholder();

        $attributeAssignmentStatement = $attributePlaceholder . ' = ' . sprintf(
            '%s->getAttribute(\'%s\')',
            $elementPlaceholder,
            $this->singleQuotedStringEscaper->escape((string) $attributeIdentifier->getAttributeName())
        );

        $statements = array_merge(
            $elementAssignmentCall->getStatements(),
            [
                $attributeAssignmentStatement,
            ]
        );

        $calls = [
            $elementAssignmentCall,
        ];

        $compilableSource = $this->transpilableSourceComposer->compose(
            $statements,
            $calls,
            new ClassDependencyCollection(),
            $variableExports,
            new VariablePlaceholderCollection()
        );

        return new VariableAssignmentCall($compilableSource, $attributePlaceholder);
    }

    /**
     * @param DomIdentifierInterface $elementIdentifier
     * @param VariablePlaceholder $valuePlaceholder
     *
     * @return VariableAssignmentCall
     */
    private function createForElementCollectionValue(
        DomIdentifierInterface $elementIdentifier,
        VariablePlaceholder $valuePlaceholder
    ): VariableAssignmentCall {
        $collectionCall = $this->createForElementCollection(
            $elementIdentifier,
            $this->createElementLocatorPlaceholder(),
            $valuePlaceholder
        );
        $collectionPlaceholder = $collectionCall->getElementVariablePlaceholder();

        $variableExports = new VariablePlaceholderCollection();
        $variableExports = $variableExports->withAdditionalItems([
            $valuePlaceholder,
            $collectionPlaceholder,
        ]);

        $assignmentCall = $this->webDriverElementInspectorCallFactory->createGetValueCall($collectionPlaceholder);

        $assignmentStatement = $valuePlaceholder . ' = ' . $assignmentCall;

        $statements = array_merge(
            $collectionCall->getStatements(),
            [
                $assignmentStatement,
            ]
        );

        $calls = [
            $collectionCall,
            $assignmentCall,
        ];

        $compilableSource = $this->transpilableSourceComposer->compose(
            $statements,
            $calls,
            new ClassDependencyCollection(),
            $variableExports,
            new VariablePlaceholderCollection()
        );

        return new VariableAssignmentCall($compilableSource, $valuePlaceholder);
    }

    /**
     * @param DomIdentifierInterface $attributeIdentifier
     * @param VariablePlaceholder $valuePlaceholder
     *
     * @return VariableAssignmentCall
     */
    private function createForAttributeValue(
        DomIdentifierInterface $attributeIdentifier,
        VariablePlaceholder $valuePlaceholder
    ): VariableAssignmentCall {
        $assignmentCall = $this->createForAttribute(
            $attributeIdentifier,
            $this->createElementLocatorPlaceholder(),
            $this->createElementPlaceholder(),
            $valuePlaceholder
        );

        $variableExports = new VariablePlaceholderCollection();
        $variableExports = $variableExports->withAdditionalItems([
            $valuePlaceholder
        ]);

        $compilableSource = $this->transpilableSourceComposer->compose(
            $assignmentCall->getStatements(),
            [$assignmentCall],
            new ClassDependencyCollection(),
            $variableExports,
            new VariablePlaceholderCollection()
        );

        return new VariableAssignmentCall($compilableSource, $valuePlaceholder);
    }

    /**
     * @param DomIdentifierInterface $attributeIdentifier
     * @param VariablePlaceholder $valuePlaceholder
     *
     * @return VariableAssignmentCall
     */
    private function createForAttributeExistence(
        DomIdentifierInterface $attributeIdentifier,
        VariablePlaceholder $valuePlaceholder
    ): VariableAssignmentCall {
        $variableExports = new VariablePlaceholderCollection();
        $variableExports = $variableExports->withAdditionalItems([
            $valuePlaceholder
        ]);

        $assignmentCall = $this->createForAttribute(
            $attributeIdentifier,
            $this->createElementLocatorPlaceholder(),
            $this->createElementPlaceholder(),
            $valuePlaceholder
        );

        $existenceAssignmentStatement = sprintf(
            '%s = %s !== null',
            (string) $valuePlaceholder,
            (string) $valuePlaceholder
        );

        $compilableSource = $this->transpilableSourceComposer->compose(
            array_merge(
                $assignmentCall->getStatements(),
                [
                    $existenceAssignmentStatement,
                ]
            ),
            [$assignmentCall],
            new ClassDependencyCollection(),
            $variableExports,
            new VariablePlaceholderCollection()
        );

        return new VariableAssignmentCall($compilableSource, $valuePlaceholder);
    }


    /**
     * @param DomIdentifierInterface $elementIdentifier
     * @param VariablePlaceholder $elementLocatorPlaceholder
     * @param VariablePlaceholder $returnValuePlaceholder
     * @param CompilableSourceInterface $hasCall
     * @param CompilableSourceInterface $findCall
     *
     * @return VariableAssignmentCall
     */
    private function createForElementOrCollection(
        DomIdentifierInterface $elementIdentifier,
        VariablePlaceholder $elementLocatorPlaceholder,
        VariablePlaceholder $returnValuePlaceholder,
        CompilableSourceInterface $hasCall,
        CompilableSourceInterface $findCall
    ) {
        $variableExports = new VariablePlaceholderCollection();
        $variableExports = $variableExports->withAdditionalItems([
            $elementLocatorPlaceholder,
            $returnValuePlaceholder,
        ]);

        $hasVariablePlaceholder = $variableExports->create('HAS');

        $elementLocatorConstructor = $this->elementLocatorCallFactory->createConstructorCall($elementIdentifier);

        $hasAssignmentStatement = sprintf(
            '%s = %s',
            (string) $hasVariablePlaceholder,
            (string) $hasCall
        );

        $hasAssignmentCall = $this->transpilableSourceComposer->compose(
            [
                $hasAssignmentStatement,
            ],
            [
                $hasCall
            ],
            new ClassDependencyCollection(),
            $variableExports,
            new VariablePlaceholderCollection()
        );

        $hasVariableAssignmentCall = new VariableAssignmentCall(
            $hasAssignmentCall,
            $hasVariablePlaceholder
        );

        $elementExistsAssertionCall = $this->assertionCallFactory->createValueIsTrueAssertionCall(
            $hasVariableAssignmentCall
        );

        $elementLocatorConstructorStatement = $elementLocatorPlaceholder . ' = ' . $elementLocatorConstructor;
        $findStatement = $returnValuePlaceholder . ' = ' . $findCall;

        $statements = array_merge(
            [
                $elementLocatorConstructorStatement,
            ],
            $elementExistsAssertionCall->getStatements(),
            [
                $findStatement,
            ]
        );

        $calls = [
            $elementLocatorConstructor,
            $hasCall,
            $findCall,
            $elementExistsAssertionCall,
        ];

        $compilableSource = $this->transpilableSourceComposer->compose(
            $statements,
            $calls,
            new ClassDependencyCollection(),
            $variableExports,
            new VariablePlaceholderCollection()
        );

        return new VariableAssignmentCall($compilableSource, $returnValuePlaceholder);
    }

    /**
     * @param ValueInterface $value
     * @param VariablePlaceholder $variablePlaceholder
     * @param string $default
     *
     * @return VariableAssignmentCall
     *
     * @throws NonTranspilableModelException
     */
    private function createForScalar(
        ValueInterface $value,
        VariablePlaceholder $variablePlaceholder,
        string $default = 'null'
    ) {
        $variableExports = new VariablePlaceholderCollection([
            $variablePlaceholder,
        ]);

        $variableAccessCall = $this->valueTranspiler->transpile($value);
        $variableAccessStatements = $variableAccessCall->getStatements();
        $variableAccessLastStatement = array_pop($variableAccessStatements);

        $assignmentStatement = sprintf(
            '%s = %s ?? ' . $default,
            $variablePlaceholder,
            $variableAccessLastStatement
        );

        $statements = array_merge($variableAccessStatements, [
            $assignmentStatement,
        ]);

        $calls = [
            $variableAccessCall,
        ];

        $compilableSource = $this->transpilableSourceComposer->compose(
            $statements,
            $calls,
            new ClassDependencyCollection(),
            $variableExports,
            new VariablePlaceholderCollection()
        );

        return new VariableAssignmentCall($compilableSource, $variablePlaceholder);
    }


    /**
     * @param ValueInterface $value
     * @param VariablePlaceholder $variablePlaceholder
     *
     * @return VariableAssignmentCall
     *
     * @throws NonTranspilableModelException
     */
    private function createForScalarExistence(ValueInterface $value, VariablePlaceholder $variablePlaceholder)
    {
        $variableExports = new VariablePlaceholderCollection([
            $variablePlaceholder,
        ]);

        $assignmentCall = $this->createForScalar($value, $variablePlaceholder);

        $comparisonStatement = $variablePlaceholder . ' = ' . $variablePlaceholder . ' !== null';

        $compilableSource = $this->transpilableSourceComposer->compose(
            array_merge(
                $assignmentCall->getStatements(),
                [
                    $comparisonStatement,
                ]
            ),
            [
                $assignmentCall,
            ],
            new ClassDependencyCollection(),
            $variableExports,
            new VariablePlaceholderCollection()
        );

        return new VariableAssignmentCall($compilableSource, $variablePlaceholder);
    }

    private function createElementLocatorPlaceholder(): VariablePlaceholder
    {
        return new VariablePlaceholder(self::DEFAULT_ELEMENT_LOCATOR_PLACEHOLDER_NAME);
    }

    private function createElementPlaceholder(): VariablePlaceholder
    {
        return new VariablePlaceholder(self::DEFAULT_ELEMENT_PLACEHOLDER_NAME);
    }
}
