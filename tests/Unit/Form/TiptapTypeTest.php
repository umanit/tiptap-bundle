<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Umanit\TiptapBundle\Form\Type\TiptapType;

#[CoversClass(TiptapType::class)]
class TiptapTypeTest extends TypeTestCase
{
    public function testDefaultOptionsAreExposedToTheView(): void
    {
        $view = $this->factory->create(TiptapType::class)->createView();

        self::assertSame('default', $view->vars['toolbar']);
        self::assertSame('default', $view->vars['styles_set']);
        self::assertSame('default', $view->vars['colors_set']);
        self::assertSame('default', $view->vars['background_colors_set']);
        self::assertSame('default', $view->vars['cell_background_colors_set']);
        self::assertSame('default', $view->vars['templates_set']);
        self::assertNull($view->vars['file_manager_url']);
        self::assertNull($view->vars['file_manager_adapter']);
    }

    public function testBackgroundColorsFollowTheColorsSetByDefault(): void
    {
        $view = $this->factory->create(TiptapType::class, null, ['colors_set' => 'brand'])->createView();

        self::assertSame('brand', $view->vars['background_colors_set']);
        self::assertSame('brand', $view->vars['cell_background_colors_set']);
    }

    public function testBackgroundColorsCanBeOverridden(): void
    {
        $view = $this->factory->create(TiptapType::class, null, [
            'colors_set'            => 'brand',
            'background_colors_set' => 'pastel',
        ])->createView();

        self::assertSame('pastel', $view->vars['background_colors_set']);
        self::assertSame('brand', $view->vars['cell_background_colors_set']);
    }

    public function testFileManagerOptions(): void
    {
        $view = $this->factory->create(TiptapType::class, null, [
            'file_manager_url'     => '/files',
            'file_manager_adapter' => 'ckeditor',
        ])->createView();

        self::assertSame('/files', $view->vars['file_manager_url']);
        self::assertSame('ckeditor', $view->vars['file_manager_adapter']);
    }

    public function testSetOptionsMustBeStrings(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(TiptapType::class, null, ['toolbar' => null]);
    }

    public function testSubmitKeepsTheHtml(): void
    {
        $form = $this->factory->create(TiptapType::class);
        $form->submit('<p>Hello</p>');

        self::assertTrue($form->isSynchronized());
        self::assertSame('<p>Hello</p>', $form->getData());
    }

    public function testTypeIsATextareaWithADedicatedBlockPrefix(): void
    {
        $type = new TiptapType();

        self::assertSame(TextareaType::class, $type->getParent());
        self::assertSame('umanit_tiptap', $type->getBlockPrefix());
    }
}
