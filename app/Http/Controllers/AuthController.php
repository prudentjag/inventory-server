<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Validation\ValidationException;

use App\Http\Requests\LoginRequest;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        // Validation handled by LoginRequest

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['Account is inactive.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Load units for frontend context
        return ResponseService::success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('units'),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return ResponseService::success(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('units'));
    }

    public function register(RegisterRequest $request){
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);
        $user = User::create($data);
        return ResponseService::success($user, "User {$user->name} created successfully");
    }

    public function users(Request $request)
    {
        $user = $request->user();

        // Admin sees everyone
        if ($user->role === 'admin') {
            return ResponseService::success(User::with('units')->get(), 'Users fetched successfully');
        }

        // Staff, Manager, Stockist can see users with 'server' role
        if (in_array($user->role, ['staff', 'manager', 'stockist'])) {
            $servers = User::where('role', 'server')->with('units')->get();
            return ResponseService::success($servers, 'Servers fetched successfully');
        }

        return ResponseService::error('Unauthorized', 403);
    }
}
