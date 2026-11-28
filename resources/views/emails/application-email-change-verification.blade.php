@component('emails.layout', ['heading' => 'Verify your updated email address', 'emailMessage' => $message ?? null])
<p style="margin:0 0 12px;">Hello {{ $emailChange->application->first_name }},</p>
<p style="margin:0 0 16px;">Confirm this address to update the contact email on application <strong>{{ $emailChange->application->reference_no }}</strong> and return it to Registrar review.</p>
<p style="margin:0 0 18px;color:#475569;word-break:break-word;">New email: <strong>{{ $emailChange->new_email }}</strong></p>
@include('emails.action', ['url' => $confirmationUrl, 'label' => 'Verify email and resubmit'])
<p style="margin:0;color:#64748b;font-size:12px;">This link expires in 60 minutes. Your application email will not change unless you confirm it.</p>
@endcomponent
