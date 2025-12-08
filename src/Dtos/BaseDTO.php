<?php

namespace KevinRider\LaravelEtrade\Dtos;

use Illuminate\Contracts\Support\Arrayable;
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
}
