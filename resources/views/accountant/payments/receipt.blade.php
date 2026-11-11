<!DOCTYPE html>
<html>
<head>
    <title>Receipt {{ $payment->provider_reference }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>
<body>
        <div style=" border:1px dashed #696969;  margin-top: 10px;">
            <div style="padding:10px;background: #ffffff;">
                <table border="0" style="width:100%;margin-top: 20px;height: 100px;">
                    <tr>
                      <td style="width:40%;vertical-align: top;">
                            @if($logo != 'null')
                            <img src="{{ $logo }}" style="max-width:125px;">
                             @endif
                      </td>
                       <td style="width:60%;vertical-align: top;">
                          <table align="right" class="table-head">
                             <tr>
                               <th style="font-size: 20px;">{{ $payment->school->name }}</th>
                             </tr>
                             <tr>
                               <td>
                                 <span class="comma">{{ $payment->school->address }}</span>
                                 <span class="comma">{{ optional($payment->school->city)->name }},</span>
                                 <span class="comma">{{ optional($payment->school->state)->name }},</span>
                                 <span class="comma">{{ optional($payment->school->country)->name }}</span>
                                 <span class="comma">{{ $payment->school->pincode }}</span>
                               </td>
                              </tr>
                              <tr>
                                <td>{{ $payment->school->phone }}</td>
                              </tr>
                              <tr>
                               <td>{{ $payment->school->email }}</td>
                              </tr>
                           </table>
                        </td>
                    </tr>
                </table>
    <h2 style="text-align: center;">Fee Payment Receipt</h2>
    <table class="heading" style="width: 100%; margin-bottom: 20px;">
       <tr>
         <td>Receipt {{ $payment->provider_reference }}</td>
         <td style="text-align: right;">Date: {{ $payment->paid_at->format('d-m-Y H:i') }}</td>
       </tr>
    </table>
    <table style="width: 100%;" cellpadding="6">
       <tbody>
         <tr>
           <td><strong>Student</strong></td>
           <td>{{ $payment->student->full_name }} ({{ $payment->student->id }})</td>
         </tr>
         <tr>
           <td><strong>Amount</strong></td>
           <td>{{ $payment->formatted_amount }}</td>
         </tr>
         <tr>
           <td><strong>Method</strong></td>
           <td>{{ ucfirst(str_replace('_', ' ', $payment->gateway)) }}@if($payment->method) — {{ ucfirst(str_replace('_', ' ', $payment->method)) }}@endif</td>
         </tr>
         <tr>
           <td><strong>Reference</strong></td>
           <td>{{ $payment->provider_reference }}</td>
         </tr>
         @if(!empty($payment->meta['notes']))
         <tr>
           <td><strong>Notes</strong></td>
           <td>{{ $payment->meta['notes'] }}</td>
         </tr>
         @endif
         <tr>
           <td><strong>Recorded by</strong></td>
           <td>{{ optional($payment->recordedBy)->full_name }}</td>
         </tr>
       </tbody>
    </table>
    <p style="margin-top: 40px; font-size: 12px; color: #555;">
        This receipt was generated on {{ now()->format('d-m-Y H:i') }}.
    </p>
            </div>
        </div>
</body>
</html>
