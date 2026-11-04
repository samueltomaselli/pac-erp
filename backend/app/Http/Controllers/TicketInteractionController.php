<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\AddTicketComment;
use App\Enums\TicketStatus;
use App\Http\Requests\Tickets\StoreTicketAttachmentRequest;
use App\Http\Requests\Tickets\StoreTicketCommentRequest;
use App\Http\Resources\TicketAttachmentResource;
use App\Http\Resources\TicketCommentResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TicketInteractionController extends Controller
{
    public function comment(StoreTicketCommentRequest $request, Ticket $ticket, AddTicketComment $addComment): TicketCommentResource
    {
        $comment = $addComment->handle(
            $ticket,
            $request->user(),
            $request->string('body')->toString(),
            $request->file('attachments', [])
        );

        return new TicketCommentResource($comment);
    }

    public function attachment(StoreTicketAttachmentRequest $request, Ticket $ticket): JsonResponse
    {
        if ($ticket->status === TicketStatus::Closed) {
            throw ValidationException::withMessages(['ticket' => 'Chamado fechado não aceita novos anexos.']);
        }

        $storedPaths = [];

        try {
            $attachments = DB::transaction(function () use ($request, $ticket, &$storedPaths) {
                return collect($request->file('attachments'))->map(function ($file) use ($ticket, $request, &$storedPaths) {
                    $path = 'tickets/'.$ticket->id.'/'.str()->uuid().'.'.$file->extension();
                    $stored = Storage::disk('local')->putFileAs('', $file, $path);

                    if ($stored === false) {
                        throw new \RuntimeException('Não foi possível salvar o anexo.');
                    }

                    $storedPaths[] = $path;

                    return $ticket->attachments()->create([
                        'uploaded_by' => $request->user()->id,
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                });
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }

        return response()->json(['data' => TicketAttachmentResource::collection($attachments)], 201);
    }

    public function download(Request $request, TicketAttachment $attachment)
    {
        Gate::authorize('view', $attachment->ticket);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
