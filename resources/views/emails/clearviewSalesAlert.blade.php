<html>
<head></head>
<body>
    <table class="main" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td class="alert alert-warning" align="center">
                <strong>Sales Agent sales per-day threshold met!</strong>
                <br>
            </td>
        </tr>
        <tr>
            <td class="content-wrap">
                <table class="table">
                    <tbody>
                        <tr>
                            <td><strong>Name:</strong> {{ $first_name }} {{ $last_name }}</td>
                        </tr>
                        <tr>
                            <td><strong>Rep ID:</strong> {{ $tsr_id }}</td>
                        </tr>
                        <tr>
                            <td><strong>Date:</strong> {{ $date }}</td>
                        </tr>
                        <tr>
                            <td><strong>Vendor:</strong> {{ $vendor }}</td>
                        </tr>
                        <tr>
                            <td><strong>Office:</strong> {{ $office }}</td>
                        </tr>
                    </tbody>
                </table>

                <br>

                <table class="main" cellpadding="3" cellspacing="0" border="1">
                    <tr>
                        <th>
                            Date
                        </th>
                        <th>
                            Confirmation Code
                        </th>
                    </tr>
                    @foreach ($sales as $sale)
                    <tr>
                        <td>
                            {{ $sale['created_at'] }}
                        </td>
                        <td>
                            <a href="{{ $sale['url'] }}" target="_blank">{{ $sale['confirmation_code'] }}</a>
                        </td>
                    </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
