<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;
use Umanit\TiptapBundle\Form\Type\TiptapType;
use Umanit\TiptapBundle\Tests\Fixtures\OverridingStylesSetsBuilder;
use Umanit\TiptapBundle\Tests\Fixtures\TestKernel;

#[CoversNothing]
final class EditorRenderingTest extends KernelTestCase
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

        $this->assertStringContainsString('data-controller="umanit--tiptap--editor"', $html);
        $this->assertStringContainsString('name="bar"', $html);
        $this->assertStringContainsString('Hello</textarea>', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('openFileManager', $html);
    }

    public function testRenderedMarkupHasNoThirdPartyFrameworkClasses(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" fileManagerUrl="/fm" />',
        )->render();

        $this->assertDoesNotMatchRegularExpression(
            '/class="(?:[^"]*\\s)?'
            . '(?:form-\\S+|btn(?:-\\S+)?|d-none|row|col(?:-\\S+)?|mb-\\d|c-editor|c-wysiwyg)'
            . '(?:\\s[^"]*)?"/',
            $html,
        );
        $this->assertStringNotContainsString('data-bs-', $html);
    }

    public function testModalFormsAreNotParsedInsideAHostForm(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" name="bar" />',
        )->render();

        // Nested forms are dropped by the HTML parser and close the host form too early: forms may only live in
        // the inert <template> the controller mounts.
        $withoutTemplate = preg_replace('#<template\b.*?</template>#s', '', $html);

        $this->assertStringContainsString('<template', $html);
        $this->assertStringContainsString('<form', $html);
        $this->assertStringNotContainsString('<form', (string) $withoutTemplate);
        $this->assertStringNotContainsString('<dialog', (string) $withoutTemplate);
    }

    public function testToolbarsAndLabelsAreTranslated(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();

        $this->assertStringNotContainsString('wysiwyg.', $html);
        $this->assertStringContainsString('title="Bold"', $html);
    }

    public function testApplicationBuilderIsAutoconfiguredAndReferencedByName(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" toolbar="compact" />',
        )->render();

        $this->assertStringContainsString('data-wysiwyg-tool="bold"', $html);
        $this->assertStringNotContainsString('data-wysiwyg-tool="italic"', $html);
    }

    public function testApplicationBuilderNamedDefaultReplacesTheBundleOne(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();

        $this->assertStringContainsString(OverridingStylesSetsBuilder::CSS_CLASS, $html);
        $this->assertStringNotContainsString('ut-banner-info', $html);
    }

    public function testUnknownToolbarIsRejected(): void
    {
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessageMatches('/toolbar "nope" does not exist/');

        $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" toolbar="nope" />')->render();
    }

    public function testMigrationDiffIsOffByDefault(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();

        $this->assertStringNotContainsString('migration-diff-value', $html);
        $this->assertStringNotContainsString('diffBanner', $html);
        $this->assertStringNotContainsString('diffModal-foo', $html);
    }

    public function testMigrationDiffAddsTheBannerAndTheModal(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" :migrationDiff="true" />',
        )->render();

        $this->assertStringContainsString('migration-diff-value="true"', $html);
        $this->assertStringContainsString('diffBanner', $html);
        $this->assertStringContainsString('diffModal-foo', $html);
        $this->assertStringContainsString('Show the differences', $html);
        // No `allow-scripts`: the stored content must not run anything, and the fonts need the origin of the page
        $this->assertStringContainsString('sandbox="allow-same-origin"', $html);
        $this->assertStringNotContainsString('allow-scripts', $html);
        $this->assertStringNotContainsString('wysiwyg.diff.', $html);
    }

    public function testFileManagerIsOptional(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" fileManagerUrl="/fm" fileManagerAdapter="custom" />',
        )->render();

        $this->assertStringContainsString('openFileManager', $html);
        $this->assertStringContainsString('/fm', $html);
        $this->assertStringContainsString('custom', $html);
    }

    public function testTiptapTypeIsRenderedByTheComponent(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get('test.form.factory');
        $this->assertInstanceOf(FormFactoryInterface::class, $factory);

        $form = $factory
            ->createNamed('page', FormType::class)
            ->add('body', TiptapType::class, ['data' => '<p>Hi</p>'])
        ;

        $html = $this->getTwig()->render('form.html.twig', ['form' => $form->createView()]);

        $this->assertStringContainsString('name="page[body]"', $html);
        $this->assertStringContainsString('umanit-tiptap__toolbar', $html);
    }

    private function getTwig(): Environment
    {
        self::bootKernel();

        $twig = self::getContainer()->get(Environment::class);
        $this->assertInstanceOf(Environment::class, $twig);

        return $twig;
    }
}
