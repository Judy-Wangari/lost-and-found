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
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            if($user->role !== 'security' && $user->role !== 'admin'){
                return response()->json(['message' => 'You are not authorized to view handovers.'], 403);
            }

            $query = SecurityHandover::with(['item', 'claim', 'handedOverBy', 'owner'])
                ->orderBy('created_at', 'desc');

            if($request->filled('status')){
                $query->where('status', $request->status);
            }

            return response()->json($query->get(), 200);

        } catch(\Exception $e){
            return response()->json(['error' => 'Failed to fetch handovers.', 'message' => $e->getMessage()], 500);
        }
    }

    // Finder hands item to security
    public function store(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
        ]);

        try {
            $claim = Claim::with('item')->findOrFail($request->claim_id);

            // Only the finder can hand over
            if($claim->item->posted_by !== Auth::id()){
                return response()->json(['message' => 'Only the finder can hand over this item.'], 403);
            }

            // Claim must be approved
            if($claim->status !== 'approved'){
                return response()->json(['message' => 'Only approved claims can be handed to security.'], 400);
            }

            // Check no existing pending handover
            $existing = SecurityHandover::where('claim_id', $request->claim_id)
                ->whereIn('status', ['pending_confirmation', 'confirmed'])
                ->first();
            if($existing){
                return response()->json(['message' => 'A handover request already exists for this claim.'], 400);
            }

            $handover = SecurityHandover::create([
                'item_id'        => $claim->item_id,
                'claim_id'       => $claim->id,
                'handed_over_by' => Auth::id(),
                'receiver_id'    => null,
                'owner_id'       => $claim->claimed_by,
                'status'         => 'pending_confirmation',
            ]);

            // Update item status
            $claim->item->update(['status' => 'flagged_to_security']);

            // Notify all active security officers
            $securityUsers = User::where('role', 'security')->where('status', 'active')->get();
            foreach($securityUsers as $security){
                Notification::create([
                    'user_id'        => $security->id,
                    'message_body'   => 'A finder has handed in a ' . $claim->item->category . ' to the security office. Please confirm receipt.',
                    'type'           => 'handover_requested',
                    'reference_id'   => $handover->id,
                    'reference_type' => 'handover',
                    'is_read'        => false
                ]);
            }

            // Notify admin
            $admin = User::where('role', 'admin')->first();
            if($admin){
                Notification::create([
                    'user_id'        => $admin->id,
                    'message_body'   => 'A ' . $claim->item->category . ' has been handed to the security office by the finder.',
                    'type'           => 'handover_requested',
                    'reference_id'   => $handover->id,
                    'reference_type' => 'handover',
                    'is_read'        => false
                ]);
            }

            return response()->json([
                'message'  => 'Item handed to security. Awaiting confirmation.',
                'handover' => $handover
            ], 201);

        } catch(\Exception $e){
            return response()->json(['error' => 'Failed to create handover.', 'message' => $e->getMessage()], 500);
        }
    }

    // Security confirms, rejects or collects
    public function update(Request $request, string $id)
    {
        $request->validate([
            'action'            => 'required|in:confirm,reject,collected',
            'verification_code' => 'nullable|string',
        ]);

        try {
            $user     = Auth::user();
            $handover = SecurityHandover::with(['item', 'handedOverBy'])->findOrFail($id);
            $item     = $handover->item;

            if($user->role !== 'security' && $user->role !== 'admin'){
                return response()->json(['message' => 'Only security can update handovers.'], 403);
            }

            if($request->action === 'confirm'){
                if($handover->status !== 'pending_confirmation'){
                    return response()->json(['message' => 'Only pending handovers can be confirmed.'], 400);
                }

                $handover->status      = 'confirmed';
                $handover->receiver_id = $user->id;
                $handover->save();

                // Notify owner
                Notification::create([
                    'user_id'        => $handover->owner_id,
                    'message_body'   => 'Your ' . $item->category . ' is now at the security office. Please come collect it with your verification code.',
                    'type'           => 'handover_confirmed',
                    'reference_id'   => $handover->id,
                    'reference_type' => 'handover',
                    'is_read'        => false
                ]);

                // Notify finder
                Notification::create([
                    'user_id'        => $handover->handed_over_by,
                    'message_body'   => 'Security has confirmed receipt of the ' . $item->category . '. The owner has been notified to collect it.',
                    'type'           => 'handover_confirmed',
                    'reference_id'   => $handover->id,
                    'reference_type' => 'handover',
                    'is_read'        => false
                ]);

                return response()->json(['message' => 'Receipt confirmed. Owner has been notified.'], 200);

            } elseif($request->action === 'reject'){
                if($handover->status !== 'pending_confirmation'){
                    return response()->json(['message' => 'Only pending handovers can be rejected.'], 400);
                }

                $handover->status = 'rejected';
                $handover->save();

                // Item goes back to awaiting_collection
                $item->update(['status' => 'awaiting_collection']);

                // Notify finder
                Notification::create([
                    'user_id'        => $handover->handed_over_by,
                    'message_body'   => 'Security could not find the ' . $item->category . ' you reported handing in. The item is back in awaiting collection status.',
                    'type'           => 'handover_rejected',
                    'reference_id'   => $handover->id,
                    'reference_type' => 'handover',
                    'is_read'        => false
                ]);

                return response()->json(['message' => 'Handover rejected. Finder has been notified.'], 200);

            } elseif($request->action === 'collected'){
                if($handover->status !== 'confirmed'){
                    return response()->json(['message' => 'Item must be confirmed before collection.'], 400);
                }

                if($request->verification_code != $item->verification_code){
                    return response()->json(['message' => 'Invalid verification code. Please ask the owner for their code.'], 400);
                }

                $handover->status = 'collected';
                $handover->save();

                $item->update(['status' => 'collected']);

                // Notify owner
                Notification::create([
                    'user_id'        => $handover->owner_id,
                    'message_body'   => 'Your ' . $item->category . ' has been successfully collected from the security office. Case closed.',
                    'type'           => 'item_collected',
                    'reference_id'   => $handover->id,
                    'reference_type' => 'handover',
                    'is_read'        => false
                ]);

                // Notify finder
                Notification::create([
                    'user_id'        => $handover->handed_over_by,
                    'message_body'   => 'The owner has collected their ' . $item->category . ' from security. Case closed.',
                    'type'           => 'item_collected',
                    'reference_id'   => $handover->id,
                    'reference_type' => 'handover',
                    'is_read'        => false
                ]);

                return response()->json(['message' => 'Item collected. Case closed.'], 200);
            }

        } catch(\Exception $e){
            return response()->json(['error' => 'Failed to update handover.', 'message' => $e->getMessage()], 500);
        }
    }
}