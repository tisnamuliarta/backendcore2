<?php

namespace App\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;

class CustomQueryGrammarMsSql extends Grammar
{

    /**
     * The grammar table prefix.
     *
     * @var string
     */
    protected $tablePrefix = '';
    protected $tablesPrefix = '';

    public function __construct()
    {
        //$this->tablesPrefix = env('DB_SCHEMA');
        $this->tablesPrefix = config('app.db_schema_sql');
    }

    /**
     * Wrap a table in keyword identifiers.
     *
     * @param \Illuminate\Database\Query\Expression|string $table
     * @return string
     */
    public function wrapTable($table)
    {
        if (!$this->isExpression($table)) {
            $result = $this->wrap('' . $table, true);
            //return "$this->tablePrefix" . "." . $result;

            return $this->tablesPrefix . '.' . $this->wrap('' . $table, true);
            //return $this->wrap('' . $table, true);
        }

        return $this->getValue($table);
    }

}