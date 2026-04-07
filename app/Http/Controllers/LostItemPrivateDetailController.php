<?php

namespace App\Http\Controllers;

use App\Models\LostItem;
use App\Models\LostItemPrivateDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LostItemPrivateDetailController extends Controller
{
    // storing the private details 
    public function store(Request $request){
        $validated = $request -> validate ([
        'lost_item_id'=>'required|exists:lost_items,id',
        'brand_model_or_logo'=>'nullable|string',
        'what_was_inside_or_attached'=>'nullable|string',
        'hidden_or_internal_details'=>'nullable|string',
        'extra_notes'=>'nullable|string',
        
          ]);

          $lostItem = LostItem::findOrFail($validated['lost_item_id']);
            if($lostItem->posted_by !== Auth::id()){
            return response()->json([
            'message' => 'You are not authorized to add private details for this item.'
            ], 403);
            }

            $lostItemPrivateDetail = new LostItemPrivateDetail();
            $lostItemPrivateDetail->item_id = $validated['lost_item_id'];
            $lostItemPrivateDetail->brand_model_or_logo = $validated['brand_model_or_logo'];
            $lostItemPrivateDetail->what_was_inside_or_attached = $validated['what_was_inside_or_attached'];
            $lostItemPrivateDetail->hidden_or_internal_details = $validated['hidden_or_internal_details'];
            $lostItemPrivateDetail->extra_notes = $validated['extra_notes'];
            

            try{
                $lostItemPrivateDetail->save();
                return response()->json([
                'message' => 'Private details saved successfully.',
                'details' => $lostItemPrivateDetail
            ], 201);
            }
            catch(\Exception $exception){
                return response()->json([
                    'error' => 'Failed to save private details.',
                    'message'=>$exception->getMessage()
                ],500);
            }
    }


        // Show private details --- admin only
       public function show(string $id){
        try {
            // Only admin can view private details
            if(Auth::user()->role !== 'admin'){
                return response()->json([
                    'message' => 'You are not authorized to view private details.'
                ], 403);
            }

            $lostItemPrivateDetail = LostItemPrivateDetail::where('lost_item_id', $id)->firstOrFail();

            return response()->json([
                'details' => $lostItemPrivateDetail
            ], 200);

        } catch(\Exception $exception){
            return response()->json([
                'error' => 'Failed to fetch private details.',
                'message' => $exception->getMessage()
            ], 404);
        }
      }
}
