<?php

namespace KevinRider\LaravelEtrade\Dtos;

use Illuminate\Contracts\Support\Arrayable;
use ReflectionClass;
use ReflectionProperty;

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
            $data[$property->getName()] = $this->{$property->getName()};
        }

        return $data;
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

                if ($propertyType && $propertyType->getName() === 'string' && is_array($value) && empty($value)) {
                    $value = '';
                }

                $this->{$key} = $value;
            }
        }
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
}
