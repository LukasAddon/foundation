<?php

namespace Modera\FoundationBundle\Util;

/**
 * @copyright 2025 Modera Foundation
 */
class BundleInspector
{
    private ?string $file = null;
    private ?string $name = null;
    private ?string $namespace = null;

    public function __construct(
        private readonly string $path,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFile(): string
    {
        if (!$this->file) {
            $this->file = \glob($this->getPath().'/*Bundle.php')[0];
        }

        return $this->file;
    }

    public function getName(): string
    {
        if (!$this->name) {
            $this->name = \basename($this->getFile(), '.php');
        }

        return $this->name;
    }

    public function getNamespace(): string
    {
        if (!$this->namespace) {
            $contents = \file_get_contents($this->getFile());
            if (\preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
                $this->namespace = $matches[1];
            } else {
                throw new \RuntimeException('Cannot determine namespace in '.$this->getFile());
            }
        }

        return $this->namespace;
    }
}
