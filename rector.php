<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use Rector\ValueObject\PhpVersion;
use Umanit\DevBundle\Rector\Rules\MonologExceptionContextKeyRector;

$config = RectorConfig::configure();

return $config
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPreparedSets(
        deadCode: true,
        instanceOf: true,
        phpunitCodeQuality: true,
        symfonyCodeQuality: true,
    )
    // DBAL 3 and DBAL 4 spell a class with a different case: it has to stay a string
    ->withSkip([
        StringClassNameToClassConstantRector::class => [__DIR__ . '/tests/Unit/Doctrine/PlatformSqlTest.php'],
    ])
    ->withComposerBased(twig: true, phpunit: true, symfony: true)
    ->withPhpSets(php84: true)
    ->withAttributesSets(all: true)
    ->withImportNames(importShortClasses: false)
    ->withRules([
        InlineConstructorDefaultToPropertyRector::class,
        MonologExceptionContextKeyRector::class,
    ])
    ->withConfiguredRule(TypedPropertyFromAssignsRector::class, [
        'inline_public' => true,
    ])
;
