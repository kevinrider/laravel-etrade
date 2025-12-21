<?php

namespace KevinRider\LaravelEtrade\Dtos;

use Illuminate\Support\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use DateTimeInterface;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

abstract class BaseDTO implements Arrayable
{
    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $data = [];
        foreach ($properties as $property) {
            $data[$property->getName()] = $this->{$property->getName()} ?? $this->getSafeDefault($property);
        }

        return $data;
    }

    /**
     * @param string $xml
     * @return static
     */
    public static function fromXml(string $xml): static
    {
        $data = json_decode(json_encode(simplexml_load_string($xml)), true);

        return new static($data);
    }

    /**
     * @param array $data
     * @return void
     */
    protected function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $reflectionProperty = new ReflectionProperty($this, $key);
                $propertyType = $reflectionProperty->getType();

                if ($propertyType) {
                    $typeName = $propertyType->getName();
                    if ($typeName === 'string' && is_array($value) && empty($value)) {
                        $value = '';
                    }

                    if ($typeName === 'bool' && is_string($value) && preg_match('/(true|false)/i', $value)) {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    }

                    if ($typeName === Carbon::class) {
                        $value = $this->castToCarbon($value);
                    }
                }

                $this->{$key} = $value;
            }
        }
    }

    /**
     * @param ReflectionProperty|null $property
     * @return mixed
     */
    private function getSafeDefault(?ReflectionProperty $property): mixed
    {
        $type = $property->getType();
        if ($type === null || $type->allowsNull()) {
            return null;
        }
        $typeName = $type->getName();
        return match (strtolower($typeName)) {
            'string' => '',
            'int' => 0,
            'float' => 0.0,
            'bool' => false,
            'array' => [],
            'object' => new stdClass(),
            default => null,
        };
    }

    /**
     * @param mixed $value
     * @return Carbon|null
     */
    private function castToCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            if ((int) $value === 0) {
                return null;
            }

            $digits = (string) (int) $value;
            $length = strlen($digits);

            if ($length >= 13) {
                return Carbon::createFromTimestampMs((int) $value);
            }

            if ($length === 8) {
                try {
                    return Carbon::createFromFormat('Ymd', $digits);
                } catch (\Throwable) {
                    return null;
                }
            }

            if ($length === 6 || $length === 4) {
                $format = $length === 6 ? 'His' : 'Hi';
                try {
                    $time = Carbon::createFromFormat($format, $digits);
                    return Carbon::today()->setTimeFromTimeString($time->format('H:i:s'));
                } catch (\Throwable) {
                    return null;
                }
            }

            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            $quoteFormat = 'H:i:s T m-d-Y';

            $parsed = Carbon::createFromFormat($quoteFormat, $trimmed);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
