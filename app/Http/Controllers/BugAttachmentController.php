<?php

namespace App\Http\Controllers;

use App\Models\BugAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BugAttachmentController extends Controller
{
    /**
     * Serve bug attachment for reporter or users with bug permission.
     */
    public function show(BugAttachment $attachment): Response
    {
        $attachment->loadMissing('bug');

        $user = auth()->user();
        $canView = $user !== null
            && ($user->can('manage_bugs') || $attachment->bug?->user_id === $user->id);

        if (! $canView) {
            abort(403);
        }

        if (! Storage::disk('public')->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk('public')->response($attachment->path);
    }
}
