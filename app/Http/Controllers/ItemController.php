<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\LostItem;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Item::where('status', 'listed');

        //category filter
        if($request->filled('category')){
            $query->where('category',$request->category);
        }

        $items = $query->get();
        return response()->json($items);
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
        
        //user has to input this
        $validated = $request->validate([
            'category'=>'required|in:eyewear,electronics,stationery,clothing_and_accessories,documents_and_cards,bags_and_luggage,keys,other',
            'photo_path'=>'required|image|mimes:jpeg,png,jpg',
        ]);

            // handle photo
             if ($request->hasFile('photo_path')) {
            $filename = $request->file('photo_path')->store('items', 'public');
        } else {
            return response()->json([
                'message' => 'Photo upload failed.'
            ], 422);
        }
           
            //store data
            $item = new Item();
            $item->posted_by = Auth::id();
            $item ->category =  $validated['category'];
            $item ->photo_path =  $filename;
            $item ->status =  'listed';
            $item ->claimed_by =  null;
            $item ->verification_code =  null;

         try{
             $item->save();

            
            $lostMatches = LostItem::where('category', $item->category)
                            ->where('status', 'searching')
                            ->get();

            foreach($lostMatches as $lostMatch){
                Notification::create([
                    'user_id' => $lostMatch->posted_by,
                    'message_body' => 'A possible match for your lost ' . $item->category . ' has been found. Click here to view.',
                    'type' => 'item_matched',
                    'reference_id' => $item->id,
                    'reference_type' => 'item',
                    'is_read' => false
                ]);
            }

            return response()->json([
            'message' => 'Item created successfully.',
            'item' => $item
            ], 201);
        } catch (\Exception $e) {
       
        return response()->json([
            'message' => 'An error occurred while creating the item.',
            'error' => $e->getMessage()
        ], 500);
    }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
         try{
        $item = Item::findOrFail($id);
        return response()->json($item);
    }
     catch(\Exception $exception){
            return response()->json([
                'error'=>'Failed to fetch the item.',
                'message'=>$exception->getMessage()
            ],404);
        }
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
    public function update(Request $request, Item $item)
   {
    try {
        // If logged-in user is the finder
        if (Auth::check() && Auth::id() === $item->posted_by) {
            $validated = $request->validate([
                'category' => 'sometimes|in:eyewear,electronics,stationery,clothing_and_accessories,documents_and_cards,bags_and_luggage,keys,other',
                'photo_path' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048', // 2MB limit
            ]);

            // Handle photo update
            if ($request->hasFile('photo_path')) {
                if ($item->photo_path && Storage::disk('public')->exists($item->photo_path)) {
                    Storage::disk('public')->delete($item->photo_path);
                }
                $validated['photo_path'] = $request->file('photo_path')->store('items', 'public');
            }

            $item->fill($validated);

        // If logged-in user is admin
        } elseif (Auth::check() && Auth::user()->role === 'admin') {
            $validated = $request->validate([
                'status' => 'sometimes|in:listed,under_review,awaiting_collection,clarification_requested,flagged_to_security,unresponsive,collected',
                'claimed_by' => 'sometimes|nullable|exists:users,id',
                'verification_code' => 'sometimes|nullable|string',
            ]);

            $item->fill($validated);

        // Unauthorized
        } else {
            return response()->json([
                'message' => 'You are not authorized to update this item.'
            ], 403);
        }

        $item->save();

        return response()->json([
            'message' => 'Item updated successfully.',
            'item' => $item
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to update item.',
            'message' => $e->getMessage()
        ], 500);
    }
   }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
          try{
                $item = Item::findOrFail($id);
                //check if the one who posted it is the one deleting the item
                if($item->posted_by !== Auth::id()){
                return response()->json([
                'message' => 'You are not authorized to delete this item.'
                ], 403);
                }
                $item->delete();

                return response()->json([
                'message' => 'Item deleted successfully.'
                ], 200);
           }
            catch(\Exception $exception){
                return response()->json([
                'error'=>'Failed to delete the Item',
                'message'=>$exception->getMessage()
                ],404);
            }
    }
}
