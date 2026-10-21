<?php

declare(strict_types=1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\DependsOnlyOnTheseNamespaces;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $rules = [];

    $rules[] = Rule
        ::allClasses()
        ->except('Umanit\TiptapBundle\Doctrine\*')
        ->that(new ResideInOneOfTheseNamespaces('Umanit\TiptapBundle'))
        ->should(new DependsOnlyOnTheseNamespaces('Umanit\TiptapBundle', 'Symfony', 'Twig', 'Psr', 'Dom'))
        ->because(
            'the bundle must stay standalone: it only relies on its own code, Symfony, Twig, PSR interfaces and the'
            . ' DOM extension of PHP',
        )
    ;

    $rules[] = Rule
        ::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('Umanit\TiptapBundle\Doctrine'))
        ->should(new DependsOnlyOnTheseNamespaces('Umanit\TiptapBundle', 'Symfony', 'Twig', 'Psr', 'Dom', 'Doctrine'))
        ->because('Doctrine is only an optional dependency of the code which reads and writes the database')
    ;

    $config->add($classSet, ...$rules);
};
