<?php

namespace App\Http\Controllers;

use App\Models\Appeal;
use App\Models\Claim;
use App\Models\Item;
use App\Models\LostItem;
use App\Models\SecurityHandover;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    // Main dashboard summary
    public function index()
    {
        if(Auth::user()->role !== 'admin'){
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Item statistics
            $itemStats = [
                'total' => Item::count(),
                'listed' => Item::where('status', 'listed')->count(),
                'under_review' => Item::where('status', 'under_review')->count(),
                'awaiting_collection' => Item::where('status', 'awaiting_collection')->count(),
                'flagged_to_security' => Item::where('status', 'flagged_to_security')->count(),
                'unresponsive' => Item::where('status', 'unresponsive')->count(),
                'collected' => Item::where('status', 'collected')->count(),
            ];

            // Lost item statistics
            $lostItemStats = [
                'total' => LostItem::count(),
                'searching' => LostItem::where('status', 'searching')->count(),
                'found' => LostItem::where('status', 'found')->count(),
                'expired' => LostItem::where('status', 'expired')->count(),
                'closed' => LostItem::where('status', 'closed')->count(),
            ];

            // Claim statistics
            $claimStats = [
                'total' => Claim::count(),
                'pending_review' => Claim::where('status', 'pending_review')->count(),
                'clarification_requested' => Claim::where('status', 'clarification_requested')->count(),
                'approved' => Claim::where('status', 'approved')->count(),
                'rejected' => Claim::where('status', 'rejected')->count(),
            ];

            // Appeal statistics
            $appealStats = [
                'total' => Appeal::count(),
                'pending' => Appeal::where('status', 'pending')->count(),
                'under_review' => Appeal::where('status', 'under_review')->count(),
                'resolved' => Appeal::where('status', 'resolved')->count(),
            ];

            // User statistics
            $userStats = [
                'total' => User::count(),
                'students' => User::where('role', 'student')->count(),
                'security' => User::where('role', 'security')->count(),
                'admins' => User::where('role', 'admin')->count(),
                'pending_approval' => User::where('status', 'pending')->count(),
            ];

            // Security handover statistics
            $handoverStats = [
                'total' => SecurityHandover::count(),
                'pending_confirmation' => SecurityHandover::where('status', 'pending_confirmation')->count(),
                'confirmed' => SecurityHandover::where('status', 'confirmed')->count(),
                'rejected' => SecurityHandover::where('status', 'rejected')->count(),
                'collected' => SecurityHandover::where('status', 'collected')->count(),
            ];

            return response()->json([
                'item_stats' => $itemStats,
                'lost_item_stats' => $lostItemStats,
                'claim_stats' => $claimStats,
                'appeal_stats' => $appealStats,
                'user_stats' => $userStats,
                'handover_stats' => $handoverStats,
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch dashboard data.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Get all unresponsive items
    public function unresponsiveItems()
    {
        if(Auth::user()->role !== 'admin'){
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $items = Item::where('status', 'unresponsive')
                ->with(['postedBy', 'claimedByUser'])
                ->orderBy('updated_at', 'asc')
                ->get();

            return response()->json($items, 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch unresponsive items.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Get all collected cases
    public function collectedItems()
    {
        if(Auth::user()->role !== 'admin'){
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $items = Item::where('status', 'collected')
                ->with(['postedBy', 'claimedByUser'])
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json($items, 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch collected items.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Get recent activity
    public function recentActivity()
    {
        if(Auth::user()->role !== 'admin'){
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $recentItems = Item::orderBy('created_at', 'desc')->take(5)->get();
            $recentClaims = Claim::with(['claimedBy', 'item'])->orderBy('created_at', 'desc')->take(5)->get();
            $recentAppeals = Appeal::with(['raisedBy', 'item'])->orderBy('created_at', 'desc')->take(5)->get();

            return response()->json([
                'recent_items' => $recentItems,
                'recent_claims' => $recentClaims,
                'recent_appeals' => $recentAppeals,
            ], 200);

        } catch(\Exception $e){
            return response()->json([
                'error' => 'Failed to fetch recent activity.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}