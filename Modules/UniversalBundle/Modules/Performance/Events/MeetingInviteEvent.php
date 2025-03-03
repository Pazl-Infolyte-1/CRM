<?php

namespace Modules\Performance\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Performance\Entities\Meeting;

class MeetingInviteEvent
{

    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $meeting;
    public $notifyUser;

    public function __construct(Meeting $meeting, $notifyUser)
    {
        $this->meeting = $meeting;
        $this->notifyUser = $notifyUser;
    }

}
