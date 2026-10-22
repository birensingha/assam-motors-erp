<?php
/**
 * FIX92 read-only Parts inline UI full Controller::show() render smoke test.
 * READ ONLY: executes actual JC show controller with an existing active
 * Admin account through a synthetic Laravel GET request. No POST, DB writes
 * or session save. This exercises the full Blade (not merely view:cache).
 */
declare(strict_types=1);
$root=$argv[1]??'';
if(!is_file($root.'/artisan'))exit(2);
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
$admin=\App\Models\ErpAuthUser::query()->where('active',true)->get()
    ->first(static fn($u)=>strtolower(trim((string)$u->role))==='admin');
if(!$admin){fwrite(STDERR,"FIX92_NO_ACTIVE_ADMIN_FOR_RENDER_SMOKE\n");exit(3);}
Auth::guard()->setUser($admin);
View::share('errors',new ViewErrorBag());
foreach([37,34] as $id){
    $req=Request::create("https://staging.assammotors.com/erp/job-cards/$id",'GET');
    $req->setUserResolver(static fn()=>$admin);
    $session=$app->make('session')->driver();
    $req->setLaravelSession($session);
    $app->instance('request',$req);
    try{
        $page=$app->make(\App\Http\Controllers\Web\AdminJobCardPageController::class)
             ->show($req,$id,$app->make(\App\Services\JobCardService::class));
        $html=$page->render();
        foreach(['Labour Item Selection','OSL Jobs / Outside Services','am83-workspace'] as $needle){
            if(!str_contains($html,$needle))
                throw new RuntimeException("Expected visible content missing: ".$needle);
        }
        if($id===37){
          foreach(['id="am85-part-form"','id="am85-part-search"','id="am85-discount"','class="am85-part-add-row"','name="part_master_id"'] as $needle){
            if(!str_contains($html,$needle))throw new RuntimeException('Missing JC37 inline Parts control: '.$needle);
          }
        }
        if($id===37 && (str_contains($html,'<datalist id="am85-part-options"') || !str_contains($html,'<select id="am85-part-search"')))
            throw new RuntimeException('Parts selector is not the intended native dropdown');
        if(str_contains($html,'Search or add Parts directly. Qty / Rate / Discount / GST / Delete editable until Job Card Close.'))
            throw new RuntimeException('Verbose Parts header should be removed');
        if($id===37){
          foreach(['id="FIX88_COMPACT_EDITABLE_AMOUNTS"','id="am85-discount-rs" class="am88-amount-input"',
                   'id="am85-taxable" class="am88-amount-input"',
                   'id="am85-gst-rs" class="am88-amount-input"',
                   'id="am85-grand" class="am88-amount-input"'] as $needle){
            if(!str_contains($html,$needle))throw new RuntimeException('Editable Parts amount missing: '.$needle);
          }
        }
        if($id===37){
          foreach(['id="FIX89_ARROW_TYPE_PARTS_DROPDOWN"',
                   'am89-query','am89-arrow','am89-reset',
                   '<select id="am85-part-search"'] as $needle){
            if(!str_contains($html,$needle))throw new RuntimeException('Searchable Parts combo missing: '.$needle);
          }
        }
        foreach(['id="FIX90_LABOUR_EXCEL_BILLING_GRID"','id="am90-labour-billing"',
                 'id="am90-table-body"','data-am90-labour-id',
                 'LABOUR</th>','ROT</th>','SAC</th>','STD HRS</th>',
                 'DIS AMT</th>','TAXABLE</th>','GST AMT</th>','FINAL AMT</th>','EDIT</th>',
                 'Mechanic &amp; ROT Work Controls'] as $needle){
            if(!str_contains($html,$needle))
                throw new RuntimeException('FIX92 missing Labour grid or work controls: '.$needle);
        }
        foreach(['id="FIX91_UNIFIED_OSL_ADMIN"', 'id="am90-table-body"', 'data-am91-osl-edit',
                 'am91-add-actions',
                 'erp/job-cards/' /* route path presence */] as $needle){
            if(!str_contains($html,$needle))
                throw new RuntimeException('FIX92 unified OSL grid or Admin control missing: '.$needle);
        }
        if($id===37 && !str_contains($html,'id="am91-add-osl"'))
            throw new RuntimeException('WIP Job Card missing Add OSL button');
        foreach(['id="FIX92_LABOUR_MECHANICS_DELETE_DIALOG"',
                 'data-am92-mechanic','data-am92-first-start',
                 'data-am92-productive','am92-audit-section',
                 'data-am92-delete-url','data-am92-review-url'] as $needle){
           if(!str_contains($html,$needle))
             throw new RuntimeException('FIX92 mechanical details or audit action missing: '.$needle);
        }
        foreach(['id="FIX93_OSL_MODAL_TOOLS"', 'id="am93-osl-tools-template"',
                 'data-am93-use-osl','OSL Jobs / Outside Services',
                 'Select Linked OSL / Supplier Purchase Options',
                 'OSL Register'] as $needle){
           if(!str_contains($html,$needle))
             throw new RuntimeException('FIX93 OSL unified tools or purchase link missing: '.$needle);
        }
        if(str_contains($html,'<div class="am83-osl" id="jc-osl">') &&
           !str_contains($html,'<template id="am93-osl-tools-template">'))
          throw new RuntimeException('FIX93 old OSL panel still visible outside template');
        if($id===37 && !str_contains($html,'New OSL Purchase'))
          throw new RuntimeException('WIP Job Card missing supplier OSL Purchase shortcut');
        // FIX95 screenshot layout must retain one server-truth live timer per ROT.
        foreach(['id="FIX94_COMPACT_MECHANIC_ROT_CARD"','am94-clock-foot',
                 'am94Styled','rot-status-block','rot-main',
                 'rot-meta','rot-actions'] as $needle){
          if(!str_contains($html,$needle))
            throw new RuntimeException('FIX95 ROT card/live clock control missing: '.$needle);
        }
        if($id===37){
          foreach(['data-am-clock','data-am-progress','data-am-percent',
                   'data-am-balance','am-rot-live-details'] as $needle)
            if(!str_contains($html,$needle))
              throw new RuntimeException('FIX95 active ROT live clock missing: '.$needle);
        }
        // FIX95 no redundant Customer Billing card; single actions row present.
        if(!str_contains($html,'id="FIX95_ROT_ONE_ROW_NO_BILLING_BOX"') ||
           !str_contains($html,'am95-completed-actions'))
           throw new RuntimeException('FIX95 completed ROT action row missing');
        if(str_contains($html,'Customer Billing: BILLABLE') ||
           str_contains($html,'Set No Charge / Internal'))
           throw new RuntimeException('FIX95 redundant ROT Customer Billing panel still present');
        // JC-37 currently contains completed ROTs; Running controls are retained
        // in the Blade source even when this read-only fixture has none.
        if(!str_contains($html,'Re-open + Reset End Time') && $id===37)
           throw new RuntimeException('FIX95 original Re-open action unavailable');
        if(!str_contains($html,'Edit ROT Start / End') && $id===37 &&
           !str_contains($html,'Edit ROT Start'))
           throw new RuntimeException('FIX95 time correction action missing');
        if(!str_contains($html,'id="FIX96_SINGLE_ROW_LABOUR_COMBO"'))
          throw new RuntimeException('FIX96 compact row stylesheet/script not rendered');
        if($id===37) foreach(['id="am82-rot-select"','id="am82-rot-search"',
                 'am96-visible-search','am96-arrow','am96-options-popup',
                 'grid-template-columns:minmax(190px,2.5fr)',
                 'id="am82-new-std"','id="am82-add-labour-total"',
                 'am91-add-actions','Labour / ROT',
                 'id="am90-labour-billing"'] as $needle){
           if(!str_contains($html,$needle))
              throw new RuntimeException('FIX96 one-row ROT form not intact: '.$needle);
        }
        foreach(['ONE WORKSPACE · Approved Estimate',
                 'ROT / Labour Search → Mechanic → Start',
                 'Approved Estimate se transferred ROTs niche hain.',
                 'Search ROT by name/code</label>'] as $needle){
           if(str_contains($html,$needle))
               throw new RuntimeException('FIX96 old verbose text still rendered: '.$needle);
        }
        // A single shared border around searchable input and the only caret;
        // no separate bordered arrow control.
        foreach(['id="FIX97_ONE_VISIBLE_ROT_COMBO"',
                 '.am96-shell input.am96-visible-search',
                 '.am96-shell button.am96-arrow',
                 'position:absolute!important;top:0!important;right:0!important',
                 'border:0!important;border-left:0!important'] as $needle){
           if(!str_contains($html,$needle))
              throw new RuntimeException('FIX97 shared single border ROT selection missing: '.$needle);
        }
        if(str_contains($html,'Direct Job Card Entry') || str_contains($html,'+ Add New Part'))
            throw new RuntimeException('Duplicate direct entry panel found');
        echo 'FIX97_JC_RENDER_PASS id='.$id.' chars='.strlen($html)."\n";
    }catch(Throwable $ex){
        // Do not output customer information from the rendered HTML.
        fwrite(STDERR,'FIX97_RENDER_FAILED id='.$id.' exception='.get_class($ex).
                      ' message='.substr($ex->getMessage(),0,250)."\n");
        exit(4);
    }
}
echo "FIX97_JC37_JC34_SINGLE_ROT_SELECTOR_PASS\n";
