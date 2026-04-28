<?php

namespace App\Http\Controllers;
use App\Models\Notification;
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

        if(!$request->filled('claim_id') && !$request->filled('appeal_id')){
            return response()->json(['message' => 'Please provide a claim_id or appeal_id.'], 400);
        }

        $query = AdminMessage::with(['sender', 'receiver']);

        if($request->filled('claim_id')){
            $claim = Claim::findOrFail($request->claim_id);
            $isAdmin = $user->role === 'admin';
            $isOwner = $claim->claimed_by === $userId;
            $isFinder = $claim->item->posted_by === $userId;

            if(!$isAdmin && !$isOwner && !$isFinder){
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $query->where('claim_id', $request->claim_id);

            // If a specific participant is requested (admin viewing one person's thread)
            if($request->filled('participant_id')){
                $participantId = $request->participant_id;
                $query->where(function($q) use ($userId, $participantId) {
                    $q->where(function($q2) use ($userId, $participantId) {
                        $q2->where('sender_id', $userId)->where('receiver_id', $participantId);
                    })->orWhere(function($q2) use ($userId, $participantId) {
                        $q2->where('sender_id', $participantId)->where('receiver_id', $userId);
                    });
                });
            } elseif(!$isAdmin){
                // Non-admin only sees their own thread
                $query->where(function($q) use ($userId) {
                    $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
                });
            }
        }

        if($request->filled('appeal_id')){
            $appeal = Appeal::findOrFail($request->appeal_id);
            $isAdmin = $user->role === 'admin';
            $isRaisedBy = $appeal->raised_by === $userId;
            if(!$isAdmin && !$isRaisedBy){
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
            $query->where('appeal_id', $request->appeal_id);
        }

        // Mark as read
        if($request->filled('claim_id')){
            AdminMessage::where('claim_id', $request->claim_id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        $messages = $query->orderBy('created_at', 'asc')->get();

        return response()->json($messages, 200);

    } catch(\Exception $e){
        return response()->json(['error' => 'Failed to fetch messages.', 'message' => $e->getMessage()], 500);
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
                            // If direct or unresponsive --- only admin can send NEW direct messages
                // But owner/finder can REPLY to existing admin messages
                if(in_array($request->type, ['direct', 'unresponsive'])){
                    if($user->role !== 'admin'){
                        // Allow if there is an existing admin message in this claim for this user
                        $hasAdminMessage = \App\Models\AdminMessage::where('claim_id', $request->claim_id)
                            ->where('receiver_id', $userId)
                            ->exists();
                        if(!$hasAdminMessage){
                            return response()->json([
                                'message' => 'Only admin can initiate direct messages.'
                            ], 403);
                        }
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

            // Get context for the notification message
            $contextLabel = '';
            if($request->claim_id){
                $claimContext = Claim::with('item')->find($request->claim_id);
                $contextLabel = $claimContext ? 'claim for ' . $claimContext->item->category : 'claim';
            } elseif($request->appeal_id){
                $contextLabel = 'appeal';
            }

                                    // 1. Determine Sender identity and role label
            $senderName = 'A user';

            if ($user->role === 'admin') {
                $senderName = 'Admin';
            } elseif ($request->filled('claim_id')) {
                // If it's a claim, we can check if they are the finder or owner
                $claim = Claim::with('item')->find($request->claim_id);
                
                if ($claim) {
                    $roleLabel = ($userId === $claim->item->posted_by) ? '(Finder)' : '(Owner)';
                    $senderName = $user->first_name . ' ' . $roleLabel;
                } else {
                    $senderName = $user->first_name;
                }
            } else {
                // Fallback for appeals or other types
                $senderName = $user->first_name;
            }

            // 2. Create the notification using the specific $senderName
            Notification::create([
                'user_id' => $request->receiver_id,
                'message_body' => $senderName . ' has sent you a message regarding the ' . $contextLabel . '. Tap to view.',
                'type' => 'admin_message',
                'reference_id' => $request->claim_id ?? $request->appeal_id,
                'reference_type' => $request->claim_id ? 'claim' : 'appeal',
                'is_read' => false
            ]);

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