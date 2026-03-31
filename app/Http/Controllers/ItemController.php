<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
