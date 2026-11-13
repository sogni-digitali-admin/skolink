@component('mail::message')
# {{ __('Absentee Alert', [], $locale ?? app()->getLocale()) }}

{{ __('Dear Parent,', [], $locale ?? app()->getLocale()) }}

@if(!empty($message))
{!! html_entity_decode($message) !!}
@else
{{ __('Kindly make a note that your child :name was marked absent from school on :date.', ['name' => $studentName, 'date' => $date], $locale ?? app()->getLocale()) }}
@endif

{{ __('Please contact the school office if this absence was not expected.', [], $locale ?? app()->getLocale()) }}

{{ __('Regards,', [], $locale ?? app()->getLocale()) }}<br>
{{ config('app.name') }}
@endcomponent
