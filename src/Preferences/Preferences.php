<?php

namespace Statamic\Preferences;

use Illuminate\Support\Arr;
use Statamic\Facades\User;

class Preferences
{
    protected $dotted = [];
    protected $preferences = [];
    protected $preventMergingChildren = [];

    /**
     * Get default preferences instance.
     *
     * @return DefaultPreferences
     */
    public function default()
    {
        return app(DefaultPreferences::class);
    }

    /**
     * Prevent merging child data within a specific dotted preferences key.
     *
     * @param  string  $dottedKey
     */
    public function preventMergingChildren($dottedKey)
    {
        $this->preventMergingChildren[] = $dottedKey;
    }

    /**
     * Get all preferences, merged in a specific order for precedence.
     *
     * @return array
     */
    public function all()
    {
        if (auth()->guest()) {
            return [];
        }

        if ($this->preferences) {
            return $this->preferences;
        }

        return $this
            ->resetState()
            ->mergeDottedUserPreferences()
            ->mergeDottedRolePreferences()
            ->mergeDottedDefaultPreferences()
            ->getMultiDimensionalPreferences();
    }

    /**
     * Get preference off user or role, respecting the precedence setup in `all()`.
     *
     * @param  mixed  $key
     * @param  mixed  $fallback
     * @return mixed
     */
    public function get($key, $fallback = null)
    {
        return Arr::get($this->all(), $key, $fallback);
    }

    /**
     * Reset state.
     *
     * @return $this
     */
    protected function resetState()
    {
        $this->dotted = [];
        $this->preferences = [];

        return $this;
    }

    /**
     * Merged dotted user preferences.
     *
     * @return $this
     */
    protected function mergeDottedUserPreferences()
    {
        $this->dotted += $this->arrayDotPreferences(User::current()->preferences());

        return $this;
    }

    /**
     * Merged dotted role preferences.
     *
     * @return $this
     */
    protected function mergeDottedRolePreferences()
    {
        foreach (User::current()->roles() as $role) {
            $this->dotted += $this->arrayDotPreferences($role->preferences());
        }

        return $this;
    }

    /**
     * Merged dotted default preferences.
     *
     * @return $this
     */
    protected function mergeDottedDefaultPreferences()
    {
        $defaultPreferences = $this->default()->all();

        $this->dotted += $this->arrayDotPreferences($defaultPreferences);

        return $this;
    }

    /**
     * Array dot preferences array, while respecting `preventMergingChildren` property.
     *
     * @param  array  $array
     * @return array
     */
    protected function arrayDotPreferences($array)
    {
        $preserve = [];

        foreach ($this->preventMergingChildren as $dottedKey) {
            if ($childData = Arr::pull($array, $dottedKey)) {
                $preserve[$dottedKey] = $childData;
            }
        }

        return array_merge(Arr::dot($array), $preserve);
    }

    /**
     * Get multi-dimensional array of preferences from dotted preferences.
     *
     * @return array
     */
    protected function getMultiDimensionalPreferences()
    {
        foreach ($this->dotted as $key => $value) {
            Arr::set($this->preferences, $key, $value);
        }

        return $this->preferences;
    }
}
