<?php

namespace BeyondCode\LaravelScopeChecks;

use Illuminate\Support\Str;
use BadMethodCallException;

trait HasScopeChecks
{
    /**
     * Forward a method call to the given object.
     *
     * @param  mixed  $object
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    protected function forwardCallTo($object, $method, $parameters)
    {
        $preParsedMethod = ltrim($method, '_');

        if ($this->isCheckScopeMethod($preParsedMethod)) {
            $originalScopeMethod = $this->getOriginalCheckScopeMethod($preParsedMethod);

            if (method_exists($this, "scope{$originalScopeMethod}")) {
                return $this->handleScopeCheck($method, $originalScopeMethod, $parameters);
            }
        }

        return parent::forwardCallTo($object, $method, $parameters);
    }

    /**
     * Scope check handler.
     *
     * @param  string  $method
     * @param  string  $originalScopeMethod
     * @param  array  $parameters
     * @return bool|mixed
     */
    protected function handleScopeCheck(string $method, string $originalScopeMethod, array $parameters)
    {
        $builder = $this->newQuery()->where(
            $this->getKeyName(),
            $this->getKey()
        );

        array_push($parameters, ! $this->isInMemoryCheckScope($method));

        $result = call_user_func_array([
            $builder,
            $originalScopeMethod
        ], $parameters);

        if ($this->isInMemoryCheckScope($method)) {
            return $result;
        }

        if ($this->isCheckScopeNegationMethod($method)) {
            return ! $result->exists();
        }

        return  $result->exists();
    }

    /**
     * Verify if the method is a scope check.
     *
     * @param  string  $method
     * @return bool
     */
    protected function isCheckScopeMethod(string $method): bool
    {
        return Str::startsWith($method, 'is') || Str::startsWith($method, 'has');
    }

    /**
     * Is the scope check a negation?
     *
     * @param  string  $method
     * @return bool
     */
    protected function isCheckScopeNegationMethod(string $method): bool
    {
        return Str::startsWith($method, 'isNot') || Str::startsWith($method, 'hasNo');
    }

    /**
     * Is an "in memory" scope check?
     *
     * @param  string  $method
     * @return bool
     */
    protected function isInMemoryCheckScope(string $method): bool
    {
        return Str::startsWith($method, '_');
    }

    /**
     * Returns the original scope name.
     *
     * @param  string  $method
     * @return string
     */
    protected function getOriginalCheckScopeMethod(string $method): string
    {
        return preg_replace('/^(isNot|hasNo|is|has)(.*)$/m', '$2', $method);
    }
}
