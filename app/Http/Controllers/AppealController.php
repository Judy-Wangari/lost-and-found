<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use App\Models\Claim;
use App\Models\Item;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppealController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if($user->role === 'admin'){
            $query = Appeal::with(['raisedBy', 'item', 'claim']);
            if($request->filled('status')){
                $query->where('status', $request->status);
            } else {
                $query->where('status', 'pending');
            }
            return response()->json($query->latest()->get());
        }

        // Student sees own appeals
        $appeals = Appeal::with(['item', 'claim'])
            ->where('raised_by', $user->id)
            ->latest()
            ->get();

        return response()->json($appeals);
    }

    public function show(string $id)
    {
        $user = Auth::user();
        $appeal = Appeal::with([
            'raisedBy',
            'item.itemPrivateDetail',
            'claim'
        ])->findOrFail($id);

        // Only admin or the person who raised it can view
        if($user->role !== 'admin' && $appeal->raised_by !== $user->id){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($appeal);
    }

    public function store(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
            'reason'   => 'required|string|max:2000',
        ]);

        $user = Auth::user();
        $claim = Claim::with('item')->findOrFail($request->claim_id);

        // Only the owner of the rejected claim can appeal
        if($claim->claimed_by !== $user->id){
            return response()->json(['message' => 'Only the claimant can appeal this decision.'], 403);
        }

        if($claim->status !== 'rejected'){
            return response()->json(['message' => 'You can only appeal a rejected claim.'], 400);
        }

        // Check if appeal already exists for this claim
        $existing = Appeal::where('claim_id', $request->claim_id)
            ->where('raised_by', $user->id)
            ->first();
        if($existing){
            return response()->json(['message' => 'You have already submitted an appeal for this claim.'], 400);
        }

        $appeal = Appeal::create([
            'claim_id'  => $request->claim_id,
            'item_id'   => $claim->item_id,
            'raised_by' => $user->id,
            'reason'    => $request->reason,
            'status'    => 'pending',
        ]);

        // Notify admin
        $admin = User::where('role', 'admin')->first();
        if($admin){
            Notification::create([
                'user_id'        => $admin->id,
                'message_body'   => $user->first_name . ' ' . $user->last_name . ' has submitted an appeal for a rejected claim on ' . $claim->item->category . '. Please review.',
                'type'           => 'appeal_submitted',
                'reference_id'   => $appeal->id,
                'reference_type' => 'appeal',
                'is_read'        => false
            ]);
        }

        return response()->json([
            'message' => 'Appeal submitted successfully. Admin will review your appeal.',
            'appeal'  => $appeal
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        if(Auth::user()->role !== 'admin'){
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'action'     => 'required|in:under_review,resolve,reject',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $appeal = Appeal::with(['claim', 'item'])->findOrFail($id);

        if($request->action === 'under_review'){
            $appeal->status = 'under_review';
            $appeal->admin_note = $request->admin_note;
            $appeal->save();

            // Notify appellant
            Notification::create([
                'user_id'        => $appeal->raised_by,
                'message_body'   => 'Admin is reviewing your appeal for the ' . $appeal->item->category . '. You will be notified of the final decision.',
                'type'           => 'appeal_under_review',
                'reference_id'   => $appeal->id,
                'reference_type' => 'appeal',
                'is_read'        => false
            ]);

        } elseif($request->action === 'resolve'){
            $appeal->status = 'resolved';
            $appeal->admin_note = $request->admin_note;
            $appeal->save();

            // Notify appellant --- appeal resolved, they can re-claim
            Notification::create([
                'user_id'        => $appeal->raised_by,
                'message_body'   => 'Your appeal for the ' . $appeal->item->category . ' has been resolved in your favour. You may now submit a new claim for this item.',
                'type'           => 'appeal_resolved',
                'reference_id'   => $appeal->id,
                'reference_type' => 'appeal',
                'is_read'        => false
            ]);

            // Reset the claim so owner can re-claim
            if($appeal->claim){
                $appeal->claim->update(['status' => 'appeal_resolved']);
            }

        } elseif($request->action === 'reject'){
            $appeal->status = 'rejected';
            $appeal->admin_note = $request->admin_note;
            $appeal->save();

            // Notify appellant
            Notification::create([
                'user_id'        => $appeal->raised_by,
                'message_body'   => 'Your appeal for the ' . $appeal->item->category . ' has been reviewed and rejected by the admin' . ($request->admin_note ? ': ' . $request->admin_note : '.'),
                'type'           => 'appeal_rejected',
                'reference_id'   => $appeal->id,
                'reference_type' => 'appeal',
                'is_read'        => false
            ]);
        }

        return response()->json(['message' => 'Appeal updated successfully.']);
    }
}