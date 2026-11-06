@extends('layouts.app')
@section('title', __('Usos no histórico'))
@section('page-title', __('Usos no histórico'))
@section('page-actions')
    <a class="btn btn-outline-primary sge-icon-action" href="{{ route('history-curriculum.index') }}" aria-label="{{ __('Catálogo de componentes') }}" title="{{ __('Catálogo de componentes') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
@endsection
@section('content')
@include('knowledge-areas._navigation')
<p><strong>{{ $catalogComponent->name }}</strong> · {{ $catalogComponent->area->name }} · {{ __($catalogComponent->formation) }} · {{ __(\App\Support\HistoryCurriculumCatalog::stageLabels()[$catalogComponent->education_stage]) }}</p>
<section class="card sge-panel-card sge-livewire-table-card mb-4"><div class="card-body"><livewire:history-curriculum-usages-table :catalog-id="$catalogComponent->id" /></div></section>
@endsection
