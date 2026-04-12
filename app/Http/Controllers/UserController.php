<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Admin approves or rejects pending user accounts
     */
    public function approveAccount(Request $request, string $id)
    {
        // Only admin can approve accounts
        if(Auth::user()->role !== 'admin'){
            return response()->json([
                'message' => 'You are not authorized to approve accounts.'
            ], 403);
        }

        $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        try {
            $user = User::findOrFail($id);

            // Can only approve/reject pending accounts
            if($user->status !== 'pending'){
                return response()->json([
                    'message' => 'Only pending accounts can be approved or rejected.'
                ], 400);
            }

            // Can only approve non-student accounts (admin, security)
            if($user->role === 'student'){
                return response()->json([
                    'message' => 'Student accounts are automatically active upon registration. Cannot approve.'
                ], 400);
            }

            if($request->action === 'approve'){
                $user->status = 'active';
                $user->save();

                // Notify user of approval
                Notification::create([
                    'user_id' => $user->id,
                    'message_body' => 'Your account has been approved! You can now login to the Lost & Found platform.',
                    'type' => 'account_approved',
                    'reference_id' => $user->id,
                    'reference_type' => 'user',
                    'is_read' => false
                ]);

                return response()->json([
                    'message' => 'Account approved successfully.',
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'status' => $user->status
                    ]
                ], 200);

            } elseif($request->action === 'reject'){
                $user->status = 'rejected';
                $user->save();

                // Notify user of rejection
                Notification::create([
                    'user_id' => $user->id,
                    'message_body' => 'Your account registration has been rejected. Please contact support for more information.',
                    'type' => 'account_rejected',
                    'reference_id' => $user->id,
                    'reference_type' => 'user',
                    'is_read' => false
                ]);

                return response()->json([
                    'message' => 'Account rejected successfully.',
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'status' => $user->status
                    ]
                ], 200);
            }

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to process account approval.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all pending accounts (admin only)
     */
    public function getPendingAccounts()
    {
        // Only admin can view pending accounts
        if(Auth::user()->role !== 'admin'){
            return response()->json([
                'message' => 'You are not authorized to view pending accounts.'
            ], 403);
        }

        try {
            $pendingUsers = User::where('status', 'pending')
                ->where('role', '!=', 'student')
                ->select('id', 'first_name', 'last_name', 'email', 'phone_number', 'role', 'status', 'created_at')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'pending_accounts' => $pendingUsers,
                'count' => $pendingUsers->count()
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch pending accounts.',
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
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'profile_picture' => $user->profile_picture,
                    'role' => $user->role,
                    'status' => $user->status,
                    'created_at' => $user->created_at
                ]
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch profile.',
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
            'phone_number' => 'sometimes|string',
            'profile_picture' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $user = User::findOrFail(Auth::id());

            // Handle profile picture update
            if($request->hasFile('profile_picture')){
                // Delete old picture if exists
                if($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)){
                    Storage::disk('public')->delete($user->profile_picture);
                }
                $filename = $request->file('profile_picture')->store('profiles', 'public');
                $user->profile_picture = $filename;
            }

            // Update other fields
            if($request->filled('phone_number')){
                $user->phone_number = $request->phone_number;
            }

            $user->save();

            return response()->json([
                'message' => 'Profile updated successfully.',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'profile_picture' => $user->profile_picture,
                    'role' => $user->role
                ]
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to update profile.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all users (admin only)
     */
    public function index()
    {
        // Only admin can view all users
        if(Auth::user()->role !== 'admin'){
            return response()->json([
                'message' => 'You are not authorized to view users.'
            ], 403);
        }

        try {
            $role = request('role'); // Filter by role if provided
            $status = request('status'); // Filter by status if provided

            $query = User::select('id', 'first_name', 'last_name', 'email', 'phone_number', 'role', 'status', 'created_at');

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
                'error' => 'Failed to fetch users.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single user (admin only)
     */
    public function show(string $id)
    {
        // Only admin can view user details
        if(Auth::user()->role !== 'admin'){
            return response()->json([
                'message' => 'You are not authorized to view this user.'
            ], 403);
        }

        try {
            $user = User::select('id', 'first_name', 'last_name', 'email', 'phone_number', 'profile_picture', 'role', 'status', 'created_at', 'updated_at')
                ->findOrFail($id);

            return response()->json([
                'user' => $user
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch user.',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Delete user account (admin only)
     */
    public function destroy(string $id)
    {
        // Only admin can delete users
        if(Auth::user()->role !== 'admin'){
            return response()->json([
                'message' => 'You are not authorized to delete users.'
            ], 403);
        }

        // Cannot delete yourself
        if(Auth::id() == $id){
            return response()->json([
                'message' => 'You cannot delete your own account.'
            ], 400);
        }

        try {
            $user = User::findOrFail($id);
            $userName = $user->first_name . ' ' . $user->last_name;

            // Delete profile picture if exists
            if($user->profile_picture && Storage::disk('public')->exists($user->profile_picture)){
                Storage::disk('public')->delete($user->profile_picture);
            }

            $user->delete();

            return response()->json([
                'message' => 'User ' . $userName . ' deleted successfully.'
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to delete user.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
