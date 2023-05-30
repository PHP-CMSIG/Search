<?php

declare(strict_types=1);

/*
 * This file is part of the Schranz Search package.
 *
 * (c) Alexander Schranz <alexander@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Schranz\Search\SEAL\Marshaller;

use Schranz\Search\SEAL\Schema\Field;

/**
 * @internal This class currently in discussion to be open for all adapters.
 *
 * The FlattenMarshaller will flatten all fields and save original document under a `_source` field.
 * The FlattenMarshaller should only be used when the Search Engine does not support nested objects and so
 *     the Marshaller should used in many cases instead.
 */
final class FlattenMarshaller
{
    /**
     * @param class-string<Field\AbstractField[]> $multiFieldJsonTypes
     */
    public function __construct(
        private readonly bool $dateAsInteger = false,
        private readonly bool $addRawFilterTextField = false,
        private readonly string $separator = '.',
        private readonly array $multiFieldJsonTypes = [],
    ) {
    }

    /**
     * @param Field\AbstractField[] $fields
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    public function marshall(array $fields, array $document): array
    {
        $jsonFields = [];
        $flattenDocument = $this->flatten($fields, $document, false, $jsonFields);
        foreach (array_unique($jsonFields) as $key) {
            if (isset($flattenDocument[$key])) {
                $flattenDocument[$key] = \json_encode($flattenDocument[$key], \JSON_THROW_ON_ERROR);
            }
        }

        $flattenDocument['_source'] = \json_encode($document, \JSON_THROW_ON_ERROR);

        return $flattenDocument;
    }

    /**
     * @param Field\AbstractField[] $fields
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    public function unmarshall(array $fields, array $raw): array
    {
        /** @var array<string, mixed> */
        return \json_decode($raw['_source'], true, flags: \JSON_THROW_ON_ERROR); // @phpstan-ignore-line
    }

    /**
     * @param Field\AbstractField[] $fields
     * @param array<string, mixed> $raw
     * @param string[] $jsonFields
     *
     * @return array<string, mixed>
     */
    private function flatten(array $fields, array $raw, bool $rootIsParentMultiple = false, array &$jsonFields = [])
    {
        foreach ($fields as $name => $field) {
            if (!\array_key_exists($name, $raw)) {
                continue;
            }

            match (true) {
                $field instanceof Field\ObjectField => $raw = $this->flattenObject($name, $raw, $field, $rootIsParentMultiple, $jsonFields),
                $field instanceof Field\TypedField => $raw = $this->flattenTyped($name, $raw, $field, $rootIsParentMultiple, $jsonFields),
                $field instanceof Field\DateTimeField => $document[$name] = $this->flattenDateTimeField($raw[$field->name], $field), // @phpstan-ignore-line
                default => null,
            };

            if ($this->addRawFilterTextField
                && $field instanceof Field\TextField && $field->searchable && ($field->sortable || $field->filterable)
            ) {
                $raw[$name . $this->separator . 'raw'] = $raw[$name];
            }

            if (($field->multiple || $rootIsParentMultiple)
                && \in_array(\get_class($field), $this->multiFieldJsonTypes)
            ) {
                $jsonFields[$name] = $name;
            }
        }

        return $raw;
    }

    /**
     * @param string|string[]|null $value
     *
     * @return int|string|string[]|int[]|null
     */
    private function flattenDateTimeField(null|string|array $value, Field\DateTimeField $field): null|int|string|array
    {
        if ($field->multiple) {
            /** @var string[]|null $value */

            return \array_map(function ($value) {
                if (null !== $value && $this->dateAsInteger) {
                    /** @var int */
                    return \strtotime($value);
                }

                return $value;
            }, (array) $value);
        }

        /** @var string|null $value */
        if (null !== $value && $this->dateAsInteger) {
            /** @var int */
            return \strtotime($value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $raw
     * @param string[] $jsonFields
     *
     * @return array<string, mixed>
     */
    private function flattenObject(string $name, array $raw, Field\ObjectField $field, bool $rootIsParentMultiple, array &$jsonFields = [])
    {
        /** @var array<array<string, mixed>> $objects */
        $objects = $field->multiple ? $raw[$name] : [$raw[$name]];

        $newRawData = [];
        foreach ($objects as $object) {
            $isParentMultiple = $rootIsParentMultiple || $field->multiple;
            $subJsonFields = [];
            $flattenedObject = $this->flatten($field->fields, $object, $isParentMultiple, $subJsonFields);
            foreach ($subJsonFields as $key) {
                $flattenKey = $name . $this->separator . $key;
                $jsonFields[$flattenKey] = $flattenKey;
            }

            foreach ($flattenedObject as $key => $value) {
                $flattenKey = $name . $this->separator . $key;

                if (!$isParentMultiple) {
                    $newRawData[$flattenKey] = $value;

                    continue;
                }

                if (!isset($newRawData[$flattenKey])) {
                    $newRawData[$flattenKey] = [];
                }

                if (!\is_array($value)) {
                    $newRawData[$flattenKey][] = $value; // @phpstan-ignore-line

                    continue;
                }

                foreach ($value as $valuePart) {
                    $newRawData[$flattenKey][] = $valuePart; // @phpstan-ignore-line
                }
            }
        }

        $keepOrderRaw = [];
        foreach ($raw as $key => $value) {
            if ($key === $name) {
                foreach ($newRawData as $key2 => $value2) {
                    $keepOrderRaw[$key2] = $value2;
                }

                continue;
            }

            $keepOrderRaw[$key] = $value;
        }

        return $keepOrderRaw;
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     */
    private function flattenTyped(string $name, array $raw, Field\TypedField $field, bool $rootIsParentMultiple, array &$jsonFields = [])
    {
        /** @var array<array<string, mixed>> $objects */
        $objects = $field->multiple ? $raw[$name] : [$raw[$name]];

        $newRawData = [];
        foreach ($objects as $object) {
            /** @var string $type */
            $type = $object[$field->typeField];
            unset($object[$field->typeField]);

            $isParentMultiple = $rootIsParentMultiple || $field->multiple;

            if (!isset($field->types[$type])) {
                throw new \RuntimeException(\sprintf(
                    'Type "%s" not found. Existing types are "%s"',
                    $type,
                    \implode('", "', \array_keys($field->types)),
                ));
            }

            $subJsonFields = [];
            $flattenedObject = $this->flatten($field->types[$type], $object, $isParentMultiple, $subJsonFields);
            foreach ($subJsonFields as $key) {
                $flattenKey = $name . $this->separator . $type . $this->separator . $key;
                $jsonFields[$flattenKey] = $flattenKey;
            }

            foreach ($flattenedObject as $key => $value) {
                $flattenKey = $name . $this->separator . $type . $this->separator . $key;

                if (!$isParentMultiple) {
                    $newRawData[$flattenKey] = $value;

                    continue;
                }

                if (!isset($newRawData[$flattenKey])) {
                    $newRawData[$flattenKey] = [];
                }

                if (!\is_array($value)) {
                    $newRawData[$flattenKey][] = $value; // @phpstan-ignore-line

                    continue;
                }

                foreach ($value as $valuePart) {
                    $newRawData[$flattenKey][] = $valuePart; // @phpstan-ignore-line
                }
            }
        }

        $keepOrderRaw = [];
        foreach ($raw as $key => $value) {
            if ($key === $name) {
                foreach ($newRawData as $key2 => $value2) {
                    $keepOrderRaw[$key2] = $value2;
                }

                continue;
            }

            $keepOrderRaw[$key] = $value;
        }

        return $keepOrderRaw;
    }
}
