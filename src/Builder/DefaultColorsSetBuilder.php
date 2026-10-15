<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Builder;

use Umanit\TiptapBundle\DTO\Color;
use Umanit\TiptapBundle\DTO\ColorsSet;

final readonly class DefaultColorsSetBuilder implements ColorsSetBuilderInterface
{
    public static function getDefaultIndexName(): string
    {
        return 'default';
    }

    public function build(): ColorsSet
    {
        return new ColorsSet([
            new Color('wysiwyg.color.white', 'white'),
            new Color('wysiwyg.color.grey_light', 'grey-light'),
            new Color('wysiwyg.color.grey', 'grey'),
            new Color('wysiwyg.color.grey_dark', 'grey-dark'),
            new Color('wysiwyg.color.black', 'black'),
            new Color('wysiwyg.color.red', 'red'),
            new Color('wysiwyg.color.orange', 'orange'),
            new Color('wysiwyg.color.yellow', 'yellow'),
            new Color('wysiwyg.color.green', 'green'),
            new Color('wysiwyg.color.blue', 'blue'),
            new Color('wysiwyg.color.indigo', 'indigo'),
            new Color('wysiwyg.color.purple', 'purple'),
            new Color('wysiwyg.color.pink', 'pink'),
        ]);
    }
}
