<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Claim;
use App\Models\Item;
use App\Models\LostItem;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;


class ClaimController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function index(Request $request)
{
    $user = Auth::user();

    // Admin sees all claims with status filter
    if($user->role === 'admin'){
        $query = Claim::with(['claimedBy', 'item', 'lostItem']);

        if($request->filled('status')){
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending_review');
        }

        return response()->json($query->get());
    }

    // Student sees only their own claims
    $claims = Claim::with(['item'])
        ->where('claimed_by', $user->id)
        ->latest()
        ->get();

    return response()->json($claims);
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'lost_item_id' => 'nullable|exists:lost_items,id',
            'brand_model_or_logo' => 'nullable|string',
            'what_was_inside_or_attached' => 'nullable|string',
            'hidden_or_internal_details' => 'nullable|string',
            'extra_notes' => 'nullable|string',
        ]);

        // Check at least 2 fields filled
        $fields = [
            $request->brand_model_or_logo,
            $request->what_was_inside_or_attached,
            $request->hidden_or_internal_details,
            $request->extra_notes,
        ];
        $filledCount = count(array_filter($fields));
        if ($filledCount < 2) {
            return response()->json(['error' => 'At least 2 fields must be filled.'], 400);
        }

        // Check claim limit: 3 failed per day
        $today = now()->toDateString();
        $failedClaimsToday = Claim::where('claimed_by', Auth::id())
            ->where('status', 'rejected')
            ->whereDate('created_at', $today)
            ->count();
        if ($failedClaimsToday >= 3) {
            return response()->json(['error' => 'Claim limit exceeded. Try again tomorrow.'], 429);
        }

        // Check if the claimer is the finder
        $item = Item::findOrFail($request->item_id);
        if($item->posted_by === Auth::id()){
            return response()->json([
                'error' => 'You cannot claim an item you posted.'
            ], 400);
        }

        // Check if already claimed this item
        $existingClaim = Claim::where('claimed_by', Auth::id())
            ->where('item_id', $request->item_id)
            ->first();
        if ($existingClaim) {
            return response()->json(['error' => 'You have already claimed this item.'], 400);
        }

                $claim = Claim::create([
            'claimed_by' => Auth::id(),
            'item_id'    => $request->item_id,
            'lost_item_id' => $request->lost_item_id,
            'brand_model_or_logo' => $request->brand_model_or_logo,
            'what_was_inside_or_attached' => $request->what_was_inside_or_attached,
            'hidden_or_internal_details' => $request->hidden_or_internal_details,
            'extra_notes' => $request->extra_notes,
            'status' => 'pending_review',
        ]);

        // Update item status to under_review
        $item->status = 'under_review';
        $item->save();

        // Notify admin of new claim
        $admin = User::where('role', 'admin')->first();
        if($admin){
            Notification::create([
                'user_id'        => $admin->id,
                'message_body'   => Auth::user()->first_name . ' ' . Auth::user()->last_name . ' has submitted a claim for the ' . $item->category . '. Please review.',
                'type'           => 'claim_submitted',
                'reference_id'   => $claim->id,
                'reference_type' => 'claim',
                'is_read'        => false
            ]);
        }

        return response()->json(['message' => 'Claim submitted. You will be notified within 24 hours.', 'claim' => $claim], 201);
            }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

       $claim = Claim::with([
            'claimedBy',
            'item.poster',
            'item.itemPrivateDetail',
            'lostItem.lostItemPrivateDetail'
        ])->findOrFail($id);
        // Get finder's private details
        $finderDetails = $claim->item->itemPrivateDetail;

        // Get owner's claim details
        $ownerClaim = $claim;

        // If there's a lost item, get owner's lost private details
        $ownerLostDetails = $claim->lost_item ? $claim->lost_item->lostItemPrivateDetail : null;

        return response()->json([
            'claim' => $claim,
            'finder_details' => $finderDetails,
            'owner_claim' => $ownerClaim,
            'owner_lost_details' => $ownerLostDetails,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'action' => 'required|in:approve,reject,clarification_requested',
            'clarification_text' => 'nullable|string|max:1000',
        ]);

        $claim = Claim::findOrFail($id);

        if ($request->action === 'approve') {
            $claim->status = 'approved';
            $claim->save();


            // Update item status to awaiting_collection (assuming item has status field)
                 $verificationCode = rand(100000, 999999);
                 $claim->item->update([
                'status' => 'awaiting_collection',
                'claimed_by' => $claim->claimed_by,
                'verification_code' => $verificationCode
]);


                                Notification::create([
                        'user_id' => $claim->claimed_by,
                        'message_body' => 'Your claim has been approved! Your verification code is: ' . $verificationCode . '. Use this code when collecting your item.',
                        'type' => 'claim_approved',
                        'reference_id' => $claim->id,
                        'reference_type' => 'claim',
                        'is_read' => false
                    ]);

                    // Notify finder
                    Notification::create([
                        'user_id' => $claim->item->posted_by,
                        'message_body' => 'A claim on your found ' . $claim->item->category . ' has been approved. You can now message the owner to arrange collection.',
                        'type' => 'claim_approved',
                        'reference_id' => $claim->id,
                        'reference_type' => 'claim',
                        'is_read' => false
                    ]);

            // TODO: Send code to owner, enable messaging

                        } elseif ($request->action === 'reject') {
                $claim->status = 'rejected';
                $claim->save();

                // Item goes back to listed
                $claim->item->update(['status' => 'listed']);

                // Notify owner with clear message and ability to appeal
                Notification::create([
                    'user_id' => $claim->claimed_by,
                    'message_body' => 'Your claim for the ' . $claim->item->category . ' has been rejected by the admin. You may submit a new claim or appeal this decision if you believe it was unfair.',
                    'type' => 'claim_rejected',
                    'reference_id' => $claim->id,
                    'reference_type' => 'claim',
                    'is_read' => false
                ]);


                // Item goes back to public
            } elseif($request->action === 'clarification_requested'){
            $claim->status = 'clarification_requested';
            $claim->clarification_request_text = $request->clarification_text;
            $claim->save();
            $claim->item->update(['status' => 'clarification_requested']);

            // Notify owner of clarification request
            Notification::create([
                'user_id' => $claim->claimed_by,
                'message_body' => 'Admin is requesting clarification on your claim. Details: ' . $request->clarification_text,
                'type' => 'clarification_requested',
                'reference_id' => $claim->id,
                'reference_type' => 'claim',
                'is_read' => false
            ]);
    }

            return response()->json(['message' => 'Claim ' . $request->action . 'd successfully.']);
        }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // Undo approval --- admin resets item back to listed
public function undoApproval(string $id)
{
    try {
        if(Auth::user()->role !== 'admin'){
            return response()->json([
                'message' => 'You are not authorized to undo approval.'
            ], 403);
        }

        $claim = Claim::findOrFail($id);

        // Can only undo if claim is approved and item not yet collected
        if($claim->status !== 'approved'){
            return response()->json([
                'message' => 'Only approved claims can be undone.'
            ], 400);
        }

        if($claim->item->status === 'collected'){
            return response()->json([
                'message' => 'Cannot undo approval. Item has already been collected.'
            ], 400);
        }

        // Reset claim status
        $claim->status = 'rejected';
        $claim->save();

        // Reset item status back to listed and clear claimed_by and verification_code
        $claim->item->update([
            'status' => 'listed',
            'claimed_by' => null,
            'verification_code' => null
        ]);

        // Notify owner
        Notification::create([
            'user_id' => $claim->claimed_by,
            'message_body' => 'The approval for your claim on ' . $claim->item->category . ' has been undone by the admin. The item is back on public listing.',
            'type' => 'claim_rejected',
            'reference_id' => $claim->id,
            'reference_type' => 'claim',
            'is_read' => false
        ]);

        // Notify finder
        Notification::create([
            'user_id' => $claim->item->posted_by,
            'message_body' => 'The approval for the claim on your found ' . $claim->item->category . ' has been undone by the admin. The item is back on public listing.',
            'type' => 'claim_rejected',
            'reference_id' => $claim->id,
            'reference_type' => 'claim',
            'is_read' => false
        ]);

        return response()->json([
            'message' => 'Approval undone successfully. Item is back on public listing.'
        ], 200);

    } catch(\Exception $e){
        return response()->json([
            'error' => 'Failed to undo approval.',
            'message' => $e->getMessage()
        ], 500);
    }
}

    // Direct collection - Finder verifies code after meeting owner in person
    public function collectDirect(Request $request, string $id)
    {
        $request->validate([
            'verification_code' => 'required|string',
        ]);

        try {
            $claim = Claim::findOrFail($id);
            $item = $claim->item;

            // Only finder can collect directly
            if($item->posted_by !== Auth::id()){
                return response()->json([
                    'message' => 'Only the finder can confirm collection.'
                ], 403);
            }

            // Claim must be approved
            if($claim->status !== 'approved'){
                return response()->json([
                    'message' => 'Only approved claims can be collected.'
                ], 400);
            }

            // Verify the code matches
            if($request->verification_code != $item->verification_code){
                return response()->json([
                    'message' => 'Invalid verification code.'
                ], 400);
            }

            // Mark item as collected
            $item->update(['status' => 'collected']);

            // Notify owner
            Notification::create([
                'user_id' => $claim->claimed_by,
                'message_body' => 'Your ' . $item->category . ' has been successfully collected. Case closed.',
                'type' => 'item_collected',
                'reference_id' => $claim->id,
                'reference_type' => 'claim',
                'is_read' => false
            ]);

            return response()->json([
                'message' => 'Item collected successfully. Case closed.',
                'item' => $item
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to collect item.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Owner submits response to clarification request
    public function submitClarification(Request $request, string $id)
    {
        $request->validate([
            'brand_model_or_logo' => 'nullable|string',
            'what_was_inside_or_attached' => 'nullable|string',
            'hidden_or_internal_details' => 'nullable|string',
            'extra_notes' => 'nullable|string',
        ]);

        try {
            $claim = Claim::findOrFail($id);

            // Only claim owner can submit clarification
            if($claim->claimed_by !== Auth::id()){
                return response()->json([
                    'message' => 'Only the claim owner can submit clarification.'
                ], 403);
            }

            // Claim must be in clarification_requested status
            if($claim->status !== 'clarification_requested'){
                return response()->json([
                    'message' => 'This claim is not requesting clarification.'
                ], 400);
            }

            // Update claim with new details
            $claim->update([
                'brand_model_or_logo' => $request->brand_model_or_logo ?? $claim->brand_model_or_logo,
                'what_was_inside_or_attached' => $request->what_was_inside_or_attached ?? $claim->what_was_inside_or_attached,
                'hidden_or_internal_details' => $request->hidden_or_internal_details ?? $claim->hidden_or_internal_details,
                'extra_notes' => $request->extra_notes ?? $claim->extra_notes,
                'status' => 'pending_review'
            ]);

            // Update item status back to under_review
            $claim->item->update(['status' => 'under_review']);

            // Notify admin that clarification has been submitted
            Notification::create([
                'user_id' => User::where('role', 'admin')->first()->id,
                'message_body' => 'Owner has submitted clarification for claim on ' . $claim->item->category . '. Please review.',
                'type' => 'clarification_responded',
                'reference_id' => $claim->id,
                'reference_type' => 'claim',
                'is_read' => false
            ]);

            return response()->json([
                'message' => 'Clarification submitted. Admin will review your response.',
                'claim' => $claim
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to submit clarification.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}