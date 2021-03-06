<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Company;
use App\Models\User;
use App\Models\UserApp;
use App\Models\UserCompany;
use App\Models\UserDivision;
use App\Models\UserItmGrp;
use App\Models\UserWhs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Role;
use App\Models\Permission;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{

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

            $user = User::where('username', $username)->first();

            $apps = Application::where('app_name', $app_name)->first();
            if ($apps->app_name != 'E-FORM') {
                if (!$user) {
                    return $this->error('Username not registered in this Application!', 401);
                }

                if (UserApp::where('user_id', $user->id)
                        ->where('app_id', $apps->id)
                        ->count() < 1) {
                    return $this->error('Unauthorized to access this Application!', 401);
                }
            }

            $response = '';

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
            }

            $user = User::where('username', $username)->first();

            if (!Auth::attempt($attr)) {
                return $this->error('Credentials not match', 401);
            }

            $company = $this->assignUserCompany($user->id);

            $this->assignRolePermissionToUser($username, $apps->app_name, $response);

//            if ($app_name == 'E-RESERVATION') {
//                $this->assignUserWareHouse($user->id, $company);
//
//                $this->assignUserItemGroups($user->id, $company);
//            }

            return response()->json([
                'token' => $request->user()->createToken('api-token')->plainTextToken,
                'user' => auth()->user()
            ]);
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 401, [
                'trace' => $exception->getTrace()
            ]);
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
            'email' => !empty($response['Data']['Email']) ? $response['Data']['Email']
                : strtotime(date('Y-m-d H:i:s')) . '@imip.co.id',
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
                    'item_group_name' => '100',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '102',
                    'item_group_name' => '102',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '107',
                    'item_group_name' => '107',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '111',
                    'item_group_name' => '111',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '112',
                    'item_group_name' => '112',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '147',
                    'item_group_name' => '147',
                    'db_code' => $company,
                    'user_id' => $username
                ],
                [
                    'item_group' => '146',
                    'item_group_name' => '146',
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
    public function logout(Request $request)
    {
        //auth()->logout();

        $request->user()->tokens()->delete();

        return $this->success([
            'message' => 'Tokens Revoked'
        ]);
    }

    /**
     * JWT refresh token
     *
     * @return mixed
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Laravel Sanctum refresh token
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken(Request $request)
    {
        $token = $request->token;
        $personal_token = PersonalAccessToken::where('token', $token)->first();

        return response()->json(['token' => $personal_token->tokenAble], 200);
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'token' => $token,
            'user' => auth()->user(),
            'token_type' => 'Bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        $user = User::where('id', '=', $request->user()->id)->with('roles')->first();
        return response()->json([
            'user' => $user
        ]);
    }

    /**
     * @param $username
     * @param $app_name
     * @param $response
     */
    protected function assignRolePermissionToUser($username, $app_name, $response)
    {
        if ($username == 'manager') {
            $role = Role::where('name', 'Superuser')->first();
            $permissions = Permission::all();
            $user = User::where('username', $username)->first();
            $user->assignRole($role);
            foreach ($permissions as $permission) {
                $user->givePermissionTo($permission->name);
            }
        }

        if ($app_name == 'E-FORM') {
            $user = User::where('username', $username)->first();
            $check_role = DB::table('model_has_roles')
                ->where('model_id', '=', $user->id)
                ->where('model_type', '=', 'App\Models\User')
                ->count();
            if ($check_role < 1) {
                $role = Role::where('name', 'Personal')->first();
                $user->assignRole($role);

                $data = $response['Data']['Organization'];
                if (UserDivision::where('user_id', $user->id)->count() > 0) {
                    UserDivision::where('user_id', $user->id)->delete();
                }

                if ($data) {
                    UserDivision::updateOrCreate([
                        'user_id' => $user->id,
                        'division_name' => $response['Data']['Organization']
                    ]);
                }

                $apps = $app_name;
                if (UserApp::where('user_id', $user->id)->count() > 0) {
                    UserApp::where('user_id', $user->id)->delete();
                }

                if ($apps) {
                    $id = Application::where('app_name', '=', $app_name)->first();
                    UserApp::updateOrCreate([
                        'user_id' => $user->id,
                        'app_id' => $id->id
                    ]);
                }
            }
        }
    }

    /**
     * Display a listing of permissions from current logged user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function permissions(): \Illuminate\Http\JsonResponse
    {
        return response()->json(auth()->user()->getAllPermissions()->pluck('name'));
    }

    /**
     * Display a listing of roles from current logged user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function roles(): \Illuminate\Http\JsonResponse
    {
        return response()->json(auth()->user()->getRoleNames());
    }
}
