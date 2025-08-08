<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $req)
    {
        $this->validate($req, [
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|string|min:6|confirmed',
            'role'                  => 'sometimes|string|in:user' 
        ]);

        $user = User::create([
            'name'     => $req->input('name'),
            'email'    => $req->input('email'),
            'password' => $req->input('password'),
            'role'     => $req->input('role', 'user'),
        ]);

        $token = auth()->login($user);

        return response()->json([
            'user'  => $user->only(['id','name','email','role']),
            'token' => $token,
        ], 201);
    }

    public function login(Request $req)
    {
        $this->validate($req, [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $req->only(['email','password']);

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Wrong password or email'], 401);
        }

        return response()->json(compact('token'));
    }

    public function me()
    {
        $user = auth()->user();
        $data = [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ];
        return response()->json($data);
    }
}
