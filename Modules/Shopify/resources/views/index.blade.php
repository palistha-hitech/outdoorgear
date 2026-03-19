@extends('shopify::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('shopify.name') !!}</p>
@endsection
