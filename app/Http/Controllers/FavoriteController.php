<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FavoriteController extends Controller
{
    /**
     * Display a listing of the user's favorites.
     */
    public function index()
    {
        $favorites = Favorite::forUser(Auth::id())
            ->ordered()
            ->get();

        return response()->json($favorites);
    }

    /**
     * Store a newly created favorite.
     */
    public function store(Request $request)
    {
        $request->validate([
            'page_id' => 'required|string|max:255',
            'page_title' => 'required|string|max:255',
            'page_url' => 'required|string|max:500',
        ]);

        // Get the next sort order
        $nextSortOrder = Favorite::forUser(Auth::id())->max('sort_order') + 1;

        try {
            $favorite = Favorite::create([
                'user_id' => Auth::id(),
                'page_id' => $request->page_id,
                'page_title' => $request->page_title,
                'page_url' => $request->page_url,
                'sort_order' => $nextSortOrder,
            ]);

            return redirect()->back()->with('success', 'Added to favorites');
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle duplicate key constraint
            if ($e->getCode() === '23000') {
                throw ValidationException::withMessages([
                    'page_id' => ['This page is already in your favorites.']
                ]);
            }
            throw $e;
        }
    }

    /**
     * Check if a page is favorited by the current user.
     */
    public function show(string $pageId)
    {
        $favorite = Favorite::forUser(Auth::id())
            ->where('page_id', $pageId)
            ->first();

        return response()->json([
            'is_favorited' => $favorite !== null,
            'favorite' => $favorite
        ]);
    }

    /**
     * Update favorites order.
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'favorites' => 'required|array',
            'favorites.*.id' => 'required|integer|exists:favorites,id',
            'favorites.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->favorites as $favoriteData) {
            Favorite::where('id', $favoriteData['id'])
                ->where('user_id', Auth::id()) // Ensure user owns the favorite
                ->update(['sort_order' => $favoriteData['sort_order']]);
        }

        return redirect()->back();
    }

    /**
     * Remove the specified favorite.
     */
    public function destroy(string $id)
    {
        $favorite = Favorite::forUser(Auth::id())->findOrFail($id);
        $favorite->delete();

        return redirect()->back();
    }

    /**
     * Toggle favorite status for a page.
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'page_id' => 'required|string|max:255',
            'page_title' => 'required|string|max:255',
            'page_url' => 'required|string|max:500',
        ]);

        $favorite = Favorite::forUser(Auth::id())
            ->where('page_id', $request->page_id)
            ->first();

        if ($favorite) {
            // Remove from favorites
            $favorite->delete();
            return redirect()->back();
        } else {
            // Add to favorites
            $nextSortOrder = Favorite::forUser(Auth::id())->max('sort_order') + 1;
            
            Favorite::create([
                'user_id' => Auth::id(),
                'page_id' => $request->page_id,
                'page_title' => $request->page_title,
                'page_url' => $request->page_url,
                'sort_order' => $nextSortOrder,
            ]);

            return redirect()->back();
        }
    }
}
