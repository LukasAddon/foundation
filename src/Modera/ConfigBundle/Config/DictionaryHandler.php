<?php

namespace Modera\ConfigBundle\Config;

use Modera\ConfigBundle\Entity\ConfigurationEntry;

/**
 * @copyright 2014 Modera Foundation
 */
class DictionaryHandler implements HandlerInterface
{
    public function getReadableValue(ConfigurationEntry $entry): ?string
    {
        $cfg = $entry->getServerHandlerConfig();

        /** @var bool|int|string $value */
        $value = $entry->getDenormalizedValue();

        if (\is_array($cfg['dictionary'] ?? null) && \is_string($cfg['dictionary'][$value] ?? null)) {
            return $cfg['dictionary'][$value];
        }

        return null;
    }

    public function getValue(ConfigurationEntry $entry): bool|int|string
    {
        /** @var bool|int|string $value */
        $value = $entry->getDenormalizedValue();

        return $value;
    }

    public function convertToStorageValue(mixed $value, ConfigurationEntry $entry): bool|int|string
    {
        if (\is_bool($value) || \is_int($value) || \is_string($value)) {
            return $value;
        }

        throw new \RuntimeException('Unsupported value type.');
    }
}
