<?php

declare(strict_types=1);

namespace Playground\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Umanit\TiptapBundle\Form\Type\TiptapType;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class DemoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('body', TiptapType::class, ['file_manager_url' => '/file-manager'])
            ->add('save', SubmitType::class)
        ;
    }
}
