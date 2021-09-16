<?php

namespace Aspire\DIC;

class Config
{
    const CONSTANT = '::CONSTANT';
    const GLOBAL = '::GLOBAL';
    const INSTANCE = '::INSTANCE';
    const CHAIN_CALL = '::CHAIN_CALL';
    const SELF = '::SELF';

    /**
     * @var array $rules Rules which have been set using addRule() or load()
     */
    private $rules = [];

    public function __construct($defaultRule = [])
    {
        if (!empty($defaultRule)) {
            $this->rules['*'] = $defaultRule;
        }
    }

    public function load(Config\Format $format)
    {
        $data = $format->load();
        $config = $this;

        if (isset($data['rules'])) {
            foreach ($data['rules'] as $rule) {
                $name = $rule['name'];
                unset($rule['name']);
                $config = $config->addRule($name, $rule);
            }
            return $config;
        }

        foreach ($data as $name => $rule) {
            $config = $config->addRule($name, $rule);
        }
        return $config;
    }

    // TODO: only clone instance once when adding multiple rules

    /**
     * Adds a rule $rule to the class $classname.
     *
     * The container can be fully configured using rules provided by associative arrays.
     * See {@link https://r.je/dice.html#example3} for a description of the rules.
     *
     * @param string $id The name of the class to add the rule for
     * @param array $rule The rule to add to it
     */
    public function addRule(string $id, $rule)
    {
        if (isset($rule['instanceOf'])
            && \is_string($rule['instanceOf'])
            && (!\array_key_exists('inherit', $rule) || $rule['inherit'] === true)
        ) {
            $rule = \array_merge_recursive($this->getRule($rule['instanceOf']), $rule);
        }

        // Allow substitutions rules to be defined with a leading slash
        if (isset($rule['substitutions'])) {
            foreach($rule['substitutions'] as $key => $value) {
                $rule['substitutions'][ltrim($key, '\\')] = $value;
            }
        }

        $config = clone $this;
        $config->rules[self::normalizeName($id)] = \array_merge_recursive($config->getRule($id), $rule);

        return $config;
    }

    /**
     * Returns the rule that will be applied to the class $id during make().
     *
     * @param string $id The name of the ruleset to get - can be a class or not
     * @return array Ruleset that applies when instantiating the given name
     */
    public function getRule(string $id)
    {
        // first, check for exact match
        $normalname = self::normalizeName($id);
        if (isset($this->rules[$normalname])) {
            return $this->rules[$normalname];
        }
        // next, look for a rule where:
        foreach ($this->rules as $key => $rule) {
            if ($key !== '*'                    // it's not the default rule,
                && \is_subclass_of($id, $key) // its name is a parent class of what we're looking for,
                && empty($rule['instanceOf'])   // it's not a named instance,
                && (!array_key_exists('inherit', $rule) || $rule['inherit'] === true) // and it applies to subclasses
            ) {
                return $rule;
            }
        }
        // if we get here, return the default rule if it's set
        return (isset($this->rules['*'])) ? $this->rules['*'] : [];
    }

    /**
     * @param string $name
     */
    private static function normalizeName(string $name)
    {
        return \strtolower(\ltrim($name, '\\'));
    }
}
