@extends('layouts.app')

@push('styles')
    @include('sections.daterange_css')
@endpush

@section('filter-section')
    <x-filters.filter-box>
        <!-- DATE START -->
        <div class="select-box d-flex pr-2 border-right-grey border-right-grey-sm-0 performance-dashboard">
            <p class="mb-0 pr-2 f-14 text-dark-grey d-flex align-items-center">@lang('app.duration')</p>
            <div class="select-status d-flex">
                <input type="text" class="position-relative text-dark form-control border-0 p-2 text-left f-14 f-w-500 border-additional-grey"
                    id="datatableRange2" placeholder="@lang('placeholders.dateRange')">
            </div>
        </div>
        <!-- DATE END -->

        <!-- RESET START -->
        <div class="select-box d-flex py-1 px-lg-2 px-md-2 px-0">
            <x-forms.button-secondary class="btn-xs d-none" id="reset-filters" icon="times-circle">
                @lang('app.clearFilters')
            </x-forms.button-secondary>
        </div>
        <!-- RESET END -->

    </x-filters.filter-box>
@endsection

@section('content')
    <!-- CONTENT WRAPPER START -->
    <div class="content-wrapper">
        <div class="row">
            <div class="col-md-12" id="objective-chart-card">
            </div>
            <div class="col-md-12">
                <x-cards.data id="progress" :title="__('performance::app.objectiveProgress')">
                </x-cards.data>
            </div>
        </div>

        <!-- Task Box Start -->
        <div class="d-flex flex-column w-tables rounded mt-4 bg-white table-responsive">
            {{-- table --}}
        </div>
        <!-- Task Box End -->
    </div>
    <!-- CONTENT WRAPPER END -->

@endsection

@push('scripts')
<script src="{{ asset('vendor/jquery/frappe-charts.min.iife.js') }}"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="{{ asset('vendor/jquery/daterangepicker.min.js') }}"></script>

<script>
    $(function () {
        var format = '{{ company()->moment_date_format }}';
        var startDate = "{{ $startDate->format(company()->date_format) }}";
        var endDate = "{{ $endDate->format(company()->date_format) }}";
        var start = moment(startDate, format);
        var end = moment(endDate, format);

        $('#datatableRange2').daterangepicker({
            locale: daterangeLocale,
            linkedCalendars: false,
            startDate: start,
            endDate: end,
            ranges: daterangeConfig,
            opens: 'right',
            parentEl: '.dashboard-header'
        }, cb);

        $('#datatableRange2').on('apply.daterangepicker', function (ev, picker) {
            lineChart();
        });

        function lineChart() {
            var dateRangePicker = $('#datatableRange2').data('daterangepicker');
            var startDate = dateRangePicker.startDate.format(format);
            var endDate = dateRangePicker.endDate.format(format);

            if ($('#datatableRange2').val() == '') {
                startDate = null;
                endDate = null;
            }

            var url = "{{ route('performance-dashboard.chart') }}";

            $.easyAjax({
                url: url,
                container: '#progress',
                blockUI: true,
                type: "POST",
                data: {
                    startDate: startDate,
                    endDate: endDate,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    $('#progress .card-body').html(response.html);
                    $('#objective-chart-card').html(response.html2);

                    if (!response.chartData) {
                        $('#progress .card-body').html(
                            "<div class='d-flex justify-content-center p-20'>{{ __('messages.noRecordFound') }}</div>"
                        );
                    }

                    return;
                }
            });
        }

        lineChart();

        $('#reset-filters').click(function() {
            // Reset the date picker to original start and end dates
            $('#datatableRange2').data('daterangepicker').setStartDate(start);
            $('#datatableRange2').data('daterangepicker').setEndDate(end);

            // Reset the filter UI
            $('.filter-box .select-picker').selectpicker("refresh");
            $('#reset-filters').addClass('d-none');

            // Trigger the line chart update with the reset dates
            lineChart();
        });

        $(body).on('click', '.toObjectives', function () {
            var dateRangePicker = $('#datatableRange2').data('daterangepicker');
            var startDate = dateRangePicker.startDate.format(format);
            var endDate = dateRangePicker.endDate.format(format);
            var status = $(this).data('status');

            if ($('#datatableRange2').val() == '') {
                startDate = null;
                endDate = null;
            }

            let url = "{{ route('objectives.index') }}?status=" + status + "&startDate=" + startDate + "&endDate=" + endDate;
            window.location.href = url;
        });

    });
</script>

@endpush
