@extends('myob::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('myob.name') !!}</p>
@endsection
