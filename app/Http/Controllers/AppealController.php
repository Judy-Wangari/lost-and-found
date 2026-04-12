<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appeal;
use App\Models\Claim;
use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AppealController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $appeals = Appeal::with(['claim', 'raisedBy', 'item'])->get();
        return response()->json($appeals);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
            'reason' => 'required|string|max:1000',
        ]);

        $claim = Claim::findOrFail($request->claim_id);

        if ($claim->claimed_by !== Auth::id() || $claim->status !== 'rejected') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $existingAppeal = Appeal::where('claim_id', $request->claim_id)->first();
        if ($existingAppeal) {
            return response()->json(['error' => 'Appeal already submitted.'], 400);
        }

        $appeal = Appeal::create([
            'claim_id' => $request->claim_id,
            'raised_by' => Auth::id(),
            'item_id' => $claim->item_id,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        // Notify admin via email
        // Mail::to('admin@example.com')->send(new AppealSubmitted($appeal));

        return response()->json(['message' => 'Appeal submitted successfully.', 'appeal' => $appeal], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $appeal = Appeal::with(['claim', 'raisedBy', 'item'])->findOrFail($id);

        // Set to under review if pending
        if ($appeal->status === 'pending') {
            $appeal->update(['status' => 'under_review']);
        }

        // Get finder's private details
        $finderDetails = $appeal->item->itemPrivateDetail;

        // Get owner's claim details (which are in claim)
        $ownerClaim = $appeal->claim;

        // If there's a lost item, get owner's lost private details
        $ownerLostDetails = $appeal->claim->lost_item ? $appeal->claim->lost_item->lostItemPrivateDetail : null;

        return response()->json([
            'appeal' => $appeal,
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
            'action' => 'required|in:approve,reject',
        ]);

        $appeal = Appeal::findOrFail($id);
        $claim = $appeal->claim;

                if($request->action === 'approve'){
                $claim->status = 'approved';
                $claim->save();

                $verificationCode = rand(100000, 999999);
                $claim->item->update([
                    'status' => 'awaiting_collection',
                    'claimed_by' => $claim->claimed_by,
                    'verification_code' => $verificationCode
                ]);

                $appeal->status = 'resolved';
                $appeal->save();
            

            // Generate 6-digit code, send to owner
            // Notify finder and owner that messaging is open

                }elseif($request->action === 'reject'){
            $appeal->status = 'resolved';
            $appeal->save();
            $claim->item->update(['status' => 'listed']);
        }

        return response()->json(['message' => 'Appeal ' . $request->action . 'd successfully.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
