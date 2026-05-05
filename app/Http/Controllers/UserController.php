<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Admin approves or rejects pending user accounts
     */
    public function approveAccount(Request $request, string $id)
{
    if(Auth::user()->role !== 'admin'){
        return response()->json(['message' => 'You are not authorized.'], 403);
    }

    $request->validate([
        'action' => 'required|in:approve,reject,activate,deactivate',
    ]);

    try {
        $user = User::findOrFail($id);

        // Only block modifying your own account
        if($user->id === Auth::id()){
            return response()->json(['message' => 'You cannot modify your own account.'], 400);
        }

        if($request->action === 'approve'){
            if($user->status !== 'pending'){
                return response()->json(['message' => 'Only pending accounts can be approved.'], 400);
            }
            $user->status = 'active';
            $user->save();

            Notification::create([
                'user_id'        => $user->id,
                'message_body'   => 'Your account has been approved! You can now login to the platform.',
                'type'           => 'account_approved',
                'reference_id'   => $user->id,
                'reference_type' => 'user',
                'is_read'        => false
            ]);

            return response()->json(['message' => 'Account approved successfully.', 'user' => $user], 200);

        } elseif($request->action === 'reject'){
            if($user->status !== 'pending'){
                return response()->json(['message' => 'Only pending accounts can be rejected.'], 400);
            }
            $user->status = 'rejected';
            $user->save();

            Notification::create([
                'user_id'        => $user->id,
                'message_body'   => 'Your account registration has been rejected. Please contact support.',
                'type'           => 'account_rejected',
                'reference_id'   => $user->id,
                'reference_type' => 'user',
                'is_read'        => false
            ]);

            return response()->json(['message' => 'Account rejected.', 'user' => $user], 200);

        } elseif($request->action === 'deactivate'){
            if($user->status !== 'active'){
                return response()->json(['message' => 'Only active accounts can be deactivated.'], 400);
            }
            $user->status = 'suspended';
            $user->save();

            return response()->json(['message' => 'Account deactivated successfully.', 'user' => $user], 200);

        } elseif($request->action === 'activate'){
            if($user->status !== 'suspended'){
                return response()->json(['message' => 'Only suspended accounts can be reactivated.'], 400);
            }
            $user->status = 'active';
            $user->save();

            Notification::create([
                'user_id'        => $user->id,
                'message_body'   => 'Your account has been reactivated. You can now login.',
                'type'           => 'account_approved',
                'reference_id'   => $user->id,
                'reference_type' => 'user',
                'is_read'        => false
            ]);

            return response()->json(['message' => 'Account reactivated successfully.', 'user' => $user], 200);
        }

    } catch(\Exception $e){
        return response()->json(['error' => 'Failed.', 'message' => $e->getMessage()], 500);
    }
}

    /**
     * Get all pending accounts (admin only)
     */
    public function getPendingAccounts()
    {
        if(Auth::user()->role !== 'admin'){
            return response()->json(['message' => 'You are not authorized to view pending accounts.'], 403);
        }

        try {
            $pendingUsers = User::where('status', 'pending')
                ->where('role', '!=', 'student')
                ->select('id', 'first_name', 'last_name', 'email', 'phone_number', 'profile_picture', 'role', 'status', 'created_at')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'pending_accounts' => $pendingUsers,
                'count'            => $pendingUsers->count()
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error'   => 'Failed to fetch pending accounts.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user profile
     */
    public function getProfile()
    {
        try {
            $user = Auth::user();

            return response()->json([
                'user' => [
                    'id'              => $user->id,
                    'first_name'      => $user->first_name,
                    'last_name'       => $user->last_name,
                    'email'           => $user->email,
                    'phone_number'    => $user->phone_number,
                    'profile_picture' => $user->profile_picture,
                    'role'            => $user->role,
                    'status'          => $user->status,
                    'created_at'      => $user->created_at
                ]
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error'   => 'Failed to fetch profile.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update current user profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'first_name'      => 'sometimes|string|max:255',
            'last_name'       => 'sometimes|string|max:255',
            'phone_number'    => 'sometimes|string',
            'profile_picture' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $user = User::findOrFail(Auth::id());

            if($request->hasFile('profile_picture')){
                if($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)){
                    Storage::disk('public')->delete($user->profile_picture);
                }
                $filename = $request->file('profile_picture')->store('profiles', 'public');
                $user->profile_picture = $filename;
            }

            if($request->filled('first_name')){
                $user->first_name = $request->first_name;
            }

            if($request->filled('last_name')){
                $user->last_name = $request->last_name;
            }

            if($request->filled('phone_number')){
                $user->phone_number = $request->phone_number;
            }

            $user->save();

            return response()->json([
                'message' => 'Profile updated successfully.',
                'user'    => [
                    'id'              => $user->id,
                    'first_name'      => $user->first_name,
                    'last_name'       => $user->last_name,
                    'email'           => $user->email,
                    'phone_number'    => $user->phone_number,
                    'profile_picture' => $user->profile_picture,
                    'role'            => $user->role,
                    'status'          => $user->status,
                ]
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error'   => 'Failed to update profile.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users (admin only)
     */
    public function index()
    {
        if(Auth::user()->role !== 'admin'){
            return response()->json(['message' => 'You are not authorized to view users.'], 403);
        }

        try {
            $role   = request('role');
            $status = request('status');

            $query = User::select(
                'id', 'first_name', 'last_name', 'email',
                'phone_number', 'profile_picture', 'role', 'status', 'created_at'
            );

            if($role){
                $query->where('role', $role);
            }

            if($status){
                $query->where('status', $status);
            }

            $users = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json($users, 200);

        } catch(\Exception $e){
            return response()->json([
                'error'   => 'Failed to fetch users.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single user (admin only)
     */
    public function show(string $id)
    {
        if(Auth::user()->role !== 'admin'){
            return response()->json(['message' => 'You are not authorized to view this user.'], 403);
        }

        try {
            $user = User::select(
                'id', 'first_name', 'last_name', 'email', 'phone_number',
                'profile_picture', 'role', 'status', 'created_at', 'updated_at'
            )->findOrFail($id);

            return response()->json(['user' => $user], 200);

        } catch(\Exception $e){
            return response()->json([
                'error'   => 'Failed to fetch user.',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Delete user account (admin only)
     */
    public function destroy(string $id)
    {
        if(Auth::user()->role !== 'admin'){
            return response()->json(['message' => 'You are not authorized to delete users.'], 403);
        }

        if(Auth::id() == $id){
            return response()->json(['message' => 'You cannot delete your own account.'], 400);
        }

        try {
            $user     = User::findOrFail($id);
            $userName = $user->first_name . ' ' . $user->last_name;

            if($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)){
                Storage::disk('public')->delete($user->profile_picture);
            }

            $user->delete();

            return response()->json([
                'message' => 'User ' . $userName . ' deleted successfully.'
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error'   => 'Failed to delete user.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        try {
            $user = User::findOrFail(Auth::id());

            if(!Hash::check($request->current_password, $user->password)){
                return response()->json(['message' => 'Current password is incorrect.'], 400);
            }

            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json(['message' => 'Password changed successfully.'], 200);

        } catch(\Exception $e){
            return response()->json([
                'error'   => 'Failed to change password.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}