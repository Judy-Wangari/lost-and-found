<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\Item;
use App\Models\Notification;
use App\Models\SecurityHandover;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecurityHandoverController extends Controller
{
    // Security sees all pending handover requests
    public function index()
    {
        try {
            $user = Auth::user();

            if($user->role !== 'security' && $user->role !== 'admin'){
                return response()->json([
                    'message' => 'You are not authorized to view handovers.'
                ], 403);
            }

            $handovers = SecurityHandover::with(['item', 'claim', 'handedOverBy', 'owner'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($handovers, 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch handovers.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Finder clicks Hand to Security
    public function store(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
        ]);

        try {
            $claim = Claim::findOrFail($request->claim_id);

            // Only the finder can hand over the item
            if($claim->item->posted_by !== Auth::id()){
                return response()->json([
                    'message' => 'You are not authorized to hand over this item.'
                ], 403);
            }

            // Claim must be approved before handing to security
            if($claim->status !== 'approved'){
                return response()->json([
                    'message' => 'Only approved claims can be handed to security.'
                ], 400);
            }

            // Check if handover already exists
            $existingHandover = SecurityHandover::where('claim_id', $request->claim_id)->first();
            if($existingHandover){
                return response()->json([
                    'message' => 'A handover request already exists for this claim.'
                ], 400);
            }

            $handover = new SecurityHandover();
            $handover->item_id = $claim->item_id;
            $handover->claim_id = $claim->id;
            $handover->handed_over_by = Auth::id();
            $handover->receiver_id = null;
            $handover->owner_id = $claim->claimed_by;
            $handover->status = 'pending_confirmation';
            $handover->save();

            // Update item status to flagged_to_security
            $claim->item->update(['status' => 'flagged_to_security']);

            // Notify security
            $security = User::where('role', 'security')->first();
            if($security){
                Notification::create([
                    'user_id' => $security->id,
                    'message_body' => 'A finder has handed in a ' . $claim->item->category . '. Please confirm receipt.',
                    'type' => 'handover_requested',
                    'reference_id' => $handover->id,
                    'reference_type' => 'security_handover',
                    'is_read' => false
                ]);
            }

            return response()->json([
                'message' => 'Item handed to security successfully. Awaiting confirmation.',
                'handover' => $handover
            ], 201);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to create handover request.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Security confirms, rejects or marks as collected
    public function update(Request $request, string $id)
    {
        $request->validate([
            'action' => 'required|in:confirm,reject,collected',
            'verification_code' => 'nullable|string',
        ]);

        try {
            $user = Auth::user();
            $handover = SecurityHandover::findOrFail($id);
            $item = Item::findOrFail($handover->item_id);

            if($request->action === 'confirm'){
                // Only security can confirm
                if($user->role !== 'security'){
                    return response()->json([
                        'message' => 'Only security can confirm handovers.'
                    ], 403);
                }

                $handover->status = 'confirmed';
                $handover->receiver_id = Auth::id();
                $handover->save();

                // Notify owner that item is at security
                Notification::create([
                    'user_id' => $handover->owner_id,
                    'message_body' => 'Your ' . $item->category . ' is now at the security office. Please come collect it with your verification code.',
                    'type' => 'handover_confirmed',
                    'reference_id' => $handover->id,
                    'reference_type' => 'security_handover',
                    'is_read' => false
                ]);

                // Notify finder that security confirmed
                Notification::create([
                    'user_id' => $handover->handed_over_by,
                    'message_body' => 'Security has confirmed receipt of the ' . $item->category . '. The owner has been notified to collect it.',
                    'type' => 'handover_confirmed',
                    'reference_id' => $handover->id,
                    'reference_type' => 'security_handover',
                    'is_read' => false
                ]);

            } elseif($request->action === 'reject'){
                // Only security can reject
                if($user->role !== 'security'){
                    return response()->json([
                        'message' => 'Only security can reject handovers.'
                    ], 403);
                }

                $handover->status = 'rejected';
                $handover->save();

                // Item goes back to awaiting collection
                $item->update(['status' => 'awaiting_collection']);

                // Notify finder that security rejected
                Notification::create([
                    'user_id' => $handover->handed_over_by,
                    'message_body' => 'Security could not find the ' . $item->category . ' you claimed to hand in. Please check and try again.',
                    'type' => 'handover_rejected',
                    'reference_id' => $handover->id,
                    'reference_type' => 'security_handover',
                    'is_read' => false
                ]);

            } elseif($request->action === 'collected'){
                // Security enters verification code when owner comes to collect
                if($user->role !== 'security'){
                    return response()->json([
                        'message' => 'Only security can mark items as collected.'
                    ], 403);
                }

                // Verify the code
                if($request->verification_code != $item->verification_code){
                    return response()->json([
                        'message' => 'Invalid verification code. Please ask the owner for their code.'
                    ], 400);
                }

                $handover->status = 'collected';
                $handover->save();

                // Mark item as collected
                $item->update(['status' => 'collected']);

                // Notify owner
                Notification::create([
                    'user_id' => $handover->owner_id,
                    'message_body' => 'Your ' . $item->category . ' has been successfully collected. Case closed.',
                    'type' => 'item_collected',
                    'reference_id' => $handover->id,
                    'reference_type' => 'security_handover',
                    'is_read' => false
                ]);

                // Notify finder
                Notification::create([
                    'user_id' => $handover->handed_over_by,
                    'message_body' => 'The owner has collected their ' . $item->category . ' from security. Case closed.',
                    'type' => 'item_collected',
                    'reference_id' => $handover->id,
                    'reference_type' => 'security_handover',
                    'is_read' => false
                ]);
            }

            return response()->json([
                'message' => 'Handover updated successfully.',
                'handover' => $handover
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to update handover.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}