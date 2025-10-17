<?php

namespace Modera\ConfigBundle\Config;

use Modera\ConfigBundle\Entity\ConfigurationEntry;

/**
 * @copyright 2014 Modera Foundation
 */
class AsIsHandler implements HandlerInterface
{
    public function getReadableValue(ConfigurationEntry $entry): string
    {
        $value = $entry->getDenormalizedValue();

        if (\is_array($value)) {
            return \json_encode($value) ?: '';
        }

        return (string) $value;
    }

    /**
     * @return array<mixed>|bool|float|int|string
     */
    public function getValue(ConfigurationEntry $entry): array|bool|float|int|string
    {
        return $entry->getDenormalizedValue();
    }

    public function convertToStorageValue(mixed $value, ConfigurationEntry $entry): array|bool|float|int|string
    {
        if (\is_array($value) || \is_bool($value) || \is_float($value) || \is_int($value) || \is_string($value)) {
            return $value;
        }

        throw new \RuntimeException('Unsupported value type.');
    }
}
