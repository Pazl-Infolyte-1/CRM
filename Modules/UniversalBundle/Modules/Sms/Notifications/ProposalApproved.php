<?php

namespace Modules\Sms\Notifications;

use App\Models\Proposal;
use App\Models\ProposalSign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;
use Modules\Sms\Entities\SmsNotificationSetting;
use Modules\Sms\Http\Traits\WhatsappMessageTrait;
use NotificationChannels\Telegram\TelegramMessage;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;

class ProposalApproved extends Notification implements ShouldQueue
{
    use Queueable, WhatsappMessageTrait;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    private $smsSetting;

    private $message;

    private $proposal;

    private $proposalSign;

    private $company;

    public function __construct(Proposal $proposal)
    {
        $this->proposal = $proposal;
        $this->proposalSign = ProposalSign::where('proposal_id', $this->proposal->id)->first();

        $this->company = $this->proposal->company;
        $this->smsSetting = SmsNotificationSetting::where('slug', 'proposal-approved')->where('company_id', $this->company->id)->first();

    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        if ($this->smsSetting && $this->smsSetting->send_sms != 'yes') {
            return [];
        }

        $this->message = __('email.proposalSigned.subject')."\n".__('email.proposalSigned.approve').' : '.$this->proposalSign->full_name."\n".__('app.status').' : '.$this->proposal->status;

        $via = [];

        if (! is_null($notifiable->mobile) && ! is_null($notifiable->country_phonecode)) {
            if (sms_setting()->status) {
                array_push($via, TwilioChannel::class);
            }

            if (sms_setting()->nexmo_status) {
                array_push($via, 'vonage');
            }

            if ($this->smsSetting->msg91_flow_id && sms_setting()->msg91_status) {
                array_push($via, 'msg91');
            }
        }

        if (sms_setting()->telegram_status && $notifiable->telegram_user_id) {
            array_push($via, 'telegram');
        }

        return $via;
    }

    public function toTwilio($notifiable)
    {
        $this->toWhatsapp(
            $this->smsSetting->slug,
            $notifiable,
            __($this->smsSetting->slug->translationString(), ['name' => $this->proposalSign->full_name, 'status' => $this->proposal->status]),
            ['1' => $this->proposalSign->full_name, '2' => $this->proposal->status]
        );

        if (sms_setting()->status) {
            return (new TwilioSmsMessage)
                ->content($this->message);
        }
    }

    //phpcs:ignore
    public function toVonage($notifiable)
    {

        if (sms_setting()->nexmo_status) {
            return (new VonageMessage)
                ->content($this->message)->unicode();
        }
    }

    //phpcs:ignore
    public function toMsg91($notifiable)
    {
        if ($this->smsSetting->msg91_flow_id && sms_setting()->msg91_status) {
            return (new \Craftsys\Notifications\Messages\Msg91SMS)
                ->flow($this->smsSetting->msg91_flow_id)
                ->variable('name', $this->proposalSign->full_name)
                ->variable('status', $this->proposal->status);
        }
    }

    public function toTelegram($notifiable)
    {

        return TelegramMessage::create()
            // Optional recipient user id.
            ->to($notifiable->telegram_user_id)
            // Markdown supported.
            ->content($this->message)
            ->button(__('app.view').' '.__('app.proposal'), route('front.proposal', $this->proposal->hash));
    }
}
