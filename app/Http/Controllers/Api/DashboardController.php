<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function userDashboard()
    {
        $user = auth('api')->user();
        if ($user->blocked) {
            return response()->json([
                'status' => 403,
                'message' => 'You\'re blocked.',
                'data' => [],
            ], 403);
        }
        return response()->json([
            'status' => 200,
            'message' => 'Operation successful.',
            'data' => ['message' => 'Welcome to User Dashboard'],
        ], 200);
    }

    public function adminDashboard()
    {
        $user = auth('api')->user();
        if ($user->role !== 'admin') {
            return response()->json([
                'status' => 403,
                'message' => 'Forbidden action.',
                'data' => [],
            ], 403);
        }
        return response()->json([
            'status' => 200,
            'message' => 'Operation successful.',
            'data' => ['message' => 'Welcome to Admin Dashboard'],
        ], 200);
    }
}
