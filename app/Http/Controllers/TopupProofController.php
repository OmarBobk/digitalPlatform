<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TopupProof;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TopupProofController extends Controller
{
    /**
     * Serve the proof file only for admin or the top-up request owner.
     * No public URLs; file is on private disk.
     */
    public function show(TopupProof $proof): Response
    {
        $proof->loadMissing('topupRequest');

        $canView = auth()->user()?->can('manage_topups')
            || $proof->topupRequest->user_id === auth()->id();

        if (! $canView) {
            abort(403);
        }

        if (! Storage::disk('local')->exists($proof->file_path)) {
            abort(404);
        }

        $headers = $proof->mime_type
            ? ['Content-Type' => $proof->mime_type]
            : [];

        return Storage::disk('local')->response(
            $proof->file_path,
            $proof->file_original_name ?? 'proof',
            $headers
        );
    }
}
