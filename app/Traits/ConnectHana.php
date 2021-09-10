<?php

namespace App\Traits;

trait ConnectHana
{
    /**
     * @return bool|false|resource|string
     */
    protected function connectHana()
    {
        try {
            $conn = odbc_connect(
                'hanab1imipresv',
                'IMIP_ERESV_TEST',
                'Ereserve#1234',
                SQL_CUR_USE_ODBC
            );
            if (!$conn) {
                return false;
            } else {
                return $conn;
            }
        } catch (\Exception $exception) {
            return $exception->getMessage() . ' : ' . $exception->getFile();
        }
    }
}
