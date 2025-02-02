<?php

namespace Spatie\SchemalessAttributes;

use ArrayAccess;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * @mixin Collection
 */
class SchemalessAttributes implements ArrayAccess, Arrayable, Countable, IteratorAggregate, Jsonable, JsonSerializable
{
    protected Model $model;

    protected string $sourceAttributeName;

    protected Collection $collection;

    public static function createForModel(Model $model, string $sourceAttributeName): self
    {
        return new static($model, $sourceAttributeName);
    }

    public function __construct(Model $model, string $sourceAttributeName)
    {
        $this->model = $model;

        $this->sourceAttributeName = $sourceAttributeName;

        $this->collection = new Collection($this->getRawSchemalessAttributes());
    }

    public function __call($name, $arguments)
    {
        $result = call_user_func_array([$this->collection, $name], $arguments);

        $this->override($this->collection->toArray());

        return $result;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value): void
    {
        $this->set($name, $value);
    }

    public function __isset($name): bool
    {
        $property = $this->get($name);

        return isset($property);
    }

    public function __empty($name): bool
    {
        $property = $this->get($name);

        return empty($property);
    }

    public function get($key, mixed $default = null): mixed
    {
        return data_get($this->collection, $key, $default);
    }

    public function set($key, mixed $value = null): static
    {
        if (is_iterable($key)) {
            return $this->override($this->collection->merge($key));
        }

        $items = $this->collection->toArray();

        return $this->override(data_set($items, $key, $value));
    }

    public function forget($keys): static
    {
        $items = $this->collection->toArray();

        foreach ((array) $keys as $key) {
            Arr::forget($items, $key);
        }

        return $this->override($items);
    }

    public function modelScope(): Builder
    {
        $arguments = debug_backtrace()[1]['args'];

        if (count($arguments) === 1) {
            [$builder] = $arguments;
            $schemalessAttributes = [];
        }

        if (count($arguments) === 2) {
            [$builder, $schemalessAttributes] = $arguments;
        }

        if (count($arguments) === 3) {
            [$builder, $name, $value] = $arguments;
            $schemalessAttributes = [$name => $value];
        }

        if (count($arguments) >= 4) {
            [$builder, $name, $operator, $value] = $arguments;
            $schemalessAttributes = [$name => $value];
        }

        foreach ($schemalessAttributes as $name => $value) {
            $builder->where("{$this->sourceAttributeName}->{$name}", $operator ?? '=', $value);
        }

        return $builder;
    }

    /**
     * Adds orWhere to the Query Builder instance to enable larger scopes for searching
     *
     * @return Builder
     */
    public function modelScopeByOrWhere(): Builder
    {
        $arguments = debug_backtrace()[1]['args'];

        if (count($arguments) === 1) {
            [$builder] = $arguments;
            $schemalessAttributes = [];
        }

        if (count($arguments) === 2) {
            [$builder, $schemalessAttributes] = $arguments;
        }

        if (count($arguments) === 3) {
            [$builder, $name, $value] = $arguments;
            $schemalessAttributes = [$name => $value];
        }

        if (count($arguments) >= 4) {
            [$builder, $name, $operator, $value] = $arguments;
            $schemalessAttributes = [$name => $value];
        }

        foreach ($schemalessAttributes as $name => $value) {
            $builder->orWhere("{$this->sourceAttributeName}->{$name}", $operator ?? '=', $value);
        }

        return $builder;
    }

    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetExists($offset): bool
    {
        return $this->collection->offsetExists($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->forget($offset);
    }

    public function toArray(): array
    {
        return $this->collection->toArray();
    }

    public function toJson($options = 0): string
    {
        return $this->collection->toJson($options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->collection->jsonSerialize();
    }

    public function count(): int
    {
        return $this->collection->count();
    }

    public function getIterator(): Traversable
    {
        return $this->collection->getIterator();
    }

    protected function getRawSchemalessAttributes(): array
    {
        $attributes = $this->model->getAttributes()[$this->sourceAttributeName] ?? '{}';

        $array = $this->model->fromJson($attributes);

        return is_array($array) ? $array : [];
    }

    protected function override(iterable $collection): static
    {
        $this->collection = new Collection($collection);
        $this->model->{$this->sourceAttributeName} = $this->collection->toArray();

        return $this;
    }
}
