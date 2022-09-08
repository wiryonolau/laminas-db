<?php

declare(strict_types=1);

namespace Itseasy\Database\Sql\Filter;

use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\SqlInterface;

/**
 * Example Rule
 *  regex : 
 *      is:active
 *  callback : 
 *      function() : PredicateInterface { 
 *          $p = new Laminas\Db\Sql\Predicate(); 
 *          return $p->equalTo("state" => true); 
 *      }
 * 
 *  regex : 
 *      tech_creation_date:(\d{4}-\d{2}-\d{2}):(\d{4}-\d{2}-\d{2})
 *  callback : 
 *      function(arg1, arg2) : PredicateInterface {
 *          return new Laminas\Db\Sql\Predicate\Betweeen("tech_creation_date", $arg1, $arg2);
 *      }
 *  example value:
 *      tech_creation_date:2022-01-01:2022-02-03
 * 
 *  regex : 
 *      name:([a-z0-9]+)
 *  callback : 
 *      function($value) : PredicateInterface {
 *          return new Laminas\Db\Sql\PredicateSet([
 *              new Lamainas|Db\Sql\Predicate\Like("first_name", "%$value%"),
 *              new Lamainas|Db\Sql\Predicate\Like("last_name", "%$value%"),
 *              new Lamainas|Db\Sql\Predicate\Like("email", "%$value%"),
 *          ], Laminas\Db\Sql\Predicate\PredicateSet::COMBINED_BY_AND );
 *      }
 *  example value:
 *      name:somebody
 */
class RegexSqlFilter implements SqlFilterInterface
{
    protected $rules = [];

    public function __construct(array $rules = [])
    {
        $this->setRules($rules);
    }

    public function clearRules(): void
    {
        $this->rules = [];
    }

    public function setRules(array $rules, bool $clear = true): void
    {
        if ($clear) {
            $this->clearRules();
        }
        foreach ($rules as $rule) {
            if (empty($rule) or count($rule) !== 2) {
                continue;
            }

            call_user_func_array(
                [$this, "addRule"],
                $rule
            );
        }
    }

    public function addRule(string $regex, $callback): void
    {
        if (is_callable($callback)) {
            $obj = (object) [
                "regex" => $regex,
                "callback" => $callback
            ];
            $this->rules[] = $obj;
        }
    }

    public function applyFilter(
        SqlInterface $sql,
        string $value,
        string $combination = PredicateSet::OP_AND
    ): SqlInterface {
        foreach ($this->rules as $rule) {
            $regex = sprintf("/^%s$/", $rule->regex);
            preg_match($regex, $value, $matches);

            if (empty($matches)) {
                continue;
            }

            array_shift($matches);
            $sql->where(
                call_user_func_array($rule->callback, $matches),
                $combination
            );
        }
        return $sql;
    }
}
