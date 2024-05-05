<?php

namespace App\Http\Controllers;

use App\Models\Control;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActionplanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        abort_if(
            ! ((Auth::User()->role === 1) || (Auth::User()->role === 2)),
            Response::HTTP_FORBIDDEN,
            '403 Forbidden'
        );

        // Build query
        $actions = DB::table('controls as c1')
            ->leftjoin('controls as c2', 'c1.next_id', '=', 'c2.id');

        // filter on auditee controls
        if (Auth::User()->role === 5) {
            $actions = $actions
                ->leftjoin('control_user', 'c1.id', '=', 'control_user.control_id')
                ->where('control_user.user_id', '=', Auth::User()->id);
        }

        // filter on scores that are red or orange
        $actions = $actions
            ->where(function ($query) {
                $query->where('c1.score', '=', 1)
                    ->orWhere('c1.score', '=', 2);
            });

        // filter on not yet realised next control
        $actions = $actions
            // ->whereNull('c2.realisation_date');
            ->whereIn('c2.status', [0,1]);

        // Query DB
        $actions = $actions->select(
            [
                'c1.measure_id',
                'c1.id',
                'c1.clause',
                'c1.action_plan',
                'c1.score',
                'c1.name',
                'c1.scope',
                'c1.plan_date',
                'c2.id as next_id',
                'c2.plan_date as next_date',
            ]
        )
            ->orderBy('c1.realisation_date')->get();

        // return
        return view('actions.index')
            ->with('actions', $actions);
    }

    /**
     * Save an action plan
     *
     * @param  \App\Domain $domain
     *
     * @return \Illuminate\Http\Response
     */
    public function save(Request $request)
    {
        abort_if(
            ! ((Auth::User()->role === 1) || (Auth::User()->role === 2)),
            Response::HTTP_FORBIDDEN,
            '403 Forbidden'
        );

        $id = (int) $request->get('id');

        // save control
        $control = Control::find($id);
        $control->action_plan = request('action_plan');
        $control->update();

        // save next control
        $next_id = $control->next_id;
        $next_control = Control::find($next_id);
        $next_control->plan_date = request('plan_date');
        $next_control->update();

        return redirect('/actions');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Domain $domain
     *
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        abort_if(
            ! ((Auth::User()->role === 1) || (Auth::User()->role === 2)),
            Response::HTTP_FORBIDDEN,
            '403 Forbidden'
        );

        $action = DB::table('controls as c1')
            ->select(
                'c1.id',
                'c1.measure_id',
                'c1.clause',
                'c1.name',
                'c1.scope',
                'c1.objective',
                'c1.observations',
                'c1.action_plan',
                'c1.plan_date',
                'c1.score',
                'c1.realisation_date',
                'c1.score',
                'c2.plan_date as next_date',
                'c2.id as next_id'
            )
            ->leftJoin('controls as c2', function ($join) {
                $join->on('c1.next_id', '=', 'c2.id');
            })
            ->where('c1.id', '=', $id)
            ->first();

        // return
        return view('actions.show')
            ->with('action', $action);
    }
}
