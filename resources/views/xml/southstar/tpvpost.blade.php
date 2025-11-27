<soap:Envelope
    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:wsa="http://schemas.xmlsoap.org/ws/2004/08/addressing"
    xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
    xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
    <soap:Header>
        <wsa:Action>http://primesw.com/webservices/</wsa:Action>
        <wsa:MessageID>urn:uuid:f76bd23e-66a9-438c-a5e3-ed43a8f90d8a</wsa:MessageID>
        <wsa:ReplyTo>
            <wsa:Address>http://schemas.xmlsoap.org/ws/2004/08/addressing/role/anonymous</wsa:Address>
        </wsa:ReplyTo>
        <wsa:To>{{ $pi->hostname }}</wsa:To>
        <wsse:Security soap:mustUnderstand="1">
            <wsse:UsernameToken xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" wsu:Id="SecurityToken-7fd837f7-41f1-4c3e-8046-fd97a8307384">
                <wsse:Username>{{ $pi->username }}</wsse:Username>
                <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">{{ $pi->password }}</wsse:Password>
                <wsse:Nonce>{{ $pi->notes['nonce'] }}</wsse:Nonce>
                <wsu:Created>{{ $sp->created_at->format('Y-m-d\TH:i:s\Z') }}</wsu:Created>
            </wsse:UsernameToken>
        </wsse:Security>
    </soap:Header>
    <soap:Body>
        <TPVDispositionNotification xmlns="http://primesw.com/webservices">
            <TPVDispositionNotificationRequest xmlns="http://tempuri.org/QuoteService.xsd">
                <TPVCaseID>{{ intval($sp->external_id) }}</TPVCaseID>
                <ConfirmedIndicator>Y</ConfirmedIndicator>
                <RejectionMessage></RejectionMessage>
                <CallDurationSeconds>{{ intval($sp->product_time * 60) }}</CallDurationSeconds>
                <VerificationNumber>{{ intval($sp->confirmation_code) }}</VerificationNumber>
            </TPVDispositionNotificationRequest>
        </TPVDispositionNotification>
    </soap:Body>
</soap:Envelope>
