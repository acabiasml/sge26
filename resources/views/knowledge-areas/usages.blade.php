@extends('layouts.app')
@section('title', $area->name)
@section('page-title', $area->name)
@section('content')
    <livewire:screen-listing screen="area-usages" :context="['area' => $area->id]" />
@endsection
