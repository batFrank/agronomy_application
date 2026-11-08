<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\StructuredOutput;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ValidationException;
use Symfony\AI\Platform\StructuredOutput\ResponseFormatFactory;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Turns what the model answered, already decoded from JSON, into the DTO that describes the output
 * (a class with `#[Schema]` and constraints). It is generic: nothing in it knows the shape of any
 * output, a fork uses it as it is with its own class.
 *
 * It exists because the package does not enforce `additionalProperties: false`: with a class as
 * `response_format` it deserializes with an ObjectNormalizer that silently ignores any key that maps
 * to no property, so an answer with a key too many would come out as a valid object and the extra data
 * would be lost without a trace. Asking for the schema as an array (responseFormat()), the package
 * hands the decoded array back untouched, and decode() checks it against that same schema before
 * denormalizing: every object that declares its properties accepts no key outside them, and every array
 * that declares its items is a JSON list, at any depth.
 *
 * What the package did on its own is reproduced here so nothing is lost: the serializer is the recipe of
 * the package's own StructuredOutput\Serializer (which is internal to it) and the exceptions are the
 * ones it throws, all of the family the gateways catch.
 */
final readonly class StructuredOutputDecoder
{
    private ResponseFormatFactory $responseFormatFactory;

    private DenormalizerInterface $denormalizer;

    public function __construct(private ValidatorInterface $validator)
    {
        $this->responseFormatFactory = new ResponseFormatFactory();

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $this->denormalizer = new Serializer(
            [
                new DateTimeNormalizer(),
                new BackedEnumNormalizer(),
                new ObjectNormalizer(
                    propertyTypeExtractor: new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]),
                    classDiscriminatorResolver: new ClassDiscriminatorFromClassMetadata($classMetadataFactory),
                ),
                new ArrayDenormalizer(),
            ],
            [new JsonEncoder()],
        );
    }

    /**
     * What to pass as the `response_format` option: the schema of the class, built with the package's
     * own factory (the one it would use for the class itself) but as an array, so the package does not
     * deserialize on its own.
     *
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    public function responseFormat(string $class): array
    {
        return $this->responseFormatFactory->create($class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws RuntimeException    when the answer is not a JSON object, breaks the schema (a key it does not
     *                             declare, an array that is not a list) or does not deserialize into the class
     * @throws ValidationException when the object breaks one of its constraints
     * @throws \LogicException     when the schema uses a construct this decoder cannot check
     */
    public function decode(mixed $output, string $class): object
    {
        // A JSON list is an array in PHP too, and would be reported as a pile of undeclared keys (0, 1...).
        if (!\is_array($output) || ([] !== $output && array_is_list($output))) {
            throw new RuntimeException('The structured output is not a JSON object.');
        }

        /** @var array<string, mixed> $schema */
        $schema = $this->responseFormat($class)['json_schema']['schema'];
        $problems = $this->problemsOf($output, $schema, '');
        if ([] !== $problems) {
            throw new RuntimeException(\sprintf('The structured output does not fit the schema of "%s": %s.', $class, implode('; ', $problems)));
        }

        try {
            $object = $this->denormalizer->denormalize($output, $class, 'json');
        } catch (SerializerException $e) {
            throw new RuntimeException(\sprintf('Cannot deserialize the content into the "%s" class.', $class), previous: $e);
        }
        if (!$object instanceof $class) {
            throw new \LogicException(\sprintf('The denormalizer did not return an instance of "%s".', $class));
        }

        $violations = $this->validator->validate($object);
        if (0 !== \count($violations)) {
            throw new ValidationException($violations);
        }

        return $object;
    }

    /**
     * Walks the schema and the value together. What the value has that does not match the kind of the
     * node (a string where an object goes) is left for the denormalizer to reject with its own error.
     *
     * @param array<string, mixed> $schema
     *
     * @return list<string>
     */
    private function problemsOf(mixed $value, array $schema, string $path): array
    {
        foreach (['$ref', 'anyOf', 'oneOf', 'allOf'] as $keyword) {
            if (isset($schema[$keyword])) {
                throw new \LogicException(\sprintf('The schema uses "%s" at "%s", which this decoder cannot check: the keys of what it describes would go unchecked.', $keyword, '' === $path ? '<root>' : $path));
            }
        }

        $types = (array) ($schema['type'] ?? []);
        $problems = [];

        if (\in_array('object', $types, true) && isset($schema['properties']) && \is_array($schema['properties']) && \is_array($value) && !([] !== $value && array_is_list($value))) {
            if (false === ($schema['additionalProperties'] ?? null)) {
                foreach (array_diff(array_keys($value), array_keys($schema['properties'])) as $key) {
                    $problems[] = \sprintf('"%s" is not declared', self::join($path, (string) $key));
                }
            }
            foreach ($schema['properties'] as $name => $propertySchema) {
                if (\is_array($propertySchema) && \array_key_exists($name, $value)) {
                    $problems = [...$problems, ...$this->problemsOf($value[$name], $propertySchema, self::join($path, (string) $name))];
                }
            }
        }

        if (\in_array('array', $types, true) && isset($schema['items']) && \is_array($schema['items']) && \is_array($value)) {
            if (!array_is_list($value)) {
                return [...$problems, \sprintf('"%s" is not a list', $path)];
            }
            foreach ($value as $index => $item) {
                $problems = [...$problems, ...$this->problemsOf($item, $schema['items'], \sprintf('%s[%d]', $path, $index))];
            }
        }

        return $problems;
    }

    private static function join(string $path, string $key): string
    {
        return '' === $path ? $key : $path . '.' . $key;
    }
}
