<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Menu;
use App\Models\User;
use App\Models\UserCompany;
use App\Models\UserItmGrp;
use App\Models\UserMenu;
use App\Models\UserWhs;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function register(Request $request)
    {
        try {
            $attr = $request->validate([
                'name' => 'required|string|max:255',
                'username' => 'required|string|unique:users,username',
                'email' => 'required|string|email|unique:users,email',
                'password' => 'required|string|min:6|confirmed'
            ]);

            $user = User::create([
                'name' => $attr['name'],
                'username' => $attr['username'],
                'password' => bcrypt($attr['password']),
                'email' => $attr['email']
            ]);

            return $this->success([
                'token' => $user->createToken('API Token')->plainTextToken
            ], 'User Created!');
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 401);
        }
    }

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function login(Request $request)
    {
        try {
            $attr = $request->validate([
                'username' => 'required|string',
                'password' => 'required|string'
            ]);

            $username = $request->username;
            $password = $request->password;
            $app_name = (isset($request->app_name)) ? $request->app_name : 'E-RESERVATION';

            if ($username != 'manager') {
                $response = Http::post(env('CHERRY_TOKEN'), [
                    'CommandName' => 'RequestToken',
                    'ModelCode' => 'AppUserAccount',
                    'UserName' => $username,
                    'Password' => $password,
                    'ParameterData' => [],
                ]);

                if (isset($response['Token'])) {
                    // Insert data user
                    $user = $this->insertUser($response, $password);
                } else {
                    return $this->error($response['Message'], 401);
                }
            } else {
                $user = User::where('username', '=', 'manager')->first();
                $username = $user->id;
            }

            if (!Auth::attempt($attr)) {
                return $this->error('Credentials not match', 401);
            }

            $this->assignMenuToUser($username, $app_name);

            $company = $this->assignUserCompany($username);

            $this->assignRolePermissionToUser($username);

            if ($app_name == 'E-RESERVATION') {
                $this->assignUserWareHouse($username, $company);

                $this->assignUserItemGroups($username, $company);
            }

            return $this->success([
                'token' => auth()->user()->createToken('API Token')->plainTextToken,
                'user' => auth()->user()
            ]);
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 401);
        }
    }

    /**
     * @param $response
     * @param $password
     *
     * @return mixed
     */
    protected function insertUser($response, $password)
    {
        $data = [
            'name' => $response['Data']['Name'],
            'cherry_token' => $response['Token'],
            'username' => $response['UserName'],
            'password' => bcrypt($password),
            'email' => $response['Data']['Email'],
            'department' => $response['Data']['Organization'],
            'company' => $response['Data']['Company'],
            'position' => $response['Data']['Position'],
            'location' => $response['Data']['Location'],
            'company_code' => $response['Data']['CompanyCode'],
            'employee_code' => $response['Data']['EmployeeCode'],
        ];

        if (User::where('username', '=', $response['UserName'])->first()) {
            $user = User::where('username', '=', $response['UserName'])
                ->update($data);
        } else {
            $user = User::create($data);
        }
        return $user;
    }

    /**
     * @param $user_id
     * @param $value
     */
    protected function insertUserMenu($user_id, $value)
    {
        if (UserMenu::where('user_id', '=', $user_id)
                ->where('menu_id', '=', $value->id)
                ->count() < 1
        ) {
            UserMenu::create([
                'user_id' => $user_id,
                'menu_id' => $value->id
            ]);
        }
    }

    /**
     * @param $username
     * @param $user_id
     * @param $app_name
     */
    protected function assignMenuToUser($username, $app_name)
    {
        if ($username == '1') {
            $menu = Menu::all();
            foreach ($menu as $value) {
                $this->insertUserMenu($username, $value);
            }
        }
    }

    /**
     * @param $username
     *
     * @return mixed
     */
    protected function assignUserCompany($username)
    {
        if (env('APP_ENV') == 'local') {
            $company = Company::where('db_code', '=', 'IMIP_TEST_1217')->first();
        } else {
            $company = Company::where('db_code', '=', 'IMIP_LIVE')->first();
        }
        if (UserCompany::where('user_id', '=', $username)
                ->where('company_id', '=', $company->id)
                ->count() < 1) {
            UserCompany::create([
                'user_id' => $username,
                'company_id' => $company->id
            ]);
        }
        return $company->db_code;
    }

    /**
     * @param $username
     * @param $company
     */
    protected function assignUserWareHouse($username, $company)
    {
        if (UserWhs::where('user_id', '=', $username)->count() < 1) {
            $list_whs = [
                [
                    'whs_code' => 'MW-GE',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'whs_code' => 'MW-GA',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'whs_code' => 'MW-IT',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'whs_code' => 'MW-HSE',
                    'db_code' => $company,
                    'user_id' => $username
                ],
            ];

            UserWhs::insert($list_whs);
        }
    }

    /**
     * @param $username
     * @param $company
     */
    protected function assignUserItemGroups($username, $company)
    {
        if (UserItmGrp::where('user_id', '=', $username)->count() < 1) {
            $data = [
                [
                    'item_group' => '100',
                    'item_group_name' => 'Items',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '102',
                    'item_group_name' => 'Services',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '107',
                    'item_group_name' => 'xWork Supplies',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '111',
                    'item_group_name' => 'xOffice Supplies',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '112',
                    'item_group_name' => 'xIT Supplies',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '147',
                    'item_group_name' => 'OFFICE SUPPLIES',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '146',
                    'item_group_name' => 'IT SUPPLIES',
                    'db_code' => $company,
                    'user_id' => $username
                ],
            ];
            UserItmGrp::insert($data);
        }
    }


    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->user()->tokens()->delete();

        return $this->success([
            'message' => 'Tokens Revoked'
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return $this->success([
            'user' => $request->user()
        ]);
    }

    /**
     * @param $username
     */
    private function assignRolePermissionToUser($username)
    {
        if ($username == '1') {
            $role = Role::findByName('Superuser');
            $user = User::where('id', $username)->first();
            $user->assignRole($role);
        }
    }
}
