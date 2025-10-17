<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\ProductVariantModel;
use App\Models\ProductModel;
use App\Models\UploadModel;


class UploadController extends Controller
{

    public function deleteVariantPhoto(int $variant, int $photo)
    {
        // 1) Get variant
        $variant = ProductVariantModel::findOrFail($variant);

        // 2) Parse CSV robustly -> array of ints in exact order
        $ids = collect(explode(',', (string) $variant->photo_id))
            ->map(fn($v) => (int) trim($v))
            ->filter(fn($v) => $v > 0)
            ->values();

        if (!$ids->contains($photo)) {
            return response()->json([
                'success' => false,
                'message' => 'Photo not found.'
            ], 404);
        }

        DB::transaction(function () use ($variant, $photo, $ids) {
            // 3) Remove and update CSV
            $newIds = $ids->reject(fn($v) => $v === (int) $photo)->values();
            $variant->photo_id = $newIds->implode(',');
            $variant->save();

            // 4) Delete physical and DB row for this upload
            if ($upload = UploadModel::find($photo)) {
                // storage/app/public/<path> expected; adjust disk if needed
                Storage::disk('public')->delete($upload->file_path);
                $upload->delete();
            }
        });

        // Build updated [{id,url}] list in the SAME order as remaining CSV
        $remainingIds = collect(explode(',', (string) $variant->photo_id))
            ->map(fn($v) => (int) trim($v))
            ->filter(fn($v) => $v > 0)
            ->values();

        $files = UploadModel::whereIn('id', $remainingIds)->get(['id','file_path'])->keyBy('id');

        $fileObjs = [];
        foreach ($remainingIds as $id) {
            if (isset($files[$id])) {
                $fileObjs[] = [
                    'id'  => $id,
                    'url' => Storage::disk('public')->url($files[$id]->file_path),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Photo deleted successfully.',
            'data' => [
                'variant_id' => $variant->id,
                'file_urls'  => $fileObjs
            ]
        ], 200);
    }

    // public function deleteVariantBanner($variantId, $uploadId)
    // {
    //     $variant = ProductVariantModel::findOrFail($variantId);

    //     // 1️⃣ Remove banner from CSV
    //     $bids = array_values(array_filter(array_map('intval', explode(',', $variant->banner_id ?? ''))));
    //     if (!in_array($uploadId, $bids)) {
    //         return response()->json(['success' => false, 'message' => 'Banner not found.'], 404);
    //     }
    //     $bids = array_values(array_diff($bids, [$uploadId]));
    //     $variant->banner_id = implode(',', $bids);
    //     $variant->save();

    //     // 2️⃣ Delete orphan if unused
    //     $inPhotos  = ProductVariantModel::whereRaw("FIND_IN_SET(?, photo_id)", [$uploadId])->exists();
    //     $inBanners = ProductVariantModel::whereRaw("FIND_IN_SET(?, banner_id)", [$uploadId])->exists();
    //     $inProducts= ProductModel::whereRaw("FIND_IN_SET(?, image)", [$uploadId])->exists();
    //     if (!$inPhotos && !$inBanners && !$inProducts) {
    //         if ($upload = UploadModel::find($uploadId)) {
    //             Storage::disk('public')->delete($upload->file_path);
    //             $upload->delete();
    //         }
    //     }

    //     // 3️⃣ Return updated list [{id,url}] in your custom order
    //     $files = UploadModel::whereIn('id', $bids)->get(['id','file_path'])->keyBy('id');
    //     $order = [
    //         'productpage-main'   => 1,
    //         'productpage-bldc'   => 2,
    //         'productpage-scan'   => 3,
    //         'productpage-colors' => 4,
    //         'unknown'            => 99,
    //     ];

    //     $tmp = [];
    //     foreach ($bids as $bid) {
    //         if (isset($files[$bid])) {
    //             $path = $files[$bid]->file_path;
    //             $type = 'unknown';
    //             foreach ($order as $key => $pos) {
    //                 if (strpos($path, $key) !== false) {
    //                     $type = $key;
    //                     break;
    //                 }
    //             }
    //             $tmp[] = ['id'=>$bid, 'path'=>$path, 'sort_key'=>$order[$type]];
    //         }
    //     }

    //     usort($tmp, fn($a,$b) => $a['sort_key'] <=> $b['sort_key']);
    //     $bannerObjs = array_map(fn($r) => [
    //         'id'  => $r['id'],
    //         'url' => Storage::disk('public')->url($r['path']),
    //     ], $tmp);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Banner deleted successfully.',
    //         'data'    => ['variant_id' => $variant->id, 'banner_urls' => $bannerObjs]
    //     ], 200);
    // }
    public function deleteVariantBanner(int $variantId, int $bannerId)
    {
        // 1️⃣ Find the variant
        $variant = ProductVariantModel::findOrFail($variantId);

        // 2️⃣ Parse CSV and remove the given bannerId
        $banners = collect(explode(',', (string) $variant->banner_id))
            ->map(fn($v) => (int) trim($v))
            ->filter(fn($v) => $v > 0)
            ->values();

        if (!$banners->contains($bannerId)) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found for this variant.'
            ], 404);
        }

        // Remove banner ID and update variant
        $updated = $banners->reject(fn($id) => $id === $bannerId)->values();
        $variant->banner_id = $updated->implode(',');
        $variant->save();

        // 3️⃣ Delete file + row from uploads table
        if ($upload = UploadModel::find($bannerId)) {
            Storage::disk('public')->delete($upload->file_path);
            $upload->delete();
        }

        // 4️⃣ Return updated banners as [{id, url}] in sorted order
        $remainingIds = $updated->toArray();
        if (empty($remainingIds)) {
            return response()->json([
                'success' => true,
                'message' => 'Banner deleted successfully.',
                'data' => ['variant_id' => $variant->id, 'banner_urls' => []],
            ]);
        }

        $files = UploadModel::whereIn('id', $remainingIds)->get(['id','file_path'])->keyBy('id');

        // Custom order: Main → BLDC → Scan → Colors → Unknown
        $order = [
            'productpage-main'   => 1,
            'productpage-bldc'   => 2,
            'productpage-scan'   => 3,
            'productpage-colors' => 4,
            'unknown'            => 99,
        ];

        $tmp = [];
        foreach ($remainingIds as $id) {
            if (!isset($files[$id])) continue;
            $path = $files[$id]->file_path;
            $typeKey = 'unknown';
            foreach (array_keys($order) as $key) {
                if (strpos($path, $key) !== false) { $typeKey = $key; break; }
            }
            $tmp[] = [
                'id'       => $id,
                'path'     => $path,
                'sort_key' => $order[$typeKey],
            ];
        }

        usort($tmp, fn($a,$b) => $a['sort_key'] <=> $b['sort_key']);

        $bannerObjs = array_map(fn($r) => [
            'id'  => $r['id'],
            'url' => Storage::disk('public')->url($r['path']),
        ], $tmp);

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully.',
            'data' => [
                'variant_id'  => $variant->id,
                'banner_urls' => $bannerObjs,
            ],
        ], 200);
    }


}
