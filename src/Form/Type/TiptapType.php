<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Rich text field rendered with the TipTap editor.
 *
 * Each option maps to a prop of the `Umanit:Tiptap:Editor` Twig component.
 *
 * @extends AbstractType<string|null>
 */
class TiptapType extends AbstractType
{
    private const array COMPONENT_OPTIONS = [
        'toolbar',
        'styles_set',
        'colors_set',
        'background_colors_set',
        'cell_background_colors_set',
        'templates_set',
        'file_manager_url',
        'file_manager_adapter',
    ];

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'toolbar'                    => 'default',
            'styles_set'                 => 'default',
            'colors_set'                 => 'default',
            'background_colors_set'      => static fn(Options $options): mixed => $options['colors_set'],
            'cell_background_colors_set' => static fn(Options $options): mixed => $options['colors_set'],
            'templates_set'              => 'default',
            'file_manager_url'           => null,
            'file_manager_adapter'       => null,
        ]);

        foreach (self::COMPONENT_OPTIONS as $option) {
            $nullable = str_starts_with($option, 'file_manager_');

            $resolver->setAllowedTypes($option, $nullable ? ['null', 'string'] : 'string');
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        foreach (self::COMPONENT_OPTIONS as $option) {
            $view->vars[$option] = $options[$option];
        }
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'umanit_tiptap';
    }
}
