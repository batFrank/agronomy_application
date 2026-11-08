<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Config;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

#[CoversNothing]
class ServiceConfigurationTest extends TestCase
{
    private const string CONFIG_DIR = __DIR__ . '/../../../config';

    /**
     * A misspelled service id or alias does not break the container: Symfony registers an
     * alias for every singly implemented interface it finds in src/, so the misspelled
     * definition is dropped as unused and the interface silently resolves to whichever
     * implementation the src/ glob happens to see (issue #94).
     *
     * @throws \ReflectionException
     */
    #[DataProvider('provideClassReferencedInServiceConfigurationExistsCases')]
    public function testClassReferencedInServiceConfigurationExists(string $file, string $fqcn): void
    {
        self::assertTrue(
            class_exists($fqcn) || interface_exists($fqcn),
            \sprintf('"%s" references "%s", which does not exist.', $file, $fqcn),
        );

        // class_exists() is case-insensitive once the real class has been loaded by any
        // other test, so it is not enough on its own: only the declared name is reliable.
        self::assertSame(
            $fqcn,
            (new \ReflectionClass($fqcn))->getName(),
            \sprintf('"%s" references "%s", but the declared name is spelled differently.', $file, $fqcn),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideClassReferencedInServiceConfigurationExistsCases(): iterable
    {
        foreach (self::configurationFiles() as $file) {
            $parsed = Yaml::parseFile($file, Yaml::PARSE_CUSTOM_TAGS);
            $services = \is_array($parsed) ? $parsed['services'] ?? null : null;

            $found = [];
            self::collectAppClasses($services, $found);

            $label = basename(\dirname($file)) . '/' . basename($file);
            foreach (array_unique($found) as $fqcn) {
                yield $label . ': ' . $fqcn => [$label, $fqcn];
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function configurationFiles(): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::CONFIG_DIR, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && 'yaml' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param list<string> $found
     */
    private static function collectAppClasses(mixed $node, array &$found): void
    {
        if ($node instanceof TaggedValue) {
            self::collectAppClasses($node->getValue(), $found);

            return;
        }

        if (!\is_array($node)) {
            if (\is_string($node) && 1 === preg_match('/^@[?!]?(App\\\\[A-Za-z0-9_\\\\]+)$/', $node, $matches)) {
                $found[] = $matches[1];
            }

            return;
        }

        foreach ($node as $key => $value) {
            // Service ids ending in a backslash are resource prefixes, not classes.
            if (\is_string($key) && str_starts_with($key, 'App\\') && !str_ends_with($key, '\\')) {
                $found[] = $key;
            }

            if ('alias' === $key && \is_string($value)) {
                $target = ltrim($value, '@?');
                if (str_starts_with($target, 'App\\')) {
                    $found[] = $target;
                }
            }

            self::collectAppClasses($value, $found);
        }
    }
}
