<?php

use App\Http\Controllers\Superadmin\DashboardController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Auth;

Route::middleware([SetLocale::class])->group(function () {

// Session-level locale switcher (EN/FR). Persists to the user profile when
// logged in (userprofiles.locale); session-only otherwise.
Route::get('/locale/{locale}', function ($locale) {
    abort_unless(array_key_exists($locale, config('school.locale.available', ['en' => 'English'])), 404);

    session(['locale' => $locale]);

    if (Auth::check() && Auth::user()->userprofile) {
        Auth::user()->userprofile->update(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

/*Route::get('/', function () {
  return redirect()->route('login');
    //return view('welcome');
});*/
Route::get('/', function () {
    return redirect()->route('login');
});


Auth::routes();



//Impersonate as teacher
Route::get('/teacher/{id}/impersonate', 'Auth\ImpersonateController@impersonate')->middleware('auth', 'schooladmin');
Route::get('/library/{id}/impersonate', 'Auth\ImpersonateController@librarianimpersonate')->middleware('auth', 'schooladmin');
Route::get('/student/{id}/impersonate', 'Auth\ImpersonateController@studentimpersonate')->middleware('auth', 'schooladmin');
Route::get('/teacher/impersonate/stop', 'Auth\ImpersonateController@stopImpersonate');

Route::get('/schooladmin/{id}/impersonate', 'Auth\ImpersonateController@schoolAdminimpersonate')->middleware('auth', 'superadmin');

//Reset Password for member
// Route::get('/password/reset/{token}', 'Auth\ResetPasswordController@showResetForm');
// Route::post('/password/reset', 'Auth\ResetPasswordController@reset')->name('password.reset');
//Email Verification for Member
Route::get('/emailverification/{token}', 'Auth\EmailVerificationController@emailverification');
// OTP Verification
Route::get('/checksms', 'TestController@checksms');
Route::get('/verifyotp', 'OTPController@create');
Route::post('/verifyotp', 'OTPController@store');
//siteadmin
Route::group(['middleware' => ['siteadmin'], 'namespace' => 'Admin'], function () {
    Route::get('/payment/subscription', 'PaymentController@Subscription');
});


Route::get('/cache-clear', function () {
    Artisan::call('cache:clear');
});

Route::get('/{slug}/standardlist','AdmissionController@list');
Route::get( '/{slug}/admission-form', 'AdmissionController@create' );
Route::post( '/{slug}/admission-form', 'AdmissionController@store' );


Route::post( '/{slug}/admission-form/validationAvatar', 'AdmissionController@validationAvatar' );
Route::post( '/{slug}/admission-form/validationFatherAvatar', 'AdmissionController@validationFatherAvatar' );
Route::post( '/{slug}/admission-form/validationMotherAvatar', 'AdmissionController@validationMotherAvatar' );
Route::post( '/{slug}/admission-form/validationStandard', 'AdmissionController@validationStandard' );
Route::post( '/{slug}/admission-form/validationStudentDetail', 'AdmissionController@validationStudentDetail' );
Route::post( '/{slug}/admission-form/validationAcademicDetail', 'AdmissionController@validationAcademicDetail' );
Route::post( '/{slug}/admission-form/validationParentDetail', 'AdmissionController@validationParentDetail' );
Route::post( '/{slug}/admission-form/validationPersonalDetail', 'AdmissionController@validationPersonalDetail' );




});
