<?php

declare(strict_types=1);

namespace App\Application\Email\Template;

use App\Application\Email\Template\Exception\MissingTemplateParameterException;

final readonly class EmailTemplate
{
    /**
     * @param array<string, mixed> $parameters
     */
    private function __construct(
        private string $name,
        private array $parameters,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws \InvalidArgumentException
     */
    public static function named(string $name, array $parameters = []): self
    {
        if ('' === $name) {
            throw new \InvalidArgumentException('Email template name cannot be empty.');
        }

        return new self($name, $parameters);
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    public function has(string $parameter): bool
    {
        return \array_key_exists($parameter, $this->parameters);
    }

    /**
     * @throws MissingTemplateParameterException
     */
    public function get(string $parameter): mixed
    {
        if (!$this->has($parameter)) {
            throw new MissingTemplateParameterException($parameter, $this->name);
        }

        return $this->parameters[$parameter];
    }
}
