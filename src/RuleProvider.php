<?php

namespace Aspire\Di;

abstract class RuleProvider
{
    public abstract static function load();

    public function rules()
    {
        return $this->rules;
    }
}
