<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\LostItem;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LostItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    
    public function index(Request $request)
   {
    
        $query = LostItem::where('status', 'searching');

        if($request->filled('category')){
            $query->where('category', $request->category);
        }

        $lostItems = $query->get();
        return response()->json($lostItems);
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
        $validated = $request->validate([
            'category' => 'required|in:eyewear,electronics,stationery,clothing_and_accessories,documents_and_cards,bags_and_luggage,keys,other',
            'general_description' => 'required|string',
            'photo_path' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Handle optional photo
        $filename = null;
        if($request->hasFile('photo_path')){
            $filename = $request->file('photo_path')->store('lost_items', 'public');
        }

        $lostItem = new LostItem();
        $lostItem->posted_by = Auth::id();
        $lostItem->category = $validated['category'];
        $lostItem->general_description = $validated['general_description'];
        $lostItem->photo_path = $filename;
        $lostItem->status = 'searching';

        try{
            $lostItem->save();

            // Check for matching found items in same category
            $matches = Item::where('category', $lostItem->category)
                            ->where('status', 'listed')
                            ->get();

            if($matches->count() > 0){
                Notification::create([
                    'user_id' => Auth::id(),
                    'message_body' => 'We found ' . $matches->count() . ' possible match(es) for your lost ' . $lostItem->category . '. Click here to view.',
                    'type' => 'item_matched',
                    'reference_id' => $lostItem->id,
                    'reference_type' => 'lost_item',
                    'is_read' => false
                ]);
            }

            return response()->json([
                'message' => 'Lost item posted successfully.',
                'lost_item' => $lostItem
            ], 201);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to post lost item.',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    // View a single lost item
    public function show(string $id)
    {
        try{
            $lostItem = LostItem::findOrFail($id);
            return response()->json($lostItem, 200);
        } catch(\Exception $e){
            return response()->json([
                'error' => 'Lost item not found.',
                'message' => $e->getMessage()
            ], 404);
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
    
     public function update(Request $request, string $id)
    {
        try{
            $lostItem = LostItem::findOrFail($id);

            // Only the owner can update their lost post
            if($lostItem->posted_by !== Auth::id()){
                return response()->json([
                    'message' => 'You are not authorized to update this lost item.'
                ], 403);
            }

            $validated = $request->validate([
                'category' => 'sometimes|in:eyewear,electronics,stationery,clothing_and_accessories,documents_and_cards,bags_and_luggage,keys,other',
                'general_description' => 'sometimes|string',
                'photo_path' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',
                'status' => 'sometimes|in:searching,found,expired,closed',
            ]);

            // Handle photo update
            if($request->hasFile('photo_path')){
                if($lostItem->photo_path && Storage::disk('public')->exists($lostItem->photo_path)){
                    Storage::disk('public')->delete($lostItem->photo_path);
                }
                $validated['photo_path'] = $request->file('photo_path')->store('lost_items', 'public');
            }

            $lostItem->fill($validated);
            $lostItem->save();

            return response()->json([
                'message' => 'Lost item updated successfully.',
                'lost_item' => $lostItem
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to update lost item.',
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
            $lostItem = LostItem::findOrFail($id);

            // Only the owner can delete their lost post
            if($lostItem->posted_by !== Auth::id()){
                return response()->json([
                    'message' => 'You are not authorized to delete this lost item.'
                ], 403);
            }

            $lostItem->delete();

            return response()->json([
                'message' => 'Lost item deleted successfully.'
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to delete lost item.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
