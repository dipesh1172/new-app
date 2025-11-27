@extends('layouts.emails')

@section('title')
Enrollment File write failed
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content-block">
                            Enrollment file failed to write at 
                        </td>
                    </tr>
                    <tr>
                        <td>{{$filename}}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection