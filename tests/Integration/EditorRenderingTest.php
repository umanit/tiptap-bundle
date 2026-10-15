<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;
use Umanit\TiptapBundle\Form\Type\TiptapType;
use Umanit\TiptapBundle\Tests\Fixtures\TestKernel;

#[CoversNothing]
class EditorRenderingTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testEditorComponentRendersWithDefaults(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" name="bar" content="Hello" />',
        )->render();

        self::assertStringContainsString('data-controller="umanit--tiptap--editor"', $html);
        self::assertStringContainsString('name="bar"', $html);
        self::assertStringContainsString('Hello</textarea>', $html);
        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('openFileManager', $html);
    }

    public function testRenderedMarkupHasNoThirdPartyFrameworkClasses(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" fileManagerUrl="/fm" />',
        )->render();

        self::assertDoesNotMatchRegularExpression(
            '/class="(?:[^"]*\\s)?'
            . '(?:form-\\S+|btn(?:-\\S+)?|d-none|row|col(?:-\\S+)?|mb-\\d|c-editor|c-wysiwyg)'
            . '(?:\\s[^"]*)?"/',
            $html,
        );
        self::assertStringNotContainsString('data-bs-', $html);
    }

    public function testModalFormsAreNotParsedInsideAHostForm(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" name="bar" />',
        )->render();

        // Nested forms are dropped by the HTML parser and close the host form too early: forms may only live in
        // the inert <template> the controller mounts.
        $withoutTemplate = preg_replace('#<template\b.*?</template>#s', '', $html);

        self::assertStringContainsString('<template', $html);
        self::assertStringContainsString('<form', $html);
        self::assertStringNotContainsString('<form', (string) $withoutTemplate);
        self::assertStringNotContainsString('<dialog', (string) $withoutTemplate);
    }

    public function testToolbarsAndLabelsAreTranslated(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();

        self::assertStringNotContainsString('wysiwyg.', $html);
        self::assertStringContainsString('title="Bold"', $html);
    }

    public function testApplicationBuilderIsAutoconfiguredAndReferencedByName(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" toolbar="compact" />',
        )->render();

        self::assertStringContainsString('data-wysiwyg-tool="bold"', $html);
        self::assertStringNotContainsString('data-wysiwyg-tool="italic"', $html);
    }

    public function testUnknownToolbarIsRejected(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessageMatches('/toolbar "nope" does not exist/');

        $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" toolbar="nope" />')->render();
    }

    public function testFileManagerIsOptional(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" fileManagerUrl="/fm" fileManagerAdapter="custom" />',
        )->render();

        self::assertStringContainsString('openFileManager', $html);
        self::assertStringContainsString('/fm', $html);
        self::assertStringContainsString('custom', $html);
    }

    public function testTiptapTypeIsRenderedByTheComponent(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $form = $factory
            ->createNamed('page', FormType::class)
            ->add('body', TiptapType::class, ['data' => '<p>Hi</p>'])
        ;

        $html = $this->getTwig()->render('form.html.twig', ['form' => $form->createView()]);

        self::assertStringContainsString('name="page[body]"', $html);
        self::assertStringContainsString('umanit-tiptap__toolbar', $html);
    }

    private function getTwig(): Environment
    {
        self::bootKernel();

        $twig = self::getContainer()->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }
}
