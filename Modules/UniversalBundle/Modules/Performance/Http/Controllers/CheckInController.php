<?php

namespace Modules\Performance\Http\Controllers;

use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Entities\CheckIn;
use Modules\Performance\Entities\KeyResults;
use Modules\Performance\Entities\Objective;
use Modules\Performance\Entities\ObjectiveProgressStatus;
use Modules\Performance\Entities\PerformanceSetting;
use Modules\Performance\Http\Requests\CreateCheckInRequest;

class CheckInController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'performance::app.checkIns';

        $this->middleware(function ($request, $next) {
            abort_403(!in_array(PerformanceSetting::MODULE_NAME, $this->user->modules));
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        abort(404);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->pageTitle = __('performance::app.addCheckIns');
        $this->confidenceLevels = ['low' => __('app.low'), 'medium' => __('app.medium'), 'high' => __('app.high')];
        $keyResultId = request()->keyResultId;
        $this->keyResults = KeyResults::all();
        $this->keyResultId  = [];

        if ($keyResultId) {
            $this->keyResultId = KeyResults::findOrFail($keyResultId);
        }

        return view('performance::check-ins.ajax.create', $this->data);
    }

    public function calculateProgressPercentage($currentValue, $originalCurrentValue, $targetValue)
    {
        // If current value equals the target value, return 100%
        if ($currentValue == $targetValue) {
            return 100.00;
        }

        // If current value equals the original current value, return 0%
        if ($currentValue == $originalCurrentValue) {
            return 0.00;
        }

        // If current value is less than the target value
        if ($currentValue < $targetValue && $targetValue > $originalCurrentValue) {
            // Calculate progress based on original value
            return round((($currentValue - $originalCurrentValue) / ($targetValue - $originalCurrentValue)) * 100, 2);
        }

        // If current value is between the target and original current value, calculate progress
        if ($currentValue > $targetValue && $currentValue < $originalCurrentValue) {
            return round((($originalCurrentValue - $currentValue) / ($originalCurrentValue - $targetValue)) * 100, 2);
        }

        // If current value is greater than the target value, return 100%
        if ($currentValue > $targetValue || ($currentValue < $targetValue && $targetValue < $originalCurrentValue)) {
            return 100.00;
        }

        // If current value is greater than the original current value, return 0%
        return 0.00;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateCheckInRequest $request)
    {
        $checkIn = new CheckIn();
        $checkIn->key_result_id = $request->key_result_id;
        $checkIn->progress_update = trim_editor($request->progress_update);
        $checkIn->current_value = $request->current_value;
        $checkIn->confidence_level = $request->confidence_level;
        $checkIn->barriers = $request->barriers ? trim_editor($request->barriers) : null;

        $date = $request->check_in_date ? Carbon::createFromFormat(company()->date_format, $request->check_in_date)->format('Y-m-d') : now()->format('Y-m-d');
        $time = $request->time ? Carbon::createFromFormat($this->company->time_format, $request->time)->format('H:i:s') : now()->format('H:i:s');

        $date_time = $date.' '.$time;
        $checkIn->check_in_date = $date_time;

        $keyResult = KeyResults::findOrFail($request->key_result_id);
        $keyResult->current_value = $request->current_value;
        $keyResult->last_check_in = $date_time ? Carbon::parse($date_time)->format('Y-m-d') : now()->format('Y-m-d');
        $keyResult->save();

        if ($keyResult->objective_id)
        {
            $keyResults = $keyResult::where('objective_id', $keyResult->objective_id);
            $totalKeyPercent = $keyResults->sum('key_percentage');
            $totalKeyResult = $keyResults->count();

            $objectivePercent = $totalKeyPercent / $totalKeyResult;
            $checkIn->objective_percentage = $objectivePercent;

            $objectiveProgress = ObjectiveProgressStatus::where('objective_id', $keyResult->objective_id)->first();
            $objectiveProgress->objective_progress = $objectivePercent;
            $objectiveProgress->save();
        }

        $checkIn->save();

        return Reply::success(__('messages.recordSaved'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $this->pageTitle = __('performance::app.editCheckIns');
        $this->keyResults = KeyResults::all();
        $this->confidenceLevels = ['low' => __('app.low'), 'medium' => __('app.medium'), 'high' => __('app.high')];
        $this->checkIn = CheckIn::with('keyResult')->findOrFail($id);

        return view('performance::check-ins.ajax.edit', $this->data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreateCheckInRequest $request, $id)
    {
        DB::beginTransaction();

        $checkIn = CheckIn::findOrFail($id);
        $checkIn->key_result_id = $request->key_result_id;
        $checkIn->confidence_level = $request->confidence_level;
        $checkIn->progress_update = trim_editor($request->progress_update);
        $checkIn->barriers = $request->barriers ? trim_editor($request->barriers) : null;
        $date_time = Carbon::createFromFormat(company()->date_format, $request->check_in_date)->format('Y-m-d') . ' ' . Carbon::createFromFormat($this->company->time_format, $request->time)->format('H:i:s');
        $checkIn->check_in_date = $date_time;

        $keyResult = KeyResults::findOrFail($request->key_result_id);

        if ($keyResult->current_value == $checkIn->current_value) {
            $keyResult->current_value = $request->current_value;
        }

        $keyResult->save();

        $checkIn->current_value = $request->current_value;

        if ($keyResult->objective_id)
        {
            $keyResults = $keyResult::where('objective_id', $keyResult->objective_id);
            $totalKeyPercent = $keyResults->sum('key_percentage');
            $totalKeyResult = $keyResults->count();

            $objectivePercent = $totalKeyPercent / $totalKeyResult;
            $checkIn->objective_percentage = $objectivePercent;

            $objectiveProgress = ObjectiveProgressStatus::where('objective_id', $keyResult->objective_id)->first();
            $objectiveProgress->objective_progress = $objectivePercent;
            $objectiveProgress->save();
        }

        $checkIn->save();

        DB::commit();

        return Reply::success(__('messages.updateSuccess'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $checkIn = CheckIn::findOrFail($id);

        if ($checkIn) {
            $notifyData = ['Modules\Performance\Notifications\CheckInReminderNotification'];
            \App\Models\Notification::deleteNotification($notifyData, $checkIn->id);

            $checkIn->delete();
            return Reply::success(__('messages.deleteSuccess'));
        }

        return Reply::error(__('performance::messages.checkInsNotFound'));
    }

}
