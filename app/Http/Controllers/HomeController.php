<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function table(Request $request)
    {
        return response()->json([
            'columns' => [
                [
                    'field' => 'id',
                    'headerName' => 'ID',
                    'width' => 90
                ],
                [
                    'field' => 'firstName',
                    'headerName' => 'First name',
                    'width' => 150
                ],
                [
                    'field' => 'lastName',
                    'headerName' => 'Last name',
                    'width' => 150
                ]
            ],
            'rows' => [
                [
                    'id' => 1,
                    'lastName' => 'Snow',
                    'FirstName' => 'Jon'
                ],
                [
                    'id' => 2,
                    'lastName' => 'Lannister',
                    'FirstName' => 'Cersei'
                ]
            ]
        ]);
    }
}
