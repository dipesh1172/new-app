<html>
<head></head>
<body>
    <table class="table">
        <tbody>
            <tr>
                <th scope="row" width="10%" align="left">Brand name: </th>
                <td>{{ $brand_name }}</td>
            </tr>
            <tr>
                <th scope="row" align="left">User name: </th>
                <td>{{$user_name}}</td>
            </tr>
            <tr>
                <th scope="row" align="left">Date: </th>
                <td>{{$timestamp}}</td>
            </tr>
            <tr>
                <th scope="row" align="left">Config Tab: </th>
                <td>{{$config_info['tab']}}</td>
            </tr>
            @if (isset($content))
                @foreach (array_keys($content) as $key)
                    @if(count($content[$key]) > 0)
                    <tr>
                            <th scope="row" valign="top" align="left">{{ucfirst($key)}} records: </th>
                            <td>
                                <pre>
                                    {{ print_r($content[$key],true) }}
                                </pre>
                            </td>
                        </tr>
                    @endif
                @endforeach
            @endif
        </tbody>
    </table>
</body>
</html>
