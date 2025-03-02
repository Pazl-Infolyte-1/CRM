<?php

namespace Modules\Performance\Http\Controllers;

use App\Helper\Reply;
use Illuminate\Http\Request;
use App\Http\Controllers\AccountBaseController;
use App\Models\EmployeeDetails;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Performance\Entities\GoalType;
use Modules\Performance\Entities\Objective;
use Modules\Performance\Entities\PerformanceSetting;

class DashboardController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'performance::app.dashboard';
        $this->objectiveProgress = 'performance::app.objectiveProgress';

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
        $this->objectives = Objective::all();
        $this->currentCurrencyId = $this->company->currency_id;

        $this->startDate = (request('startDate') != '') ? Carbon::createFromFormat($this->company->date_format, request('startDate')) : now($this->company->timezone)->subMonths(3);
        $this->endDate = (request('endDate') != '') ? Carbon::createFromFormat($this->company->date_format, request('endDate')) : now($this->company->timezone);

        return view('performance::dashboard.index', $this->data);
    }

    public function objectiveChartData(Request $request)
    {
        $startDate = $request->startDate ? companyToDateString($request->startDate) : now($this->company->timezone)->startOfMonth()->toDateString();
        $endDate = $request->endDate ? companyToDateString($request->endDate) : now($this->company->timezone)->toDateString();

        $objectives = Objective::with(['status', 'goalType', 'keyResults.checkIns' => function ($query) use ($startDate, $endDate) {
                // $query->where('check_in_date', '>=', $startDate)
                //     ->orWhere('check_in_date', '<=', $endDate)
                    // $query->whereDate('check_in_date', '>=', $startDate)
                    //     ->orwhereDate('check_in_date', '<=', $endDate)

                    $startDate = Carbon::parse($startDate)->copy()->addDays(1);
                    $endDate = Carbon::parse($endDate)->copy()->addDays(1);

                    $query->whereBetween('check_in_date', [$startDate, $endDate])
                        ->select('key_result_id', 'objective_percentage', 'check_in_date');
            }])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
                // $query->whereBetween('start_date', [$startDate, $endDate])
                    // ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->orderBy('start_date', 'ASC')
            ->get([
                'id', 'title', 'goal_type', 'start_date', 'end_date', 'rotation_date',
                DB::raw('DATE_FORMAT(start_date, "%d-%M-%y") as start_date_formatted'),
                DB::raw('YEAR(start_date) as year, MONTH(start_date) as month'),
                DB::raw('DATE_FORMAT(end_date, "%d-%M-%y") as end_date_formatted')
            ]);

        // Filter objectives based on user access
        $objectives = $objectives->filter(function ($objective) {
            $objective->has_access = !$this->checkManageAccess($objective->id);
            return !$this->checkViewAccess($objective->id);
        });

        // Count objectives by status
        $objectivesForCount = $objectives;
        $statusCounts = $objectivesForCount->groupBy('status.status')->map->count();
        $counts = [
            'total' => $objectivesForCount->count(),
            'onTrack' => $statusCounts->get('onTrack', 0),
            'offTrack' => $statusCounts->get('offTrack', 0),
            'atRisk' => $statusCounts->get('atRisk', 0),
            'completed' => $statusCounts->get('completed', 0),
        ];

        // Aggregate all check-in dates for axis
        $allCheckInDates = collect();
        $data = [];
        $colors = $this->generateRandomColors(count($objectives));

        foreach ($objectives as $objective) {

            $objectiveCheckIns = $objective->keyResults->flatMap(function ($keyResult) {
                return $keyResult->checkIns->map(fn($checkIn) => [
                    'date' => Carbon::parse($checkIn->check_in_date)->format('Y-m-d H:i'),
                    'percentage' => $checkIn->objective_percentage,
                ]);
            });

            $objectiveCheckIns = $objectiveCheckIns
                ->groupBy('date')
                ->map(fn($group) => $group->sortByDesc('percentage')->last())
                ->sortBy('date');

            $allCheckInDates->push(Carbon::parse($objective->start_date)->format('Y-m-d H:i'));
            $allCheckInDates = $allCheckInDates->merge($objectiveCheckIns->pluck('date'))->unique()->sort()->values();

            $dataPoints = [
                ['x' => Carbon::parse($objective->start_date)->format('Y-m-d H:i'), 'y' => 0]
            ];

            foreach ($objectiveCheckIns as $checkIn) {
                $dataPoints[] = ['x' => $checkIn['date'], 'y' => $checkIn['percentage'] ?? 0];
            }

            $data[] = [
                'name' => str()->limit($objective->title, 12),
                'data' => $dataPoints,
                'start_date' => Carbon::parse($objective->start_date)->format('Y-m-d H:i'),
                'end_date' => Carbon::parse($objective->end_date)->format('Y-m-d H:i'),
            ];
        }

        $this->chartData = $data;
        $this->colors = $colors;
        $this->allDates = $allCheckInDates->values()->all();

        $html = view('performance::dashboard.chart', $this->data)->render();
        $html2 = view('performance::dashboard.counts', $counts)->render();

        return Reply::dataOnly(['status' => 'success', 'html' => $html, 'html2' => $html2, 'chartData' => count($this->chartData), 'title' => $this->pageTitle]);
    }

    protected function checkViewAccess($id)
    {
        $objective = Objective::with('owners')->findOrFail($id);
        $ownerIds = $objective->owners->pluck('id')->toArray();
        $goal = GoalType::find($objective->goal_type);

        $managerIds = EmployeeDetails::whereNotNull('reporting_to')
            ->whereIn('user_id', $ownerIds)
            ->pluck('reporting_to')
            ->toArray();

        $currentUserRoleIds = user()->roles()->pluck('id')->toArray();
        $viewByRoles = json_decode($goal->view_by_roles, true) ?? [];

        return !(($goal && $goal->view_by_owner == 1 && in_array(user()->id, $ownerIds)) ||
            ($goal && $goal->view_by_manager == 1 && in_array(user()->id, $managerIds)) ||
            (!empty($viewByRoles) && array_intersect($currentUserRoleIds, $viewByRoles)) ||
            user()->hasRole('admin') || $objective->created_by == user()->id);
    }

    protected function checkManageAccess($id)
    {
        $objective = Objective::with('owners')->findOrFail($id);
        $ownerIds = $objective->owners->pluck('id')->toArray();
        $goal = GoalType::find($objective->goal_type);

        $managerIds = EmployeeDetails::whereNotNull('reporting_to')
            ->whereIn('user_id', $ownerIds)
            ->pluck('reporting_to')
            ->toArray();

        $currentUserRoleIds = user()->roles()->pluck('id')->toArray();
        $manageByRoles = json_decode($goal->manage_by_roles, true) ?? [];

        return !(user()->hasRole('admin') ||
            $objective->created_by == user()->id ||
            ($goal && $goal->manage_by_owner == 1 && in_array(user()->id, $ownerIds)) ||
            ($goal && $goal->manage_by_manager == 1 && in_array(user()->id, $managerIds)) ||
            (!empty($manageByRoles) && array_intersect($currentUserRoleIds, $manageByRoles)));
    }

    protected function generateRandomColors($count)
    {
        $colors = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate random HSL values
            // Hue: 0-360, Saturation: 60-90%, Lightness: 45-65%
            $hue = rand(0, 360);
            $saturation = rand(60, 90);
            $lightness = rand(45, 65);

            // Convert HSL to RGB
            $h = $hue / 360;
            $s = $saturation / 100;
            $l = $lightness / 100;

            if ($s == 0) {
                $r = $g = $b = $l;
            }
            else {
                $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
                $p = 2 * $l - $q;

                $r = $this->hue2rgb($p, $q, $h + 1 / 3);
                $g = $this->hue2rgb($p, $q, $h);
                $b = $this->hue2rgb($p, $q, $h - 1 / 3);
            }

            // Convert RGB to hex
            $hex = sprintf("#%02x%02x%02x",
                round($r * 255),
                round($g * 255),
                round($b * 255)
            );

            $colors[] = $hex;
        }

        return $colors;
    }

    protected function hue2rgb($p, $q, $t)
    {
        if ($t < 0) {
            $t += 1;
        }

        if ($t > 1) {
            $t -= 1;
        }

        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }

        if ($t < 1 / 2) {
            return $q;
        }

        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2/3 - $t) * 6;
        }

        return $p;
    }

}
