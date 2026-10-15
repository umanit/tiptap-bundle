<?php

declare(strict_types=1);

namespace Umanit\TiptapBundle\Tests\Unit\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Umanit\TiptapBundle\Builder\DefaultColorsSetBuilder;
use Umanit\TiptapBundle\Builder\DefaultStylesSetsBuilder;
use Umanit\TiptapBundle\Builder\DefaultTemplatesSetBuilder;
use Umanit\TiptapBundle\Builder\DefaultToolbarBuilder;

#[CoversClass(DefaultColorsSetBuilder::class)]
#[CoversClass(DefaultStylesSetsBuilder::class)]
#[CoversClass(DefaultTemplatesSetBuilder::class)]
#[CoversClass(DefaultToolbarBuilder::class)]
class DefaultBuildersTest extends TestCase
{
    public function testDefaultBuildersAreRegisteredUnderTheDefaultName(): void
    {
        self::assertSame('default', DefaultColorsSetBuilder::getDefaultIndexName());
        self::assertSame('default', DefaultStylesSetsBuilder::getDefaultIndexName());
        self::assertSame('default', DefaultTemplatesSetBuilder::getDefaultIndexName());
        self::assertSame('default', DefaultToolbarBuilder::getDefaultIndexName());
    }

    public function testDefaultToolbarContainsTheOptionalTools(): void
    {
        $toolbar = new DefaultToolbarBuilder()->build();

        self::assertTrue($toolbar->hasTableTool());
        self::assertTrue($toolbar->hasImageTool());
        self::assertTrue($toolbar->hasLinkTool());
        self::assertTrue($toolbar->hasTemplateTool());
        self::assertTrue($toolbar->hasAnchorTool());
    }

    public function testDefaultStylesAreNamespacedWithTheBundlePrefix(): void
    {
        foreach (new DefaultStylesSetsBuilder()->build() as $set) {
            foreach ($set->styles as $style) {
                self::assertStringStartsWith('ut-', $style->class);
            }
        }
    }

    /**
     * Every label returned by a default builder must exist in both shipped translation files.
     */
    public function testDefaultBuildersLabelsAreTranslatedInEveryLocale(): void
    {
        $keys = [];

        foreach (new DefaultColorsSetBuilder()->build() as $color) {
            $keys[] = $color->label;
        }

        foreach (new DefaultStylesSetsBuilder()->build() as $set) {
            $keys[] = $set->title;

            foreach ($set->styles as $style) {
                $keys[] = $style->name;
            }
        }

        foreach (new DefaultTemplatesSetBuilder()->build() as $template) {
            $keys[] = $template->name;
            $keys[] = $template->description;
        }

        foreach (['en', 'fr'] as $locale) {
            $messages = $this->loadCatalogue($locale);

            foreach (array_filter($keys) as $key) {
                self::assertArrayHasKey($key, $messages, \sprintf('"%s" is missing in "%s".', $key, $locale));
            }
        }
    }

    public function testTranslationCatalogsHaveTheSameKeys(): void
    {
        $en = $this->loadCatalogue('en');
        $fr = $this->loadCatalogue('fr');

        self::assertSame([], array_values(array_diff(array_keys($en), array_keys($fr))), 'Missing in fr.');
        self::assertSame([], array_values(array_diff(array_keys($fr), array_keys($en))), 'Missing in en.');
    }

    /**
     * @return array<string, string>
     */
    private function loadCatalogue(string $locale): array
    {
        $messages = Yaml::parseFile(\dirname(__DIR__, 3) . '/translations/UmanitTiptapBundle.' . $locale . '.yaml');
        self::assertIsArray($messages);

        /** @var array<string, string> $messages */
        return $messages;
    }
}
