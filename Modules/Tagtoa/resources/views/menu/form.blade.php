@extends('tagtoa::layouts.dashboard')
@php $editing = $menu->exists; @endphp
@section('title', $editing ? __('Modifier le menu') : __('Nouveau menu'))
@section('page', $editing ? __('Modifier le menu') : __('Nouveau menu'))

@section('content')
@include('tagtoa::menu._form-body')
@endsection
