@extends("layout")

@section("content")
<div class="p-3">
    <div data-role="panel" data-title-caption="{{ trans('cruds.control.plan') }}" data-collapsible="true" data-title-icon="<span class='mif-chart-line'></span>">

	@if (count($errors))
	<div class="grid">
	    <div class="cell-3 bg-red fg-white">
			<ul>
			@foreach ($errors->all() as $error)
				<li>{{ $error }}</li>
			@endforeach
			</ul>
		</div>
	</div>
	@endif

	<form method="POST" action="/bob/plan">
	@csrf

	<input type="hidden" name="id" value="{{ $control->id }}"/>

	<div class="grid">
    	<div class="row">
    		<div class="cell-1">
	    		<strong>{{ trans('cruds.control.fields.name') }}</strong>
	    	</div>
			<div class="cell">
	    		<a href="/measures/{{ $control->measure_id }}">{{ $control->clause }}</a> &nbsp; - &nbsp; {{ $control->name }}
			</div>
		</div>
    	<div class="row">
    		<div class="cell-1">
	    		<strong>{{ trans('cruds.control.fields.objective') }}</strong>
	    	</div>
			<div class="cell-6">
				<pre>{!! $control->objective !!}</pre>
			</div>
		</div>

    	<div class="row">
    		<div class="cell-1">
	    		<strong>{{ trans('cruds.control.fields.scope') }}</strong>
	    	</div>
			<div class="cell-4">
				<input type="text" name="scope" data-role="input" autocomplete="on" size="32"
				value="" data-autocomplete="{{ implode(",",$scopes) }}"/>
			</div>
		</div>

    	<div class="row">
    		<div class="cell-1">
				<strong>{{ trans('cruds.control.fields.plan_date') }}</strong>
	    	</div>
			<div class="cell-2">
				<input type="text" data-role="calendarpicker" name="plan_date" value="{{
				\Carbon\Carbon
				::createFromFormat('Y-m-d',$control->plan_date)
				->format('Y-m-d')
				}}"
				data-input-format="%Y-%m-%d">
			</div>
		</div>

    	<div class="row">
    		<div class="cell-1">
	    		<strong>{{ trans('cruds.control.fields.periodicity') }}</strong>
	    	</div>
			<div class="cell-2">
				<select name="periodicity" data-role="select">
				    <option value="1" {{ $control->periodicity==1 ? "selected" : ""}}>{{ trans('common.monthly') }}</option>
				    <option value="3" {{ $control->periodicity==3 ? "selected" : ""}}>{{ trans('common.quarterly') }}</option>
				    <option value="6" {{ $control->periodicity==6 ? "selected" : ""}}>{{ trans('common.biannually') }}</option>
				    <option value="12" {{ $control->periodicity==12 ? "selected" : ""}}>{{ trans('common.annually') }}</option>
				 </select>
			</div>
		</div>

		<div class="row">
			<div class="cell-1">
				<strong>{{ trans('cruds.control.fields.owners') }}</strong>
	    	</div>
			<div class="cell-4">
	            <select data-role="select" name="owners[]" id="owners" multiple>
	                @foreach($users as $user)
	                    <option value="{{ $user->id }}" {{ (in_array($user->id, old('owners', [])) || $control->owners->contains($user->id)) ? 'selected' : '' }}>{{ $user->name }}</option>
	                @endforeach
	            </select>

			</div>
		</div>

    	<div class="row">
			@if (Auth::User()->role !== 3)
    		<div class="cell-12">
				<button type="submit" class="button success">
					<span class="mif-calendar"></span>
					&nbsp;
					{{ trans("common.plan") }}
				</button>
				</form>
				&nbsp;
			@endif
			@if (Auth::User()->role !== 3)
				<form action="/bob/unplan" method="POST" onSubmit="if(!confirm('{{ trans('common.confirm') }}')){return false;}">
					@csrf
					<input type="hidden" name="id" value="{{ $control->id }}"/>
		            <button class="button alert" type="submit">
						<span class="mif-fire"></span>
						&nbsp;
						{{ trans("common.unplan") }}
					</button>
				</form>
				&nbsp;
			@endif
				<form action="/bob/show/{{$control->id}}"/>
					<button type="submit" class="button">
						<span class="mif-cancel"></span>
						&nbsp;
						{{ trans("common.cancel") }}
					</button>
				</form>
			</div>
		</div>
	</div>
	</form>
</div>
</div>
@endsection
