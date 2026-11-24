<?php

namespace App\Mail;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AnnouncementPublished extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Announcement $announcement)
    {
    }

    public function build(): self
    {
        $mail = $this->subject($this->announcement->title)
            ->view('emails.announcement-published', [
                'announcement' => $this->announcement,
                'announcementUrl' => route('announcements.show', $this->announcement, true),
            ]);

        if ($this->announcement->photo_path) {
            $mail->attachFromStorageDisk(
                'local',
                $this->announcement->photo_path,
                'announcement-image.'.pathinfo($this->announcement->photo_path, PATHINFO_EXTENSION),
            );
        }

        return $mail;
    }
}
