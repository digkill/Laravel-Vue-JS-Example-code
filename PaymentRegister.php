<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\QueryFilters\Analytics\PaymentRegisterFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class PaymentRegister
{
    /**
     * CityController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:admin-api');
        $this->middleware('role:root|boss');
    }

    /**
     * All cities
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $filters = PaymentRegisterFilter::hydrate($request->query());
        $user = auth()->user();
        $model = Invoice::filterBy($filters)
            ->leftjoin('users', 'users.id', 'invoices.user_id')
            ->join('schools', 'schools.id', 'users.school_id')
            ->select(
                'users.name',
                'users.username',
                'schools.title AS school_title',
                DB::raw('(
                    SELECT groups.title
                    FROM groups
                    LEFT JOIN schedules ON schedules.group_id = groups.id
                    LEFT JOIN invoice_schedule ON invoice_schedule.schedule_id = schedules.id
                    WHERE invoice_schedule.invoice_id = invoices.id
                    LIMIT 1
                    ) AS group_title'),
                DB::raw('(
                    SELECT groups.id
                    FROM groups
                    LEFT JOIN schedules ON schedules.group_id = groups.id
                    LEFT JOIN invoice_schedule ON invoice_schedule.schedule_id = schedules.id
                    WHERE invoice_schedule.invoice_id = invoices.id
                    LIMIT 1
                    ) AS group_id'),
                'invoices.created_at',
                'invoices.pay_at',
                'invoices.payment_type',
                'invoices.pay_amount',
                'invoices.response_amount',
                'invoices.user_id',
                'invoices.data',
                'invoices.service_name',
                DB::raw('(
                    SELECT courses.title
                    FROM groups
                    LEFT JOIN schedules ON schedules.group_id = groups.id
                    LEFT JOIN invoice_schedule ON invoice_schedule.schedule_id = schedules.id
                    LEFT JOIN courses ON groups.course_id = courses.id
                    WHERE invoice_schedule.invoice_id = invoices.id
                    LIMIT 1
                    ) AS courses_title'),
                DB::raw('(
                    SELECT courses.id
                    FROM groups
                    LEFT JOIN schedules ON schedules.group_id = groups.id
                    LEFT JOIN invoice_schedule ON invoice_schedule.schedule_id = schedules.id
                    LEFT JOIN courses ON groups.course_id = courses.id
                    WHERE invoice_schedule.invoice_id = invoices.id
                    LIMIT 1
                    ) AS course_id'),
                DB::raw('(
                    SELECT admins.name
                    FROM admins
                    WHERE admins.franchise_id = invoices.franchise_id
                    LIMIT 1
                    ) AS admins_name'),
                DB::raw('DATE_FORMAT(invoices.pay_at, "%d.%m.%Y") as date_pay')
            )
            ->where(function ($q) {
                $q->where('payment_type', Invoice::PAYMENT_TYPE_CASH)
                    ->orWhereNotNull('service_payment_id');
            })
            ->whereNotNull('pay_at')
            ->where('invoices.payment_type', '!=', Invoice::PAYMENT_TYPE_ADD_BALANCE)
            ->where('invoices.status', '!=', Invoice::PAYMENT_TYPE_MOVE)
            ->where('invoices.status', '!=', Invoice::PAYMENT_TYPE_RETURNING)
            ->orderBy('invoices.pay_at', 'DESC');

        if ($user->level < (int)config('level.root')) {
            $model = $model->where('users.franchise_id', $user->franchise_id);
        }

        return DataTables::eloquent($model)
            ->editColumn('payment_type', function ($data) {
                if ($data->payment_type === 1) {
                    return 'Б/Н';
                } elseif ($data->payment_type === 2) {
                    return 'Наличные';
                }
                return 'n/a';
            })
            ->addColumn('lessons_old', function ($data) {
                $dataOld = json_decode($data->data, true);
                return $dataOld['lessons'] ?? '';
            })
            ->addColumn('group_old', function ($data) {
                $dataOld = json_decode($data->data, true);
                return $dataOld['group'] ?? '';
            })

            ->toJson();
    }

    public function counts(Request $request)
    {
        $filters = PaymentRegisterFilter::hydrate($request->query());

        $user = auth()->user();
        $model = Invoice::filterBy($filters)
            ->leftjoin('users', 'users.id', 'invoices.user_id')
            ->join('schools', 'schools.id', 'users.school_id')
            ->select(
                DB::raw('invoices.id'),
                DB::raw('invoices.pay_amount'),
                DB::raw('invoices.pay_at'),
                DB::raw('(
                    SELECT courses.id
                    FROM groups
                    LEFT JOIN schedules ON schedules.group_id = groups.id
                    LEFT JOIN invoice_schedule ON invoice_schedule.schedule_id = schedules.id
                    LEFT JOIN courses ON groups.course_id = courses.id
                    WHERE invoice_schedule.invoice_id = invoices.id
                    LIMIT 1
                    ) AS course_id'),
                DB::raw('(
                    SELECT groups.id
                    FROM groups
                    LEFT JOIN schedules ON schedules.group_id = groups.id
                    LEFT JOIN invoice_schedule ON invoice_schedule.schedule_id = schedules.id
                    WHERE invoice_schedule.invoice_id = invoices.id
                    LIMIT 1
                    ) AS group_id'),
                DB::raw('DATE_FORMAT(invoices.pay_at, "%d.%m.%Y") as date_pay')
            )
            ->where(function ($q) {
                $q->where('payment_type', Invoice::PAYMENT_TYPE_CASH)
                    ->orWhereNotNull('service_payment_id');
            })
            ->whereNotNull('pay_at')
            ->where('payment_type', '!=', Invoice::PAYMENT_TYPE_ADD_BALANCE)
            ->where('status', '!=', Invoice::PAYMENT_TYPE_MOVE)
            ->where('status', '!=', Invoice::PAYMENT_TYPE_RETURNING)
            ->orderBy('invoices.pay_at', 'ASC');

        if ($user->level < (int)config('level.root')) {
            $model = $model->where('users.franchise_id', $user->franchise_id);
        }

        return DataTables::eloquent($model)->toJson();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function dateRange(Request $request)
    {
        $dateArray = [];
        if (!empty($request->get('date_to'))) {
            $dateFrom = \DateTime::createFromFormat('Y-m-d', mb_substr($request->get('date_from'), 0, 10));
            $dateTo = \DateTime::createFromFormat('Y-m-d', mb_substr($request->get('date_to'), 0, 10));
            while ($dateFrom <= $dateTo) {
                $dateArray[] = $dateFrom->format('d.m.Y');
                $dateFrom->modify('+1 day');
            }
        }
        return response()->json(['data' => $dateArray], 200);
    }

}
