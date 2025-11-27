@extends('layouts.emails')

@section('title')
Send EZTPV Contract to Customer
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content-block">
                            Hello {{ $customer_name }} <br><br>

                            Thank you for your enrollment with Greenlight Energy. <br><br>

                            Please follow the link below to read the Terms & Agreements of choosing Greenlight Energy as your supplier.
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block aligncenter">
                            <a href="{{ $url }}" class="btn-primary">
                                @if ($language === 'spanish')
                                    Haz click aquí para proceder
                                @else
                                    Click here to proceed
                                @endif
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            Thank you again, please don’t hesitate to reach out with any questions at 1-888-453-4427.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
