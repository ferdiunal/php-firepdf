<?php

declare(strict_types=1);

namespace Ferdiunal\FirePdf\Support;

final class ArrayValue
{
    private function __construct() {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function string(array $data, string $key, string $default = ''): string
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        if (! array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function int(array $data, string $key, int $default = 0): int
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function float(array $data, string $key, float $default = 0.0): float
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_float($value)) {
            return $value !== 0.0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public static function intList(array $data, string $key): array
    {
        if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
            return [];
        }

        $result = [];
        foreach ($data[$key] as $value) {
            if (is_int($value)) {
                $result[] = $value;

                continue;
            }
            if (is_float($value)) {
                $result[] = (int) $value;

                continue;
            }
            if (is_string($value) && is_numeric($value)) {
                $result[] = (int) $value;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function assoc(array $data, string $key): array
    {
        return self::assocFromValue($data[$key] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function assocFromValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $entryKey => $entryValue) {
            if (is_string($entryKey)) {
                $result[$entryKey] = $entryValue;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function assocList(array $data, string $key): array
    {
        if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
            return [];
        }

        $result = [];
        foreach ($data[$key] as $value) {
            if (! is_array($value)) {
                continue;
            }

            $result[] = self::assocFromValue($value);
        }

        return $result;
    }
}
