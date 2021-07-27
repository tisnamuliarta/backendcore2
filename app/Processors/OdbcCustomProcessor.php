<?php

namespace App\Processors;

use Odbc\OdbcProcessor;
use Illuminate\Database\Query\Builder;

class OdbcCustomProcessor extends OdbcProcessor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param Builder $query
     * @param string $sql
     * @param array $values
     * @param string $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null): int
    {
        $query->getConnection()->insert($sql, $values);

        $id = $this->getLastInsertId($query, $sequence);

        return is_numeric($id) ? (int)$id : $id;
    }

    /**
     * @param Builder $query
     * @param null $sequence
     * @return mixed
     */
    public function getLastInsertId(Builder $query, $sequence = null)
    {
        if ($query->from == 'OUSR_H') {
            $entry = $query->getConnection()->table($query->from)->latest('U_UserID')->first();
            return $entry['U_UserID'];
        } elseif ($query->from == 'U_OWST') {
            $entry = $query->getConnection()->table($query->from)->latest('U_WstCode')->first();
            return $entry['U_WstCode'];
        } elseif ($query->from == 'U_OWTM') {
            $entry = $query->getConnection()->table($query->from)->latest('U_WtmCode')->first();
            return $entry['U_WtmCode'];
        } elseif ($query->from == 'U_OWDD') {
            $entry = $query->getConnection()->table($query->from)->latest('U_WddCode')->first();
            return $entry['U_WddCode'];
        } elseif ($query->from == 'JOBS' || $query->from == 'FAILED_JOBS' || $query->from == 'AUDITS') {
            $entry = $query->getConnection()->table($query->from)->latest('id')->first();
            return $entry['id'];
        } else {
            $entry = $query->getConnection()->table($query->from)->latest('U_DocEntry')->first();
            return $entry['U_DocEntry'];
        }
    }
}
