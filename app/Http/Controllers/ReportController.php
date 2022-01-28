<?php

namespace App\Http\Controllers;

use \Carbon\Carbon;

use App\Exports\ControlsExport;
use Maatwebsite\Excel\Facades\Excel;

use App\Domain;
use App\Measure;
use App\Control;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Chart;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\PhpWord;

class ReportController extends Controller
{

    /**
     * Rapport de pilotage du SMSI
     *
     * @return \Illuminate\Http\Response
     */
    public function pilotage(Request $request)
    {

        // start date
        $start_date = $request->get("start_date");
        if ($start_date==null) {
            return back()
                ->withErrors(['pilotage' => 'pas de date de début'])
                ->withInput();
        }            
        
        $start_date=\Carbon\Carbon::createFromFormat('Y-m-d', $start_date);

        // end date
        $end_date = $request->get("end_date");
        if ($end_date==null) {
            return back()
                ->withErrors(['pilotage' => 'pas de date de fin'])
                ->withInput();
        }
        $end_date=\Carbon\Carbon::createFromFormat('Y-m-d', $end_date);

        // start_date > end_date
        if($start_date->gt($end_date)){
            return back()
                ->withErrors(['pilotage' => 'date début > date fin'])
                ->withInput();
        }

        // today
        $today=\Carbon\Carbon::today();

        // end_date<=today
        if($end_date->gt($today)){
            return back()
                ->withErrors(['pilotage' => 'date de fin dans le futur'])
                ->withInput();
        }

        // get template
        $templateProcessor = new TemplateProcessor(
            storage_path('app/models/pilotage.docx')
        );

        //-------------------------------------------------------------
        // make changes 
        //-------------------------------------------------------------
        $templateProcessor->setValue('today', $today->format('d/m/Y'));
        $templateProcessor->setValue('start_date', $start_date->format('d/m/Y'));
        $templateProcessor->setValue('end_date', $end_date->format('d/m/Y'));

        // addText('', $fontStyle);

        //----------------------------------------------------------------        
        $controls =  Control::where(
            [
                    ["realisation_date",">=",$start_date],            
                    ["realisation_date","<",$end_date],
            ]
        )
            ->orderBy("realisation_Date")->get();
        /*
        $values = [];
        foreach($controls as $control) {

            $values[] =  [
                'ctrl_id' => $control->clause,
                'ctrl_date' => $control->realisation_date, 
                'ctrl_name' => $control->name,
                'ctrl_score' => '<w:highlight w:val="red">'.'⬤'.$control->score.'</w:highlight>',
            ];
        }

        $templateProcessor->cloneRowAndSetValues('ctrl_id', $values);
        */
        //----------------------------------------------------------------

        // $myParagraphStyle = array('align'=>'left', 'spaceBefore'=>50, 'spaceafter' => 50);        
        // $myFontStyle = array('name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '#FF0000');

        // create table
        $table =new Table(array('borderSize' => 3, 'borderColor' => 'black', 'width' => 9800 , 'unit' => TblWidth::TWIP));
        // create header
        $table->addRow();
        $table->addCell(2500, ['bgColor'=>'#FFD5CA'])
            ->addText('#', ['bold' => true ], ['align'=>'center']);
        $table->addCell(12500, ['bgColor'=>'#FFD5CA'])
            ->addText('Nom', ['bold' => true]);
        $table->addCell(2800, ['bgColor'=>'#FFD5CA'])
            ->addText('Date', ['bold' => true], ['align'=>'center']);
        $table->addCell(2000, ['bgColor'=>'#FFD5CA'])
            ->addText('Score', ['bold' => true], ['align'=>'center']);

        foreach($controls as $control) {
            $table->addRow();
            $table->addCell(2500)->addText($control->clause);
            $table->addCell(12500)->addText($control->name);
            $table->addCell(2800)->addText($control->realisation_date, null, ['align'=>'center']);
            $table->addCell(2000)->addText(
                '⬤',
                ($control->score==1 ? ['color'=>'#FF0000'] : 
                ($control->score==2 ? ['color'=>'#FF8000'] :
                ($control->score==3 ? ['color'=>'#00CC00'] : null))),
                ['align'=>'center']
            );
        }
        $templateProcessor->setComplexBlock('made_control_table', $table);

        // ---------------------------------------------------------------        
        // https://github.com/PHPOffice/PHPWord/pull/1864        

        // $domains = [];
        $values = [];

        // get all domains
        $domains = DB::table('domains')->get();

        // get status report
        $controls=DB::select(
            DB::raw(
                "
            SELECT 
            c2.measure_id, 
            c2.domain_id,
            c2.score,
            c2.realisation_date
            FROM
                (select 
                measure_id,
                max(id) as id
                from controls
                where realisation_date is not null and score is not null
                group by measure_id) as c1, controls as c2
            where c1.id=c2.id;
                "
            )
        );

        for($j=0;$j<count($domains);$j++) {
            $values[0][$j]=0;
            $values[1][$j]=0;
            $values[2][$j]=0;
        }

        //
        $colors = [];
        foreach($domains as $domain) { 
            $colors[] = '00CC00';
        }
        foreach($domains as $domain) { 
            $colors[] = 'FF8000';
        }
        foreach($domains as $domain) { 
            $colors[] = 'FF0000';
        }

        $i=0;
        foreach($domains as $domain) {
            $domains[$i]=$domain->title;            
            foreach($controls as $control) {
                if ($control->domain_id==$domain->id) {
                    $values[3-$control->score][$i]=$values[3-$control->score][$i]+1;
                }
            }
            $i++;
        }
        
        $chart = new Chart("stacked_column", $domains, $values[0]);
        $chart->addSeries($domains, $values[1]);
        $chart->addSeries($domains, $values[2]);
        
        $chart->getStyle()
            ->setWidth(Converter::inchToEmu(7))
            ->setHeight(Converter::inchToEmu(3))
            ->setShowGridX(false)
            ->setShowGridY(true)
            ->setShowAxisLabels(true)
            ->set3d(false)
            ->setShowLegend(false)            
            // ->setValueLabelPosition("none")
            ->setColors($colors)
            ->setDataLabelOptions(['showCatName'=>false,]);

        $templateProcessor->setChart('control_table', $chart);            
        
        //----------------------------------------------------------------
        // kpi_table

        // get all domains
        $domains = DB::table('domains')->get();

        // create table
        $table =new Table(array('borderSize' => 3, 'borderColor' => 'black', 'width' => 9800 , 'unit' => TblWidth::TWIP));
        // create header
        $table->addRow();
        $table->addCell(2000, ['bgColor'=>'#FFD5CA'])
            ->addText('#', ['bold' => true, ], ['align'=>'center']);
        $table->addCell(12500, ['bgColor'=>'#FFD5CA'])
            ->addText('Domaine', ['bold' => true]);
        $table->addCell(2500, ['bgColor'=>'#FFD5CA'])
            ->addText('KPI', ['bold' => true], ['align'=>'center']);
        $table->addCell(1000, ['bgColor'=>'#FFD5CA'])
            ->addText('0', ['bold' => true, 'color' => '#FF0000' ], ['align'=>'center']);
        $table->addCell(1000, ['bgColor'=>'#FFD5CA'])
            ->addText('1', ['bold' => true, 'color' => '#FF8000'], ['align'=>'center']);
        $table->addCell(1000, ['bgColor'=>'#FFD5CA'])
            ->addText('2', ['bold' => true, 'color' => '#00CC00'], ['align'=>'center']);


        $d=0;
        foreach($domains as $domain) {
            $table->addRow();
            $table->addCell(2000)->addText(
                $domain->title, null,
                ['spaceBefore'=>0,'spaceAfter'=>0,'align'=>'center']
            );
            $table->addCell(12500)->addText(
                $domain->description, null,
                ['spaceBefore'=>0,'spaceAfter'=>0]
            );

            // PKI
            $v=$values[0][$d]+$values[1][$d]+$values[2][$d];
            if ($v!=0)
                $v=intdiv($values[0][$d]*100, $v);

            $table->addCell(2500)
                ->addText(
                    $v .'%',
                    ($v>=90 ? ['bold' => true, 'color' => '#00CC00'] :
                    ($v>=80 ? ['bold' => true, 'color' => '#FF8000'] :
                    ['bold' => true,'color' => '#FF0000'])),
                    ['align'=>'center','spaceBefore'=>0,'spaceAfter'=>0]
                );
            // values
            $table->addCell(1000)
                ->addText(
                    $values[2][$d],
                    ['bold' => true, 'color' => '#FF0000'  ], 
                    ['align'=>'center','spaceBefore'=>0,'spaceAfter'=>0 ]
                );
            $table->addCell(1000)
                ->addText(
                    $values[1][$d],
                    ['bold' => true, 'color' => '#FF8000'],
                    ['align'=>'center','spaceBefore'=>0,'spaceAfter'=>0 ]
                );
            $table->addCell(1000)
                ->addText(
                    $values[0][$d],
                    ['bold' => true, 'color' => '#00CC00'],
                    ['align'=>'center','spaceBefore'=>0,'spaceAfter'=>0 ]
                );

            // next
            $d++;
        }

        $templateProcessor->setComplexBlock('kpi_table', $table);

        //----------------------------------------------------------------
        // Action plans
        $actions=
            DB::select("
                select
                    c2.measure_id,
                    c2.id,
                    c2.clause,
                    c2.action_plan,
                    c2.score,
                    c2.name,
                    c2.plan_date,
                    c2.realisation_date,
                    c3.id as next_id,
                    c3.plan_date as next_date,
                    c2.action_plan 
                from
                    (
                    select 
                        measure_id,
                        max(id) as id
                    from 
                        controls
                    where
                        realisation_date is not null
                    group by measure_id
                    ) as c1,                    
                    controls c2,
                    controls c3
                where
                    (c1.id = c2.id ) and
                    (c2.score=1 or c2.score=2) and
                    (c3.measure_id = c2.measure_id and c3.id > c2.id)
                order by measure_id
                    ;");

        $table =new Table(array('borderSize' => 3, 'borderColor' => 'black', 'width' => 9800 , 'unit' => TblWidth::TWIP));

        // create header
        $table->addRow();
        $table->addCell(2000, ['bgColor'=>'#FFD5CA'])
            ->addText('#', ['bold' => true, ], ['align'=>'center']);
        $table->addCell(13000, ['bgColor'=>'#FFD5CA'])
            ->addText('Titre', ['bold' => true]);
        $table->addCell(3000, ['bgColor'=>'#FFD5CA'])
            ->addText('Next', ['bold' => true]);

        // table content
        foreach($actions as $action) {
            $table->addRow();
            $table->addCell(2000)->addText(
                $action->clause, null,
                ['align'=>'center']
            );
            $table->addCell(13000)->addText(
                $action->name, null,
                ['align'=>'left']
            );
            $table->addCell(3000)->addText(
                $action->next_date, null,
                ['align'=>'left']
            );

            $table->addRow();            
            $section=$table->addCell(18000, ['gridSpan' => 3]);
            $textlines = explode("\n", $action->action_plan);
            foreach ($textlines as $textline) 
                $section->addText($textline);
        }

        // get action plans
        $domains = DB::table('domains')->get();

        $templateProcessor->setComplexBlock('action_plans_table', $table);

        //----------------------------------------------------------------
        // save a copy
        $filepath=storage_path('templates/pilotage-'. Carbon::today()->format("Y-m-d") .'.docx');
        // if (file_exists($filepath)) unlink($filepath);
        $templateProcessor->saveAs($filepath);

        // return
        return response()->download($filepath);       
    }




    /**
     * Generate tests data. !!!! DANGEROUS !!!!
     *
     * @return \Illuminate\Http\Response
     */
    public function generateTests()
    {        
        // remove all measurements
        DB::table('documents')->delete();
        DB::table('measurements')->delete();

        // period in month
        $period = 12;

        // Start date
        $curDate=Carbon::now()->addMonth(-$period)->day(1);
        // Log::Alert("startDate=" . $curDate->toDateString());

        // get all controls
        $controls = Control::All();
        $cntControls = DB::table('controls')->count();
        // Log::Alert("controld count=" . $cntControls);

        // controls per period
        $perPeriod = (int)($cntControls / $period);
        // Log::Alert("control per period=" . $perPeriod);

        // loop on controls
        $curControl = 1; 
        foreach ($controls as $control) {
            // go to next period
            if (($curControl++ % $perPeriod)==0) {
                $curDate->addMonth(1);                
            }

            // Log::Alert("Control " . $control->clause . " curDate=" . $curDate->toDateString());

            // create a measurement
            // TODO : loop on plan_date until date is in the futur
            $control = new Control();
            $control->control_id=$control->id;
            $control->domain_id=$control->domain_id;
            $control->name=$control->name;
            $control->clause=$control->clause;
            $control->objective = $control->objective;
            $control->attributes = $control->attributes;
            $control->model = $control->model;
            $control->indicator = $control->indicator;
            $control->action_plan = $control->action_plan;
            $control->owner = $control->owner;
            $control->periodicity = $control->periodicity;
            $control->retention = $control->retention;
            // do it            
            $control->plan_date = $curDate->toDateString();
            $control->realisation_date = (new Carbon($curDate))->addDay(rand(0, 28))->toDateString();
            $control->note = rand(0, 10);
            $control->score = rand(0, 100)<90 ? 3 : (rand(0, 2)<2 ? 2 : 1);
            // save it
            $control->save();

            // create next measurement
            $control = new Control();
            $control->control_id=$control->id;
            $control->domain_id=$control->domain_id;
            $control->name=$control->name;
            $control->clause=$control->clause;
            $control->objective = $control->objective;
            $control->attributes = $control->attributes;
            $control->model = $control->model;
            $control->indicator = $control->indicator;
            $control->action_plan = $control->action_plan;
            $control->owner = $control->owner;
            $control->periodicity = $control->periodicity;
            $control->retention = $control->retention;
            // next one            
            $control->plan_date = (new Carbon($curDate))->addMonth($control->periodicity)->toDateString();
            // fix it
            $control->realisation_date=null;
            $control->note=null;
            $control->score=null;
            // save it
            $control->save();
        }
        return redirect("/");
    }

}

