@extends('layouts.emails')

@section('title')
Alert
@endsection

@section('content')
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-wrap">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content-block">
                            <center><img alt="TPV.com Logo" src="https://tpv-assets.s3.amazonaws.com/tpv-new-220x120.png" /></center><br />

                            <center><b>{!! $alert->client_alert->title !!}</b></center><br />
                        </td>
                    </tr>
                    <tr>
                        <td class="content-block aligncenter">
                            @if($alert->event_id !== null)
                                Event: {{ $alert->event->confirmation_code }} <br />
                                {!! $alert->client_alert->description !!}

                                <br /><br />

                                <a href="{{ config('app.urls.clients') }}/events/{!! $alert->event_id !!}" target="_blank">View Event &rarr;</a>
                                <br /> <br />
                            @else
                                Source: EzTPV <br />
                                {!! $alert->client_alert->description !!}
                            @endif

                            @if(in_array($brand, $genieBrands))
                                @if($btn)
                                <div>Phone number: {{$btn}}</div>
                                @endif
                                @if($accountNumber)
                                <div>Account number: {{$accountNumber}}</div>
                                @endif
                            @endif


                            {{-- @if(isset($alert->data['conflicts']))
                            <br>
                                The following records are responsible for triggering this alert:<br>
                                <ul>
                                    @foreach($alert->data['conflicts'] as $conflict)
                                        <li>{{ $conflict }}</li>
                                    @endforeach
                                </ul>
                            @endif --}}
                            @if($emailType === 'client')
                                <a href="{{ config('app.urls.clients') }}/alert/{!! $alert->id !!}" target="_blank">View Alert Conflicts &rarr;</a>
                                <br />
                            @endif
                            Alert ID: {{ $alert->id }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
