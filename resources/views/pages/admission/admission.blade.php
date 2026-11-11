@extends('layouts.admission')
@section('content')
    <div class="w-full lg:mx-2">
        <div class="bg-white shadow py-3">
            <div class="container mx-auto px-3 lg:px-0">
                <div>
                    <a href="{{ url('/') }}">
                        <img src="{{ $logo }}" class="inline-block" style="height:55px;">
                    </a>
                    <span class="float-right mt-3 text-sm">
                        @foreach(config('school.locale.available', ['en' => 'English']) as $localeCode => $localeName)
                            @if(app()->getLocale() === $localeCode)
                                <strong class="mx-1">{{ $localeName }}</strong>
                            @else
                                <a href="{{ route('locale.switch', $localeCode) }}" class="mx-1 text-blue-600 underline">{{ $localeName }}</a>
                            @endif
                        @endforeach
                    </span>
                </div>
            </div>
        </div>
        @if($admission_open['meta_value']=="1")
            <h1 class="admin-h1 my-3 flex items-center">       
                <span class="mx-3">{{ __('Admission Form') }}</span>
            </h1>
            @include('partials.message')
            <!-- multistep form -->
            <form method="POST" action="" enctype="multipart/form-data" id="msform" class="w-full lg:w-1/2 mx-auto">
                @csrf
                <add-admission url="{{ url('/') }}" slug="{{ $slug }}"></add-admission>
                <div id="add_admissionform"></div>

                <fieldset class="my-4 px-3 py-3 border border-gray-300 rounded">
                    <legend class="text-sm font-semibold px-1">{{ __('Consent and Declarations') }}</legend>

                    @error('data_processing_consent')
                        <p class="text-red-600 text-sm mb-2">{{ __('Your consent to data processing is required to submit the admission form.') }}</p>
                    @enderror

                    <label class="flex items-start my-2 text-sm">
                        <input type="checkbox" name="data_processing_consent" value="1" class="mt-1 mr-2" {{ old('data_processing_consent') ? 'checked' : '' }}>
                        <span>{{ __('I consent to the processing of the personal data provided in this form by the school for admission and schooling purposes, in accordance with the Data Protection Act 2017 of Mauritius.') }}</span>
                    </label>

                    <label class="flex items-start my-2 text-sm">
                        <input type="checkbox" name="notification_opt_in" value="1" class="mt-1 mr-2" {{ old('notification_opt_in') ? 'checked' : '' }}>
                        <span>{{ __('I agree to receive notifications from the school (email / SMS) about my child\'s schooling.') }}</span>
                    </label>

                    <label class="flex items-start my-2 text-sm">
                        <input type="checkbox" name="whatsapp_opt_in" value="1" class="mt-1 mr-2" {{ old('whatsapp_opt_in') ? 'checked' : '' }}>
                        <span>{{ __('I agree to be contacted by the school on WhatsApp at the mobile number provided.') }}</span>
                    </label>
                </fieldset>
            </form>

        @elseif($admission_open['meta_value']=="0")
            <h1 class="admin-h1 my-3 flex items-center">       
                <span class="mx-3">{{ $closedetails['meta_value'] }}</span>
            </h1>   
        @else
        <h1 class="admin-h1 my-3 flex items-center">       
                <span class="mx-3">{{ __('Admission Not Opened') }}</span>
            </h1>    
        @endif
    </div>
@endsection
