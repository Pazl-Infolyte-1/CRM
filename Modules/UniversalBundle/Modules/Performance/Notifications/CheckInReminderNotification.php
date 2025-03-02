<?php

namespace Modules\Performance\Notifications;

use App\Models\EmailNotificationSetting;
use App\Notifications\BaseNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class CheckInReminderNotification extends BaseNotification
{

    use Queueable;

    protected $objective;
    private $emailSetting;

    /**
     * Create a new notification instance.
     */
    public function __construct($objective)
    {
        $this->objective = $objective;
        $this->company = $objective->company;
        $this->emailSetting = EmailNotificationSetting::where('company_id', $this->company->id)->first();
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $via = ['database'];

        if ($this->emailSetting->send_email == 'yes' && $notifiable->email_notifications && $notifiable->email != '') {
            array_push($via, 'mail');
        }

        if ($this->emailSetting->send_slack == 'yes' && $this->company->slackSetting->status == 'active') {
            $this->slackUserNameCheck($notifiable) ? array_push($via, 'slack') : null;
        }

        if ($this->emailSetting->send_push == 'yes' && push_setting()->status == 'active') {
            array_push($via, OneSignalChannel::class);
        }

        return $via;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $build = parent::build($notifiable);
        $url = route('objectives.show', $this->objective->id);
        $url = getDomainSpecificUrl($url, $this->company);

        $subject = '📅 ' . __('performance::email.checkin.subject') . ' ' . $this->objective->title;

        $startDate = $this->objective->start_date ? Carbon::parse($this->objective->start_date)->format($this->company->date_format) : '--';
        $endDate = $this->objective->end_date ? Carbon::parse($this->objective->end_date)->format($this->company->date_format) : '--';

        $content = __('performance::email.checkin.title') . ' <b>' . $this->objective->title . '</b><br><br>' .
            '📅 <b>' . __('performance::email.objective.startDate') . ':</b> ' . $startDate . '<br><br>' .
            '📅 <b>' . __('performance::email.objective.endDate') . ':</b> ' . $endDate . '<br><br>' .
            '🔍 <b>' . __('performance::email.checkin.description') . '</b><br><br> ' . $this->objective->description . '<br>' .
            '<br>' . __('performance::email.checkin.text') . ' 💪 <br><br>' .
            __('performance::email.checkin.note') . ' 🌟 <br>';

        $build
            ->subject($subject)
            ->markdown('mail.email', [
                'url' => $url,
                'content' => $content,
                'actionText' => __('performance::email.checkin.checkInNow'),
                'themeColor' => $this->company->header_color,
                'notifiableName' => $notifiable->name
            ]);

        parent::resetLocale();

        return $build;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'id' => $this->objective->id,
            'heading' => $this->objective->title,
            'created_at' => $this->objective->created_at ?? Carbon::parse($this->objective->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    public function toSlack($notifiable)
    {
        try {
            $subject = '📅 ' . __('performance::email.checkin.subject') . ' ' . $this->objective->title;
            $greeting = '👋 *' . __('performance::email.objective.hi') . ' ' . $notifiable->name . '*,';

            $startDate = $this->objective->start_date ? Carbon::parse($this->objective->start_date)->format($this->company->date_format) : '--';
            $endDate = $this->objective->end_date ? Carbon::parse($this->objective->end_date)->format($this->company->date_format) : '--';

            $content = __('performance::email.checkin.title') . ' *' . $this->objective->title . '*' . "\n\n" .
            '🔍 *' . __('performance::email.checkin.description') . '* ' . "\n\n" . $this->objective->description . "\n\n" .
            '📅 *' . __('performance::email.objective.startDate') . ': * ' . $startDate . "\n\n" .
            '📅 *' . __('performance::email.objective.endDate') . ': * ' . $endDate . "\n\n" .
            __('performance::email.checkin.text') . ' 💪' . "\n\n";

            $url = route('objectives.show', $this->objective->id);
            $url = getDomainSpecificUrl($url, $this->company);
            $url = '👉 ' . '<'.$url.'|' . __('performance::email.checkin.checkInNow') . '>' . ' ' . __('performance::email.checkin.note') . ' 🌟' . "\n\n";

            return $this->slackBuild($notifiable)
                ->content($subject . "\n\n" . $greeting . "\n\n" . $content . "\n\n" . $url);
        }
        catch (\Exception $e) {
            return $this->slackRedirectMessage('performance::email.checkin.subject', $notifiable);
        }
    }

    public function toOneSignal()
    {
        return OneSignalMessage::create()
            ->setSubject(__('performance::email.checkin.subject'))
            ->setBody($this->objective->title);
    }

}
