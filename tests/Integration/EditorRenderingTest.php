<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;
use Umanit\TiptapBundle\Builder\DefaultToolsBuilder;
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

    /**
     * Older versions of Stimulus Bundle write `{` as `&#x7B;` in an attribute: the same value once parsed, not the same
     * string.
     */
    private static function withoutNumericReferences(string $html): string
    {
        return (string) \preg_replace_callback(
            '/&#x([0-9A-F]+);/i',
            static fn(array $match): string => \mb_chr((int) \hexdec($match[1])),
            $html,
        );
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

    public function testEveryToolOfTheDefaultToolbarIsRendered(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();

        foreach (new DefaultToolsBuilder()->build() as $tool) {
            if (null !== $tool->command) {
                $this->assertStringContainsString('data-wysiwyg-tool="' . $tool->name . '"', $html, $tool->name);
            }
        }

        // The tools which are rendered by a component
        $this->assertSame(2, substr_count($html, 'umanit-tiptap__color-palette umanit-tiptap__dropdown-menu'));
        $this->assertStringContainsString('title="Choose a Color"', $html);
        $this->assertStringContainsString('title="Background color"', $html);
        $this->assertStringContainsString('umanit-tiptap__styles-btn', $html);
        $this->assertStringContainsString('title="Block Styles"', $html);
        $this->assertMatchesRegularExpression('#umanit-tiptap__styles-btn[^>]*>\s*Styles\s*</button>#', $html);
        $this->assertStringContainsString('ut-fs-12', $html);
        $this->assertStringContainsString('title="Font size"', $html);
        $this->assertStringContainsString('umanit-tiptap__btn--source" title="Source Mode"', $html);
    }

    public function testEveryLabelOfTheModalsIsTiedToAField(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();

        preg_match_all('/\sid="([^"]+)"/', $html, $ids);
        preg_match_all('/<label\b[^>]*\sfor="([^"]+)"/', $html, $targets);

        $this->assertNotEmpty($targets[1]);

        foreach ($targets[1] as $target) {
            $this->assertContains($target, $ids[1], \sprintf('No field has the id "%s"', $target));
        }

        // The fields of the table, cell and link modals are named after their label
        foreach (['tableRowsInput', 'tableCaptionInput', 'cellTextAlignInput', 'linkUrlInput'] as $field) {
            $this->assertMatchesRegularExpression('/for="' . $field . '-[^"]+"/', $html, $field);
        }
    }

    public function testTwoEditorsOfAPageDoNotShareAnId(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" /><twig:Umanit:Tiptap:Editor id="bar" />',
        )->render();

        preg_match_all('/\sid="([^"]+)"/', $html, $ids);

        $this->assertSame([], array_keys(array_filter(
            array_count_values($ids[1]),
            static fn(int $count): bool => $count > 1,
        )));
        $this->assertStringContainsString('id="tableRowsInput-tableConfigModal-foo"', $html);
        $this->assertStringContainsString('id="tableRowsInput-tableConfigModal-bar"', $html);
    }

    public function testDropdownsAndTheirButtonsAreAccessible(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();

        // A dropdown (styles, colors, font size) tells its state on its own button, which is named
        $this->assertGreaterThanOrEqual(4, substr_count($html, 'aria-expanded="false"'));
        $this->assertSame(
            substr_count($html, 'data-umanit--tiptap--dropdown-target="button"'),
            substr_count($html, 'aria-expanded="false"'),
        );
        $this->assertMatchesRegularExpression(
            '#<button[^>]*class="umanit-tiptap__btn"[^>]*title="Choose a Color"[^>]*aria-label="Choose a Color"#',
            $html,
        );
        $this->assertMatchesRegularExpression('#<button[^>]*title="Font size"[^>]*aria-label="Font size"#', $html);
        // The wrapper no longer carries the title
        $this->assertDoesNotMatchRegularExpression(
            '#<div class="umanit-tiptap__color-wrapper umanit-tiptap__dropdown"[^>]*title=#',
            $html,
        );
    }

    public function testTableContextMenuIsAMenu(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();

        $this->assertMatchesRegularExpression('#<ul[^>]*role="menu"#', $html);
        $this->assertStringContainsString('role="menuitem"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('data-umanit--tiptap--editor-target="contextMenu"', $html);
    }

    public function testTemplatesAreAFieldsetWhoseLegendIsTheHint(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();

        $this->assertMatchesRegularExpression(
            '#<fieldset[^>]*>\s*<legend[^>]*umanit-tiptap__hint[^>]*>[^<]+</legend>#',
            $html,
        );
        $this->assertStringNotContainsString('<p class="umanit-tiptap__hint">wysiwyg', $html);
    }

    public function testRequiredFieldsOfTheModalsAreExplained(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();

        // The table and the image modals
        $this->assertSame(2, substr_count($html, 'Fields marked with an asterisk (*) are required.'));
    }

    public function testToolsTellWhenTheyAreActive(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();
        $html = self::withoutNumericReferences($html);

        $this->assertStringContainsString(
            'data-umanit--tiptap--editor-active-param="{&quot;name&quot;:&quot;bold&quot;}"',
            $html,
        );
        $this->assertStringContainsString(
            'data-umanit--tiptap--editor-active-param="{&quot;name&quot;:&quot;heading&quot;,'
            . '&quot;attributes&quot;:{&quot;level&quot;:2}}"',
            $html,
        );
        $this->assertStringContainsString(
            'data-umanit--tiptap--editor-active-param="{&quot;attributes&quot;:{&quot;textAlign&quot;:'
            . '&quot;center&quot;}}"',
            $html,
        );
    }

    public function testDropdownEntriesTellWhenTheyAreActive(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" />')->render();
        $html = self::withoutNumericReferences($html);

        // A color, a size and a block style
        $this->assertStringContainsString(
            'data-umanit--tiptap--editor-active-param="{&quot;spanClass&quot;:&quot;ut-color-red&quot;}"',
            $html,
        );
        $this->assertStringContainsString(
            'data-umanit--tiptap--editor-active-param="{&quot;spanClass&quot;:&quot;ut-bg-red&quot;}"',
            $html,
        );
        $this->assertStringContainsString(
            'data-umanit--tiptap--editor-active-param="{&quot;spanClass&quot;:&quot;ut-fs-12&quot;}"',
            $html,
        );
        $this->assertStringContainsString(
            'data-umanit--tiptap--editor-active-param="{&quot;blockClass&quot;:&quot;'
            . OverridingStylesSetsBuilder::CSS_CLASS . '&quot;}"',
            $html,
        );
    }

    public function testToolOfTheApplicationIsRenderedAsACommandButton(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" toolbar="custom" />',
        )->render();
        $html = self::withoutNumericReferences($html);

        $this->assertStringContainsString('data-wysiwyg-tool="highlight"', $html);
        $this->assertStringContainsString(
            'data-umanit--tiptap--editor-active-param="{&quot;name&quot;:&quot;highlight&quot;}"',
            $html,
        );
        $this->assertStringContainsString('title="Highlight"', $html);
        $this->assertStringContainsString('data-umanit--tiptap--editor-command-param="toggleHighlight"', $html);
        $this->assertStringContainsString(
            'data-umanit--tiptap--editor-args-param="{&quot;color&quot;:&quot;yellow&quot;}"',
            $html,
        );
        $this->assertStringContainsString('data-umanit--tiptap--editor-command-param="callout.open"', $html);
        $this->assertStringContainsString('>Callout</span>', $html);
        $this->assertStringNotContainsString('ut-visually-hidden">Callout', $html);
        $this->assertStringContainsString('data-wysiwyg-tool="bold"', $html);
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
        $html = self::withoutNumericReferences($html);

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

    public function testFormWidgetAttributesLandOnTheTextarea(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get('test.form.factory');
        $this->assertInstanceOf(FormFactoryInterface::class, $factory);

        $form = $factory
            ->createNamed('page', FormType::class)
            ->add('body', TiptapType::class)
        ;

        $html = $this->getTwig()->createTemplate(<<<'TWIG'
            {{ form_widget(form.body, {attr: {
              'data-app--wysiwyg-target': 'input',
              'aria-describedby': 'help"x',
              class: 'app-field',
              rows: 8,
              'data-flag': true,
              'data-off': false,
              'data-none': null,
              id: 'ignored',
              name: 'ignored',
            }}) }}
            TWIG)->render(['form' => $form->createView()]);

        preg_match('#<textarea[^>]*>#', $html, $matches);
        $textarea = $matches[0] ?? '';

        $this->assertStringContainsString('data-app--wysiwyg-target="input"', $textarea);
        $this->assertStringContainsString('aria-describedby="help&quot;x"', $textarea);
        $this->assertStringContainsString('class="umanit-tiptap__source ut-hidden app-field"', $textarea);
        $this->assertStringContainsString('rows="8"', $textarea);
        $this->assertStringNotContainsString('rows="15"', $textarea);
        $this->assertMatchesRegularExpression('#\sdata-flag(\s|>)#', $textarea);
        $this->assertStringNotContainsString('data-off', $textarea);
        $this->assertStringNotContainsString('data-none', $textarea);
        $this->assertStringContainsString('id="page_body"', $textarea);
        $this->assertStringContainsString('name="page[body]"', $textarea);
        $this->assertStringNotContainsString('ignored', $textarea);
        // The editor keeps its own target on the textarea
        $this->assertStringContainsString('data-umanit--tiptap--editor-target="input"', $textarea);
    }

    public function testTextareaHasTheDefaultsWithoutAttributes(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" name="bar" />')->render();

        preg_match('#<textarea[^>]*>#', $html, $matches);

        $this->assertStringContainsString('class="umanit-tiptap__source ut-hidden"', $matches[0] ?? '');
        $this->assertStringContainsString('rows="15"', $matches[0] ?? '');
    }

    public function testEditorIsEnabledByDefault(): void
    {
        $html = $this->getTwig()->createTemplate('<twig:Umanit:Tiptap:Editor id="foo" name="bar" />')->render();

        preg_match('#<textarea[^>]*>#', $html, $matches);

        $this->assertStringNotContainsString('umanit-tiptap--disabled', $html);
        $this->assertStringNotContainsString('disabled-value', $html);
        $this->assertDoesNotMatchRegularExpression('#\sdisabled(\s|>)#', $matches[0] ?? '');
    }

    public function testDisabledEditorIsFlaggedAndItsTextareaIsDisabled(): void
    {
        $html = $this->getTwig()->createTemplate(
            '<twig:Umanit:Tiptap:Editor id="foo" name="bar" :disabled="true" />',
        )->render();

        preg_match('#<textarea[^>]*>#', $html, $matches);

        $this->assertStringContainsString('umanit-tiptap--disabled', $html);
        $this->assertStringContainsString('disabled-value="true"', $html);
        $this->assertMatchesRegularExpression('#\sdisabled(\s|>)#', $matches[0] ?? '');
    }

    public function testDisabledFormFieldDisablesTheEditor(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get('test.form.factory');
        $this->assertInstanceOf(FormFactoryInterface::class, $factory);

        $form = $factory
            ->createNamed('page', FormType::class)
            ->add('body', TiptapType::class, ['disabled' => true])
        ;

        $html = $this->getTwig()->render('form.html.twig', ['form' => $form->createView()]);

        $this->assertStringContainsString('umanit-tiptap--disabled', $html);
        $this->assertStringContainsString('disabled-value="true"', $html);
    }

    private function getTwig(): Environment
    {
        self::bootKernel();

        $twig = self::getContainer()->get(Environment::class);
        $this->assertInstanceOf(Environment::class, $twig);

        return $twig;
    }
}
