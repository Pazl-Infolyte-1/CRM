<div class="modal-header">
    <h5 class="modal-title"><i class="fas fa-comment-alt mr-2"></i> @lang('performance::app.editDiscussionPoint')</h4>
    </h5>
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
</div>

<x-form id="update-discussion-form">
    @method('PUT')
    <div class="modal-body">
        <div class="portlet-body"></div>
        <div class="row">
            <input type="hidden" name="send_mail" id="send_mail" value="no">
            <input type="hidden" name="tab" id="tab" value="list">
            <input type="hidden" name="meeting_id" id="meeting_id" value="{{ $agenda->meeting_id }}">
            <div class="col-md-12">
                <x-forms.text fieldId="discussion_point" :fieldLabel="__('performance::app.discussionPoint')" :fieldValue="$agenda->discussion_point" fieldName="discussion_point" :fieldRequired="true" :fieldPlaceholder="__('performance::app.discussionPoint')"></x-forms.text>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <x-forms.button-cancel data-dismiss="modal" class="border-0 mr-3">@lang('app.close')</x-forms.button-cancel>
        <x-forms.button-primary id="update-discussion" icon="check">@lang('app.save')</x-forms.button-primary>
    </div>
</x-form>

<script>
    $('#update-discussion').click(function() {
        let id = "{{ $agenda->id }}";
        var url = "{{ route('agenda.update', ':id') }}";
        url = url.replace(':id', id);

        if (url) {
            $.easyAjax({
                url: url,
                container: '#update-discussion-form',
                type: "POST",
                disableButton: true,
                blockUI: true,
                data: $('#update-discussion-form').serialize(),
                success: function(response) {
                    if (response.status == "success") {

                        $('#nav-tabContent').html('');
                        $('#nav-tabContent').html(response.html);

                        $.easyUnblockUI();
                        $(MODAL_LG).modal('hide');
                    }
                }
            });
        }
    });
</script>
