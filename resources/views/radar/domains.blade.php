@extends("layout")

@section("content")
<form action="/radar/domains">
    <div class="p-3">
        <div data-role="panel" data-title-caption="Tableau de bord" data-collapsible="true" data-title-icon="<span class='mif-chart-line'></span>">

        <div class="row">
            <div class="cell-md-7">
                <div class="row">
                    <div class="cell-8">
                    </div>
                    <div class="cell-2">
                        <strong>{{ trans("cruds.domain.fields.framework") }}</strong>
                        <select name="framework" data-role="select" id="framework">
                            <option value='none'></option>
                            @foreach ($frameworks as $framework)
                            <option
                                @if (Session::get("framework")==$framework->title)
                                    selected
                                @endif >
                                {{ $framework->title }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cell-2">
                        <strong>{{ trans("cruds.control.fields.scope") }}</strong>
                        <select name="scope" data-role="select" id="scope">
                            <option value='none'></option>
                            @foreach ($scopes as $key => $value)
                            <option
                                @if (Session::get("scope")==$value->scope)
                                    selected
                                @endif >
                                {{ $value->scope }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="panel mt-2">
                    <div data-role="panel" data-title-caption="Etat des contrôles au {{ date('d/m/Y')}}" data-collapsible="true" data-title-icon="<span class='mif-chart-line'></span>">
                        <div class="p-7">
                            <canvas id="canvas-status" class="chartjs-render-monitor"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    var color = Chart.helpers.color;
    var barChartData = {
        labels : [
            @foreach ($domains as $domain)
                '{{ $domain->title }}'
                 {{ $loop->last ? '' : ',' }}
            @endforeach
            ],
        datasets: [{
            // label: 'Vert',
            backgroundColor: '#60a917',
            borderColor: window.chartColors.green,
            pointBackgroundColor: window.chartColors.green,
            stack: 'Stack 0',
            data: [
                @foreach ($domains as $domain)
                    <?php $count=0; ?>
                    @foreach($active_controls as $c)
                      <?php if (($c->score==3)&&($c->title==$domain->title)) { $count++; } ?>
                    @endforeach
                    {{ $count }}
                    {{ $loop->last ? '' : ',' }}
                @endforeach
            ]
        }, {
            // label: 'Orange',
            backgroundColor: '#fa6800',
            borderColor: window.chartColors.orange,
            borderWidth: 1,
            stack: 'Stack 0',
            data: [
                @foreach ($domains as $domain)
                    <?php $count=0; ?>
                    @foreach($active_controls as $c)
                      <?php if (($c->score==2)&&($c->title==$domain->title)) { $count++;
                      } ?>
                    @endforeach
                    {{ $count }}
                    {{ $loop->last ? '' : ',' }}
                @endforeach
            ]
        }, {
            // label: 'Rouge',
            backgroundColor: '#ce352c',
            borderColor: window.chartColors.red,
            pointBackgroundColor: window.chartColors.red,
            stack: 'Stack 0',
            data: [
                @foreach ($domains as $domain)
                    <?php $count=0; ?>
                    @foreach($active_controls as $c)
                      <?php if (($c->score==1)&&($c->title==$domain->title)) { $count++;
                      } ?>
                    @endforeach
                    {{ $count }}
                    {{ $loop->last ? '' : ',' }}
                @endforeach
            ]
        }, {
            label: 'Gris',
            backgroundColor: color(window.chartColors.grey).alpha(1).rgbString(),
            borderColor: window.chartColors.black,
            borderWidth: 1,
            stack: 'Stack 0',
            data: [
                @foreach ($domains as $domain)
                    <?php $count=0; ?>
                    @foreach($controls_never_made as $c)
                      <?php if ($c->domain_id==$domain->id) { $count++; } ?>
                    @endforeach
                    {{ $count }}
                    {{ $loop->last ? '' : ',' }}
                @endforeach
            ]
        }]
    };
    var ctx1 = document.getElementById('canvas-status').getContext('2d');
    window.myBar = new Chart(ctx1, {
        responsive: true,
        type: 'bar',
        data: barChartData,
        options: {
            responsive: true,
            legend: {
                display: false,
            },
            title: {
                display: false
            }
        }
    });
    document.getElementById('canvas-status').onclick = function(evt){
            var activePoints = window.myBar.getElementsAtEvent(evt);
            var firstPoint = activePoints[0];
            var label = barChartData.labels[firstPoint._index];
            var value = barChartData.datasets[firstPoint._datasetIndex].data[firstPoint._index];
            window.location.href="/bob/index?attribute=none&status=2&period=99&domain_title="+label;
        };

    window.addEventListener('load', function(){
        var select = document.getElementById('scope');
        select.addEventListener('change', function(){
            window.location = '/radar/domains?scope=' + this.value;
        }, false);
        var select = document.getElementById('framework');
        select.addEventListener('change', function(){
            window.location = '/radar/domains?framework=' + this.value;
        }, false);
    });

    </script>

@endsection
