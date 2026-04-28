<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    // Get all messages for a specific claim
    public function index(string $claimId)
    {
        try {
            $claim = Claim::findOrFail($claimId);
            $userId = Auth::id();
            $user = Auth::user();

            $isAdmin = $user->role === 'admin';
            $isOwner = $claim->claimed_by === $userId;
            $isFinder = $claim->item->posted_by === $userId;

            if(!$isAdmin && !$isOwner && !$isFinder){
                return response()->json([
                    'message' => 'You are not authorized to view these messages.'
                ], 403);
            }

            // Only mark as read for non-admin
            if(!$isAdmin){
                Message::where('claim_id', $claimId)
                    ->where('receiver_id', $userId)
                    ->where('is_read', false)
                    ->update(['is_read' => true]);
            }

            // Admin can always see messages regardless of claim status
            // For non-admin only show if claim is approved or further
            if(!$isAdmin && !in_array($claim->status, ['approved', 'clarification_requested'])){
                // Still return messages so they can see history, just block sending
            }

            $messages = Message::where('claim_id', $claimId)
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'asc')
                ->get();

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
            'claim_id' => 'required|exists:claims,id',
            'message_body' => 'required|string',
        ]);

        try {
            $claim = Claim::findOrFail($request->claim_id);

            // Check claim is approved
            if($claim->status !== 'approved'){
                return response()->json([
                    'message' => 'Messaging is only available for approved claims.'
                ], 403);
            }

            // Check sender is either finder or owner
            $userId = Auth::id();
            $isOwner = $claim->claimed_by === $userId;
            $isFinder = $claim->item->posted_by === $userId;

            if(!$isOwner && !$isFinder){
                return response()->json([
                    'message' => 'You are not authorized to send messages for this claim.'
                ], 403);
            }

            // Determine receiver
            // If sender is owner, receiver is finder and vice versa
            $receiverId = $isOwner ? $claim->item->posted_by : $claim->claimed_by;

            $message = new Message();
            $message->claim_id = $request->claim_id;
            $message->item_id = $claim->item_id;
            $message->sender_id = $userId;
            $message->receiver_id = $receiverId;
            $message->message_body = $request->message_body;
            $message->is_read = false;
            $message->save();

            

            return response()->json([
                'message' => 'Message sent successfully.',
                'data' => $message
            ], 201);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to send message.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}