<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use GuzzleHttp\Client;

class AuthController extends Controller
{

    public function loginGoogle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'google_id' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|string',
        ], MessagesController::messages());

        if ($validator->fails()) {
            return ResponseController::getResponse(null, 422, $validator->errors()->first());
        }

        $user = User::where([
            ['users.google_id', '=', $request['google_id']],
        ])
            ->first();

        if (empty($user)) {
            return ResponseController::getResponse(null, 400, "Invalid Credentials");
        }

        $payloadable = [
            'user_guid' => $user->guid,
            'name' => $user->name,
            'email' => $user->email,
        ];

        $token = JWTAuth::fromUser($user, $payloadable);

        $response = [
            "access_token" => $token,
        ];

        return ResponseController::getResponse($response, 200, 'Login Success');
    }
    public function syncGoogle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'google_id' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|string',
        ], MessagesController::messages());

        if ($validator->fails()) {
            return ResponseController::getResponse(null, 422, $validator->errors()->first());
        }

        $user = User::where('guid', auth('api')->user()->guid)
            ->first();

        $existingUser = User::where('email', $request['email'])->where('guid', '!=', $user->guid)->first();

        if ($existingUser) {
            return ResponseController::getResponse(null, 400, "Account Already Exists");
        } else {
            $user->name = $request['name'];
            $user->email = $request['email'];
            $user->google_id = $request['google_id'];
            $user->save();
        }

        return ResponseController::getResponse($user, 200, 'Sync Success');
    }

    public function verifyGoogle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'google_id' => 'required|string',
            'name' => 'required|string',
            'email' => 'required|string',
            'guid' => 'required|string|max:36',
        ], MessagesController::messages());

        if ($validator->fails()) {
            return ResponseController::getResponse(null, 422, $validator->errors()->first());
        }

        $user = User::where('guid', $request['guid'])->first();
        if ($request['email'] != $user['email']) {
            return ResponseController::getResponse(null, 400, "Email Google are Different");
        }

        $user->email = $request['email'];
        $user->google_id = $request['google_id'];
        $user->email_verified_at = Carbon::now();
        $user->save();


        return ResponseController::getResponse($user, 200, 'Verify Success');
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ], MessagesController::messages());

        if ($validator->fails()) {
            return ResponseController::getResponse(
                null, 422, 
                $validator->errors()->first());
        }

        $user = User::where([
            ['users.email', '=', $request['email']],
        ])
            ->first();

        if (empty($user)) {
            return ResponseController::getResponse(
                null, 400, 
                "User Not Found");
        }

        if (!Hash::check($request->get('password'), $user->password)) {
            return ResponseController::getResponse(
                null, 400, 
                "Invalid Credentials");
        }

        if (empty($user->email_verified_at)) {
            return ResponseController::getResponse(
                null, 400, 
                "Email has not been verified, please contact admin");
        }
        $payloadable = [
            'user_id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
        ];

        $token = JWTAuth::fromUser($user, $payloadable);

        $respone = [
            "access_token" => $token,
        ];

        return ResponseController::getResponse(
            $respone, 200, 
            'Login Success');
    }
    public function register(Request $request)
    {
        // Validasi input form
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
                // Validasi regex untuk email dengan domain yang diperbolehkan dan tidak ada angka di bagian depan
                'regex:/^[a-zA-Z]+(\.[a-zA-Z]+)*@(maranatha\.ac\.id|it\.maranatha\.edu)$/', 
                'regex:/^[a-zA-Z]+(\.[a-zA-Z]+)*@(?!student\.it\.maranatha\.edu$)/', // Tidak boleh domain student.it.maranatha.edu
            ],
            'phone_number' => 'required|string',
            'password' => 'required|string|min:6',
        ], [
            'email.regex' => 'Email harus menggunakan domain maranatha.ac.id atau it.maranatha.edu, dan bagian depan tidak boleh hanya angka.',
            'email.unique' => 'Email sudah terdaftar.',
        ]);
    
        // Cek jika validasi gagal
        if ($validator->fails()) {
            return ResponseController::getResponse(null, 422, $validator->errors()->first());
        }
    
        // Jika validasi sukses, buat pengguna baru
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'password' => Hash::make($request->password),
        ]);
    
        // Return response sukses
        return ResponseController::getResponse($user, 200, 'User Created Successfully');
    }
    

    public function logout()
    {
        Auth::logout();
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'status' => 'success',
            'user' => Auth::user(),
            'authorisation' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ]);
    }
}
