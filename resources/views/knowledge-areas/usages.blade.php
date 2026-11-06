@extends('layouts.app')
@section('title', $area->name)
@section('page-title', $area->name)
@section('content')
@include('knowledge-areas._navigation')
    <livewire:screen-listing screen="area-usages" :context="['area' => $area->id]" />
@endsection
