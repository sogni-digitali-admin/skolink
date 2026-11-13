@component('mail::message')
# {{ __('New Message', [], $locale ?? app()->getLocale()) }}

{{ __('Dear Parent,', [], $locale ?? app()->getLocale()) }}

{!! html_entity_decode($message) !!}

{{ __('Log in to the parent portal or the app for more details.', [], $locale ?? app()->getLocale()) }}

{{ __('Regards,', [], $locale ?? app()->getLocale()) }}<br>
{{ config('app.name') }}
@endcomponent
