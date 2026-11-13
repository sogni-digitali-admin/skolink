@component('mail::message')
# {{ __('Fee Payment Reminder', [], $locale ?? app()->getLocale()) }}

{{ __('Dear Parent,', [], $locale ?? app()->getLocale()) }}

@if(!empty($studentName))
{{ __('The payment for :fee (student: :student) is pending. Last date for payment: :date.', ['fee' => $feeName, 'student' => $studentName, 'date' => $dueDate], $locale ?? app()->getLocale()) }}
@else
{{ __('The payment for :fee is pending. Last date for payment: :date.', ['fee' => $feeName, 'date' => $dueDate], $locale ?? app()->getLocale()) }}
@endif

{{ __('Kindly settle the payment before the due date to avoid any inconvenience.', [], $locale ?? app()->getLocale()) }}

{{ __('Regards,', [], $locale ?? app()->getLocale()) }}<br>
{{ config('app.name') }}
@endcomponent
