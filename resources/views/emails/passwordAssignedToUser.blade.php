@extends('layouts.emails')

@section('title')
Password Assigned To User
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="alert alert-warning">
                Welcome!
            </td>
        </tr>
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content-block">
                            You have been added to the <b>{{$company}}</b> TPV portal.<br /><br />

                            Below you will find your temporary password. Please login using your email address and the password below, and then change your password.
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block aligncenter">
                            {{ $password }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection