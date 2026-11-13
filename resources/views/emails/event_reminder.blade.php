@component('mail::message')
# {{ __('Event Reminder', [], $locale ?? app()->getLocale()) }}

@if(!empty($schoolName))
{{ __('School', [], $locale ?? app()->getLocale()) }}: {{ $schoolName }}
@endif

@component('mail::panel')
**{{ $title }}**

@if(!empty($description))
{!! html_entity_decode($description) !!}
@endif
@endcomponent

@if(!empty($location))
**{{ __('Location', [], $locale ?? app()->getLocale()) }}:** {{ $location }}
@endif
@if(!empty($startDate))
**{{ __('Start Date', [], $locale ?? app()->getLocale()) }}:** {{ $startDate }}
@endif
@if(!empty($endDate))
**{{ __('End Date', [], $locale ?? app()->getLocale()) }}:** {{ $endDate }}
@endif

{{ __('Regards,', [], $locale ?? app()->getLocale()) }}<br>
{{ config('app.name') }}
@endcomponent
