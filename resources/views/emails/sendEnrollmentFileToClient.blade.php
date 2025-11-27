@extends('layouts.emails')

@section('title')
Enrollment File from TPV.com
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content-block">
                            Attached: Your enrollment file from TPV.com as of {{$timestamp}}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection