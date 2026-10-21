<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AddTicketComment
{
    public function __construct(private TransitionTicketStatus $transition) {}

    /** @param array<int, UploadedFile> $files */
    public function handle(Ticket $ticket, User $author, string $body, array $files = []): TicketComment
    {
        if ($ticket->status === TicketStatus::Closed) {
            throw ValidationException::withMessages(['ticket' => 'Chamado fechado não aceita novos comentários.']);
        }

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($ticket, $author, $body, $files, &$storedPaths): TicketComment {
                $comment = $ticket->comments()->create(['user_id' => $author->id, 'body' => $body]);

                foreach ($files as $file) {
                    $path = 'tickets/'.$ticket->id.'/'.str()->uuid().'.'.$file->extension();
                    $stored = Storage::disk('local')->putFileAs('', $file, $path);

                    if ($stored === false) {
                        throw new \RuntimeException('Não foi possível salvar o anexo.');
                    }

                    $storedPaths[] = $path;
                    $comment->attachments()->create([
                        'ticket_id' => $ticket->id,
                        'uploaded_by' => $author->id,
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }

                if ($author->role === UserRole::Customer && $ticket->status === TicketStatus::WaitingCustomer) {
                    $this->transition->handle($ticket, TicketStatus::InProgress);
                }

                return $comment->load(['author', 'attachments']);
            });
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }
    }
}
