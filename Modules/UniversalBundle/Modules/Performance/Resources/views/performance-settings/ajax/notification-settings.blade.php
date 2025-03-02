<div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4">
    @method('PUT')
    <div class="row">
        <div class="col-md-12">
            <x-alert type="info" icon="info-circle">
                @lang('performance::app.sendNotificationsNote')
            </x-alert>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="form-group mb-4">
                <x-forms.label fieldId="send_notification" :fieldLabel="__('performance::app.sendNotifications')">
                </x-forms.label>
                <div class="custom-control custom-switch">
                    <input type="checkbox" @if ($settings->send_notification == 'yes') checked @endif
                        class="custom-control-input change-notification-setting" data-setting-id="{{ $settings->id }}" id="send_notification" name="send_notification" value="yes">
                    <label class="custom-control-label cursor-pointer" for="send_notification"></label>
                </div>
            </div>
        </div>
    </div>
</div>
