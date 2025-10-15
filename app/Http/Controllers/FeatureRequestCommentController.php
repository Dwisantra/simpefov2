<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\FeatureRequest;
use App\Models\FeatureRequestComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FeatureRequestCommentController extends Controller
{
    public function store(Request $request, FeatureRequest $featureRequest)
    {
        $data = $request->validate([
            'comment' => 'required|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
        ]);

        $user = $request->user();

        if ((int) $user->level !== UserRole::ADMIN->value) {
            return response()->json([
                'message' => 'Hanya admin yang dapat menambahkan komentar.'
            ], 403);
        }

        $attachmentPath = null;
        $attachmentName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $storedName = Str::uuid()->toString() . ($extension ? '.' . $extension : '');
            $attachmentPath = $file->storeAs('feature-request-comments', $storedName, 'local');
        }

        $comment = $featureRequest->comments()->create([
            'user_id' => $user->id,
            'comment' => $data['comment'],
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        $comment->load('user:id,name,level');

        return response()->json($comment, 201);
    }

    public function downloadAttachment(Request $request, FeatureRequest $featureRequest, FeatureRequestComment $comment)
    {
        if ($comment->feature_request_id !== $featureRequest->id || ! $comment->attachment_path) {
            abort(404);
        }

        $user = $request->user();

        if ((int) $user->level === UserRole::USER->value && $featureRequest->user_id !== $user->id) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk mengunduh berkas ini.'], 403);
        }

        if (! Storage::disk('local')->exists($comment->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $comment->attachment_path,
            $comment->attachment_name ?? basename($comment->attachment_path)
        );
    }
}
