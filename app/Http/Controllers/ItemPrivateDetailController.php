<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemPrivateDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemPrivateDetailController extends Controller
{
    // storing the private details 
    public function store(Request $request){
        $validated = $request -> validate ([
        'item_id'=>'required|exists:items,id',
        'brand_model_or_logo'=>'nullable|string',
        'what_was_inside_or_attached'=>'nullable|string',
        'hidden_or_internal_details'=>'nullable|string',
        'extra_notes'=>'nullable|string',
        'location_found'=>'nullable|string',
          ]);

          $item = Item::findOrFail($validated['item_id']);
         if($item->posted_by !== Auth::id()){
         return response()->json([
        'message' => 'You are not authorized to add private details for this item.'
         ], 403);
         }

        $itemPrivateDetail = new ItemPrivateDetail();
        $itemPrivateDetail->item_id = $validated['item_id'];
        $itemPrivateDetail->brand_model_or_logo = $validated['brand_model_or_logo'];
        $itemPrivateDetail->what_was_inside_or_attached = $validated['what_was_inside_or_attached'];
        $itemPrivateDetail->hidden_or_internal_details = $validated['hidden_or_internal_details'];
        $itemPrivateDetail->extra_notes = $validated['extra_notes'];
        $itemPrivateDetail->location_found = $validated['location_found'];

         try{
             $itemPrivateDetail->save();
             return response()->json([
            'message' => 'Private details saved successfully.',
            'details' => $itemPrivateDetail
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

            $itemPrivateDetail = ItemPrivateDetail::where('item_id', $id)->firstOrFail();

            return response()->json([
                'details' => $itemPrivateDetail
            ], 200);

        } catch(\Exception $exception){
            return response()->json([
                'error' => 'Failed to fetch private details.',
                'message' => $exception->getMessage()
            ], 404);
        }
      }
}





