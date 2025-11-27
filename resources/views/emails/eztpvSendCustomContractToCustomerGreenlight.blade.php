@extends('layouts.emails')

@section('title')
Send EzTPV Contract to Customer
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    @if (!is_null($logo))
                        <tr>
                            <td class="content-block aligncenter">
                                <img id="logo" src="{{ config('services.aws.cloudfront.domain') }}/{{ $logo }}" height="80px" alt="Logo">
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td class="content-block">
                            {!! $message_body !!}
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block aligncenter">
                            <p>
                                Hello {{ $customer_name }} <br><br>

                                Thank you for your enrollment with Greenlight Energy. <br><br>

                                Please follow the link below to read the Terms & Agreements of choosing Greenlight Energy as your supplier.
                            <p>
                            <p>
                                <a href="{{ $url }}" class="btn-primary">
                                    @if ($language === 'spanish')
                                        Haz click aquí para proceder
                                    @else
                                        Click here to proceed
                                    @endif
                                </a>
                            </p>
                            <p>
                                Thank you again, please don’t hesitate to reach out with any questions at 1-888-453-4427.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
