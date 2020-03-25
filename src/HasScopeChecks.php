<?php

namespace BeyondCode\LaravelScopeChecks;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use BadMethodCallException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;

trait HasScopeChecks
{
    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        if (config('scope-checks.cache')) {
            static::updated(fn (Model $model) => self::cleanScopeChecks($model));
            static::deleted(fn (Model $model) => self::cleanScopeChecks($model));
        }
    }

    /**
     * Clean the cached scope checks.
     *
     * @param  Model  $model
     * @return void
     */
    protected static function cleanScopeChecks(Model $model)
    {
        foreach (get_class_methods($model) as $method) {
            if (Str::startsWith($method, 'scope')) {
                foreach (self::getScopeCheckPrefixes() as $prefix) {
                    $scopeCheckMethod = str_replace('scope', $prefix, $method);

                    Cache::forget(
                        self::getScopeCheckCacheKey($scopeCheckMethod, $model)
                    );
                }
            }
        }
    }

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
        if ($this->isScopeCheckMethod($method)) {
            $originalScopeMethod = $this->getOriginalScopeMethod($method);

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
        $builder = $this->newQuery()
            ->where(
                $this->getKeyName(),
                $this->getKey(),
            );

        $builder = call_user_func_array([
            $builder,
            $originalScopeMethod
        ], $parameters);

        if (config('scope-checks.cache')) {
            return Cache::rememberForever(
                self::getScopeCheckCacheKey($method, $this),
                fn () => $this->getScopeCheckResult($method, $builder)
            );
        }

        return $this->getScopeCheckResult($method, $builder);
    }

    /**
     * Returns the scope check result.
     *
     * @param  string  $method
     * @param  Builder  $builder
     * @return bool
     */
    protected function getScopeCheckResult(string $method, Builder $builder)
    {
        return $this->isScopeCheckNegationMethod($method) ? ! $builder->exists() : $builder->exists();
    }

    /**
     * Return the scope check cache key.
     *
     * @param  string  $method
     * @param  Model  $model
     * @return string
     */
    protected static function getScopeCheckCacheKey(string $method, Model $model): string
    {
        return 'scope-check.'.md5(
            $method.
            $model->getTable().
            $model->getKeyName().
            $model->getKey()
        );
    }

    /**
     * Verify if the method is a scope check.
     *
     * @param  string  $method
     * @return bool
     */
    protected function isScopeCheckMethod(string $method): bool
    {
        return Str::startsWith($method, 'is') || Str::startsWith($method, 'has');
    }

    /**
     * Is the scope check a negation?
     *
     * @param  string  $method
     * @return bool
     */
    protected function isScopeCheckNegationMethod(string $method): bool
    {
        return Str::startsWith($method, 'isNot') || Str::startsWith($method, 'hasNo');
    }

    /**
     * Returns the original scope name.
     *
     * @param  string  $method
     * @return string
     */
    protected function getOriginalScopeMethod(string $method): string
    {
        return preg_replace('/^(isNot|hasNo|is|has)(.*)$/m', '$2', $method);
    }

    /**
     * Return all scope check prefixes.
     *
     * @return array
     */
    protected static function getScopeCheckPrefixes(): array
    {
        return [
            'is',
            'isNot',
            'has',
            'hasNo',
        ];
    }
}
