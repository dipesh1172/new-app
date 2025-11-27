<?php echo('<?xml version="1.0" encoding="utf-8"?>'); ?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <TPVPost xmlns="http://tempuri.org/">
      <AccountRecord>
        <AccountCheckType>Account</AccountCheckType>
        <AccountType>N</AccountType>
        <AccountValidationCode>UnableToValidate</AccountValidationCode>
        <VendorPIN>09011:43815</VendorPIN>
        <SalesType>{{ $sales_type }}</SalesType>
        <AgentName>{{ $sales_agent_name }}</AgentName>
        <Business>{{ $business_name }}</Business>
        <Address>{{ $addr }}</Address>
        <City>{{ $city }}</City>
        <ZipCode>{{ $zip }}</ZipCode>
        <State>{{ $state }}</State>
        <LookUpValue>{{ $acct_num }}</LookUpValue>
        <Utility>{{ $utility_ldc_code }}</Utility>
        <VendorID>{{ $vendor_grp_id }}</VendorID>
        <VerificationID>{{ $confirmation_code }}</VerificationID>
        <PhoneDialed>{{ $phone }}</PhoneDialed>
        <Email>{{ $email }}</Email>
        <RIN>{{ $external_id }}</RIN>
        <Language>{{ $language }}</Language>
        <TPVStatusCode>{{ $status }}</TPVStatusCode>
      </AccountRecord>
    </TPVPost>
  </soap:Body>
</soap:Envelope>
