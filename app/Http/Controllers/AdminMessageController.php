<?php

namespace App\Http\Controllers;

use App\Models\AdminMessage;
use App\Models\Claim;
use App\Models\Appeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMessageController extends Controller
{
    // Get all messages for a specific claim or appeal
    public function index(Request $request)
    {
        try {
            $userId = Auth::id();
            $user = Auth::user();

            // Must provide either claim_id or appeal_id
            if(!$request->filled('claim_id') && !$request->filled('appeal_id')){
                return response()->json([
                    'message' => 'Please provide a claim_id or appeal_id.'
                ], 400);
            }

            $query = AdminMessage::with(['sender', 'receiver']);

            if($request->filled('claim_id')){
                $claim = Claim::findOrFail($request->claim_id);

                // Only admin, finder or owner can view messages
                $isAdmin = $user->role === 'admin';
                $isOwner = $claim->claimed_by === $userId;
                $isFinder = $claim->item->posted_by === $userId;

                if(!$isAdmin && !$isOwner && !$isFinder){
                    return response()->json([
                        'message' => 'You are not authorized to view these messages.'
                    ], 403);
                }

                $query->where('claim_id', $request->claim_id);
            }

            if($request->filled('appeal_id')){
                $appeal = Appeal::findOrFail($request->appeal_id);

                // Only admin or the one who raised the appeal can view messages
                $isAdmin = $user->role === 'admin';
                $isRaisedBy = $appeal->raised_by === $userId;

                if(!$isAdmin && !$isRaisedBy){
                    return response()->json([
                        'message' => 'You are not authorized to view these messages.'
                    ], 403);
                }

                $query->where('appeal_id', $request->appeal_id);
            }

            // Mark messages as read scoped to claim or appeal
            $readQuery = AdminMessage::where('receiver_id', $userId)
                ->where('is_read', false);

            if($request->filled('claim_id')){
                $readQuery->where('claim_id', $request->claim_id);
            }

            if($request->filled('appeal_id')){
                $readQuery->where('appeal_id', $request->appeal_id);
            }

            $readQuery->update(['is_read' => true]);

            $messages = $query->orderBy('created_at', 'asc')->paginate(20);

            return response()->json($messages, 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch messages.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Send a message
    public function store(Request $request)
    {
        $request->validate([
            'claim_id' => 'nullable|exists:claims,id',
            'appeal_id' => 'nullable|exists:appeals,id',
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:claim,appeal,direct,unresponsive',
        ]);

        try {
            $userId = Auth::id();
            $user = Auth::user();

            // Ensure claim_id or appeal_id is provided for non direct messages
            if(!$request->filled('claim_id') && !$request->filled('appeal_id') 
                && $request->type !== 'direct' 
                && $request->type !== 'unresponsive'){
                return response()->json([
                    'message' => 'Please provide a claim_id or appeal_id.'
                ], 400);
            }

            // If claim message --- check sender is admin, finder or owner
            if($request->filled('claim_id')){
                $claim = Claim::findOrFail($request->claim_id);
                $isAdmin = $user->role === 'admin';
                $isOwner = $claim->claimed_by === $userId;
                $isFinder = $claim->item->posted_by === $userId;

                if(!$isAdmin && !$isOwner && !$isFinder){
                    return response()->json([
                        'message' => 'You are not authorized to send messages for this claim.'
                    ], 403);
                }
            }

            // If appeal message --- check sender is admin or the one who raised the appeal
            if($request->filled('appeal_id')){
                $appeal = Appeal::findOrFail($request->appeal_id);
                $isAdmin = $user->role === 'admin';
                $isRaisedBy = $appeal->raised_by === $userId;

                if(!$isAdmin && !$isRaisedBy){
                    return response()->json([
                        'message' => 'You are not authorized to send messages for this appeal.'
                    ], 403);
                }
            }

            // If direct or unresponsive --- only admin can send
            if(in_array($request->type, ['direct', 'unresponsive'])){
                if($user->role !== 'admin'){
                    return response()->json([
                        'message' => 'Only admin can send direct messages.'
                    ], 403);
                }
            }

            $adminMessage = new AdminMessage();
            $adminMessage->claim_id = $request->claim_id ?? null;
            $adminMessage->appeal_id = $request->appeal_id ?? null;
            $adminMessage->sender_id = $userId;
            $adminMessage->receiver_id = $request->receiver_id;
            $adminMessage->message = $request->message;
            $adminMessage->type = $request->type;
            $adminMessage->is_read = false;
            $adminMessage->save();

            return response()->json([
                'message' => 'Message sent successfully.',
                'data' => $adminMessage
            ], 201);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to send message.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}