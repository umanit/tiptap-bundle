<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Umanit\TiptapBundle\Form\Type\TiptapType;

#[CoversClass(TiptapType::class)]
final class TiptapTypeTest extends TypeTestCase
{
    public function testDefaultOptionsAreExposedToTheView(): void
    {
        $view = $this->factory->create(TiptapType::class)->createView();

        $this->assertSame('default', $view->vars['toolbar']);
        $this->assertSame('default', $view->vars['styles_set']);
        $this->assertSame('default', $view->vars['colors_set']);
        $this->assertSame('default', $view->vars['background_colors_set']);
        $this->assertSame('default', $view->vars['cell_background_colors_set']);
        $this->assertSame('default', $view->vars['templates_set']);
        $this->assertNull($view->vars['file_manager_url']);
        $this->assertNull($view->vars['file_manager_adapter']);
    }

    public function testBackgroundColorsFollowTheColorsSetByDefault(): void
    {
        $view = $this->factory->create(TiptapType::class, null, ['colors_set' => 'brand'])->createView();

        $this->assertSame('brand', $view->vars['background_colors_set']);
        $this->assertSame('brand', $view->vars['cell_background_colors_set']);
    }

    public function testBackgroundColorsCanBeOverridden(): void
    {
        $view = $this->factory->create(TiptapType::class, null, [
            'colors_set'            => 'brand',
            'background_colors_set' => 'pastel',
        ])->createView();

        $this->assertSame('pastel', $view->vars['background_colors_set']);
        $this->assertSame('brand', $view->vars['cell_background_colors_set']);
    }

    public function testFileManagerOptions(): void
    {
        $view = $this->factory->create(TiptapType::class, null, [
            'file_manager_url'     => '/files',
            'file_manager_adapter' => 'ckeditor',
        ])->createView();

        $this->assertSame('/files', $view->vars['file_manager_url']);
        $this->assertSame('ckeditor', $view->vars['file_manager_adapter']);
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

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('<p>Hello</p>', $form->getData());
    }

    public function testTypeIsATextareaWithADedicatedBlockPrefix(): void
    {
        $type = new TiptapType();

        $this->assertSame(TextareaType::class, $type->getParent());
        $this->assertSame('umanit_tiptap', $type->getBlockPrefix());
    }
}
