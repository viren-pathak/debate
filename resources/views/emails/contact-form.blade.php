@component('mail::message')
# Contact Form Submission

<!-- Name -->
**Full Name:**
{{ $name }}

<!-- Email -->
**Email:**
{{ $email }}

<!-- Subject of form -->
**Subject:**
{{ $subject }}

<!-- Message in form -->
**Message:**
{{ $message }}

<!-- Screenshot -->
@if ($attachments)
**Attachments:**
Attached below
@endif

<!-- Reply User to their email addres -->
@component('mail::button', ['url' => 'mailto:' . $email])
Reply me via Email
@endcomponent

Thanks,<br>
{{ $name }}
@endcomponent
