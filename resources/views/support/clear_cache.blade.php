@extends('layouts.app')

@section('title')
Support: Clear System Cache
@endsection

@section('content')
    <!-- Breadcrumb -->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">Support</li>
        <li class="breadcrumb-item active">Clear System Cache</li>
    </ol>

    <div class="container-fluid">
        <div class="animated fadeIn">
            <div class="card">
                <div class="card-header">
                    <i class="fa fa-th-large"></i> Clear System Cache
                </div>
                <div class="card-body text-center">
                    @if(Session::has('flash_message'))
                        <div class="alert alert-success"><span class="fa fa-check-circle"></span><em> {!! session('flash_message') !!}</em></div>
                    @endif
                
                    <div class="alert alert-danger text-lg">
                        This button will clear <strong>ALL</strong> caches in this environment. <br>
                        This is generally safe but can cause unintended consequenses such as logging users out or preventing an item from saving if performed at the same time as the clear. <br>
                        To signify your understanding and agreement you can click the <strong>big red button</strong> to clear system caches.
                    </div>
                    <form method="POST" action="/support/clear_cache">
                        {{ csrf_field() }}
                        <button type="submit" onclick="return confirm('Are you sure?');" class="btn text-center btn-danger big-red-button" style="padding: 350px 150px; border-color: black; border-radius: 50%; font-size: 4em; text-shadow: 2px 2px black;">Clear System Caches</button>
                    </form>
                </div>
            </div>
                    
                
        </div>
    </div>
@endsection
