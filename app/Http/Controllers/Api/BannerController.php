<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Http\Resources\BannerResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Banner::query();

        if ($request->boolean('active_only', true)) {
            $query->where('status', true);
        }

        $banners = $query->orderBy('sort_order')->paginate($request->get('per_page', 15));

        return BannerResource::collection($banners)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'required|string|max:255',
            'link' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'status' => 'boolean',
        ]);

        $banner = Banner::create($validated);

        return (new BannerResource($banner))->response()->setStatusCode(201);
    }

    public function show(Banner $banner): JsonResponse
    {
        return (new BannerResource($banner))->response();
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'sometimes|required|string|max:255',
            'link' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'status' => 'boolean',
        ]);

        $banner->update($validated);

        return (new BannerResource($banner))->response();
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $banner->delete();

        return response()->json(['message' => 'Banner deleted successfully']);
    }
}